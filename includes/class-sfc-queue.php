<?php
if (!defined('ABSPATH')) { exit; }
class SFC_Permanent_Job_Exception extends RuntimeException {}

class SFC_Queue {
    const DEFAULT_LEASE_TIMEOUT = 900;

    public static function init() {
        add_action('sfc_queue_tick', array(__CLASS__,'process'));
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('sfc_queue_tick');
    }

    /**
     * Backwards-compatible boolean enqueue API.
     * True means that the job was inserted or requeued; an active duplicate is false.
     */
    public static function enqueue($type,$payload,$delay=0) {
        $result = self::enqueue_result($type,$payload,$delay);
        return in_array($result['status'],array('inserted','requeued'),true);
    }

    /** @return array{status:string,job_id:int,error:string} */
    public static function enqueue_result($type,$payload,$delay=0) {
        global $wpdb;
        if(!SFC_DB::schema_ready())return array('status'=>'failed','job_id'=>0,'error'=>'Database schema is not ready.');
        $type=sanitize_key($type);
        $encoded=wp_json_encode($payload);
        if ($type==='' || $encoded===false) return array('status'=>'failed','job_id'=>0,'error'=>'Invalid job type or payload.');
        $key=hash('sha256',$type.'|'.$encoded);
        $table=SFC_DB::jobs_table();
        $now=current_time('mysql',true);
        $available=gmdate('Y-m-d H:i:s',time()+max(0,(int)$delay));
        $ok=$wpdb->insert($table,array('job_key'=>$key,'job_type'=>$type,'payload'=>$encoded,'status'=>'queued','attempts'=>0,'available_at'=>$available,'started_at'=>null,'lease_token'=>null,'lease_expires_at'=>null,'execution_token'=>null,'finished_at'=>null,'last_error'=>null,'created_at'=>$now),array('%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'));
        if($ok!==false) return array('status'=>'inserted','job_id'=>(int)$wpdb->insert_id,'error'=>'');

        $insert_error=$wpdb->last_error;
        $existing=$wpdb->get_row($wpdb->prepare("SELECT id,status FROM {$table} WHERE job_key=%s",$key),ARRAY_A);
        if(!$existing){
            SFC_DB::log('error','queue_enqueue',$insert_error,array('type'=>$type));
            return array('status'=>'failed','job_id'=>0,'error'=>$insert_error ?: 'Unable to insert job.');
        }
        $job_id=(int)$existing['id'];
        if(in_array($existing['status'],array('queued','processing'),true)) return array('status'=>'already_exists','job_id'=>$job_id,'error'=>'');

        $updated=$wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET job_type=%s,payload=%s,status='queued',attempts=0,available_at=%s,started_at=NULL,lease_token=NULL,lease_expires_at=NULL,execution_token=NULL,finished_at=NULL,last_error=NULL,created_at=%s WHERE id=%d AND status IN ('done','failed')",
            $type,$encoded,$available,$now,$job_id
        ));
        if($updated===1) return array('status'=>'requeued','job_id'=>$job_id,'error'=>'');
        if($updated===0) return array('status'=>'already_exists','job_id'=>$job_id,'error'=>'');
        SFC_DB::log('error','queue_requeue',$wpdb->last_error,array('job_id'=>$job_id));
        return array('status'=>'failed','job_id'=>$job_id,'error'=>$wpdb->last_error ?: 'Unable to requeue job.');
    }

    public static function process() {
        global $wpdb;
        if(!SFC_DB::schema_ready()){SFC_DB::log('error','queue_paused','Queue skipped because database schema is not ready.');return;}
        self::recover_stale_jobs();
        $batch=(int)SFC_Settings::get('queue_batch',2);
        $table=SFC_DB::jobs_table();
        for($i=0;$i<$batch;$i++){
            $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE status='queued' AND available_at<=%s ORDER BY id ASC LIMIT 1",current_time('mysql',true)),ARRAY_A);
            if(!$job){if($wpdb->last_error)SFC_DB::log('error','queue_select',$wpdb->last_error);break;}
            $lock='sfc_job_'.(int)$job['id'];
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)continue;
            $token=wp_generate_uuid4();
            $execution=!empty($job['execution_token'])?$job['execution_token']:wp_generate_uuid4();
            $started=current_time('mysql',true);
            $expires=gmdate('Y-m-d H:i:s',time()+self::lease_timeout());
            $claimed=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing',started_at=%s,lease_token=%s,lease_expires_at=%s,execution_token=%s,finished_at=NULL WHERE id=%d AND status='queued'",$started,$token,$expires,$execution,(int)$job['id']));
            if($claimed===false){SFC_DB::log('error','queue_claim',$wpdb->last_error,array('job_id'=>$job['id']));$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));continue;}
            if($claimed!==1){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));continue;}
            try{
                $payload=json_decode($job['payload'],true);
                if(!is_array($payload) || json_last_error()!==JSON_ERROR_NONE) throw new RuntimeException('Некорректный payload задания.');
                $lease=array('job_id'=>(int)$job['id'],'token'=>$token,'completion_key'=>$execution);
                if($job['job_type']==='generate_page'){
                    if(!SFC_Generator::job_was_completed($payload,$execution))SFC_Generator::generate_page($payload,$lease);
                }else throw new SFC_Permanent_Job_Exception('Неизвестный тип job: '.$job['job_type']);
                if(!self::heartbeat($lease))throw new RuntimeException('Worker lost its lease before completion.');
                $done=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='done',finished_at=%s,lease_token=NULL,lease_expires_at=NULL,last_error=NULL WHERE id=%d AND status='processing' AND lease_token=%s",current_time('mysql',true),(int)$job['id'],$token));
                if($done===false) SFC_DB::log('error','queue_completion_update',$wpdb->last_error,array('job_id'=>$job['id']));
                elseif($done===0) SFC_DB::log('warning','queue_lease_lost','Результат worker не записан: lease больше не принадлежит процессу.',array('job_id'=>$job['id']));
            }catch(Throwable $e){
                $attempts=(int)$job['attempts']+1;
                $permanent=$e instanceof SFC_Permanent_Job_Exception;$status=($permanent||$attempts>=3)?'failed':'queued';
                $next=gmdate('Y-m-d H:i:s',time()+($attempts*300));
                $failed=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status=%s,attempts=%d,available_at=%s,last_error=%s,finished_at=%s,lease_token=NULL,lease_expires_at=NULL WHERE id=%d AND status='processing' AND lease_token=%s",$status,$attempts,$next,$e->getMessage(),current_time('mysql',true),(int)$job['id'],$token));
                if($failed===false) SFC_DB::log('error','queue_failure_update',$wpdb->last_error,array('job_id'=>$job['id']));
                elseif($failed===0) SFC_DB::log('warning','queue_lease_lost','Ошибка worker не записана: lease больше не принадлежит процессу.',array('job_id'=>$job['id']));
                else SFC_DB::log('error','queue_job_failed',$e->getMessage(),array('job_id'=>$job['id'],'attempts'=>$attempts));
            }
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }

    private static function lease_timeout(){return max(60,(int)apply_filters('sfc_queue_lease_timeout',self::DEFAULT_LEASE_TIMEOUT));}
    public static function heartbeat($lease){global $wpdb;if(empty($lease['job_id'])||empty($lease['token']))return false;$table=SFC_DB::jobs_table();$expires=gmdate('Y-m-d H:i:s',time()+self::lease_timeout());$updated=$wpdb->query($wpdb->prepare("UPDATE {$table} SET lease_expires_at=%s WHERE id=%d AND status='processing' AND lease_token=%s",$expires,(int)$lease['job_id'],$lease['token']));if($updated===1)return true;if($updated===false)return false;return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id=%d AND status='processing' AND lease_token=%s",(int)$lease['job_id'],$lease['token']))===1;}

    public static function recover_stale_jobs() {
        global $wpdb;
        $table=SFC_DB::jobs_table();
        $stale=$wpdb->get_results("SELECT id,lease_token FROM {$table} WHERE status='processing' AND lease_expires_at IS NOT NULL AND lease_expires_at<UTC_TIMESTAMP()",ARRAY_A);$recovered=0;if($stale===null&&$wpdb->last_error){SFC_DB::log('error','queue_recovery_select',$wpdb->last_error);return false;}
        foreach((array)$stale as $row){$lock='sfc_job_'.(int)$row['id'];if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1)continue;$updated=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status=IF(attempts+1>=3,'failed','queued'),attempts=attempts+1,available_at=UTC_TIMESTAMP(),lease_token=NULL,lease_expires_at=NULL,finished_at=UTC_TIMESTAMP(),last_error='Worker lease expired before completion.' WHERE id=%d AND status='processing' AND lease_token=%s AND lease_expires_at<UTC_TIMESTAMP()",(int)$row['id'],$row['lease_token']));if($updated===1)$recovered++;elseif($updated===false)SFC_DB::log('error','queue_recovery',$wpdb->last_error,array('job_id'=>$row['id']));$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
        if($recovered>0) SFC_DB::log('warning','queue_recovery','Восстановлены зависшие задания.',array('count'=>$recovered));
        return $recovered;
    }

    public static function retry_failed() {
        global $wpdb;
        $updated=$wpdb->query("UPDATE ".SFC_DB::jobs_table()." SET status='queued',attempts=0,available_at=UTC_TIMESTAMP(),started_at=NULL,lease_token=NULL,lease_expires_at=NULL,execution_token=NULL,finished_at=NULL,last_error=NULL WHERE status='failed'");
        return $updated!==false;
    }

    public static function clear_done() {
        global $wpdb;
        return $wpdb->query("DELETE FROM ".SFC_DB::jobs_table()." WHERE status='done'")!==false;
    }
}

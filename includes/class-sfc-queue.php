<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Queue {
    public static function init() {
        add_action('sfc_queue_tick', array(__CLASS__,'process'));
    }
    public static function deactivate() {
        wp_clear_scheduled_hook('sfc_queue_tick');
    }
    public static function enqueue($type,$payload,$delay=0) {
        global $wpdb;
        $key=hash('sha256',$type.'|'.wp_json_encode($payload));
        $now=current_time('mysql',true); $available=gmdate('Y-m-d H:i:s',time()+max(0,(int)$delay));
        $ok=$wpdb->insert(SFC_DB::jobs_table(),array('job_key'=>$key,'job_type'=>sanitize_key($type),'payload'=>wp_json_encode($payload),'status'=>'queued','attempts'=>0,'available_at'=>$available,'created_at'=>$now),array('%s','%s','%s','%s','%d','%s','%s'));
        if($ok===false && stripos($wpdb->last_error,'duplicate')===false){SFC_DB::log('error','queue_enqueue',$wpdb->last_error,array('type'=>$type)); return false;}
        return true;
    }
    public static function process() {
        global $wpdb;
        $batch=(int)SFC_Settings::get('queue_batch',2); $table=SFC_DB::jobs_table();
        for($i=0;$i<$batch;$i++){
            $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE status='queued' AND available_at<=%s ORDER BY id ASC LIMIT 1",current_time('mysql',true)),ARRAY_A);
            if(!$job)break;
            $claimed=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing', started_at=%s WHERE id=%d AND status='queued'",current_time('mysql',true),(int)$job['id']));
            if(!$claimed)continue;
            try{
                $payload=json_decode($job['payload'],true); 
                if($job['job_type']==='generate_page') SFC_Generator::generate_page($payload); else throw new RuntimeException('Неизвестный тип job: '.$job['job_type']);
                $wpdb->update($table,array('status'=>'done','finished_at'=>current_time('mysql',true)),array('id'=>(int)$job['id']),array('%s','%s'),array('%d'));
            }catch(Throwable $e){
                $attempts=(int)$job['attempts']+1; $status=$attempts>=3?'failed':'queued'; $next=gmdate('Y-m-d H:i:s',time()+($attempts*300));
                $wpdb->update($table,array('status'=>$status,'attempts'=>$attempts,'available_at'=>$next,'last_error'=>$e->getMessage(),'finished_at'=>current_time('mysql',true)),array('id'=>(int)$job['id']),array('%s','%d','%s','%s','%s'),array('%d'));
                SFC_DB::log('error','queue_job_failed',$e->getMessage(),array('job_id'=>$job['id'],'attempts'=>$attempts));
            }
        }
    }
    public static function retry_failed() { global $wpdb; $wpdb->query("UPDATE ".SFC_DB::jobs_table()." SET status='queued', available_at=UTC_TIMESTAMP(), last_error=NULL WHERE status='failed'"); }
    public static function clear_done() { global $wpdb; $wpdb->query("DELETE FROM ".SFC_DB::jobs_table()." WHERE status='done'"); }
}

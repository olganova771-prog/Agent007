<?php
if (!defined('ABSPATH')) exit;

class UWSB_Engine {
    const LEASE_SECONDS=900;

    public static function init(){ add_action('uwsb_job_tick',[__CLASS__,'tick']); }

    public static function plan_project($project_id){
        $p=UWSB_DB::project($project_id);
        if(!$p) return false;
        $pages=UWSB_Planner::build($project_id,$p['config']);
        foreach($pages as $page) UWSB_DB::enqueue($project_id,'render_page',['page_key'=>$page['page_key']]);
        return count($pages);
    }

    public static function tick(){ self::process_jobs(4); }

    public static function recover_stale(){
        global $wpdb;
        $table=UWSB_DB::t('jobs');
        return $wpdb->query(
            "UPDATE {$table}
             SET status=IF(attempts>=3,'failed','queued'),lease_token=NULL,lease_expires_at=NULL,last_error='Worker lease expired.',available_at=UTC_TIMESTAMP()
             WHERE status='processing' AND lease_expires_at IS NOT NULL AND lease_expires_at<UTC_TIMESTAMP()"
        );
    }

    public static function process_jobs($limit=20,$project_id=0){
        global $wpdb;
        if(!UWSB_DB::schema_ready()) return 0;
        self::recover_stale();
        $table=UWSB_DB::t('jobs');
        $processed=0;

        for($i=0;$i<max(1,(int)$limit);$i++){
            if($project_id){
                $job=$wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE project_id=%d AND status='queued' AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1",
                    (int)$project_id
                ),ARRAY_A);
            }else{
                $job=$wpdb->get_row("SELECT * FROM {$table} WHERE status='queued' AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1",ARRAY_A);
            }
            if(!$job) break;

            $lock='uwsb_job_'.(int)$job['id'];
            if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1) continue;

            $token=wp_generate_uuid4();
            $execution=wp_generate_uuid4();
            $expires=gmdate('Y-m-d H:i:s',time()+self::LEASE_SECONDS);
            $claimed=$wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status='processing',started_at=UTC_TIMESTAMP(),attempts=attempts+1,lease_token=%s,lease_expires_at=%s,execution_token=%s,finished_at=NULL
                 WHERE id=%d AND status='queued'",
                $token,$expires,$execution,(int)$job['id']
            ));

            if($claimed!==1){
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
                continue;
            }

            try{
                $payload=json_decode($job['payload'],true);
                if(!is_array($payload)) throw new RuntimeException('Invalid job payload.');
                if($job['job_type']!=='render_page') throw new RuntimeException('Unknown job type.');
                self::render_page((int)$job['project_id'],(string)($payload['page_key']??''));

                $done=$wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET status='done',finished_at=UTC_TIMESTAMP(),lease_token=NULL,lease_expires_at=NULL,last_error=NULL
                     WHERE id=%d AND status='processing' AND lease_token=%s",
                    (int)$job['id'],$token
                ));
                if($done!==1) throw new RuntimeException('Lost job lease before completion.');
            }catch(Throwable $e){
                $attempts=(int)$job['attempts']+1;
                $status=$attempts>=3?'failed':'queued';
                $next=gmdate('Y-m-d H:i:s',time()+min(1800,$attempts*180));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET status=%s,available_at=%s,finished_at=UTC_TIMESTAMP(),lease_token=NULL,lease_expires_at=NULL,last_error=%s
                     WHERE id=%d AND lease_token=%s",
                    $status,$next,mb_substr($e->getMessage(),0,1000),(int)$job['id'],$token
                ));
            }

            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
            $processed++;
        }
        return $processed;
    }

    public static function render_page($project_id,$key){
        global $wpdb;
        $project=UWSB_DB::project($project_id);
        $row=UWSB_DB::page_by_key($project_id,$key);
        if(!$project||!$row) throw new RuntimeException('Missing project/page plan.');
        $plan=json_decode($row['plan'],true)?:[];
        $lang=$row['lang']==='ru'?'ru':'uk';

        $title=self::title_for($row['page_type'],$project,$plan,$lang);
        if($title==='') throw new RuntimeException('Page title is empty.');
        $slug=self::slug_for($row,$plan);

        $existing=(int)$row['wp_post_id'];
        if(!$existing){
            $existing=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_uwsb_page_key' AND meta_value=%s LIMIT 1",
                $project_id.'|'.$key
            ));
        }

        $postarr=[
            'post_type'=>'page',
            'post_status'=>$existing?(get_post_status($existing)?:'draft'):'draft',
            'post_title'=>$title,
            'post_name'=>$slug,
            'post_content'=>'',
            'post_excerpt'=>'',
        ];
        if($existing) $postarr['ID']=$existing;

        $post_id=wp_insert_post(wp_slash($postarr),true);
        if(is_wp_error($post_id)) throw new RuntimeException($post_id->get_error_message());

        $traits=UWSB_Profiles::traits($project_id,$key,$project['profile'],$project['config']['editorial']??[]);
        $render=[
            'plan'=>$plan,
            'traits'=>$traits,
            'variant'=>UWSB_Profiles::variant($project_id,$key,'layout',4),
        ];

        self::meta($post_id,'_uwsb_managed','1');
        self::meta($post_id,'_uwsb_project_id',(int)$project_id);
        self::meta($post_id,'_uwsb_page_key',$project_id.'|'.$key);
        self::meta($post_id,'_uwsb_translation_key',$row['translation_key']);
        self::meta($post_id,'_uwsb_lang',$lang);
        self::meta($post_id,'_uwsb_page_type',$row['page_type']);
        self::meta($post_id,'_uwsb_indexable',(int)$row['indexable']);
        self::meta($post_id,'_uwsb_render_data',$render);
        self::meta($post_id,'_uwsb_seo_title',$title.' — '.$project['name']);
        self::meta($post_id,'_uwsb_meta_description',self::description_for($row['page_type'],$project,$plan,$lang));

        $links=self::links_for($project_id,$row);
        self::meta($post_id,'_uwsb_internal_links',$links);

        $wpdb->update(UWSB_DB::t('pages'),[
            'wp_post_id'=>$post_id,
            'status'=>'generated',
            'updated_at'=>UWSB_DB::now(),
        ],['id'=>(int)$row['id']],['%d','%s','%s'],['%d']);

        self::maybe_sync_wc($project,$row,$plan,$post_id);
        return $post_id;
    }

    private static function meta($post_id,$key,$value){
        update_post_meta($post_id,$key,wp_slash($value));
        if(get_post_meta($post_id,$key,true)!=$value) throw new RuntimeException('Failed to persist meta '.$key.'.');
    }

    private static function title_for($type,$project,$plan,$lang){
        if($type==='home') return $project['name'];
        if($type==='catalog') return $lang==='ru'?'Каталог':'Каталог';
        if($type==='contacts') return $lang==='ru'?'Контакты':'Контакти';
        if($type==='coverage') return $lang==='ru'?'География работы':'Географія роботи';
        if($type==='collection') return $lang==='ru'?'Подборка товаров':'Добірка товарів';
        if($type==='comparison') return $lang==='ru'?'Сравнение товаров':'Порівняння товарів';
        if($type==='product_city'){
            $item=$plan['item']['name']??'';
            $city=$plan['city']['name']??'';
            return trim($item.' — '.$city);
        }
        return (string)($plan['item']['name']??$project['name']);
    }

    private static function slug_for($row,$plan){
        $lang=$row['lang']==='ru'?'ru':'uk';
        $type=$row['page_type'];
        if($type==='home') return $lang.'-home';
        if($type==='catalog') return $lang.'-catalog';
        if($type==='contacts') return $lang.'-contacts';
        if($type==='coverage') return $lang.'-coverage';
        if($type==='collection') return $lang.'-collection';
        if($type==='comparison') return $lang.'-comparison';
        if($type==='product_city') return sanitize_title($lang.'-'.($plan['item']['name']??'product').'-'.($plan['city']['name']??'city'));
        return sanitize_title($lang.'-'.($plan['item']['name']??'product'));
    }

    private static function description_for($type,$project,$plan,$lang){
        $text='';
        if(in_array($type,['product','product_city'],true)){
            $it=$plan['item']??[];
            $parts=array_filter([$it['short']??'',$it['description']??'',implode('. ',$it['facts']??[])]);
            $text=implode(' ',array_slice($parts,0,2));
            if($type==='product_city'&&!empty($plan['city']['name'])) $text.=' · '.$plan['city']['name'];
        }elseif($type==='coverage'){
            $count=(int)($plan['city_count']??0);
            $text=$lang==='ru'?'География работы: '.$count.' городов.':'Географія роботи: '.$count.' міст.';
        }elseif($type==='contacts'){
            $text=$project['name'].' — '.($lang==='ru'?'контактная информация.':'контактна інформація.');
        }else{
            $text=$project['name'].' — '.($lang==='ru'?'структурированная страница сайта.':'структурована сторінка сайту.');
        }
        $text=trim(wp_strip_all_tags($text));
        return mb_substr($text,0,155);
    }

    private static function links_for($project_id,$row){
        global $wpdb;
        $table=UWSB_DB::t('pages');
        $lang=$row['lang'];
        $links=[];

        $base=$wpdb->get_results($wpdb->prepare(
            "SELECT wp_post_id,page_type,entity_key,city_slug FROM {$table}
             WHERE project_id=%d AND lang=%s AND wp_post_id IS NOT NULL
             AND page_type IN ('home','catalog','product') ORDER BY priority,id LIMIT 12",
            $project_id,$lang
        ),ARRAY_A);
        foreach($base as $p){
            if((int)$p['wp_post_id']!==(int)$row['wp_post_id']) $links[]=(int)$p['wp_post_id'];
            if(count($links)>=6) break;
        }

        if($row['page_type']==='product_city'&&$row['entity_key']){
            $pid=$wpdb->get_var($wpdb->prepare(
                "SELECT wp_post_id FROM {$table} WHERE project_id=%d AND lang=%s AND page_type='product' AND entity_key=%s AND wp_post_id IS NOT NULL LIMIT 1",
                $project_id,$lang,$row['entity_key']
            ));
            if($pid) array_unshift($links,(int)$pid);
        }

        return array_values(array_unique(array_filter(array_map('intval',$links))));
    }

    private static function maybe_sync_wc($project,$row,$plan,$page_id){
        if($project['site_type']!=='store'||$row['page_type']!=='product'||!class_exists('WC_Product_Simple')) return;
        $item=$plan['item']??[];
        $sku=$item['sku']?:('uwsb-'.$project['id'].'-'.$row['entity_key']);
        $pid=wc_get_product_id_by_sku($sku);
        $product=$pid?wc_get_product($pid):new WC_Product_Simple();
        $product->set_name($item['name']??get_the_title($page_id));
        $product->set_status('draft');
        if(!$pid) $product->set_sku($sku);
        if(!empty($item['price'])&&is_numeric(str_replace(',','.',$item['price']))) $product->set_regular_price(str_replace(',','.',$item['price']));
        $product->set_description((string)($item['description']??''));
        $product->save();
    }

    public static function publish_project($project_id){
        UWSB_QA::run($project_id);
        if(UWSB_QA::has_critical($project_id)) return new WP_Error('qa_block','Critical QA issues block publication.');
        $home_id=0;
        foreach(UWSB_DB::plans($project_id) as $p){
            if(!$p['wp_post_id']) continue;
            $result=wp_update_post(['ID'=>(int)$p['wp_post_id'],'post_status'=>'publish'],true);
            if(is_wp_error($result)) return $result;
            if($p['page_type']==='home'&&$p['lang']==='uk') $home_id=(int)$p['wp_post_id'];
        }
        if($home_id&&(int)get_option('page_on_front')===0){
            update_option('show_on_front','page');
            update_option('page_on_front',$home_id);
        }
        global $wpdb;
        $wpdb->update(UWSB_DB::t('projects'),['status'=>'published','updated_at'=>UWSB_DB::now()],['id'=>(int)$project_id],['%s','%s'],['%d']);
        return true;
    }
}

<?php
if (!defined('ABSPATH')) exit;
class UWSB_Engine {
    public static function init(){ add_action('uwsb_job_tick',[__CLASS__,'tick']); }
    public static function plan_project($project_id){ $p=UWSB_DB::project($project_id); if(!$p)return false; UWSB_Planner::build($project_id,$p['config']); foreach(UWSB_DB::plans($project_id) as $plan) UWSB_DB::enqueue($project_id,'render_page',['page_key'=>$plan['page_key']]); return true; }
    public static function tick(){ self::process_jobs(4); }
    public static function process_jobs($limit=20,$project_id=0){
        global $wpdb; $table=UWSB_DB::t('jobs');
        if($project_id){ $jobs=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE project_id=%d AND status='queued' AND available_at<=%s ORDER BY id ASC LIMIT %d",$project_id,UWSB_DB::now(),$limit),ARRAY_A); }
        else { $jobs=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status='queued' AND available_at<=%s ORDER BY id ASC LIMIT %d",UWSB_DB::now(),$limit),ARRAY_A); }
        foreach($jobs as $j){ $wpdb->update($table,['status'=>'processing','started_at'=>UWSB_DB::now(),'attempts'=>(int)$j['attempts']+1],['id'=>$j['id']]); try{ $payload=json_decode($j['payload'],true)?:[]; if($j['job_type']==='render_page') self::render_page((int)$j['project_id'],$payload['page_key']); $wpdb->update($table,['status'=>'done','finished_at'=>UWSB_DB::now(),'last_error'=>null],['id'=>$j['id']]); }catch(Throwable $e){ $wpdb->update($table,['status'=>'failed','finished_at'=>UWSB_DB::now(),'last_error'=>mb_substr($e->getMessage(),0,1000)],['id'=>$j['id']]); } }
        return count($jobs);
    }
    private static function page_plan($project_id,$key){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.UWSB_DB::t('pages').' WHERE project_id=%d AND page_key=%s',$project_id,$key),ARRAY_A); }
    public static function render_page($project_id,$key){
        global $wpdb; $project=UWSB_DB::project($project_id); $row=self::page_plan($project_id,$key); if(!$project||!$row)throw new RuntimeException('Missing project/page plan'); $plan=json_decode($row['plan'],true)?:[];
        $title=self::title_for($row['page_type'],$project,$plan); $slug=self::slug_for($row['page_type'],$project,$plan);
        $existing=(int)$row['wp_post_id']; if(!$existing){ $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_uwsb_page_key' AND meta_value=%s LIMIT 1",$project_id.'|'.$key)); }
        $postarr=['post_type'=>'page','post_status'=>'draft','post_title'=>$title,'post_name'=>$slug,'post_content'=>'','post_excerpt'=>'']; if($existing)$postarr['ID']=$existing;
        $post_id=wp_insert_post(wp_slash($postarr),true); if(is_wp_error($post_id))throw new RuntimeException($post_id->get_error_message());
        update_post_meta($post_id,'_uwsb_managed','1'); update_post_meta($post_id,'_uwsb_project_id',$project_id); update_post_meta($post_id,'_uwsb_page_key',$project_id.'|'.$key); update_post_meta($post_id,'_uwsb_page_type',$row['page_type']);
        $traits=UWSB_Profiles::traits($project_id,$key,$project['profile']);
        update_post_meta($post_id,'_uwsb_render_data',['plan'=>$plan,'traits'=>$traits,'variant'=>UWSB_Profiles::variant($project_id,$key,'layout',4)]);
        update_post_meta($post_id,'_uwsb_seo_title',$title.' — '.$project['name']);
        $desc=self::description_for($row['page_type'],$project,$plan); update_post_meta($post_id,'_uwsb_meta_description',$desc);
        $wpdb->update(UWSB_DB::t('pages'),['wp_post_id'=>$post_id,'status'=>'generated','updated_at'=>UWSB_DB::now()],['id'=>$row['id']],['%d','%s','%s'],['%d']);
        self::sync_links($project_id); self::maybe_sync_wc($project,$row,$plan,$post_id);
        return $post_id;
    }
    private static function title_for($type,$project,$plan){ if($type==='home')return $project['name']; if($type==='catalog')return 'Каталог'; if($type==='contacts')return $project['lang']==='ru'?'Контакты':'Контакти'; if($type==='coverage')return $project['lang']==='ru'?'География работы':'Географія роботи'; return $plan['item']['name']??$project['name']; }
    private static function slug_for($type,$project,$plan){ if($type==='home')return 'site-factory-home-'.$project['id']; if($type==='catalog')return 'catalog-'.$project['id']; if($type==='contacts')return 'contacts-'.$project['id']; if($type==='coverage')return 'coverage-'.$project['id']; return sanitize_title(($plan['item']['name']??'item').'-'.$project['id']); }
    private static function description_for($type,$project,$plan){ if($type==='product'){ $it=$plan['item']??[]; $parts=array_merge([$it['name']??''],$it['facts']??[]); return mb_substr(wp_strip_all_tags(implode('. ',array_filter($parts))),0,155); } return mb_substr($project['name'].' — '.ucfirst($type),0,155); }
    private static function sync_links($project_id){ $plans=UWSB_DB::plans($project_id); $catalog=0;$home=0;$products=[]; foreach($plans as $p){ if(!$p['wp_post_id'])continue; if($p['page_type']==='home')$home=(int)$p['wp_post_id']; if($p['page_type']==='catalog')$catalog=(int)$p['wp_post_id']; if($p['page_type']==='product')$products[]=(int)$p['wp_post_id']; }
        foreach($plans as $p){ if(!$p['wp_post_id'])continue; $links=[]; if($p['page_type']==='home')$links=array_slice($products,0,6); elseif($p['page_type']==='catalog')$links=$products; elseif($p['page_type']==='product'){$links=array_filter([$catalog,$home]); foreach($products as $pid)if($pid!=$p['wp_post_id']){$links[]=$pid;if(count($links)>=5)break;}} else $links=array_filter([$home,$catalog]); update_post_meta((int)$p['wp_post_id'],'_uwsb_internal_links',array_values(array_unique(array_map('intval',$links)))); }
    }
    private static function maybe_sync_wc($project,$row,$plan,$page_id){ if($project['site_type']!=='store'||$row['page_type']!=='product'||!class_exists('WooCommerce')||!class_exists('WC_Product_Simple'))return; $sku='uwsb-'.$project['id'].'-'.$row['entity_key']; $pid=wc_get_product_id_by_sku($sku); $product=$pid?wc_get_product($pid):new WC_Product_Simple(); $product->set_name($plan['item']['name']??get_the_title($page_id)); $product->set_status('draft'); $product->set_sku($sku); if(!empty($plan['item']['price'])&&is_numeric(str_replace(',','.',$plan['item']['price'])))$product->set_regular_price(str_replace(',','.',$plan['item']['price'])); $product->set_description(implode("\n",$plan['item']['facts']??[])); $product->save(); }
    public static function publish_project($project_id){ UWSB_QA::run($project_id); if(UWSB_QA::has_critical($project_id))return new WP_Error('qa_block','Critical QA issues block publication.'); $home_id=0; foreach(UWSB_DB::plans($project_id) as $p){ if($p['wp_post_id']){ wp_update_post(['ID'=>(int)$p['wp_post_id'],'post_status'=>'publish']); if($p['page_type']==='home')$home_id=(int)$p['wp_post_id']; } } if($home_id && (int)get_option('page_on_front')===0){ update_option('show_on_front','page'); update_option('page_on_front',$home_id); } global $wpdb; $wpdb->update(UWSB_DB::t('projects'),['status'=>'published','updated_at'=>UWSB_DB::now()],['id'=>$project_id]); return true; }
}
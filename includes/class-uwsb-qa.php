<?php
if (!defined('ABSPATH')) exit;
class UWSB_QA {
    public static function run($project_id){
        UWSB_DB::qa_clear($project_id); $plans=UWSB_DB::plans($project_id); $seen=[]; $ids=[];
        foreach($plans as $p){
            if(!$p['wp_post_id']){ UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','missing_post','Planned page has no WordPress post.'); continue; }
            $id=(int)$p['wp_post_id']; $ids[$id]=1; $post=get_post($id); if(!$post){UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','post_not_found','Generated WordPress post is missing.');continue;}
            if(trim($post->post_title)==='')UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','missing_title','Page title is empty.');
            $slug=$post->post_name; if(isset($seen[$slug]))UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','duplicate_slug','Duplicate generated slug.',['slug'=>$slug]); $seen[$slug]=1;
            $data=get_post_meta($id,'_uwsb_render_data',true); if(!is_array($data))UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','missing_render_data','Render data is missing.');
            if($p['page_type']==='product'){ $plan=json_decode($p['plan'],true)?:[]; if(empty($plan['item']['facts']) && empty($plan['item']['price'])) UWSB_DB::qa_add($project_id,$p['page_key'],'WARNING','insufficient_facts','Product has only minimal factual input.'); }
        }
        foreach($plans as $p){ if(!$p['wp_post_id'])continue; $links=(array)get_post_meta((int)$p['wp_post_id'],'_uwsb_internal_links',true); foreach($links as $target){ if(!isset($ids[(int)$target]))UWSB_DB::qa_add($project_id,$p['page_key'],'CRITICAL','broken_internal_link','Internal target is outside current project.',['target'=>(int)$target]); } }
        global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.UWSB_DB::t('qa').' WHERE project_id=%d ORDER BY FIELD(severity,"CRITICAL","WARNING","INFO"),id',$project_id),ARRAY_A);
    }
    public static function has_critical($project_id){ global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.UWSB_DB::t('qa').' WHERE project_id=%d AND severity=%s',$project_id,'CRITICAL'))>0; }
}
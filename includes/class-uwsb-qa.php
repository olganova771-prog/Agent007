<?php
if (!defined('ABSPATH')) exit;

class UWSB_QA {
    public static function run($project_id){
        global $wpdb;
        UWSB_DB::qa_clear($project_id);
        $plans=UWSB_DB::plans($project_id);
        $pending=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".UWSB_DB::t('jobs')." WHERE project_id=%d AND status IN ('queued','processing')",$project_id));
        $seen=[];$ids=[];

        foreach($plans as $p){
            if(!$p['wp_post_id']){
                UWSB_DB::qa_add($project_id,$p['page_key'],$pending?'info':'critical','missing_post',$pending?'Page is still queued for generation.':'Planned page has no WordPress post.');
                continue;
            }
            $id=(int)$p['wp_post_id'];$ids[$id]=1;
            $post=get_post($id);
            if(!$post){UWSB_DB::qa_add($project_id,$p['page_key'],'critical','post_not_found','Generated WordPress post is missing.');continue;}
            if(trim($post->post_title)==='')UWSB_DB::qa_add($project_id,$p['page_key'],'critical','missing_title','Page title is empty.');
            $slug=$post->post_name;
            if(isset($seen[$slug]))UWSB_DB::qa_add($project_id,$p['page_key'],'critical','duplicate_slug','Duplicate generated slug.',['slug'=>$slug]);
            $seen[$slug]=1;

            $data=get_post_meta($id,'_uwsb_render_data',true);
            if(!is_array($data))UWSB_DB::qa_add($project_id,$p['page_key'],'critical','missing_render_data','Render data is missing.');

            if(in_array($p['page_type'],['product','product_city'],true)){
                $plan=json_decode($p['plan'],true)?:[];$it=$plan['item']??[];
                if(empty($it['description'])&&empty($it['facts'])&&empty($it['features']))UWSB_DB::qa_add($project_id,$p['page_key'],'warning','insufficient_facts','Product page has minimal factual input.');
            }

            if($p['page_type']==='product_city'&&!$p['indexable']){
                UWSB_DB::qa_add($project_id,$p['page_key'],'info','geo_noindex','Geo page generated but intentionally excluded from indexing until local value is available.');
            }
        }

        foreach($plans as $p){
            if(!$p['wp_post_id'])continue;
            $links=(array)get_post_meta((int)$p['wp_post_id'],'_uwsb_internal_links',true);
            foreach($links as $target){
                if(!isset($ids[(int)$target]))UWSB_DB::qa_add($project_id,$p['page_key'],'critical','broken_internal_link','Internal target is outside current project.',['target'=>(int)$target]);
            }
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM '.UWSB_DB::t('qa').' WHERE project_id=%d ORDER BY FIELD(severity,"critical","warning","info"),id',
            $project_id
        ),ARRAY_A);
    }

    public static function has_critical($project_id){
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM '.UWSB_DB::t('qa').' WHERE project_id=%d AND severity=%s',
            $project_id,'critical'
        ))>0;
    }
}

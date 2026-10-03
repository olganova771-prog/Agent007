<?php
if (!defined('ABSPATH')) exit;

class UWSB_Renderer {
    public static function init(){
        add_filter('template_include',[__CLASS__,'template'],99);
        add_action('wp_enqueue_scripts',[__CLASS__,'assets']);
    }

    public static function template($template){
        if(is_singular('page')&&get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1') return UWSB_DIR.'templates/site-page.php';
        return $template;
    }

    public static function assets(){
        if(is_singular('page')&&get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1'){
            wp_enqueue_style('uwsb-site',UWSB_URL.'assets/site.css',[],UWSB_VERSION);
        }
    }

    public static function project_for_post($post_id){
        return UWSB_DB::project((int)get_post_meta($post_id,'_uwsb_project_id',true));
    }

    public static function data_for_post($post_id){
        $raw=get_post_meta($post_id,'_uwsb_render_data',true);
        return is_array($raw)?$raw:[];
    }

    public static function lang_for_post($post_id){
        return get_post_meta($post_id,'_uwsb_lang',true)==='ru'?'ru':'uk';
    }

    public static function nav($project_id,$lang){
        $out=[];
        foreach(UWSB_DB::plans($project_id,$lang) as $p){
            if(!$p['wp_post_id']) continue;
            if(in_array($p['page_type'],['home','catalog','collection','comparison','coverage','contacts'],true)){
                $out[]=['label'=>get_the_title((int)$p['wp_post_id']),'url'=>get_permalink((int)$p['wp_post_id'])];
            }
        }
        return $out;
    }

    public static function cards($project_id,$lang,$limit=0){
        $out=[];
        foreach(UWSB_DB::plans($project_id,$lang) as $p){
            if($p['page_type']!=='product'||!$p['wp_post_id']) continue;
            $d=json_decode($p['plan'],true)?:[];
            $it=$d['item']??[];
            $out[]=[
                'name'=>$it['name']??get_the_title((int)$p['wp_post_id']),
                'price'=>$it['price']??'',
                'currency'=>$it['currency']??'',
                'facts'=>$it['facts']??[],
                'short'=>$it['short']??'',
                'url'=>get_permalink((int)$p['wp_post_id']),
            ];
            if($limit&&count($out)>=$limit) break;
        }
        return $out;
    }

    public static function translation($post_id){
        $project_id=(int)get_post_meta($post_id,'_uwsb_project_id',true);
        $key=(string)get_post_meta($post_id,'_uwsb_translation_key',true);
        $lang=self::lang_for_post($post_id)==='ru'?'uk':'ru';
        $row=UWSB_DB::counterpart($project_id,$key,$lang);
        return $row&&$row['wp_post_id']?(int)$row['wp_post_id']:0;
    }

    public static function monogram($name){
        $s=trim(wp_strip_all_tags($name));
        return esc_html(mb_strtoupper(mb_substr($s,0,1)));
    }
}

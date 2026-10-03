<?php
if (!defined('ABSPATH')) exit;
class UWSB_Renderer {
    public static function init(){ add_filter('template_include',[__CLASS__,'template'],99); add_action('wp_enqueue_scripts',[__CLASS__,'assets']); }
    public static function template($template){ if(is_singular('page') && get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1') return UWSB_DIR.'templates/site-page.php'; return $template; }
    public static function assets(){ if(is_singular('page') && get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1') wp_enqueue_style('uwsb-site',UWSB_URL.'assets/site.css',[],UWSB_VERSION); }
    public static function project_for_post($post_id){ $pid=(int)get_post_meta($post_id,'_uwsb_project_id',true); return UWSB_DB::project($pid); }
    public static function data_for_post($post_id){ $raw=get_post_meta($post_id,'_uwsb_render_data',true); return is_array($raw)?$raw:[]; }
    public static function nav($project_id){ $plans=UWSB_DB::plans($project_id); $out=[]; foreach($plans as $p){ if(!$p['wp_post_id'])continue; if(in_array($p['page_type'],['home','catalog','coverage','contacts'],true)) $out[]=['label'=>get_the_title((int)$p['wp_post_id']),'url'=>get_permalink((int)$p['wp_post_id'])]; } return $out; }
    public static function cards($project_id){ $out=[]; foreach(UWSB_DB::plans($project_id) as $p){ if($p['page_type']!=='product'||!$p['wp_post_id'])continue; $d=json_decode($p['plan'],true)?:[]; $it=$d['item']??[]; $out[]=['name'=>$it['name']??get_the_title($p['wp_post_id']),'price'=>$it['price']??'','facts'=>$it['facts']??[],'url'=>get_permalink((int)$p['wp_post_id'])]; } return $out; }
    public static function monogram($name){ $s=trim(wp_strip_all_tags($name)); return esc_html(mb_strtoupper(mb_substr($s,0,1))); }
}
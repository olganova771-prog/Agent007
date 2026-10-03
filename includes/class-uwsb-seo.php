<?php
if (!defined('ABSPATH')) exit;
class UWSB_SEO {
    public static function init(){
        add_filter('pre_get_document_title',[__CLASS__,'title']);
        add_action('wp_head',[__CLASS__,'head'],2);
    }
    public static function managed(){ return is_singular('page') && get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1'; }
    public static function title($title){ if(!self::managed())return $title; $custom=get_post_meta(get_queried_object_id(),'_uwsb_seo_title',true); return $custom?:$title; }
    public static function head(){ if(!self::managed())return; if(defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION'))return; $id=get_queried_object_id(); $desc=get_post_meta($id,'_uwsb_meta_description',true); $canonical=get_permalink($id); $robots=get_post_status($id)==='publish'?'index,follow':'noindex,follow';
        if($desc) echo '<meta name="description" content="'.esc_attr($desc).'">'."\n";
        echo '<link rel="canonical" href="'.esc_url($canonical).'">'."\n";
        echo '<meta name="robots" content="'.esc_attr($robots).'">'."\n";
        echo '<meta property="og:title" content="'.esc_attr(get_the_title($id)).'">'."\n";
        echo '<meta property="og:url" content="'.esc_url($canonical).'">'."\n";
        if($desc) echo '<meta property="og:description" content="'.esc_attr($desc).'">'."\n";
        $schema=['@context'=>'https://schema.org','@type'=>'WebPage','name'=>get_the_title($id),'url'=>$canonical];
        echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";
    }
}
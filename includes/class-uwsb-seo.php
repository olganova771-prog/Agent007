<?php
if (!defined('ABSPATH')) exit;

class UWSB_SEO {
    public static function init(){
        add_filter('pre_get_document_title',[__CLASS__,'title']);
        add_action('wp_head',[__CLASS__,'head'],2);
        add_filter('wp_sitemaps_posts_query_args',[__CLASS__,'sitemap_args'],10,2);
    }

    public static function managed(){
        return is_singular('page')&&get_post_meta(get_queried_object_id(),'_uwsb_managed',true)==='1';
    }

    public static function title($title){
        if(!self::managed()) return $title;
        $custom=get_post_meta(get_queried_object_id(),'_uwsb_seo_title',true);
        return $custom?:$title;
    }

    public static function head(){
        if(!self::managed()) return;
        $id=get_queried_object_id();
        $desc=(string)get_post_meta($id,'_uwsb_meta_description',true);
        $canonical=get_permalink($id);
        $indexable=(int)get_post_meta($id,'_uwsb_indexable',true)===1;
        $robots=($indexable&&get_post_status($id)==='publish')?'index,follow':'noindex,follow';

        if(!defined('WPSEO_VERSION')&&!defined('RANK_MATH_VERSION')){
            if($desc) echo '<meta name="description" content="'.esc_attr($desc).'">'."\n";
            echo '<link rel="canonical" href="'.esc_url($canonical).'">'."\n";
            echo '<meta name="robots" content="'.esc_attr($robots).'">'."\n";
            echo '<meta property="og:title" content="'.esc_attr(get_the_title($id)).'">'."\n";
            echo '<meta property="og:url" content="'.esc_url($canonical).'">'."\n";
            if($desc) echo '<meta property="og:description" content="'.esc_attr($desc).'">'."\n";
        }

        $translation=UWSB_Renderer::translation($id);
        $lang=UWSB_Renderer::lang_for_post($id);
        echo '<link rel="alternate" hreflang="'.esc_attr($lang==='ru'?'ru-UA':'uk-UA').'" href="'.esc_url($canonical).'">'."\n";
        if($translation){
            $other=$lang==='ru'?'uk-UA':'ru-UA';
            echo '<link rel="alternate" hreflang="'.esc_attr($other).'" href="'.esc_url(get_permalink($translation)).'">'."\n";
        }

        $schema=['@context'=>'https://schema.org','@type'=>'WebPage','name'=>get_the_title($id),'url'=>$canonical,'inLanguage'=>$lang==='ru'?'ru-UA':'uk-UA'];
        echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>'."\n";
    }

    public static function sitemap_args($args,$post_type){
        if($post_type!=='page') return $args;
        $args['meta_query']=(array)($args['meta_query']??[]);
        $args['meta_query'][]=[
            'relation'=>'OR',
            ['key'=>'_uwsb_managed','compare'=>'NOT EXISTS'],
            [
                'relation'=>'AND',
                ['key'=>'_uwsb_managed','value'=>'1'],
                ['key'=>'_uwsb_indexable','value'=>'1'],
            ],
        ];
        return $args;
    }
}

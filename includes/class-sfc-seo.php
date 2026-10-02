<?php
if (!defined('ABSPATH')) { exit; }

class SFC_SEO {
    public static function init() {
        add_action('wp_head', array(__CLASS__,'head'), 1);
        add_filter('wp_robots', array(__CLASS__,'robots'), 20);
        add_filter('wp_sitemaps_posts_query_args', array(__CLASS__,'sitemap_filter'), 20, 2);
        add_filter('pre_get_document_title', array(__CLASS__,'document_title'));
        add_filter('wpseo_title', array(__CLASS__,'wpseo_title'));
        add_filter('wpseo_metadesc', array(__CLASS__,'wpseo_desc'));
        add_filter('wpseo_canonical', array(__CLASS__,'wpseo_canonical'));
        add_filter('rank_math/frontend/title', array(__CLASS__,'rank_title'));
        add_filter('rank_math/frontend/description', array(__CLASS__,'rank_desc'));
        add_filter('rank_math/frontend/canonical', array(__CLASS__,'rank_canonical'));
    }
    public static function managed_id(){if(!is_singular())return 0;$id=get_queried_object_id();return get_post_meta($id,'_sfc_managed',true)==='1'?$id:0;}
    private static function has_external(){return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION');}
    public static function head(){
        $id=self::managed_id();if(!$id)return;
        $title=(string)get_post_meta($id,'_sfc_seo_title',true);$desc=(string)get_post_meta($id,'_sfc_meta_description',true);$canonical=get_permalink($id);$lang=get_post_meta($id,'_sfc_lang',true);$tr=(int)get_post_meta($id,'_sfc_translation_id',true);
        if(!self::has_external() && !current_theme_supports('title-tag')){
            if($title)echo '<title>'.esc_html($title).'</title>\n';
            if($desc)echo '<meta name="description" content="'.esc_attr($desc).'">\n';
            echo '<link rel="canonical" href="'.esc_url($canonical).'">\n';
            echo '<meta property="og:type" content="article">\n<meta property="og:title" content="'.esc_attr($title).'">\n<meta property="og:description" content="'.esc_attr($desc).'">\n<meta property="og:url" content="'.esc_url($canonical).'">\n';
        }
        $alts=array();$alts[$lang]=$canonical;
        if($tr){$trlang=get_post_meta($tr,'_sfc_lang',true);$alts[$trlang]=get_permalink($tr);}
        foreach($alts as $al=>$url)echo '<link rel="alternate" hreflang="'.esc_attr($al).'" href="'.esc_url($url).'">\n';
        echo '<link rel="alternate" hreflang="x-default" href="'.esc_url($canonical).'">\n';
        echo '<script type="application/ld+json">'.wp_json_encode(self::schema($id)).'</script>\n';
    }
    public static function schema($id){
        $type=get_post_meta($id,'_sfc_page_type',true);$lang=get_post_meta($id,'_sfc_lang',true);$graph=array();
        $graph[]=array('@type'=>'WebPage','@id'=>get_permalink($id).'#webpage','url'=>get_permalink($id),'name'=>get_the_title($id),'inLanguage'=>$lang==='ru'?'ru-UA':'uk-UA','description'=>get_post_meta($id,'_sfc_meta_description',true));
        $graph[]=array('@type'=>'BreadcrumbList','itemListElement'=>array(array('@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>home_url('/')),array('@type'=>'ListItem','position'=>2,'name'=>get_the_title($id),'item'=>get_permalink($id))));
        if($type==='product'){
            $source=SFC_Generator::product_source((int)get_post_meta($id,'_sfc_entity_id',true),$lang);$product=array('@type'=>'Product','name'=>$source['name']);
            if($source['description'])$product['description']=wp_strip_all_tags($source['description']);
            if($source['sku'])$product['sku']=$source['sku'];
            if($source['thumbnail'])$product['image']=array(wp_get_attachment_image_url($source['thumbnail'],'full'));
            if($source['price']!=='')$product['offers']=array('@type'=>'Offer','price'=>$source['price'],'priceCurrency'=>$source['currency']?:'UAH','url'=>get_permalink($id));
            $graph[]=$product;
        }
        return array('@context'=>'https://schema.org','@graph'=>$graph);
    }
    public static function document_title($title){$id=self::managed_id();if($id && ($x=get_post_meta($id,'_sfc_seo_title',true))) return $x; return $title;}
    public static function robots($robots){$id=self::managed_id();if($id && (get_post_meta($id,'_sfc_noindex',true)==='1' || get_post_meta($id,'_sfc_root',true)==='1'))$robots['noindex']=true;return $robots;}
    public static function sitemap_filter($args,$post_type){if($post_type==='page'){$args['meta_query']=isset($args['meta_query'])?$args['meta_query']:array();$args['meta_query'][]=array('relation'=>'AND',array('relation'=>'OR',array('key'=>'_sfc_noindex','compare'=>'NOT EXISTS'),array('key'=>'_sfc_noindex','value'=>'1','compare'=>'!=')),array('key'=>'_sfc_root','compare'=>'NOT EXISTS'));}return $args;}
    public static function wpseo_title($v){$id=self::managed_id();return $id&&($x=get_post_meta($id,'_sfc_seo_title',true))?$x:$v;}
    public static function wpseo_desc($v){$id=self::managed_id();return $id&&($x=get_post_meta($id,'_sfc_meta_description',true))?$x:$v;}
    public static function wpseo_canonical($v){$id=self::managed_id();return $id?get_permalink($id):$v;}
    public static function rank_title($v){return self::wpseo_title($v);}
    public static function rank_desc($v){return self::wpseo_desc($v);}
    public static function rank_canonical($v){return self::wpseo_canonical($v);}
}

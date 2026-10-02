<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Renderer {
    public static function init() {
        add_filter('template_include', array(__CLASS__,'template'), 99);
        add_action('wp_enqueue_scripts', array(__CLASS__,'assets'));
    }

    public static function is_managed_page(){return is_singular('page') && get_post_meta(get_queried_object_id(),'_sfc_managed',true)==='1';}

    public static function template($template){
        if(self::is_managed_page()) return SFC_DIR.'templates/sfc-page.php';
        if(is_singular('sf_product')||is_singular('sf_city')||is_singular('sf_region')) return $template;
        return $template;
    }

    public static function assets(){
        if(self::is_managed_page() || is_page(get_option('sfc_settings')['home_page_id'] ?? 0)){
            wp_enqueue_style('sfc-frontend',SFC_URL.'assets/css/frontend.css',array(),SFC_VERSION);
            wp_enqueue_script('sfc-frontend',SFC_URL.'assets/js/frontend.js',array(),SFC_VERSION,true);
        }
    }

    public static function theme_class(){return 'sfc-theme-'.sanitize_html_class(SFC_Settings::get('theme','premium-dark'));}

    public static function shortcode_home(){
        $lang=isset($_GET['sf_lang']) && $_GET['sf_lang']==='ru' ? 'ru' : 'uk';
        ob_start();
        $products=get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>8,'orderby'=>'title','order'=>'ASC'));
        $cities=get_posts(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>8,'meta_query'=>array(array('key'=>'_sfc_active','value'=>'1')),'orderby'=>'title','order'=>'ASC'));
        $site=SFC_Settings::get('site_name');
        echo '<div class="sfc-shell '.esc_attr(self::theme_class()).'">';
        echo '<header class="sfc-header"><div class="sfc-container"><a class="sfc-brand" href="'.esc_url(home_url('/')).'">'.esc_html($site).'</a><button class="sfc-menu-toggle" type="button" aria-expanded="false">☰</button><nav class="sfc-nav"><a href="#products">'.esc_html($lang==='ru'?'Товары':'Товари').'</a><a href="#cities">'.esc_html($lang==='ru'?'Города':'Міста').'</a><a href="?sf_lang='.($lang==='ru'?'uk':'ru').'">'.esc_html($lang==='ru'?'UA':'RU').'</a></nav></div></header>';
        echo '<main><section class="sfc-hero"><div class="sfc-container"><span class="sfc-eyebrow">Site Factory</span><h1>'.esc_html($lang==='ru'?'Современный каталог с управляемой структурой':'Сучасний каталог з керованою структурою').'</h1><p>'.esc_html($lang==='ru'?'Контент строится из заданных данных товаров, регионов и городов — без AI-зависимости и случайных фактов.':'Контент будується з заданих даних товарів, регіонів і міст — без AI-залежності та випадкових фактів.').'</p><a class="sfc-button" href="'.esc_url(SFC_Settings::get('cta_url')).'">'.esc_html($lang==='ru'?SFC_Settings::get('cta_label_ru'):SFC_Settings::get('cta_label_uk')).'</a></div></section>';
        echo '<section class="sfc-section" id="products"><div class="sfc-container"><div class="sfc-section-head"><span class="sfc-eyebrow">'.esc_html($lang==='ru'?'Каталог':'Каталог').'</span><h2>'.esc_html($lang==='ru'?'Товары':'Товари').'</h2></div><div class="sfc-card-grid">';
        foreach($products as $p){$src=SFC_Generator::product_source($p->ID,$lang);$pid=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>'_sfc_signature','value'=>hash('sha256',implode('|',array('product',$p->ID,0,$lang)))))));$url=!empty($pid)?get_permalink($pid[0]):'#';echo '<a class="sfc-card" href="'.esc_url($url).'" ><h3>'.esc_html($src['name']?:$p->post_title).'</h3><p>'.esc_html(wp_trim_words($src['short']?:$src['description'],18)).'</p></a>';}
        echo '</div></div></section>';
        echo '<section class="sfc-section sfc-section-alt" id="cities"><div class="sfc-container"><div class="sfc-section-head"><span class="sfc-eyebrow">'.esc_html($lang==='ru'?'География':'Географія').'</span><h2>'.esc_html($lang==='ru'?'Города':'Міста').'</h2></div><div class="sfc-link-grid">';
        foreach($cities as $c){$src=SFC_Generator::city_source($c->ID,$lang);$pid=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>'_sfc_signature','value'=>hash('sha256',implode('|',array('city',$c->ID,0,$lang)))))));$url=!empty($pid)?get_permalink($pid[0]):'#';echo '<a class="sfc-link-card" href="'.esc_url($url).'">'.esc_html($src['name']?:$c->post_title).'<span aria-hidden="true">→</span></a>';}
        echo '</div></div></section></main><footer class="sfc-footer"><div class="sfc-container"><span>'.esc_html($site).' · Site Factory</span></div></footer></div>';
        return ob_get_clean();
    }
}

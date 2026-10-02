<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Meta_Boxes {
    public static function init(){
        add_action('add_meta_boxes',array(__CLASS__,'boxes'));
        add_action('save_post',array(__CLASS__,'save'),10,2);
    }
    public static function boxes(){
        add_meta_box('sfc_product_source','Источник фактов товара',array(__CLASS__,'product_box'),'sf_product','normal','high');
        add_meta_box('sfc_geo','География и SEO-генерация',array(__CLASS__,'product_geo_box'),'sf_product','side','default');
        add_meta_box('sfc_region_box','Данные региона',array(__CLASS__,'region_box'),'sf_region','normal','high');
        add_meta_box('sfc_city_box','Данные города',array(__CLASS__,'city_box'),'sf_city','normal','high');
    }
    private static function field($key,$value,$label,$type='text',$extra=''){
        echo '<p><label><strong>'.esc_html($label).'</strong></label><br>'; if($type==='textarea') echo '<textarea name="'.esc_attr($key).'" rows="5" style="width:100%" '. $extra .'>'.esc_textarea($value).'</textarea>'; else echo '<input type="'.esc_attr($type).'" name="'.esc_attr($key).'" value="'.esc_attr($value).'" style="width:100%" '. $extra .'>'; echo '</p>';
    }
    public static function product_box($post){
        wp_nonce_field('sfc_meta','sfc_nonce');
        foreach(array('uk'=>'UA','ru'=>'RU') as $lang=>$label){
            echo '<h3>'.$label.'</h3>';
            self::field('_sfc_name_'.$lang,get_post_meta($post->ID,'_sfc_name_'.$lang,true),'Название');
            self::field('_sfc_short_'.$lang,get_post_meta($post->ID,'_sfc_short_'.$lang,true),'Краткое описание','textarea');
            self::field('_sfc_description_'.$lang,get_post_meta($post->ID,'_sfc_description_'.$lang,true),'Полное описание','textarea');
            self::field('_sfc_features_'.$lang,get_post_meta($post->ID,'_sfc_features_'.$lang,true),'Характеристики (одна строка = один факт)','textarea');
            self::field('_sfc_benefits_'.$lang,get_post_meta($post->ID,'_sfc_benefits_'.$lang,true),'Преимущества (только подтверждённые)','textarea');
            self::field('_sfc_limitations_'.$lang,get_post_meta($post->ID,'_sfc_limitations_'.$lang,true),'Ограничения / важные оговорки','textarea');
            self::field('_sfc_use_cases_'.$lang,get_post_meta($post->ID,'_sfc_use_cases_'.$lang,true),'Сценарии использования','textarea');
            self::field('_sfc_variants_'.$lang,get_post_meta($post->ID,'_sfc_variants_'.$lang,true),'Варианты / комплектации','textarea');
            self::field('_sfc_facts_'.$lang,get_post_meta($post->ID,'_sfc_facts_'.$lang,true),'Факты / примечания','textarea');
            self::field('_sfc_faq_'.$lang,get_post_meta($post->ID,'_sfc_faq_'.$lang,true),'FAQ: Вопрос :: Ответ','textarea');
            self::field('_sfc_queries_'.$lang,get_post_meta($post->ID,'_sfc_queries_'.$lang,true),'Популярные запросы (по одному на строку)','textarea');
            self::field('_sfc_forbidden_'.$lang,get_post_meta($post->ID,'_sfc_forbidden_'.$lang,true),'Запрещённые утверждения','textarea');
        }
        self::field('_sfc_price',get_post_meta($post->ID,'_sfc_price',true),'Цена (не заполнять, если неизвестна)');
        self::field('_sfc_currency',get_post_meta($post->ID,'_sfc_currency',true),'Валюта');
        self::field('_sfc_sku',get_post_meta($post->ID,'_sfc_sku',true),'SKU / артикул');
        self::field('_sfc_gallery',get_post_meta($post->ID,'_sfc_gallery',true),'ID изображений галереи через запятую');
        self::field('_sfc_allowed_types',get_post_meta($post->ID,'_sfc_allowed_types',true),'Разрешённые типы страниц: product, product_city, collection, comparison','textarea');
    }
    public static function product_geo_box($post){
        $geo=get_post_meta($post->ID,'_sfc_geo_landing',true)==='1';
        echo '<p><label><input type="checkbox" name="_sfc_geo_landing" value="1" '.checked($geo,true,false).'> Разрешить geo-landing для товара</label></p>';
        self::field('_sfc_city_allowlist',get_post_meta($post->ID,'_sfc_city_allowlist',true),'Разрешённые города для Product+City (ID через запятую)');
        echo '<p class="description">Geo-страницы создаются только при прохождении usefulness gate матрицы.</p>';
    }
    public static function region_box($post){
        wp_nonce_field('sfc_meta','sfc_nonce');
        foreach(array('uk'=>'UA','ru'=>'RU') as $lang=>$label){echo '<h3>'.$label.'</h3>'; self::field('_sfc_name_'.$lang,get_post_meta($post->ID,'_sfc_name_'.$lang,true),'Название'); self::field('_sfc_description_'.$lang,get_post_meta($post->ID,'_sfc_description_'.$lang,true),'Описание','textarea'); self::field('_sfc_facts_'.$lang,get_post_meta($post->ID,'_sfc_facts_'.$lang,true),'Факты','textarea');}
        self::field('_sfc_region_code',get_post_meta($post->ID,'_sfc_region_code',true),'Код региона');
        $enable=get_post_meta($post->ID,'_sfc_enable_landing',true)==='1'; echo '<p><label><input type="checkbox" name="_sfc_enable_landing" value="1" '.checked($enable,true,false).'> Разрешить региональный landing</label></p>';
    }
    public static function city_box($post){
        wp_nonce_field('sfc_meta','sfc_nonce');
        foreach(array('uk'=>'UA','ru'=>'RU') as $lang=>$label){echo '<h3>'.$label.'</h3>'; self::field('_sfc_name_'.$lang,get_post_meta($post->ID,'_sfc_name_'.$lang,true),'Название'); self::field('_sfc_description_'.$lang,get_post_meta($post->ID,'_sfc_description_'.$lang,true),'Описание','textarea'); self::field('_sfc_facts_'.$lang,get_post_meta($post->ID,'_sfc_facts_'.$lang,true),'Локальные факты','textarea'); self::field('_sfc_delivery_'.$lang,get_post_meta($post->ID,'_sfc_delivery_'.$lang,true),'Доставка / получение','textarea'); self::field('_sfc_pickup_'.$lang,get_post_meta($post->ID,'_sfc_pickup_'.$lang,true),'Пункт выдачи','textarea'); self::field('_sfc_hours_'.$lang,get_post_meta($post->ID,'_sfc_hours_'.$lang,true),'Часы','textarea'); self::field('_sfc_address_'.$lang,get_post_meta($post->ID,'_sfc_address_'.$lang,true),'Адрес','textarea');}
        self::field('_sfc_region_id',get_post_meta($post->ID,'_sfc_region_id',true),'ID региона');
        $enable=get_post_meta($post->ID,'_sfc_enable_landing',true)==='1'; $active=get_post_meta($post->ID,'_sfc_active',true)==='1';
        echo '<p><label><input type="checkbox" name="_sfc_enable_landing" value="1" '.checked($enable,true,false).'> Разрешить city landing</label></p>';
        echo '<p><label><input type="checkbox" name="_sfc_active" value="1" '.checked($active,true,false).'> Город активен</label></p>';
    }
    public static function save($post_id,$post){
        if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(wp_is_post_revision($post_id))return;if(empty($_POST['sfc_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sfc_nonce'])),'sfc_meta'))return;
        if(!current_user_can('edit_post',$post_id))return;
        $types=array('sf_product','sf_region','sf_city');if(!in_array($post->post_type,$types,true))return;
        $fields=array('_sfc_name_uk','_sfc_name_ru','_sfc_short_uk','_sfc_short_ru','_sfc_description_uk','_sfc_description_ru','_sfc_features_uk','_sfc_features_ru','_sfc_benefits_uk','_sfc_benefits_ru','_sfc_limitations_uk','_sfc_limitations_ru','_sfc_use_cases_uk','_sfc_use_cases_ru','_sfc_variants_uk','_sfc_variants_ru','_sfc_facts_uk','_sfc_facts_ru','_sfc_faq_uk','_sfc_faq_ru','_sfc_queries_uk','_sfc_queries_ru','_sfc_forbidden_uk','_sfc_forbidden_ru','_sfc_price','_sfc_currency','_sfc_sku','_sfc_gallery','_sfc_allowed_types','_sfc_city_allowlist','_sfc_region_code','_sfc_region_id','_sfc_delivery_uk','_sfc_delivery_ru','_sfc_pickup_uk','_sfc_pickup_ru','_sfc_hours_uk','_sfc_hours_ru','_sfc_address_uk','_sfc_address_ru');
        foreach($fields as $key){if(array_key_exists($key,$_POST))update_post_meta($post_id,$key,sanitize_textarea_field(wp_unslash($_POST[$key])));}
        update_post_meta($post_id,'_sfc_geo_landing',empty($_POST['_sfc_geo_landing'])?'0':'1');
        update_post_meta($post_id,'_sfc_enable_landing',empty($_POST['_sfc_enable_landing'])?'0':'1');
        update_post_meta($post_id,'_sfc_active',empty($_POST['_sfc_active'])?'0':'1');
    }
}

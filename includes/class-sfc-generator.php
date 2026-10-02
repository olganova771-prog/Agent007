<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Generator {
    public static function init() {}

    public static function product_source($id,$lang='uk') {
        $lang = $lang === 'ru' ? 'ru' : 'uk';
        return array(
            'id'=>(int)$id,
            'name'=>(string)get_post_meta($id,"_sfc_name_{$lang}",true),
            'description'=>(string)get_post_meta($id,"_sfc_description_{$lang}",true),
            'short'=>(string)get_post_meta($id,"_sfc_short_{$lang}",true),
            'features'=>self::lines(get_post_meta($id,"_sfc_features_{$lang}",true)),
            'benefits'=>self::lines(get_post_meta($id,"_sfc_benefits_{$lang}",true)),
            'limitations'=>self::lines(get_post_meta($id,"_sfc_limitations_{$lang}",true)),
            'use_cases'=>self::lines(get_post_meta($id,"_sfc_use_cases_{$lang}",true)),
            'variants'=>self::lines(get_post_meta($id,"_sfc_variants_{$lang}",true)),
            'facts'=>self::lines(get_post_meta($id,"_sfc_facts_{$lang}",true)),
            'faq'=>self::faq(get_post_meta($id,"_sfc_faq_{$lang}",true)),
            'queries'=>self::lines(get_post_meta($id,"_sfc_queries_{$lang}",true)),
            'forbidden'=>self::lines(get_post_meta($id,"_sfc_forbidden_{$lang}",true)),
            'allowed_types'=>self::lines(get_post_meta($id,'_sfc_allowed_types',true)),
            'price'=>(string)get_post_meta($id,'_sfc_price',true),
            'currency'=>(string)get_post_meta($id,'_sfc_currency',true),
            'sku'=>(string)get_post_meta($id,'_sfc_sku',true),
            'gallery'=>self::ids(get_post_meta($id,'_sfc_gallery',true)),
            'thumbnail'=>(int)get_post_thumbnail_id($id),
        );
    }

    public static function region_source($id,$lang='uk') {
        $lang = $lang === 'ru' ? 'ru' : 'uk';
        $region = get_post_meta($id,'_sfc_region_code',true);
        return array(
            'id'=>(int)$id,
            'name'=>(string)get_post_meta($id,"_sfc_name_{$lang}",true) ?: get_the_title($id),
            'description'=>(string)get_post_meta($id,"_sfc_description_{$lang}",true),
            'facts'=>self::lines(get_post_meta($id,"_sfc_facts_{$lang}",true)),
            'enable_landing'=>get_post_meta($id,'_sfc_enable_landing',true)==='1',
            'code'=>$region,
        );
    }

    public static function city_source($id,$lang='uk') {
        $lang = $lang === 'ru' ? 'ru' : 'uk';
        return array(
            'id'=>(int)$id,
            'name'=>(string)get_post_meta($id,"_sfc_name_{$lang}",true) ?: get_the_title($id),
            'description'=>(string)get_post_meta($id,"_sfc_description_{$lang}",true),
            'facts'=>self::lines(get_post_meta($id,"_sfc_facts_{$lang}",true)),
            'delivery'=>(string)get_post_meta($id,"_sfc_delivery_{$lang}",true),
            'pickup'=>(string)get_post_meta($id,"_sfc_pickup_{$lang}",true),
            'hours'=>(string)get_post_meta($id,"_sfc_hours_{$lang}",true),
            'address'=>(string)get_post_meta($id,"_sfc_address_{$lang}",true),
            'enable_landing'=>get_post_meta($id,'_sfc_enable_landing',true)==='1',
            'region_id'=>(int)get_post_meta($id,'_sfc_region_id',true),
            'active'=>get_post_meta($id,'_sfc_active',true)==='1',
        );
    }

    public static function generate_page($job,$lease=array()) {
        $validation=SFC_Matrix::validate_job($job);
        if(empty($validation['valid'])) throw new SFC_Permanent_Job_Exception('Задание больше не прошло authoritative validation: '.$validation['reason']);
        $type = sanitize_key($job['page_type'] ?? '');
        $raw_entity = $job['entity_id'] ?? 0;
        $id = is_array($raw_entity) ? array_values(array_unique(array_filter(array_map('absint', $raw_entity)))) : absint($raw_entity);
        if (in_array($type, array('product','product_city'), true)) {
            $allowed = self::product_source($id, ($job['lang'] ?? 'uk') === 'ru' ? 'ru' : 'uk')['allowed_types'];
            if ($allowed && !in_array($type, $allowed, true)) {
                throw new SFC_Permanent_Job_Exception('Тип страницы запрещён настройками источника товара.');
            }
        }
        $related = absint($job['related_id'] ?? 0);
        $lang = ($job['lang'] ?? 'uk') === 'ru' ? 'ru' : 'uk';
        $seed = !empty($job['seed']) ? absint($job['seed']) : absint(crc32(wp_json_encode($job)));
        $profile = SFC_Variation::profile_for_page($type,$seed);

        $data = self::build_content($type,$id,$related,$lang,$profile,$job['query'] ?? '');
        if (empty($data['title']) || empty($data['content'])) throw new RuntimeException('Недостаточно данных для генерации страницы.');

        $entity_signature = is_array($id) ? implode(',', $id) : (string)$id;
        $signature = hash('sha256', implode('|',array($type,$entity_signature,$related,$lang)));
        $existing = self::find_generated_by_signature($signature);
        self::assert_lease($lease);
        $parent = self::language_root($lang);
        if(!$parent) throw new RuntimeException('Не удалось создать или найти языковой корень.');
        $postarr=array(
            'post_type'=>'page',
            'post_status'=>$existing?get_post_status($existing):'publish',
            'post_title'=>$data['title'],
            'post_content'=>$data['content'],
            'post_excerpt'=>$data['excerpt'],
            'post_name'=>sanitize_title($data['slug']),
            'post_parent'=>$parent,
            'menu_order'=>0,
        );
        if($existing){
            $postarr['ID']=$existing;
            $post_id=wp_update_post($postarr,true);
        }else{
            $post_id=wp_insert_post($postarr,true);
        }
        if (is_wp_error($post_id)) throw new RuntimeException($post_id->get_error_message());

        self::assert_lease($lease);
        $meta=array(
            '_sfc_managed'=>'1','_sfc_page_type'=>$type,'_sfc_entity_id'=>$id,'_sfc_related_id'=>$related,
            '_sfc_lang'=>$lang,'_sfc_signature'=>$signature,'_sfc_seed'=>$seed,'_sfc_generation_profile'=>$profile,
            '_sfc_content_hash'=>hash('sha256',wp_strip_all_tags($data['content'])),'_sfc_title_generated'=>$data['title'],
            '_sfc_seo_title'=>$data['seo_title'],'_sfc_meta_description'=>$data['meta_description'],
            '_sfc_noindex'=>$data['noindex']?'1':'0','_sfc_query'=>$job['query']??'',
        );
        foreach($meta as $key=>$value) self::set_meta($post_id,$key,$value);

        self::assert_lease($lease);
        self::link_translation($post_id,$type,$id,$related,$lang);
        self::assert_lease($lease);
        $qa=SFC_QA::run_for_page($post_id);if(!is_array($qa))throw new RuntimeException('QA не сохранил результат.');
        if(!empty($lease['completion_key']))self::set_meta($post_id,'_sfc_completed_execution',$lease['completion_key']);
        SFC_DB::log('info',$existing?'page_updated':'page_created',$existing?'Обновлена страница':'Создана страница',array('post_id'=>$post_id,'type'=>$type,'lang'=>$lang));
        return $post_id;
    }

    public static function job_was_completed($job,$completion_key){
        $type=sanitize_key($job['page_type']??'');$raw=$job['entity_id']??0;$id=is_array($raw)?array_values(array_unique(array_filter(array_map('absint',$raw)))):absint($raw);$entity=is_array($id)?implode(',',$id):(string)$id;$related=absint($job['related_id']??0);$lang=($job['lang']??'uk')==='ru'?'ru':'uk';
        $post_id=self::find_generated_by_signature(hash('sha256',implode('|',array($type,$entity,$related,$lang))));
        return $post_id&&hash_equals((string)get_post_meta($post_id,'_sfc_completed_execution',true),(string)$completion_key);
    }
    private static function assert_lease($lease){if($lease&&!SFC_Queue::heartbeat($lease))throw new RuntimeException('Worker lost ownership lease.');}

    private static function build_content($type,$id,$related,$lang,$profile,$query) {
        $variant = SFC_Variation::template_variant($profile['seed'],3);
        $title=''; $slug=''; $excerpt=''; $seo_title=''; $meta=''; $blocks=array();
        if ($type==='product') {
            $p=self::product_source($id,$lang); if (!$p['name']) return array();
            $title=$p['name']; $slug='tovar-'.sanitize_title($p['name']);
            $intro=self::intro_product($p,$profile,$variant,$lang);
            $blocks[] = self::block('lead',$intro);
            if ($p['description']) $blocks[] = self::block('content','<h2>'.esc_html($lang==='ru'?'Описание':'Опис').'</h2>'.self::paragraphs($p['description']));
            if ($p['features']) $blocks[] = self::list_block($lang==='ru'?'Характеристики':'Характеристики',$p['features'], $profile);
            if ($p['benefits']) $blocks[] = self::list_block($lang==='ru'?'Преимущества':'Переваги',$p['benefits'], $profile);
            if ($p['use_cases']) $blocks[] = self::list_block($lang==='ru'?'Сценарии использования':'Сценарії використання',$p['use_cases'], $profile);
            if ($p['variants']) $blocks[] = self::list_block($lang==='ru'?'Варианты':'Варіанти',$p['variants'], $profile);
            if ($p['facts']) $blocks[] = self::list_block($lang==='ru'?'Факты из карточки товара':'Факти з картки товару',$p['facts'], $profile);
            if ($p['limitations']) $blocks[] = self::list_block($lang==='ru'?'Ограничения':'Обмеження',$p['limitations'], $profile);
            if ($p['faq']) $blocks[] = self::faq_block($p['faq'],$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=$p['short'] ?: wp_trim_words(wp_strip_all_tags($p['description']),26);
            $seo_title=$p['name'].' — '.SFC_Settings::get('site_name');
            $meta=wp_trim_words(wp_strip_all_tags($p['short'] ?: $p['description']),25);
        } elseif ($type==='region') {
            $r=self::region_source($id,$lang); if (!$r['name']) return array();
            $cities=self::cities_for_region($id,$lang);
            $title=$r['name']; $slug='region-'.sanitize_title($r['name']);
            $blocks[] = self::block('lead', self::local_intro($r['name'],$r['description'],$lang,$profile,$variant));
            if ($r['facts']) $blocks[] = self::list_block($lang==='ru'?'Факты региона':'Факти регіону',$r['facts'],$profile);
            if ($cities) $blocks[] = self::list_links_block($lang==='ru'?'Города':'Міста',$cities,$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=wp_trim_words(wp_strip_all_tags($r['description']),26);
            $seo_title=$r['name'].' — '.SFC_Settings::get('site_name');
            $meta=wp_trim_words(wp_strip_all_tags($r['description'] ?: ($lang==='ru'?'Подборка городов и данных по региону.':'Добірка міст і даних по регіону.')),25);
        } elseif ($type==='city') {
            $c=self::city_source($id,$lang); if (!$c['name']) return array();
            $title=$c['name']; $slug='gorod-'.sanitize_title($c['name']);
            $blocks[] = self::block('lead',self::local_intro($c['name'],$c['description'],$lang,$profile,$variant));
            $facts=array_merge($c['facts'],array_filter(array($c['delivery'],$c['pickup'],$c['hours'],$c['address'])));
            if ($facts) $blocks[] = self::list_block($lang==='ru'?'Локальные данные':'Локальні дані',$facts,$profile);
            $products=self::city_products($id,$lang);
            if ($products) $blocks[] = self::list_links_block($lang==='ru'?'Товары, связанные с городом':'Товари, пов’язані з містом',$products,$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=wp_trim_words(wp_strip_all_tags($c['description']),26);
            $seo_title=$c['name'].' — '.SFC_Settings::get('site_name');
            $meta=wp_trim_words(wp_strip_all_tags($c['description'] ?: ($lang==='ru'?'Информация и подборка товаров для города.':'Інформація та добірка товарів для міста.')),25);
        } elseif ($type==='product_city') {
            $p=self::product_source($id,$lang); $c=self::city_source($related,$lang); if (!$p['name'] || !$c['name']) return array();
            $title=$p['name'].' — '.$c['name']; $slug=sanitize_title($p['name'].' '.$c['name']);
            $lead=$lang==='ru'?'Страница объединяет данные товара «'.$p['name'].'» с контекстом города «'.$c['name'].'». Используются только сведения, заданные в карточках товара и города.':'Сторінка поєднує дані товару «'.$p['name'].'» з контекстом міста «'.$c['name'].'». Використовуються лише відомості, задані в картках товару та міста.';
            $blocks[] = self::block('lead','<p>'.esc_html($lead).'</p>');
            if ($p['description']) $blocks[] = self::block('content','<h2>'.esc_html($lang==='ru'?'О товаре':'Про товар').'</h2>'.self::paragraphs($p['description']));
            $facts=array_merge($c['facts'],array_filter(array($c['delivery'],$c['pickup'],$c['hours'],$c['address'])));
            if ($facts) $blocks[] = self::list_block($lang==='ru'?'Контекст города':'Контекст міста',$facts,$profile);
            if ($p['features']) $blocks[] = self::list_block($lang==='ru'?'Характеристики':'Характеристики',$p['features'],$profile);
            if ($p['use_cases']) $blocks[] = self::list_block($lang==='ru'?'Сценарии использования':'Сценарії використання',$p['use_cases'],$profile);
            if ($p['faq']) $blocks[] = self::faq_block($p['faq'],$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=wp_trim_words(wp_strip_all_tags($p['short'] ?: $p['description']),26);
            $seo_title=$p['name'].' в '.$c['name'].' — '.SFC_Settings::get('site_name');
            $meta=wp_trim_words(wp_strip_all_tags(($p['short'] ?: $p['description']).' '.$c['description']),25);
        }
        elseif ($type==='collection') {
            $ids=(array)$id; $products=array(); foreach($ids as $pid){$p=self::product_source($pid,$lang); if($p['name']) $products[]=$p;}
            if(count($products)<2) return array();
            $title=$lang==='ru'?'Подборка товаров':'Добірка товарів'; $slug='catalog-'.$variant.'-'.implode('-',array_map(fn($p)=>sanitize_title($p['name']),$products));
            $blocks[] = self::block('lead','<p>'.esc_html($lang==='ru'?'Подборка сформирована из выбранных товаров без добавления неподтверждённых характеристик.':'Добірку сформовано з вибраних товарів без додавання непідтверджених характеристик.').'</p>');
            foreach($products as $p) $blocks[] = self::product_card($p,$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=$lang==='ru'?'Подборка товаров на основе заданных данных.':'Добірка товарів на основі заданих даних.';
            $seo_title=$title.' — '.SFC_Settings::get('site_name'); $meta=$excerpt;
        }
        elseif ($type==='comparison') {
            $ids=(array)$id; $products=array(); foreach($ids as $pid){$p=self::product_source($pid,$lang); if($p['name']) $products[]=$p;}
            if(count($products)<2) return array();
            $title=$lang==='ru'?'Сравнение товаров':'Порівняння товарів'; $slug='sravnenie-'.implode('-',array_map(fn($p)=>sanitize_title($p['name']),$products));
            $blocks[] = self::block('lead','<p>'.esc_html($lang==='ru'?'Сравнение построено только по характеристикам, которые есть в исходных карточках. Если поля нет, оно не заполняется автоматически.':'Порівняння побудовано лише за характеристиками, які є в початкових картках. Якщо поля немає, воно не заповнюється автоматично.').'</p>');
            $blocks[] = self::comparison_table($products,$lang);
            $blocks[] = self::cta_block($lang);
            $excerpt=$lang==='ru'?'Нейтральное сравнение по исходным характеристикам.':'Нейтральне порівняння за вихідними характеристиками.';
            $seo_title=$title.' — '.SFC_Settings::get('site_name'); $meta=$excerpt;
        }
        if (!$blocks) return array();
        $content=implode("\n",$blocks);
        $content=self::insert_internal_links($content,$lang,$type,$id,$related);
        return array('title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'content'=>$content,'seo_title'=>$seo_title,'meta_description'=>$meta,'noindex'=>false);
    }

    private static function intro_product($p,$profile,$variant,$lang) {
        $short=$p['short'] ?: $p['description'];
        if ($variant===0) $lead=$lang==='ru' ? 'Ниже собрана структурированная информация о товаре «'.$p['name'].'».' : 'Нижче зібрана структурована інформація про товар «'.$p['name'].'».';
        elseif ($variant===1) $lead=$lang==='ru' ? 'Перед выбором «'.$p['name'].'» удобно быстро посмотреть основные сведения, характеристики и сценарии использования.' : 'Перед вибором «'.$p['name'].'» зручно швидко переглянути основні відомості, характеристики та сценарії використання.';
        else $lead=$lang==='ru' ? 'Если нужен «'.$p['name'].'», эта страница объединяет ключевые данные из карточки товара в одном месте.' : 'Якщо потрібен «'.$p['name'].'», ця сторінка об’єднує ключові дані з картки товару в одному місці.';
        return '<p>'.esc_html($lead).'</p>'.($short ? '<p>'.self::paragraphs($short).'</p>' : '');
    }

    private static function local_intro($name,$description,$lang,$profile,$variant) {
        if ($variant===0) $lead=$lang==='ru'?'Страница собрана вокруг города/региона «'.$name.'» и содержит только введённые локальные сведения.':'Сторінку зібрано навколо міста/регіону «'.$name.'» і вона містить лише введені локальні відомості.';
        elseif ($variant===1) $lead=$lang==='ru'?'Здесь собраны данные, связанные с «'.$name.'», а также доступные материалы каталога.':'Тут зібрані дані, пов’язані з «'.$name.'», а також доступні матеріали каталогу.';
        else $lead=$lang==='ru'?'Ниже — структурированная информация по «'.$name.'» без добавления непроверенных фактов.':'Нижче — структурована інформація про «'.$name.'» без додавання неперевірених фактів.';
        return '<p>'.esc_html($lead).'</p>'.($description ? '<p>'.self::paragraphs($description).'</p>':'');
    }

    private static function block($class,$html){ return '<section class="sfc-block sfc-block-'.$class.'">'.$html.'</section>'; }

    private static function list_block($heading,$items,$profile){
        $use_table = ($profile['structuredness']>65 && $profile['feature_orientation']>55 && count($items)>=3);
        if ($use_table && strip_tags(implode('', $items)) !== '') {
            $html='<table class="sfc-table"><tbody>'; foreach($items as $i=>$item) $html.='<tr><th>'.esc_html('№'.($i+1)).'</th><td>'.esc_html($item).'</td></tr>'; $html.='</tbody></table>';
        } else {
            $html='<ul class="sfc-list">'; foreach($items as $item) $html.='<li>'.esc_html($item).'</li>'; $html.='</ul>';
        }
        return self::block('list','<h2>'.esc_html($heading).'</h2>'.$html);
    }

    private static function list_links_block($heading,$items,$lang){
        $html='<div class="sfc-link-grid">'; foreach($items as $item){$html.='<a class="sfc-link-card" href="'.esc_url($item['url']).'"><span>'.esc_html($item['label']).'</span><span aria-hidden="true">→</span></a>';}$html.='</div>';
        return self::block('links','<h2>'.esc_html($heading).'</h2>'.$html);
    }

    private static function faq_block($items,$lang){
        $html='<div class="sfc-faq">'; foreach($items as $f){$html.='<details><summary>'.esc_html($f['q']).'</summary><div>'.wp_kses_post(wpautop($f['a'])).'</div></details>';}$html.='</div>';
        return self::block('faq','<h2>'.esc_html($lang==='ru'?'Частые вопросы':'Поширені запитання').'</h2>'.$html);
    }

    private static function cta_block($lang){
        $label=$lang==='ru'?SFC_Settings::get('cta_label_ru'):SFC_Settings::get('cta_label_uk');
        return self::block('cta','<a class="sfc-button" href="'.esc_url(SFC_Settings::get('cta_url')).'">'.esc_html($label).'</a>');
    }

    private static function product_card($p,$lang){
        $html='<article class="sfc-product-card"><h2>'.esc_html($p['name']).'</h2>'; if($p['short'])$html.='<p>'.esc_html(wp_trim_words($p['short'],34)).'</p>'; if($p['features']){$html.='<ul>';foreach(array_slice($p['features'],0,5)as$f)$html.='<li>'.esc_html($f).'</li>';$html.='</ul>';}$html.='</article>'; return self::block('card',$html);
    }

    private static function comparison_table($products,$lang){
        $map=array(); foreach($products as $p){ foreach($p['features'] as $f){$parts=explode(':',$f,2);$key=trim($parts[0]);$val=isset($parts[1])?trim($parts[1]):trim($f);$map[$key][$p['name']]=$val;}}
        $html='<div class="sfc-scroll"><table class="sfc-table sfc-comparison"><thead><tr><th>'.esc_html($lang==='ru'?'Параметр':'Параметр').'</th>'; foreach($products as $p)$html.='<th>'.esc_html($p['name']).'</th>'; $html.='</tr></thead><tbody>';
        foreach($map as $key=>$vals){$html.='<tr><th>'.esc_html($key).'</th>';foreach($products as $p)$html.='<td>'.esc_html($vals[$p['name']]??'—').'</td>'; $html.='</tr>';}
        $html.='</tbody></table></div>'; return self::block('comparison',$html);
    }

    private static function insert_internal_links($content,$lang,$type,$id,$related){
        $links=array();
        if($type==='product'){
            $city_pages=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>3,'meta_query'=>array(array('key'=>'_sfc_managed','value'=>'1'),array('key'=>'_sfc_lang','value'=>$lang),array('key'=>'_sfc_page_type','value'=>'city'))));
            foreach($city_pages as $p)$links[]=array('label'=>$p->post_title,'url'=>get_permalink($p->ID));
        } elseif($type==='city'){
            $product_pages=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>5,'meta_query'=>array(array('key'=>'_sfc_managed','value'=>'1'),array('key'=>'_sfc_lang','value'=>$lang),array('key'=>'_sfc_page_type','value'=>'product'))));
            foreach($product_pages as $p)$links[]=array('label'=>$p->post_title,'url'=>get_permalink($p->ID));
        }
        if(!$links)return $content;
        $section='<section class="sfc-block sfc-links"><h2>'.esc_html($lang==='ru'?'Также может быть полезно':'Також може бути корисно').'</h2><div class="sfc-link-grid">';foreach($links as $l)$section.='<a class="sfc-link-card" href="'.esc_url($l['url']).'">'.esc_html($l['label']).' <span aria-hidden="true">→</span></a>'; $section.='</div></section>';
        return $content.$section;
    }

    private static function language_root($lang){
        $slug=$lang==='ru'?'ru':'ua';
        $title=$lang==='ru'?'RU':'UA';
        $found=get_page_by_path('sf-'.$slug);
        if($found)return $found->ID;
        $id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>'sf-'.$slug,'post_content'=>'','post_parent'=>0),true);
        if(is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        if ($id) { self::set_meta($id,'_sfc_root','1');self::set_meta($id,'_sfc_noindex','1'); }
        return $id;
    }

    private static function find_generated_by_signature($signature){
        $posts=get_posts(array('post_type'=>'page','post_status'=>array('publish','draft','private'),'posts_per_page'=>1,'meta_key'=>'_sfc_signature','meta_value'=>$signature,'fields'=>'ids')); return !empty($posts)?(int)$posts[0]:0;
    }

    private static function link_translation($post_id,$type,$id,$related,$lang){
        $other=$lang==='uk'?'ru':'uk';
        $entity_signature=is_array($id)?implode(',',$id):(string)$id;
        $signature=hash('sha256',implode('|',array($type,$entity_signature,$related,$other)));
        $other_id=self::find_generated_by_signature($signature);
        if($other_id){self::set_meta($post_id,'_sfc_translation_id',$other_id);self::set_meta($other_id,'_sfc_translation_id',$post_id);}
        else{delete_post_meta($post_id,'_sfc_translation_id');if(get_post_meta($post_id,'_sfc_translation_id',true)!=='')throw new RuntimeException('Не удалось очистить translation meta.');}
    }

    private static function cities_for_region($region_id,$lang){
        $posts=get_posts(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>20,'meta_query'=>array(array('key'=>'_sfc_region_id','value'=>$region_id),array('key'=>'_sfc_active','value'=>'1')),'orderby'=>'title','order'=>'ASC'));
        $out=array(); foreach($posts as $p){$source=self::city_source($p->ID,$lang);$page=self::find_generated_by_signature(hash('sha256',implode('|',array('city',$p->ID,0,$lang))));if($page)$out[]=array('label'=>$source['name'],'url'=>get_permalink($page));} return $out;
    }

    private static function city_products($city_id,$lang){
        $out=array(); $products=get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>20));
        foreach($products as $p){$source=self::product_source($p->ID,$lang);if(!$source['name'])continue;if(SFC_Intent::has_local_for_city($source['queries'],self::city_source($city_id,$lang)['name']) || get_post_meta($p->ID,'_sfc_city_allowlist',true)){ $pid=self::find_generated_by_signature(hash('sha256',implode('|',array('product',$p->ID,0,$lang)))); if($pid)$out[]=array('label'=>$source['name'],'url'=>get_permalink($pid)); }} return $out;
    }

    private static function lines($value){
        if (is_array($value)) return array_values(array_filter(array_map('sanitize_text_field',$value)));
        $lines=preg_split('/\r\n|\r|\n/',(string)$value); return array_values(array_filter(array_map('trim',$lines),fn($v)=>$v!==''));
    }
    private static function faq($value){
        $out=array(); foreach(self::lines($value) as $line){$parts=explode('::',$line,2);if(count($parts)===2)$out[]=array('q'=>trim($parts[0]),'a'=>trim($parts[1]));} return $out;
    }
    private static function ids($value){return array_values(array_filter(array_map('absint',self::lines($value))));}
    private static function set_meta($post_id,$key,$value){
        update_post_meta($post_id,$key,$value);
        if(get_post_meta($post_id,$key,true)!=$value) throw new RuntimeException('Не удалось сохранить meta '.$key.' для страницы '.$post_id.'.');
    }
    private static function paragraphs($text){$paras=preg_split('/\r\n\r\n|\n\n/',trim((string)$text));$out='';foreach($paras as $p){$out.='<p>'.wp_kses_post(nl2br($p)).'</p>';}$out=trim($out);return $out;}
}

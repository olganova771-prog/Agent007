<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Matrix {
    public static function init() {}

    public static function rows($args = array()) {
        $rows = array();
        $langs = array('uk','ru');
        $products = get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC')); 
        $regions = get_posts(array('post_type'=>'sf_region','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC'));
        $cities = get_posts(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC'));

        foreach ($products as $product) {
            foreach ($langs as $lang) {
                $rows[] = self::evaluate('product', $product->ID, 0, $lang, '');
                foreach ($cities as $city) {
                    $rows[] = self::evaluate('product_city', $product->ID, $city->ID, $lang, '');
                }
            }
        }
        foreach ($regions as $region) foreach ($langs as $lang) $rows[] = self::evaluate('region', $region->ID, 0, $lang, '');
        foreach ($cities as $city) foreach ($langs as $lang) $rows[] = self::evaluate('city', $city->ID, 0, $lang, '');
        return $rows;
    }

    public static function evaluate($page_type, $entity_id, $related_id, $lang, $query = '') {
        $r = array('page_type'=>$page_type,'entity_id'=>(int)$entity_id,'related_id'=>(int)$related_id,'lang'=>$lang,'decision'=>'SKIP','reason'=>'','query'=>$query);
        if (!in_array($lang,array('uk','ru'),true)) { $r['reason']='Unsupported language'; return $r; }
        if ($page_type === 'product') {
            $source = SFC_Generator::product_source($entity_id,$lang);
            if (empty($source['name'])) { $r['reason']='Нет названия/данных для языка'; return $r; }
            if (empty($source['description']) && empty($source['features']) && empty($source['benefits'])) { $r['reason']='Недостаточно данных для самостоятельной страницы товара'; return $r; }
            $r['decision']='CREATE'; $r['reason']='Есть самостоятельный источник фактов товара'; return $r;
        }
        if ($page_type === 'region') {
            $region = SFC_Generator::region_source($entity_id,$lang);
            $city_count = self::active_city_count($entity_id);
            if (!empty($region['enable_landing']) && ($city_count >= (int)SFC_Settings::get('region_min_cities') || !empty($region['description']) || !empty($region['facts']))) {
                $r['decision']='CREATE'; $r['reason']='Регион содержит самостоятельные данные или достаточный набор городов';
            } else {
                $r['reason']='Недостаточно самостоятельных данных региона';
            }
            return $r;
        }
        if ($page_type === 'city') {
            $city = SFC_Generator::city_source($entity_id,$lang);
            $has_local = self::has_local_facts($city);
            $product_count = self::matching_product_count($entity_id,$lang);
            if (!empty($city['enable_landing']) && (($has_local && $product_count > 0) || $product_count >= 2)) {
                $r['decision']='CREATE'; $r['reason']='Город имеет локальные данные и/или достаточную товарную релевантность';
            } else {
                $r['reason']='Город не проходит самостоятельный usefulness gate';
            }
            return $r;
        }
        if ($page_type === 'product_city') {
            $product = SFC_Generator::product_source($entity_id,$lang);
            $city = SFC_Generator::city_source($related_id,$lang);
            if (empty($product['name']) || empty($city['name'])) { $r['reason']='Нет данных обеих сущностей на языке'; return $r; }
            if (!$city['active']) { $r['reason']='Город отключён'; return $r; }
            if ($product['allowed_types'] && !in_array('product_city', $product['allowed_types'], true)) { $r['reason']='Product+City запрещён в источнике товара'; return $r; }
            $queries = !empty($product['queries']) ? $product['queries'] : array();
            $city_allowed = self::product_allows_city($entity_id,$related_id);
            $local_query = SFC_Intent::has_local_for_city($queries,$city['name']);
            $flag = get_post_meta($entity_id,'_sfc_geo_landing',true) === '1';
            $local_facts = self::has_local_facts($city);
            $geo_gate = !SFC_Settings::get('product_city_require_query_or_allowlist', 1) || $local_query || $city_allowed || $flag;
            if ($geo_gate && !empty($product['description']) && (!SFC_Settings::get('city_require_local_fact') || $local_facts)) {
                $r['decision']='CREATE';
                $r['reason']='Выполнен geo gate: локальный запрос/allowlist/флаг + данные';
            } else {
                $r['decision']='MERGE';
                $r['reason']='Лучше раскрыть тему на Product или City page, чем создавать тонкую комбинацию';
            }
            return $r;
        }
        return $r;
    }

    private static function active_city_count($region_id) {
        $q = new WP_Query(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(
            array('key'=>'_sfc_region_id','value'=>$region_id,'compare'=>'='),
            array('key'=>'_sfc_active','value'=>'1','compare'=>'='),
        )));
        return (int)$q->found_posts;
    }

    private static function has_local_facts($city) {
        foreach (array('description','facts','delivery','pickup','hours','address') as $key) if (!empty($city[$key])) return true;
        return false;
    }

    private static function matching_product_count($city_id,$lang) {
        $count = 0;
        $city = SFC_Generator::city_source($city_id,$lang);
        if (empty($city['name'])) return 0;
        $products = get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>-1));
        foreach ($products as $p) {
            $source = SFC_Generator::product_source($p->ID,$lang);
            if (SFC_Intent::has_local_for_city($source['queries'],$city['name']) || self::product_allows_city($p->ID,$city_id) || get_post_meta($p->ID,'_sfc_geo_landing',true)==='1') $count++;
        }
        return $count;
    }

    private static function product_allows_city($product_id,$city_id) {
        $raw = (string)get_post_meta($product_id,'_sfc_city_allowlist',true);
        if ($raw === '') return false;
        $ids = preg_split('/[^0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        return in_array((string)$city_id, $ids, true);
    }

    public static function enqueue_create_jobs($limit = 500) {
        $rows = array_slice(self::rows(),0,max(1,(int)$limit));
        $count = 0;
        foreach ($rows as $row) {
            if ($row['decision'] !== 'CREATE') continue;
            $job = array(
                'page_type'=>$row['page_type'],
                'entity_id'=>$row['entity_id'],
                'related_id'=>$row['related_id'],
                'lang'=>$row['lang'],
                'query'=>$row['query'],
            );
            if (SFC_Queue::enqueue('generate_page',$job)) $count++;
        }
        return $count;
    }
}

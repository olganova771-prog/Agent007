<?php
if (!defined('ABSPATH')) exit;

class UWSB_Planner {
    private static function lines($value){
        if(is_array($value)) return array_values(array_filter(array_map('sanitize_text_field',$value)));
        return array_values(array_filter(array_map('sanitize_text_field',preg_split('/\r\n|\r|\n/',trim((string)$value)))));
    }

    public static function normalize_products($products){
        $out=[];
        foreach((array)$products as $i=>$raw){
            if(!is_array($raw)) continue;
            $name_uk=sanitize_text_field($raw['name_uk']??'');
            $name_ru=sanitize_text_field($raw['name_ru']??'');
            if($name_uk===''&&$name_ru==='') continue;
            $seed=$raw['key']??($raw['sku']??'');
            $key=$seed!==''?sanitize_key(strtolower((string)$seed)):sanitize_title($name_uk?:$name_ru);
            if($key==='') $key='product-'.($i+1);
            $item=['key'=>$key];
            foreach(['uk','ru'] as $lang){
                $item['name_'.$lang]=sanitize_text_field($raw['name_'.$lang]??'');
                $item['short_'.$lang]=sanitize_textarea_field($raw['short_'.$lang]??'');
                $item['description_'.$lang]=sanitize_textarea_field($raw['description_'.$lang]??'');
                foreach(['features','benefits','limitations','use_cases','variants','facts','queries','forbidden'] as $field){
                    $item[$field.'_'.$lang]=self::lines($raw[$field.'_'.$lang]??[]);
                }
                $item['faq_'.$lang]=self::lines($raw['faq_'.$lang]??[]);
            }
            $item['price']=sanitize_text_field($raw['price']??'');
            $item['currency']=sanitize_text_field($raw['currency']??'UAH');
            $item['sku']=sanitize_text_field($raw['sku']??'');
            $item['geo_notes']=sanitize_textarea_field($raw['geo_notes']??'');
            $out[]=$item;
        }
        return $out;
    }

    private static function localized_item($item,$lang){
        $fallback=$lang==='ru'?'uk':'ru';
        $pick=function($field)use($item,$lang,$fallback){
            $v=$item[$field.'_'.$lang]??null;
            if((is_array($v)&&$v)||(!is_array($v)&&trim((string)$v)!=='')) return $v;
            return $item[$field.'_'.$fallback]??(is_array($v)?[]:'');
        };
        return [
            'key'=>$item['key'],
            'name'=>(string)$pick('name'),
            'short'=>(string)$pick('short'),
            'description'=>(string)$pick('description'),
            'features'=>(array)$pick('features'),
            'benefits'=>(array)$pick('benefits'),
            'limitations'=>(array)$pick('limitations'),
            'use_cases'=>(array)$pick('use_cases'),
            'variants'=>(array)$pick('variants'),
            'facts'=>(array)$pick('facts'),
            'queries'=>(array)$pick('queries'),
            'forbidden'=>(array)$pick('forbidden'),
            'faq'=>(array)$pick('faq'),
            'price'=>$item['price'],
            'currency'=>$item['currency'],
            'sku'=>$item['sku'],
            'geo_notes'=>$item['geo_notes'],
        ];
    }

    public static function build($project_id,$config){
        UWSB_DB::clear_plan($project_id);
        $project=UWSB_DB::project($project_id);
        if(!$project) return [];

        $products=self::normalize_products($config['products']??[]);
        $languages=['uk'];
        if(!empty($project['secondary_lang'])&&$project['secondary_lang']==='ru') $languages[]='ru';

        $geo_all=!empty($config['geo_all_cities']);
        $geo_index_all=!empty($config['geo_index_all']);
        $selected=array_values(array_filter(array_map('sanitize_title',(array)($config['selected_cities']??[]))));
        $cities=$geo_all?UWSB_Geo::all_cities('uk'):array_values(array_filter(UWSB_Geo::all_cities('uk'),fn($c)=>in_array($c['slug'],$selected,true)));

        $pages=[];
        foreach($languages as $lang){
            $localized=[];
            foreach($products as $p) $localized[]=self::localized_item($p,$lang);

            $pages[]=[
                'page_key'=>$lang.':home','translation_key'=>'home','lang'=>$lang,'page_type'=>'home','intent'=>'overview','priority'=>10,
                'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'title'=>$project['name'],'items'=>$localized],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            ];

            if($localized){
                $pages[]=[
                    'page_key'=>$lang.':catalog','translation_key'=>'catalog','lang'=>$lang,'page_type'=>'catalog','intent'=>'browse','priority'=>20,
                    'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'items'=>$localized],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ];
            }

            foreach($localized as $i=>$it){
                $pages[]=[
                    'page_key'=>$lang.':product:'.$it['key'],'translation_key'=>'product:'.$it['key'],'lang'=>$lang,'page_type'=>'product',
                    'entity_key'=>$it['key'],'intent'=>'entity','priority'=>30+$i,'indexable'=>1,
                    'plan'=>wp_json_encode(['lang'=>$lang,'item'=>$it],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ];

                foreach($cities as $city){
                    $city_name=$lang==='ru'?$city['name_ru']:$city['name_uk'];
                    $translation='product_city:'.$it['key'].':'.$city['slug'];
                    $pages[]=[
                        'page_key'=>$lang.':'.$translation,
                        'translation_key'=>$translation,
                        'lang'=>$lang,
                        'page_type'=>'product_city',
                        'entity_key'=>$it['key'],
                        'city_slug'=>$city['slug'],
                        'intent'=>'local_commercial',
                        'priority'=>100,
                        'indexable'=>$geo_index_all?1:0,
                        'plan'=>wp_json_encode(['lang'=>$lang,'item'=>$it,'city'=>['name'=>$city_name,'name_uk'=>$city['name_uk'],'region'=>$city['region'],'slug'=>$city['slug']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                    ];
                }
            }

            if(count($localized)>=2){
                $pages[]=[
                    'page_key'=>$lang.':collection','translation_key'=>'collection','lang'=>$lang,'page_type'=>'collection','intent'=>'commercial','priority'=>65,
                    'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'items'=>$localized],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ];
                $pages[]=[
                    'page_key'=>$lang.':comparison','translation_key'=>'comparison','lang'=>$lang,'page_type'=>'comparison','intent'=>'comparison','priority'=>66,
                    'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'items'=>array_slice($localized,0,4)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ];
            }

            $pages[]=[
                'page_key'=>$lang.':coverage','translation_key'=>'coverage','lang'=>$lang,'page_type'=>'coverage','intent'=>'service_area','priority'=>80,
                'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'city_count'=>count($cities),'all_cities'=>$geo_all],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            ];
            $pages[]=[
                'page_key'=>$lang.':contacts','translation_key'=>'contacts','lang'=>$lang,'page_type'=>'contacts','intent'=>'contact','priority'=>90,
                'indexable'=>1,'plan'=>wp_json_encode(['lang'=>$lang,'contacts'=>sanitize_textarea_field($config['contacts_'.$lang]??$config['contacts_uk']??'')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            ];
        }

        foreach($pages as $p) UWSB_DB::save_page_plan($project_id,$p);
        return $pages;
    }
}

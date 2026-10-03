<?php
if (!defined('ABSPATH')) exit;
class UWSB_Planner {
    public static function parse_items($text){
        $items=[]; foreach(preg_split('/\r\n|\r|\n/',trim((string)$text)) as $line){
            $line=trim($line); if($line==='')continue; $parts=array_map('trim',explode('|',$line)); $name=array_shift($parts); if(!$name)continue;
            $facts=[]; $price=''; foreach($parts as $p){ if(preg_match('/^(?:price|цена|ціна)\s*[:=]\s*(.+)$/iu',$p,$m)) $price=trim($m[1]); else if($p!=='')$facts[]=$p; }
            $items[]=['key'=>sanitize_title($name),'name'=>sanitize_text_field($name),'facts'=>$facts,'price'=>sanitize_text_field($price)];
        } return $items;
    }
    public static function query_clusters($queries,$items=[]){
        $clusters=[]; foreach(preg_split('/\r\n|\r|\n/',trim((string)$queries)) as $q){ $q=trim(function_exists('mb_strtolower')?mb_strtolower($q):strtolower($q)); if(!$q)continue;
            $intent=preg_match('/\b(купить|купити|цена|ціна|заказать|замовити)\b/u',$q)?'commercial':(preg_match('/\b(как|як|что|що|почему|чому|обзор|огляд)\b/u',$q)?'informational':'mixed');
            $entity=''; foreach($items as $it){ if((function_exists('mb_stripos')?mb_stripos($q,function_exists('mb_strtolower')?mb_strtolower($it['name']):strtolower($it['name'])):stripos($q,strtolower($it['name'])))!==false){$entity=$it['key'];break;} }
            $k=$entity?:$intent; if(!isset($clusters[$k]))$clusters[$k]=['intent'=>$intent,'entity_key'=>$entity,'queries'=>[]]; $clusters[$k]['queries'][]=$q;
        } return array_values($clusters);
    }
    public static function build($project_id,$config){
        $items=self::parse_items($config['items_raw']??''); $clusters=self::query_clusters($config['queries_raw']??'',$items); $pages=[];
        $pages[]=['page_key'=>'home','page_type'=>'home','intent'=>'overview','priority'=>10,'plan'=>wp_json_encode(['title'=>$config['name'],'items'=>$items,'clusters'=>$clusters])];
        if($items) $pages[]=['page_key'=>'catalog','page_type'=>'catalog','intent'=>'browse','priority'=>20,'plan'=>wp_json_encode(['title'=>'Каталог','items'=>$items])];
        foreach($items as $i=>$it) $pages[]=['page_key'=>'product:'.$it['key'],'page_type'=>'product','entity_key'=>$it['key'],'intent'=>'entity','priority'=>30+$i,'plan'=>wp_json_encode(['item'=>$it,'clusters'=>array_values(array_filter($clusters,fn($c)=>$c['entity_key']===$it['key']))])];
        if(!empty(trim($config['geography_raw']??''))) $pages[]=['page_key'=>'coverage','page_type'=>'coverage','intent'=>'service_area','priority'=>70,'plan'=>wp_json_encode(['geography'=>sanitize_textarea_field($config['geography_raw'])])];
        $pages[]=['page_key'=>'contacts','page_type'=>'contacts','intent'=>'contact','priority'=>90,'plan'=>wp_json_encode(['contacts'=>sanitize_textarea_field($config['contacts_raw']??'')])];
        foreach($pages as $p) UWSB_DB::save_page_plan($project_id,$p);
        return $pages;
    }
}
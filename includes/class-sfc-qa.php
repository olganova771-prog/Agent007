<?php
if (!defined('ABSPATH')) { exit; }

class SFC_QA {
    public static function init() {}
    public static function run_for_page($post_id){
        $issues=array(); $post=get_post($post_id); if(!$post){return array('ok'=>false,'issues'=>array('post_not_found'));}
        $content=wp_strip_all_tags($post->post_content);
        if(!$post->post_title) $issues[]='missing_title';
        if(substr_count($post->post_content,'<h1')!==0) $issues[]='h1_should_be_template_owned';
        if(strlen($content)<120) $issues[]='content_too_short';
        $type=get_post_meta($post_id,'_sfc_page_type',true);$lang=get_post_meta($post_id,'_sfc_lang',true);
        if(!$type||!in_array($lang,array('uk','ru'),true))$issues[]='missing_page_identity';
        $entity=(int)get_post_meta($post_id,'_sfc_entity_id',true);$related=(int)get_post_meta($post_id,'_sfc_related_id',true);
        $source_ids=array_filter(array($entity,$related));
        foreach($source_ids as $sid){
            $forbidden=(array)SFC_Generator::product_source($sid,$lang)['forbidden'];
            foreach($forbidden as $bad){ if($bad!=='' && mb_stripos($content,$bad)!==false) { $issues[]='forbidden_claim:'.$bad; } }
        }
        $duplicate=self::duplicate_similarity($post_id);if($duplicate!==false){$issues[]='high_similarity_with_'.$duplicate;update_post_meta($post_id,'_sfc_qa_similarity_post',$duplicate);if((int)get_post_meta($post_id,'_sfc_qa_similarity_post',true)!==(int)$duplicate)throw new RuntimeException('Не удалось сохранить similarity QA meta.');}else{delete_post_meta($post_id,'_sfc_qa_similarity_post');if(get_post_meta($post_id,'_sfc_qa_similarity_post',true)!=='')throw new RuntimeException('Не удалось очистить similarity QA meta.');}
        $result=array('ok'=>empty($issues),'issues'=>$issues,'checked_at'=>current_time('mysql'));
        update_post_meta($post_id,'_sfc_qa',$result);
        if(get_post_meta($post_id,'_sfc_qa',true)!=$result)throw new RuntimeException('Не удалось сохранить результат QA.');
        if($issues)SFC_DB::log('warning','qa_issue','QA нашёл проблемы',array('post_id'=>$post_id,'issues'=>$issues));
        return $result;
    }
    public static function duplicate_similarity($post_id){
        $post=get_post($post_id);if(!$post)return false;$threshold=(float)SFC_Settings::get('similarity_threshold',0.82);
        $hash=(string)get_post_meta($post_id,'_sfc_content_hash',true);if(!$hash)return false;
        $others=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>100,'meta_query'=>array(array('key'=>'_sfc_managed','value'=>'1'),array('key'=>'_sfc_lang','value'=>get_post_meta($post_id,'_sfc_lang',true))), 'exclude'=>array($post_id)));
        $a=self::tokens($post->post_content);if(!$a)return false;
        foreach($others as $o){$b=self::tokens($o->post_content);$sim=self::jaccard($a,$b);if($sim>=$threshold)return $o->ID;}
        return false;
    }
    private static function tokens($text){$text=mb_strtolower(wp_strip_all_tags($text));$text=preg_replace('/[^\p{L}\p{N}\s]+/u',' ',$text);$parts=preg_split('/\s+/u',trim($text));$parts=array_filter($parts,fn($x)=>mb_strlen($x)>3);$sets=array();for($i=0;$i<count($parts)-1;$i++)$sets[$parts[$i].' '.$parts[$i+1]]=1;return $sets;}
    private static function jaccard($a,$b){$i=count(array_intersect_key($a,$b));$u=count($a)+count($b)-$i;return $u?($i/$u):0;}
}

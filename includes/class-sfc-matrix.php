<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Matrix {
    private static $context;

    public static function init() {}

    private static function context() {
        if (self::$context !== null) return self::$context;
        $ctx=array(
            'products'=>get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC')),
            'regions'=>get_posts(array('post_type'=>'sf_region','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC')),
            'cities'=>get_posts(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC')),
            'product_sources'=>array(),'region_sources'=>array(),'city_sources'=>array(),
            'allowlists'=>array(),'geo_flags'=>array(),'region_city_counts'=>array(),
        );
        foreach($ctx['cities'] as $city){
            $region_id=(int)get_post_meta($city->ID,'_sfc_region_id',true);
            if(get_post_meta($city->ID,'_sfc_active',true)==='1') $ctx['region_city_counts'][$region_id]=($ctx['region_city_counts'][$region_id]??0)+1;
        }
        self::$context=$ctx;
        return self::$context;
    }

    private static function row_generator($offset=0) {
        $ctx=self::context();
        $langs=array('uk','ru');$city_count=count($ctx['cities']);$per_language=$city_count+1;
        $product_rows=count($ctx['products'])*count($langs)*$per_language;
        $region_rows=count($ctx['regions'])*count($langs);
        $total=$product_rows+$region_rows+(count($ctx['cities'])*count($langs));
        for($index=max(0,(int)$offset);$index<$total;$index++){
            if($index<$product_rows){
                $block=intdiv($index,$per_language);$position=$index%$per_language;
                $product=$ctx['products'][intdiv($block,count($langs))];$lang=$langs[$block%count($langs)];
                if($position===0)yield self::evaluate_cached($ctx,'product',$product->ID,0,$lang,'');
                else yield self::evaluate_cached($ctx,'product_city',$product->ID,$ctx['cities'][$position-1]->ID,$lang,'');
                continue;
            }
            $relative=$index-$product_rows;
            if($relative<$region_rows){$region=$ctx['regions'][intdiv($relative,count($langs))];yield self::evaluate_cached($ctx,'region',$region->ID,0,$langs[$relative%count($langs)],'');continue;}
            $relative-=$region_rows;$city=$ctx['cities'][intdiv($relative,count($langs))];
            yield self::evaluate_cached($ctx,'city',$city->ID,0,$langs[$relative%count($langs)],'');
        }
    }

    public static function rows($args=array()) {
        $offset=max(0,absint($args['offset']??0));
        $limit=isset($args['limit'])?max(1,absint($args['limit'])):0;
        $rows=array();
        foreach(self::row_generator($offset) as $row){
            $rows[]=$row;
            if($limit && count($rows)>=$limit)break;
        }
        return $rows;
    }

    public static function evaluate($page_type,$entity_id,$related_id,$lang,$query='') {
        $ctx=self::context();return self::evaluate_cached($ctx,$page_type,$entity_id,$related_id,$lang,$query);
    }

    private static function evaluate_cached(&$ctx,$page_type,$entity_id,$related_id,$lang,$query='') {
        $r=array('page_type'=>$page_type,'entity_id'=>(int)$entity_id,'related_id'=>(int)$related_id,'lang'=>$lang,'decision'=>'SKIP','reason'=>'','query'=>$query);
        if(!in_array($lang,array('uk','ru'),true)){$r['reason']='Unsupported language';return $r;}
        if($page_type==='product'){
            $source=self::product_cached($ctx,$entity_id,$lang);
            if(empty($source['name'])){$r['reason']='Нет названия/данных для языка';return $r;}
            if($source['allowed_types'] && !in_array('product',$source['allowed_types'],true)){$r['reason']='Product page запрещена в источнике товара';return $r;}
            if(empty($source['description'])&&empty($source['features'])&&empty($source['benefits'])){$r['reason']='Недостаточно данных для самостоятельной страницы товара';return $r;}
            $r['decision']='CREATE';$r['reason']='Есть самостоятельный источник фактов товара';return $r;
        }
        if($page_type==='region'){
            $region=self::region_cached($ctx,$entity_id,$lang);
            $city_count=(int)($ctx['region_city_counts'][$entity_id]??0);
            if(!empty($region['enable_landing'])&&($city_count>=(int)SFC_Settings::get('region_min_cities')||!empty($region['description'])||!empty($region['facts']))){$r['decision']='CREATE';$r['reason']='Регион содержит самостоятельные данные или достаточный набор городов';}
            else $r['reason']='Недостаточно самостоятельных данных региона';
            return $r;
        }
        if($page_type==='city'){
            $city=self::city_cached($ctx,$entity_id,$lang);
            $has_local=self::has_local_facts($city);$product_count=self::matching_product_count_cached($ctx,$entity_id,$lang);
            if(!empty($city['enable_landing'])&&(($has_local&&$product_count>0)||$product_count>=2)){$r['decision']='CREATE';$r['reason']='Город имеет локальные данные и/или достаточную товарную релевантность';}
            else $r['reason']='Город не проходит самостоятельный usefulness gate';
            return $r;
        }
        if($page_type==='product_city'){
            $product=self::product_cached($ctx,$entity_id,$lang);
            $city=self::city_cached($ctx,$related_id,$lang);
            if(empty($product['name'])||empty($city['name'])){$r['reason']='Нет данных обеих сущностей на языке';return $r;}
            if(!$city['active']){$r['reason']='Город отключён';return $r;}
            if($product['allowed_types']&&!in_array('product_city',$product['allowed_types'],true)){$r['reason']='Product+City запрещён в источнике товара';return $r;}
            $city_allowed=self::product_allows_city_cached($ctx,$entity_id,$related_id);
            $local_query=SFC_Intent::has_local_for_city($product['queries'],$city['name']);
            $flag=self::geo_flag_cached($ctx,$entity_id);
            $geo_gate=!SFC_Settings::get('product_city_require_query_or_allowlist',1)||$local_query||$city_allowed||$flag;
            if($geo_gate&&!empty($product['description'])&&(!SFC_Settings::get('city_require_local_fact')||self::has_local_facts($city))){$r['decision']='CREATE';$r['reason']='Выполнен geo gate: локальный запрос/allowlist/флаг + данные';}
            else{$r['decision']='MERGE';$r['reason']='Лучше раскрыть тему на Product или City page, чем создавать тонкую комбинацию';}
            return $r;
        }
        return $r;
    }

    private static function has_local_facts($city){foreach(array('description','facts','delivery','pickup','hours','address') as $key)if(!empty($city[$key]))return true;return false;}
    private static function product_cached(&$ctx,$id,$lang){if(!isset($ctx['product_sources'][$lang][$id]))$ctx['product_sources'][$lang][$id]=SFC_Generator::product_source($id,$lang);return $ctx['product_sources'][$lang][$id];}
    private static function region_cached(&$ctx,$id,$lang){if(!isset($ctx['region_sources'][$lang][$id]))$ctx['region_sources'][$lang][$id]=SFC_Generator::region_source($id,$lang);return $ctx['region_sources'][$lang][$id];}
    private static function city_cached(&$ctx,$id,$lang){if(!isset($ctx['city_sources'][$lang][$id]))$ctx['city_sources'][$lang][$id]=SFC_Generator::city_source($id,$lang);return $ctx['city_sources'][$lang][$id];}
    private static function geo_flag_cached(&$ctx,$id){if(!array_key_exists($id,$ctx['geo_flags']))$ctx['geo_flags'][$id]=get_post_meta($id,'_sfc_geo_landing',true)==='1';return $ctx['geo_flags'][$id];}
    private static function product_allows_city_cached(&$ctx,$product_id,$city_id){if(!isset($ctx['allowlists'][$product_id])){$raw=(string)get_post_meta($product_id,'_sfc_city_allowlist',true);$ctx['allowlists'][$product_id]=$raw===''?array():preg_split('/[^0-9]+/',$raw,-1,PREG_SPLIT_NO_EMPTY);}return in_array((string)$city_id,$ctx['allowlists'][$product_id],true);}
    private static function matching_product_count_cached(&$ctx,$city_id,$lang){
        static $counts=array();
        if(isset($counts[$lang][$city_id]))return $counts[$lang][$city_id];
        $city=self::city_cached($ctx,$city_id,$lang);$count=0;
        if(!empty($city['name']))foreach($ctx['products'] as $product){$source=self::product_cached($ctx,$product->ID,$lang);if(SFC_Intent::has_local_for_city($source['queries'],$city['name'])||self::product_allows_city_cached($ctx,$product->ID,$city_id)||self::geo_flag_cached($ctx,$product->ID))$count++;}
        $counts[$lang][$city_id]=$count;return $count;
    }

    /** Validate a queued job against current source data immediately before generation. */
    public static function validate_job($job) {
        $type=sanitize_key($job['page_type']??'');
        $lang=($job['lang']??'uk')==='ru'?'ru':'uk';
        if(in_array($type,array('collection','comparison'),true)){
            $ids=array_values(array_unique(array_filter(array_map('absint',(array)($job['entity_id']??array())))));
            if(count($ids)<2)return array('valid'=>false,'reason'=>'Недостаточно товаров для ручной страницы.');
            $valid_products=0;
            foreach($ids as $id){
                if(get_post_type($id)!=='sf_product'||!in_array(get_post_status($id),array('publish','draft'),true))return array('valid'=>false,'reason'=>'Ручная страница содержит недоступный ID товара.');
                $source=SFC_Generator::product_source($id,$lang);
                if($source['allowed_types']&&!in_array($type,$source['allowed_types'],true))return array('valid'=>false,'reason'=>'Тип ручной страницы запрещён одним из товаров.');
                if(!empty($source['name']))$valid_products++;
            }
            if($valid_products<2)return array('valid'=>false,'reason'=>'Недостаточно товарных данных для выбранного языка.');
            return array('valid'=>true,'reason'=>'');
        }
        if(!in_array($type,array('product','product_city','region','city'),true))return array('valid'=>false,'reason'=>'Неизвестный тип страницы.');
        $entity=absint($job['entity_id']??0);$related=absint($job['related_id']??0);
        $expected=array('product'=>'sf_product','product_city'=>'sf_product','region'=>'sf_region','city'=>'sf_city');
        if(!$entity||get_post_type($entity)!==$expected[$type]||!in_array(get_post_status($entity),array('publish','draft'),true))return array('valid'=>false,'reason'=>'Исходная сущность отсутствует, недоступна или имеет неверный тип.');
        if($type==='product_city'&&(!$related||get_post_type($related)!=='sf_city'||!in_array(get_post_status($related),array('publish','draft'),true)))return array('valid'=>false,'reason'=>'Связанный город отсутствует, недоступен или имеет неверный тип.');
        $row=self::evaluate_fresh($type,$entity,$related,$lang,(string)($job['query']??''));
        return array('valid'=>$row['decision']==='CREATE','reason'=>$row['reason']);
    }

    private static function evaluate_fresh($type,$entity,$related,$lang,$query){
        $r=array('decision'=>'SKIP','reason'=>'Authoritative validation failed.');
        if($type==='product'){
            $p=SFC_Generator::product_source($entity,$lang);
            if(empty($p['name'])){$r['reason']='Нет названия/данных для языка';return $r;}
            if($p['allowed_types']&&!in_array('product',$p['allowed_types'],true)){$r['reason']='Product page запрещена в источнике товара';return $r;}
            if(empty($p['description'])&&empty($p['features'])&&empty($p['benefits'])){$r['reason']='Недостаточно данных товара';return $r;}
            return array('decision'=>'CREATE','reason'=>'Fresh product validation passed');
        }
        if($type==='product_city'){
            $p=SFC_Generator::product_source($entity,$lang);$c=SFC_Generator::city_source($related,$lang);
            if(empty($p['name'])||empty($c['name'])||empty($c['active'])){$r['reason']='Товар или активный город недоступен';return $r;}
            if($p['allowed_types']&&!in_array('product_city',$p['allowed_types'],true)){$r['reason']='Product+City запрещён';return $r;}
            $raw=(string)get_post_meta($entity,'_sfc_city_allowlist',true);$ids=preg_split('/[^0-9]+/',$raw,-1,PREG_SPLIT_NO_EMPTY);
            $gate=!SFC_Settings::get('product_city_require_query_or_allowlist',1)||SFC_Intent::has_local_for_city($p['queries'],$c['name'])||in_array((string)$related,$ids,true)||get_post_meta($entity,'_sfc_geo_landing',true)==='1';
            if($gate&&!empty($p['description'])&&(!SFC_Settings::get('city_require_local_fact')||self::has_local_facts($c)))return array('decision'=>'CREATE','reason'=>'Fresh product-city validation passed');
            $r['reason']='Product+City больше не проходит usefulness gate';return $r;
        }
        if($type==='region'){
            $source=SFC_Generator::region_source($entity,$lang);$q=new WP_Query(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>'_sfc_region_id','value'=>$entity),array('key'=>'_sfc_active','value'=>'1'))));
            if(!empty($source['enable_landing'])&&((int)$q->found_posts>=(int)SFC_Settings::get('region_min_cities')||!empty($source['description'])||!empty($source['facts'])))return array('decision'=>'CREATE','reason'=>'Fresh region validation passed');
            $r['reason']='Регион больше не проходит usefulness gate';return $r;
        }
        $city=SFC_Generator::city_source($entity,$lang);$count=0;
        if(!empty($city['name']))foreach(get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'fields'=>'ids')) as $pid){$p=SFC_Generator::product_source($pid,$lang);$raw=(string)get_post_meta($pid,'_sfc_city_allowlist',true);$ids=preg_split('/[^0-9]+/',$raw,-1,PREG_SPLIT_NO_EMPTY);if(SFC_Intent::has_local_for_city($p['queries'],$city['name'])||in_array((string)$entity,$ids,true)||get_post_meta($pid,'_sfc_geo_landing',true)==='1')$count++;}
        if(!empty($city['enable_landing'])&&((self::has_local_facts($city)&&$count>0)||$count>=2))return array('decision'=>'CREATE','reason'=>'Fresh city validation passed');
        $r['reason']='Город больше не проходит usefulness gate';return $r;
    }

    /** Enqueue the next CREATE page after a stable raw-row cursor. */
    private static function create_run(){
        global $wpdb;
        $snapshot=array(
            'version'=>2,
            'products'=>array_map('intval',get_posts(array('post_type'=>'sf_product','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'menu_order title','order'=>'ASC','fields'=>'ids'))),
            'regions'=>array_map('intval',get_posts(array('post_type'=>'sf_region','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','fields'=>'ids'))),
            'cities'=>array_map('intval',get_posts(array('post_type'=>'sf_city','post_status'=>array('publish','draft'),'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','fields'=>'ids'))),
            'languages'=>array('uk','ru'),
        );
        $token=wp_generate_uuid4();$encoded=wp_json_encode($snapshot);if($encoded===false)throw new RuntimeException('Не удалось сериализовать snapshot матрицы.');
        $ok=$wpdb->insert(SFC_DB::runs_table(),array('run_token'=>$token,'snapshot'=>$encoded,'cursor'=>0,'status'=>'active','created_at'=>current_time('mysql',true),'expires_at'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS)),array('%s','%s','%d','%s','%s','%s'));
        if($ok===false)throw new RuntimeException('Не удалось создать snapshot матрицы: '.$wpdb->last_error);
        return array('token'=>$token,'snapshot'=>$snapshot,'cursor'=>0);
    }
    private static function load_run($token){
        global $wpdb;if($token==='')return self::create_run();
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.SFC_DB::runs_table().' WHERE run_token=%s AND status=%s AND expires_at>UTC_TIMESTAMP()',$token,'active'),ARRAY_A);
        if(!$row){if($wpdb->last_error)throw new RuntimeException('Не удалось прочитать snapshot матрицы: '.$wpdb->last_error);return self::create_run();}$snapshot=json_decode($row['snapshot'],true);
        if(!self::snapshot_valid($snapshot))throw new RuntimeException('Повреждён snapshot матрицы.');
        return array('token'=>$row['run_token'],'snapshot'=>$snapshot,'cursor'=>(int)$row['cursor']);
    }
    private static function snapshot_valid($snapshot){
        if(!is_array($snapshot))return false;
        if(!isset($snapshot['version']))return empty($snapshot)||array_keys($snapshot)===range(0,count($snapshot)-1);
        return $snapshot['version']===2&&isset($snapshot['products'],$snapshot['regions'],$snapshot['cities'],$snapshot['languages'])&&is_array($snapshot['products'])&&is_array($snapshot['regions'])&&is_array($snapshot['cities'])&&is_array($snapshot['languages'])&&!empty($snapshot['languages']);
    }
    private static function snapshot_total($snapshot){
        if(isset($snapshot['version'])&&$snapshot['version']===2){$languages=count($snapshot['languages']);return count($snapshot['products'])*$languages*(count($snapshot['cities'])+1)+count($snapshot['regions'])*$languages+count($snapshot['cities'])*$languages;}
        return count($snapshot);
    }
    private static function snapshot_spec($snapshot,$index){
        if(!isset($snapshot['version'])||$snapshot['version']!==2)return $snapshot[$index]??null;
        $languages=$snapshot['languages'];$language_count=count($languages);$city_count=count($snapshot['cities']);$per_language=$city_count+1;
        $product_rows=count($snapshot['products'])*$language_count*$per_language;
        if($index<$product_rows){$block=intdiv($index,$per_language);$position=$index%$per_language;$product=$snapshot['products'][intdiv($block,$language_count)];$lang=$languages[$block%$language_count];return $position===0?array('product',$product,0,$lang):array('product_city',$product,$snapshot['cities'][$position-1],$lang);}
        $relative=$index-$product_rows;$region_rows=count($snapshot['regions'])*$language_count;
        if($relative<$region_rows)return array('region',$snapshot['regions'][intdiv($relative,$language_count)],0,$languages[$relative%$language_count]);
        $relative-=$region_rows;return array('city',$snapshot['cities'][intdiv($relative,$language_count)],0,$languages[$relative%$language_count]);
    }
    public static function cleanup_runs(){global $wpdb;$deleted=$wpdb->query('DELETE FROM '.SFC_DB::runs_table()." WHERE expires_at<UTC_TIMESTAMP() OR (status='complete' AND created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");if($deleted===false)throw new RuntimeException('Не удалось очистить matrix runs: '.$wpdb->last_error);return $deleted;}

    public static function enqueue_create_jobs($limit=500,$cursor=0,$run_token='') {
        global $wpdb;$legacy=func_num_args()===1;$limit=max(1,(int)$limit);self::cleanup_runs();$run=self::load_run($run_token);$cursor=$run['cursor'];
        $result=array('inserted'=>0,'requeued'=>0,'already_exists'=>0,'failed'=>0,'next_cursor'=>$cursor,'has_more'=>false,'run_token'=>$run['token']);
        $snapshot=$run['snapshot'];$total=self::snapshot_total($snapshot);
        for($index=$cursor;$index<$total;$index++){
            $spec=self::snapshot_spec($snapshot,$index);if(!$spec)throw new RuntimeException('Повреждён traversal snapshot матрицы.');$row=self::evaluate($spec[0],(int)$spec[1],(int)$spec[2],$spec[3],'');$next=$index+1;
            if($row['decision']!=='CREATE'){$result['next_cursor']=$next;continue;}
            if(($result['inserted']+$result['requeued']+$result['already_exists']+$result['failed'])>=$limit){$result['has_more']=true;break;}
            $job=array('page_type'=>$row['page_type'],'entity_id'=>$row['entity_id'],'related_id'=>$row['related_id'],'lang'=>$row['lang'],'query'=>$row['query']);
            $queued=SFC_Queue::enqueue_result('generate_page',$job);
            $status=$queued['status'];
            if(isset($result[$status]))$result[$status]++;else$result['failed']++;
            if($status==='failed'){$result['has_more']=true;break;}
            $result['next_cursor']=$next;
        }
        $result['has_more']=$result['has_more']||$result['next_cursor']<$total;
        $status=$result['next_cursor']>=$total?'complete':'active';
        $updated=$wpdb->query($wpdb->prepare('UPDATE '.SFC_DB::runs_table().' SET cursor=%d,status=%s WHERE run_token=%s AND cursor=%d',$result['next_cursor'],$status,$run['token'],$cursor));
        if($updated!==1&&$result['failed']===0){$result['failed']++;$result['has_more']=true;}
        return $legacy?($result['inserted']+$result['requeued']):$result;
    }
}

<?php
if (!defined('ABSPATH')) exit;

class UWSB_DB {
    private static $schema_ready = null;

    public static function t($name){ global $wpdb; return $wpdb->prefix.'uwsb_'.$name; }
    public static function now(){ return current_time('mysql',true); }

    public static function init(){
        if(get_option('uwsb_db_version')!==UWSB_DB_VERSION) self::install(false);
    }

    public static function activate(){
        self::install(true);
        self::seed_demo_project();
    }

    public static function schema_ready(){
        if(self::$schema_ready!==null) return self::$schema_ready;
        global $wpdb;
        foreach(['projects','pages','jobs','qa'] as $name){
            $table=self::t($name);
            if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table){
                return self::$schema_ready=false;
            }
        }
        $job_cols=(array)$wpdb->get_col('SHOW COLUMNS FROM '.self::t('jobs'),0);
        return self::$schema_ready=in_array('lease_token',$job_cols,true)&&in_array('lease_expires_at',$job_cols,true)&&in_array('execution_token',$job_cols,true);
    }

    private static function install($activation=false){
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $c=$wpdb->get_charset_collate();

        $sql=[];
        $sql[]="CREATE TABLE ".self::t('projects')." (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            site_type varchar(32) NOT NULL DEFAULT 'catalog',
            primary_lang varchar(8) NOT NULL DEFAULT 'uk',
            secondary_lang varchar(8) NULL,
            profile varchar(32) NOT NULL DEFAULT 'businessman',
            config longtext NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $c;";

        $sql[]="CREATE TABLE ".self::t('pages')." (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            page_key varchar(191) NOT NULL,
            translation_key varchar(191) NOT NULL,
            lang varchar(8) NOT NULL DEFAULT 'uk',
            page_type varchar(40) NOT NULL,
            entity_key varchar(191) NULL,
            city_slug varchar(191) NULL,
            intent varchar(80) NULL,
            plan longtext NOT NULL,
            wp_post_id bigint(20) unsigned NULL,
            indexable tinyint(1) NOT NULL DEFAULT 1,
            status varchar(24) NOT NULL DEFAULT 'planned',
            priority int NOT NULL DEFAULT 50,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY project_page (project_id,page_key),
            KEY project_status (project_id,status),
            KEY project_translation (project_id,translation_key,lang)
        ) $c;";

        $sql[]="CREATE TABLE ".self::t('jobs')." (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            job_key varchar(191) NOT NULL,
            job_type varchar(40) NOT NULL,
            payload longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            attempts int(11) unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            started_at datetime NULL,
            lease_token varchar(64) NULL,
            lease_expires_at datetime NULL,
            execution_token varchar(64) NULL,
            finished_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_key (job_key),
            KEY project_status (project_id,status,available_at),
            KEY lease_expiry (status,lease_expires_at)
        ) $c;";

        $sql[]="CREATE TABLE ".self::t('qa')." (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            page_key varchar(191) NULL,
            severity varchar(12) NOT NULL,
            code varchar(80) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_severity (project_id,severity)
        ) $c;";

        $messages=[];
        foreach($sql as $q) $messages=array_merge($messages,(array)dbDelta($q));
        self::$schema_ready=null;

        if(!self::schema_ready()){
            $error=$wpdb->last_error?:'Site Factory database schema is incomplete.';
            update_option('uwsb_db_error',$error,false);
            return false;
        }

        delete_option('uwsb_db_error');
        update_option('uwsb_db_version',UWSB_DB_VERSION,false);
        return true;
    }

    public static function create_project($data){
        global $wpdb;
        $now=self::now();
        $config=is_array($data['config']??null)?$data['config']:[];
        $ok=$wpdb->insert(self::t('projects'),[
            'name'=>sanitize_text_field($data['name']??'Site Factory'),
            'site_type'=>sanitize_key($data['site_type']??'catalog'),
            'primary_lang'=>($data['primary_lang']??'uk')==='ru'?'ru':'uk',
            'secondary_lang'=>($data['secondary_lang']??'')==='ru'?'ru':null,
            'profile'=>sanitize_key($data['profile']??'businessman'),
            'config'=>wp_json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'status'=>'planned',
            'created_at'=>$now,
            'updated_at'=>$now,
        ],['%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        if($ok===false) return 0;
        return (int)$wpdb->insert_id;
    }

    public static function update_project_config($id,$config){
        global $wpdb;
        return $wpdb->update(self::t('projects'),[
            'config'=>wp_json_encode((array)$config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'updated_at'=>self::now(),
        ],['id'=>(int)$id],['%s','%s'],['%d'])!==false;
    }

    public static function project($id){
        global $wpdb;
        $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('projects').' WHERE id=%d',$id),ARRAY_A);
        if($r) $r['config']=json_decode($r['config'],true)?:[];
        return $r;
    }

    public static function projects(){
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM '.self::t('projects').' ORDER BY id DESC LIMIT 100',ARRAY_A);
    }

    public static function clear_plan($project_id){
        global $wpdb;
        $wpdb->delete(self::t('jobs'),['project_id'=>(int)$project_id],['%d']);
        $wpdb->delete(self::t('pages'),['project_id'=>(int)$project_id],['%d']);
    }

    public static function save_page_plan($project_id,$page){
        global $wpdb;
        $now=self::now();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO ".self::t('pages')." (project_id,page_key,translation_key,lang,page_type,entity_key,city_slug,intent,plan,indexable,status,priority,created_at,updated_at)
             VALUES (%d,%s,%s,%s,%s,%s,%s,%s,%s,%d,'planned',%d,%s,%s)
             ON DUPLICATE KEY UPDATE translation_key=VALUES(translation_key),lang=VALUES(lang),page_type=VALUES(page_type),entity_key=VALUES(entity_key),city_slug=VALUES(city_slug),intent=VALUES(intent),plan=VALUES(plan),indexable=VALUES(indexable),priority=VALUES(priority),updated_at=VALUES(updated_at)",
            (int)$project_id,
            (string)$page['page_key'],
            (string)($page['translation_key']??$page['page_key']),
            (string)($page['lang']??'uk'),
            (string)$page['page_type'],
            (string)($page['entity_key']??''),
            (string)($page['city_slug']??''),
            (string)($page['intent']??''),
            (string)$page['plan'],
            !empty($page['indexable'])?1:0,
            (int)($page['priority']??50),
            $now,$now
        ));
    }

    public static function plans($project_id,$lang=''){
        global $wpdb;
        if($lang!=='') return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('pages').' WHERE project_id=%d AND lang=%s ORDER BY priority ASC,id ASC',$project_id,$lang),ARRAY_A);
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('pages').' WHERE project_id=%d ORDER BY lang ASC,priority ASC,id ASC',$project_id),ARRAY_A);
    }

    public static function page_by_key($project_id,$key){
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('pages').' WHERE project_id=%d AND page_key=%s',$project_id,$key),ARRAY_A);
    }

    public static function counterpart($project_id,$translation_key,$lang){
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('pages').' WHERE project_id=%d AND translation_key=%s AND lang=%s LIMIT 1',$project_id,$translation_key,$lang),ARRAY_A);
    }

    public static function enqueue($project_id,$type,$payload,$suffix=''){
        global $wpdb;
        $encoded=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($encoded===false) return false;
        $key=hash('sha256',$project_id.'|'.$type.'|'.$encoded.'|'.$suffix);
        $now=self::now();
        $sql=$wpdb->prepare(
            "INSERT INTO ".self::t('jobs')." (project_id,job_key,job_type,payload,status,attempts,available_at,created_at)
             VALUES(%d,%s,%s,%s,'queued',0,%s,%s)
             ON DUPLICATE KEY UPDATE payload=VALUES(payload), status=IF(status='done','done','queued'), available_at=VALUES(available_at), last_error=NULL",
            (int)$project_id,$key,sanitize_key($type),$encoded,$now,$now
        );
        return $wpdb->query($sql)!==false;
    }

    public static function qa_clear($project_id){
        global $wpdb;
        $wpdb->delete(self::t('qa'),['project_id'=>(int)$project_id],['%d']);
    }

    public static function qa_add($project_id,$page_key,$severity,$code,$message,$context=[]){
        global $wpdb;
        $wpdb->insert(self::t('qa'),[
            'project_id'=>(int)$project_id,
            'page_key'=>(string)$page_key,
            'severity'=>sanitize_key($severity),
            'code'=>sanitize_key($code),
            'message'=>wp_strip_all_tags($message),
            'context'=>wp_json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'created_at'=>self::now(),
        ],['%d','%s','%s','%s','%s','%s','%s']);
    }

    public static function seed_demo_project(){
        if(get_option('uwsb_demo_seeded')) return;
        global $wpdb;
        $count=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::t('projects'));
        if($count===0){
            $demo=UWSB_Admin::demo_config();
            $id=self::create_project([
                'name'=>'Aurora Demo',
                'site_type'=>'catalog',
                'primary_lang'=>'uk',
                'secondary_lang'=>'ru',
                'profile'=>'businessman',
                'config'=>$demo,
            ]);
            if($id) UWSB_Engine::plan_project($id);
        }
        update_option('uwsb_demo_seeded',1,false);
    }
}

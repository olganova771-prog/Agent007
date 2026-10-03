<?php
if (!defined('ABSPATH')) exit;
class UWSB_DB {
    public static function t($name){ global $wpdb; return $wpdb->prefix.'uwsb_'.$name; }
    public static function init(){ if (get_option('uwsb_db_version')!==UWSB_DB_VERSION) self::install(); }
    public static function activate(){ self::install(); }
    private static function install(){
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
        $sql=[];
        $sql[]="CREATE TABLE ".self::t('projects')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            site_type varchar(32) NOT NULL DEFAULT 'catalog',
            lang varchar(8) NOT NULL DEFAULT 'uk',
            profile varchar(32) NOT NULL DEFAULT 'businessman',
            config longtext NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY(id), KEY status(status)
        ) $c;";
        $sql[]="CREATE TABLE ".self::t('pages')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint unsigned NOT NULL,
            page_key varchar(191) NOT NULL,
            page_type varchar(40) NOT NULL,
            entity_key varchar(191) NULL,
            intent varchar(80) NULL,
            plan longtext NOT NULL,
            wp_post_id bigint unsigned NULL,
            status varchar(24) NOT NULL DEFAULT 'planned',
            priority int NOT NULL DEFAULT 50,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY project_page(project_id,page_key), KEY project_status(project_id,status)
        ) $c;";
        $sql[]="CREATE TABLE ".self::t('jobs')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint unsigned NOT NULL,
            job_key varchar(191) NOT NULL,
            job_type varchar(40) NOT NULL,
            payload longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            attempts int unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            started_at datetime NULL,
            finished_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY job_key(job_key), KEY project_status(project_id,status,available_at)
        ) $c;";
        $sql[]="CREATE TABLE ".self::t('qa')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint unsigned NOT NULL,
            page_key varchar(191) NULL,
            severity varchar(12) NOT NULL,
            code varchar(80) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY(id), KEY project_severity(project_id,severity)
        ) $c;";
        foreach($sql as $q) dbDelta($q);
        update_option('uwsb_db_version',UWSB_DB_VERSION,false);
    }
    public static function now(){ return current_time('mysql',true); }
    public static function create_project($data){
        global $wpdb; $now=self::now();
        $wpdb->insert(self::t('projects'),[
            'name'=>$data['name'],'site_type'=>$data['site_type'],'lang'=>$data['lang'],'profile'=>$data['profile'],
            'config'=>wp_json_encode($data['config'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'status'=>'planned','created_at'=>$now,'updated_at'=>$now
        ],['%s','%s','%s','%s','%s','%s','%s','%s']);
        return (int)$wpdb->insert_id;
    }
    public static function project($id){ global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::t('projects').' WHERE id=%d',$id),ARRAY_A); if($r) $r['config']=json_decode($r['config'],true)?:[]; return $r; }
    public static function save_page_plan($project_id,$page){
        global $wpdb; $now=self::now();
        $wpdb->query($wpdb->prepare("INSERT INTO ".self::t('pages')." (project_id,page_key,page_type,entity_key,intent,plan,status,priority,created_at,updated_at)
        VALUES (%d,%s,%s,%s,%s,%s,'planned',%d,%s,%s)
        ON DUPLICATE KEY UPDATE page_type=VALUES(page_type),entity_key=VALUES(entity_key),intent=VALUES(intent),plan=VALUES(plan),priority=VALUES(priority),updated_at=VALUES(updated_at)",
        $project_id,$page['page_key'],$page['page_type'],$page['entity_key']??'',$page['intent']??'',$page['plan'],(int)($page['priority']??50),$now,$now));
    }
    public static function plans($project_id){ global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::t('pages').' WHERE project_id=%d ORDER BY priority ASC,id ASC',$project_id),ARRAY_A); }
    public static function enqueue($project_id,$type,$payload,$suffix=''){
        global $wpdb; $key=hash('sha256',$project_id.'|'.$type.'|'.wp_json_encode($payload).'|'.$suffix); $now=self::now();
        $wpdb->query($wpdb->prepare("INSERT INTO ".self::t('jobs')." (project_id,job_key,job_type,payload,status,available_at,created_at) VALUES(%d,%s,%s,%s,'queued',%s,%s)
        ON DUPLICATE KEY UPDATE payload=VALUES(payload), status=IF(status='done','done','queued'), available_at=VALUES(available_at)",$project_id,$key,$type,wp_json_encode($payload),$now,$now));
    }
    public static function qa_clear($project_id){ global $wpdb; $wpdb->delete(self::t('qa'),['project_id'=>$project_id],['%d']); }
    public static function qa_add($project_id,$page_key,$severity,$code,$message,$context=[]){ global $wpdb; $wpdb->insert(self::t('qa'),[
        'project_id'=>$project_id,'page_key'=>$page_key,'severity'=>$severity,'code'=>$code,'message'=>$message,'context'=>wp_json_encode($context),'created_at'=>self::now()
    ],['%d','%s','%s','%s','%s','%s','%s']); }
}
<?php
if (!defined('ABSPATH')) { exit; }

class SFC_DB {
    private static $schema_ready;
    public static function init() {
        if (get_option('sfc_db_version') !== SFC_DB_VERSION) self::install_schema(false);
    }

    public static function jobs_table() {
        global $wpdb;
        return $wpdb->prefix . 'sfc_jobs';
    }

    public static function logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'sfc_logs';
    }
    public static function runs_table(){global $wpdb;return $wpdb->prefix.'sfc_matrix_runs';}
    public static function schema_ready(){
        if(self::$schema_ready!==null)return self::$schema_ready;
        global $wpdb;$jobs=self::jobs_table();$runs=self::runs_table();
        $job_columns=$wpdb->get_col("SHOW COLUMNS FROM {$jobs}",0);
        $run_table=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$runs));
        $job_index=$wpdb->get_var("SHOW INDEX FROM {$jobs} WHERE Key_name='job_key'");$run_index=$run_table===$runs?$wpdb->get_var("SHOW INDEX FROM {$runs} WHERE Key_name='run_token'"):null;
        return self::$schema_ready=in_array('lease_token',(array)$job_columns,true)&&in_array('lease_expires_at',(array)$job_columns,true)&&in_array('execution_token',(array)$job_columns,true)&&$run_table===$runs&&$job_index!==null&&$run_index!==null;
    }

    public static function activate() {
        self::install_schema(true);
    }

    private static function install_schema($flush_rewrites) {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $jobs = self::jobs_table();
        $logs = self::logs_table();
        $runs = self::runs_table();

        $sql1 = "CREATE TABLE {$jobs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
            KEY status_available (status, available_at),
            KEY created_at (created_at)
        ) {$charset};";
        $sql3 = "CREATE TABLE {$runs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            run_token varchar(64) NOT NULL,
            snapshot longtext NOT NULL,
            cursor bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY run_token (run_token),
            KEY status_expires (status, expires_at)
        ) {$charset};";
        $sql2 = "CREATE TABLE {$logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            event varchar(100) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY level_event (level, event),
            KEY created_at (created_at)
        ) {$charset};";

        $migration_messages=array_merge((array)dbDelta($sql1),(array)dbDelta($sql2),(array)dbDelta($sql3));
        $migration_error=$wpdb->last_error;
        self::$schema_ready=null;
        if (!self::schema_ready()) {
            $error=$migration_error?:($wpdb->last_error?:'Required jobs columns or indexes or matrix-runs table are missing after dbDelta.');
            update_option('sfc_db_error',$error);
            self::log('error','db_schema',$error,array('version'=>SFC_DB_VERSION,'dbdelta'=>$migration_messages));
            return false;
        }
        delete_option('sfc_db_error');
        update_option('sfc_db_version', SFC_DB_VERSION);
        SFC_Post_Types::register_types();
        if ($flush_rewrites) flush_rewrite_rules();
        SFC_Settings::seed_defaults();
        return true;
    }

    public static function log($level, $event, $message, $context = array()) {
        global $wpdb;
        $wpdb->insert(self::logs_table(), array(
            'level' => sanitize_key($level),
            'event' => sanitize_key($event),
            'message' => wp_strip_all_tags($message),
            'context' => wp_json_encode($context),
            'created_at' => current_time('mysql', true),
        ), array('%s','%s','%s','%s','%s'));
    }

    public static function get_stats() {
        global $wpdb;
        $jobs = self::jobs_table();
        $stats = array('queued'=>0,'processing'=>0,'done'=>0,'failed'=>0);
        $rows = $wpdb->get_results("SELECT status, COUNT(*) c FROM {$jobs} GROUP BY status", ARRAY_A);
        foreach ((array) $rows as $row) {
            if (isset($stats[$row['status']])) $stats[$row['status']] = (int) $row['c'];
        }
        return $stats;
    }
}

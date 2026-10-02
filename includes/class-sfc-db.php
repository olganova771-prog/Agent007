<?php
if (!defined('ABSPATH')) { exit; }

class SFC_DB {
    public static function init() {}

    public static function jobs_table() {
        global $wpdb;
        return $wpdb->prefix . 'sfc_jobs';
    }

    public static function logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'sfc_logs';
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $jobs = self::jobs_table();
        $logs = self::logs_table();

        $sql1 = "CREATE TABLE {$jobs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_key varchar(191) NOT NULL,
            job_type varchar(40) NOT NULL,
            payload longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            attempts int(11) unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            started_at datetime NULL,
            finished_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_key (job_key),
            KEY status_available (status, available_at),
            KEY created_at (created_at)
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

        dbDelta($sql1);
        dbDelta($sql2);
        update_option('sfc_db_version', SFC_DB_VERSION);
        SFC_Post_Types::register_types();
        flush_rewrite_rules();
        SFC_Settings::seed_defaults();
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

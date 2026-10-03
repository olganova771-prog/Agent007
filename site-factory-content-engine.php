<?php
/**
 * Plugin Name: Site Factory — Universal WordPress Site Builder
 * Description: Autonomous deterministic site compiler for WordPress: projects, products/services, site planning, design profiles, linking, SEO, QA and publishing.
 * Version: 2.0.0
 * Author: OpenAI
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: site-factory
 */
if (!defined('ABSPATH')) exit;

define('UWSB_VERSION','2.0.0');
define('UWSB_DB_VERSION','2.0.0');
define('UWSB_FILE',__FILE__);
define('UWSB_DIR',plugin_dir_path(__FILE__));
define('UWSB_URL',plugin_dir_url(__FILE__));

require_once UWSB_DIR.'includes/class-uwsb-db.php';
require_once UWSB_DIR.'includes/class-uwsb-profiles.php';
require_once UWSB_DIR.'includes/class-uwsb-planner.php';
require_once UWSB_DIR.'includes/class-uwsb-renderer.php';
require_once UWSB_DIR.'includes/class-uwsb-seo.php';
require_once UWSB_DIR.'includes/class-uwsb-qa.php';
require_once UWSB_DIR.'includes/class-uwsb-engine.php';
require_once UWSB_DIR.'includes/class-uwsb-admin.php';

register_activation_hook(__FILE__, ['UWSB_DB','activate']);
register_deactivation_hook(__FILE__, function(){ wp_clear_scheduled_hook('uwsb_job_tick'); });

add_filter('cron_schedules', function($s){
    if (!isset($s['uwsb_every_minute'])) $s['uwsb_every_minute']=['interval'=>60,'display'=>'Site Factory every minute'];
    return $s;
});

add_action('plugins_loaded', function(){
    UWSB_DB::init();
    UWSB_Renderer::init();
    UWSB_SEO::init();
    UWSB_Engine::init();
    UWSB_Admin::init();
});

add_action('init', function(){
    if (!wp_next_scheduled('uwsb_job_tick')) wp_schedule_event(time()+60,'uwsb_every_minute','uwsb_job_tick');
});
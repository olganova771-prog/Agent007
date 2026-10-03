<?php
/**
 * Plugin Name: Site Factory — Universal WordPress Site Builder
 * Description: Autonomous deterministic WordPress site factory with products, full Ukraine geography, UA/RU layers, editorial profiles, queue, QA, SEO and draft-first publishing.
 * Version: 3.0.1
 * Author: OpenAI
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: site-factory
 */
if (!defined('ABSPATH')) exit;

define('UWSB_VERSION','3.0.1');
define('UWSB_DB_VERSION','3.0.1');
define('UWSB_FILE',__FILE__);
define('UWSB_DIR',plugin_dir_path(__FILE__));
define('UWSB_URL',plugin_dir_url(__FILE__));

foreach(['db','geo','profiles','planner','renderer','seo','qa','engine','admin'] as $f) {
    require_once UWSB_DIR.'includes/class-uwsb-'.$f.'.php';
}

register_activation_hook(__FILE__,['UWSB_DB','activate']);
register_deactivation_hook(__FILE__,function(){ wp_clear_scheduled_hook('uwsb_job_tick'); });

add_filter('cron_schedules',function($s){
    if(!isset($s['uwsb_every_minute'])) $s['uwsb_every_minute']=['interval'=>60,'display'=>'Site Factory every minute'];
    return $s;
});

add_action('plugins_loaded',function(){
    UWSB_DB::init();
    UWSB_Renderer::init();
    UWSB_SEO::init();
    UWSB_Engine::init();
    UWSB_Admin::init();
});

add_action('init',function(){
    if(!wp_next_scheduled('uwsb_job_tick')) wp_schedule_event(time()+60,'uwsb_every_minute','uwsb_job_tick');
});

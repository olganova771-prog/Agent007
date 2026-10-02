<?php
/**
 * Plugin Name: Site Factory — Content & Site Engine
 * Description: Deterministic WordPress Content Factory: products, regions, cities, bilingual UA/RU generation, content matrix, queue, QA, SEO, and five frontend themes. No AI/API runtime dependency.
 * Version: 1.0.0
 * Author: OpenAI
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Text Domain: site-factory-content-engine
 */

if (!defined('ABSPATH')) { exit; }

define('SFC_VERSION', '1.0.0');
define('SFC_FILE', __FILE__);
define('SFC_DIR', plugin_dir_path(__FILE__));
define('SFC_URL', plugin_dir_url(__FILE__));
define('SFC_DB_VERSION', '1.0.0');

autoload_sfc();

function autoload_sfc() {
    $files = array(
        'includes/class-sfc-db.php',
        'includes/class-sfc-settings.php',
        'includes/class-sfc-post-types.php',
        'includes/class-sfc-meta-boxes.php',
        'includes/class-sfc-intent.php',
        'includes/class-sfc-variation.php',
        'includes/class-sfc-matrix.php',
        'includes/class-sfc-generator.php',
        'includes/class-sfc-queue.php',
        'includes/class-sfc-seo.php',
        'includes/class-sfc-renderer.php',
        'includes/class-sfc-admin.php',
        'includes/class-sfc-qa.php',
    );
    foreach ($files as $file) {
        require_once SFC_DIR . $file;
    }
}

register_activation_hook(__FILE__, array('SFC_DB', 'activate'));
register_activation_hook(__FILE__, array('SFC_Post_Types', 'activate'));
register_deactivation_hook(__FILE__, array('SFC_Queue', 'deactivate'));

add_action('plugins_loaded', function() {
    SFC_DB::init();
    SFC_Settings::init();
    SFC_Post_Types::init();
    SFC_Meta_Boxes::init();
    SFC_Intent::init();
    SFC_Variation::init();
    SFC_Matrix::init();
    SFC_Generator::init();
    SFC_Queue::init();
    SFC_SEO::init();
    SFC_Renderer::init();
    SFC_Admin::init();
    SFC_QA::init();
});

add_action('init', function() {
    if (!wp_next_scheduled('sfc_queue_tick')) {
        wp_schedule_event(time() + 60, 'sfc_every_minute', 'sfc_queue_tick');
    }
});

add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['sfc_every_minute'])) {
        $schedules['sfc_every_minute'] = array(
            'interval' => 60,
            'display' => __('Site Factory — every minute', 'site-factory-content-engine'),
        );
    }
    return $schedules;
});

add_shortcode('sf_home', array('SFC_Renderer', 'shortcode_home'));

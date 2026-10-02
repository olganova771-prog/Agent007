<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Settings {
    const OPTION = 'sfc_settings';
    private static $defaults = array(
        'theme' => 'premium-dark',
        'default_lang' => 'uk',
        'secondary_lang' => 'ru',
        'region_min_cities' => 3,
        'city_require_local_fact' => 1,
        'product_city_require_query_or_allowlist' => 1,
        'queue_batch' => 2,
        'similarity_threshold' => 0.82,
        'home_page_id' => 0,
        'site_name' => 'Site Factory',
        'cta_label_uk' => 'Залишити заявку',
        'cta_label_ru' => 'Оставить заявку',
        'cta_url' => '#contact',
        'profile' => array(),
    );

    private static $profile_keys = array(
        'formality','conversationality','directness','expertise','factual_density','detail','emotionality','friendliness','confidence','neutrality',
        'practicality','benefit_orientation','feature_orientation','use_case_orientation','examples','structuredness','narrativity','questioning_style','direct_address','humor',
        'local_orientation','commercial_orientation','informational_orientation','faq_orientation','syntactic_variation','lexical_diversity','heading_density','list_density','internal_link_density','individuality'
    );

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register'));
    }

    public static function defaults() {
        $d = self::$defaults;
        if (empty($d['profile'])) {
            foreach (self::$profile_keys as $key) $d['profile'][$key] = 50;
        }
        return $d;
    }

    public static function seed_defaults() {
        if (!get_option(self::OPTION)) update_option(self::OPTION, self::defaults());
        else {
            $current = self::get();
            $merged = wp_parse_args($current, self::defaults());
            $merged['profile'] = wp_parse_args(isset($current['profile']) ? $current['profile'] : array(), self::defaults()['profile']);
            update_option(self::OPTION, $merged);
        }
    }

    public static function get($key = null, $default = null) {
        $value = wp_parse_args((array) get_option(self::OPTION, array()), self::defaults());
        $value['profile'] = wp_parse_args(isset($value['profile']) ? $value['profile'] : array(), self::defaults()['profile']);
        if ($key === null) return $value;
        return array_key_exists($key, $value) ? $value[$key] : $default;
    }

    public static function profile_keys() { return self::$profile_keys; }

    public static function register() {
        register_setting('sfc_settings_group', self::OPTION, array(__CLASS__, 'sanitize'));
    }

    public static function sanitize($input) {
        $d = self::defaults();
        $out = $d;
        $out['theme'] = sanitize_key(isset($input['theme']) ? $input['theme'] : $d['theme']);
        $allowed_themes = array('premium-dark','modern-light','neon-cyber','editorial-luxury','dynamic-marketplace');
        if (!in_array($out['theme'], $allowed_themes, true)) $out['theme'] = 'premium-dark';
        $out['default_lang'] = 'uk';
        $out['secondary_lang'] = 'ru';
        $out['region_min_cities'] = max(1, min(10, absint($input['region_min_cities'] ?? 3)));
        $out['city_require_local_fact'] = empty($input['city_require_local_fact']) ? 0 : 1;
        $out['product_city_require_query_or_allowlist'] = empty($input['product_city_require_query_or_allowlist']) ? 0 : 1;
        $out['queue_batch'] = max(1, min(10, absint($input['queue_batch'] ?? 2)));
        $out['similarity_threshold'] = max(0.60, min(0.95, (float)($input['similarity_threshold'] ?? 0.82)));
        $out['home_page_id'] = absint($input['home_page_id'] ?? 0);
        $out['site_name'] = sanitize_text_field($input['site_name'] ?? 'Site Factory');
        $out['cta_label_uk'] = sanitize_text_field($input['cta_label_uk'] ?? 'Залишити заявку');
        $out['cta_label_ru'] = sanitize_text_field($input['cta_label_ru'] ?? 'Оставить заявку');
        $out['cta_url'] = esc_url_raw($input['cta_url'] ?? '#contact');
        foreach (self::$profile_keys as $key) {
            $out['profile'][$key] = max(0, min(100, absint($input['profile'][$key] ?? 50)));
        }
        return $out;
    }
}

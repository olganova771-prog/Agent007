<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Post_Types {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'));
    }

    public static function activate() { self::register_types(); }

    public static function register_types() {
        $common = array(
            'show_ui' => true,
            'show_in_menu' => 'sfc-dashboard',
            'supports' => array('title','thumbnail'),
            'map_meta_cap' => true,
            'capability_type' => 'post',
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_in_rest' => true,
        );
        register_post_type('sf_product', wp_parse_args(array(
            'labels' => array('name'=>'Товары','singular_name'=>'Товар','add_new_item'=>'Добавить товар','edit_item'=>'Редактировать товар'),
            'menu_icon' => 'dashicons-cart',
        ), $common));
        register_post_type('sf_region', wp_parse_args(array(
            'labels' => array('name'=>'Регионы','singular_name'=>'Регион','add_new_item'=>'Добавить регион','edit_item'=>'Редактировать регион'),
            'menu_icon' => 'dashicons-location-alt',
        ), $common));
        register_post_type('sf_city', wp_parse_args(array(
            'labels' => array('name'=>'Города','singular_name'=>'Город','add_new_item'=>'Добавить город','edit_item'=>'Редактировать город'),
            'menu_icon' => 'dashicons-admin-site-alt3',
        ), $common));
        register_taxonomy('sf_topic', array('sf_product'), array(
            'labels' => array('name'=>'Теги','singular_name'=>'Тег'),
            'show_ui' => true,
            'show_admin_column' => true,
            'public' => false,
            'rewrite' => false,
            'show_in_rest' => true,
        ));
    }
}

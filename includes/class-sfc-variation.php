<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Variation {
    public static function init() {}

    public static function profile_for_page($page_type, $seed = null) {
        $seed = $seed ?: wp_rand(100000, 999999999);
        $base = (array) SFC_Settings::get('profile');
        $profile = $base;
        $adjust = array(
            'product' => array('commercial_orientation'=>10,'feature_orientation'=>8,'benefit_orientation'=>6),
            'region' => array('local_orientation'=>12,'structuredness'=>10,'informational_orientation'=>10),
            'city' => array('local_orientation'=>16,'practicality'=>10,'commercial_orientation'=>5),
            'product_city' => array('local_orientation'=>18,'commercial_orientation'=>10,'use_case_orientation'=>8),
            'collection' => array('structuredness'=>15,'commercial_orientation'=>10,'feature_orientation'=>8),
            'comparison' => array('structuredness'=>20,'neutrality'=>15,'feature_orientation'=>18,'informational_orientation'=>10),
        );
        if (isset($adjust[$page_type])) {
            foreach ($adjust[$page_type] as $k=>$v) if (isset($profile[$k])) $profile[$k] = max(0,min(100,$profile[$k]+$v));
        }
        mt_srand((int)$seed);
        $keys = array_keys($profile);
        shuffle($keys);
        $emphasis = array_slice($keys,0,4);
        foreach ($emphasis as $key) {
            $delta = mt_rand(-10,10);
            $profile[$key] = max(0,min(100,$profile[$key]+$delta));
        }
        // Compatibility safety: humour drops on comparison pages, neutrality rises.
        if ($page_type === 'comparison') $profile['humor'] = min(20, $profile['humor']);
        if ($page_type === 'region' || $page_type === 'city') $profile['local_orientation'] = max(30, $profile['local_orientation']);
        mt_srand();
        return array('seed'=>$seed,'page_type'=>$page_type,'emphasis'=>$emphasis,'values'=>$profile);
    }

    public static function template_variant($seed, $max) {
        $max = max(1,(int)$max);
        $seed = (int)$seed;
        return abs(crc32('sfc:' . $seed)) % $max;
    }
}

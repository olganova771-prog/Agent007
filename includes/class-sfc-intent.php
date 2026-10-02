<?php
if (!defined('ABSPATH')) { exit; }

class SFC_Intent {
    public static function init() {}

    public static function classify($query) {
        $q = mb_strtolower(trim($query));
        if ($q === '') return 'unknown';
        $local = array('в киеве','в києві','в городе','у місті','доставка','рядом','ближайший','ближайшая','киев','київ','львов','львів','одесса','одеса','днепр','дніпро','харьков','харків','запорожье','запоріжжя','полтава','чернигов','чернігів','винница','вінниця');
        $comparison = array('сравнить','сравнение','сравни','сравнительный','порівняти','порівняння','відмінність','отличие','разница','vs');
        $transactional = array('купить','заказать','цена','стоимость','купівля','замовити','ціна','вартість','купити');
        $informational = array('что такое','как выбрать','как пользоваться','характеристики','особенности','как работает','что лучше','що таке','як обрати','характеристики','особливості','як працює');
        $commercial = array('каталог','товары','варианты','подобрать','выбор','категория','категории','товари','варіанти','підбір','вибір','категорія');
        foreach ($comparison as $needle) if (mb_strpos($q, $needle) !== false) return 'comparison';
        foreach ($transactional as $needle) if (mb_strpos($q, $needle) !== false) return 'transactional';
        foreach ($local as $needle) if (mb_strpos($q, $needle) !== false) return 'local';
        foreach ($informational as $needle) if (mb_strpos($q, $needle) !== false) return 'informational';
        foreach ($commercial as $needle) if (mb_strpos($q, $needle) !== false) return 'commercial';
        return 'navigational';
    }

    public static function has_local_for_city($queries, $city_name) {
        $city = mb_strtolower($city_name);
        foreach ((array)$queries as $query) {
            $q = mb_strtolower($query);
            if (mb_strpos($q, $city) !== false || mb_strpos($q, 'в ' . $city) !== false || mb_strpos($q, 'у ' . $city) !== false) return true;
        }
        return false;
    }

    public static function summarize($queries) {
        $out = array('transactional'=>0,'commercial'=>0,'local'=>0,'informational'=>0,'comparison'=>0,'navigational'=>0,'unknown'=>0);
        foreach ((array)$queries as $q) { $intent = self::classify($q); if (isset($out[$intent])) $out[$intent]++; }
        return $out;
    }
}

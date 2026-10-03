<?php
if (!defined('ABSPATH')) exit;
$post_id=get_queried_object_id();
$project=UWSB_Renderer::project_for_post($post_id);
$data=UWSB_Renderer::data_for_post($post_id);
if(!$project){status_header(404);exit;}
$lang=UWSB_Renderer::lang_for_post($post_id);
$profile=UWSB_Profiles::get($project['profile']);
$type=get_post_meta($post_id,'_uwsb_page_type',true);
$plan=$data['plan']??[];
$cards=UWSB_Renderer::cards((int)$project['id'],$lang,12);
$nav=UWSB_Renderer::nav((int)$project['id'],$lang);
$translation=UWSB_Renderer::translation($post_id);
$tr=function($uk,$ru)use($lang){return $lang==='ru'?$ru:$uk;};
?><!doctype html>
<html lang="<?php echo esc_attr($lang==='ru'?'ru-UA':'uk-UA'); ?>">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body class="uwsb-site <?php echo esc_attr($profile['class'].' type-'.$type); ?>">
<header class="uwsb-header">
    <a class="uwsb-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($project['name']); ?></a>
    <nav><?php foreach($nav as $n): ?><a href="<?php echo esc_url($n['url']); ?>"><?php echo esc_html($n['label']); ?></a><?php endforeach; ?><?php if($translation):?><a href="<?php echo esc_url(get_permalink($translation)); ?>"><?php echo esc_html($lang==='ru'?'UA':'RU'); ?></a><?php endif;?></nav>
</header>
<main>
<section class="uwsb-hero uwsb-hero-<?php echo esc_attr($profile['hero']); ?>">
<div><span class="uwsb-kicker"><?php echo esc_html($profile['label']); ?></span><h1><?php the_title(); ?></h1><p><?php echo esc_html(get_post_meta($post_id,'_uwsb_meta_description',true)); ?></p></div>
<div class="uwsb-hero-art"><span><?php echo UWSB_Renderer::monogram(get_the_title()); ?></span></div>
</section>

<?php if($type==='home'): ?>
<section class="uwsb-section"><h2><?php echo esc_html($tr('Товари та напрямки','Товары и направления')); ?></h2><div class="uwsb-grid"><?php foreach($cards as $c): ?><a class="uwsb-card" href="<?php echo esc_url($c['url']); ?>"><div class="uwsb-thumb"><?php echo UWSB_Renderer::monogram($c['name']); ?></div><h3><?php echo esc_html($c['name']); ?></h3><?php if($c['short']):?><p><?php echo esc_html($c['short']); ?></p><?php endif;?><?php if($c['price']):?><strong><?php echo esc_html(trim($c['price'].' '.$c['currency'])); ?></strong><?php endif;?></a><?php endforeach;?></div></section>

<?php elseif($type==='catalog'||$type==='collection'): ?>
<section class="uwsb-section"><h2><?php echo esc_html($type==='catalog'?$tr('Каталог','Каталог'):$tr('Добірка','Подборка')); ?></h2><div class="uwsb-grid"><?php foreach(UWSB_Renderer::cards((int)$project['id'],$lang) as $c): ?><a class="uwsb-card" href="<?php echo esc_url($c['url']); ?>"><div class="uwsb-thumb"><?php echo UWSB_Renderer::monogram($c['name']); ?></div><h3><?php echo esc_html($c['name']); ?></h3><?php if($c['facts']):?><p><?php echo esc_html($c['facts'][0]); ?></p><?php endif;?></a><?php endforeach;?></div></section>

<?php elseif($type==='product'||$type==='product_city'): $it=$plan['item']??[]; ?>
<section class="uwsb-section uwsb-product">
<div class="uwsb-facts">
<?php if($type==='product_city'&&!empty($plan['city'])):?><p class="uwsb-local"><strong><?php echo esc_html($plan['city']['name']); ?></strong> · <?php echo esc_html($plan['city']['region']??''); ?></p><?php endif;?>
<?php if(!empty($it['description'])):?><p><?php echo nl2br(esc_html($it['description'])); ?></p><?php endif;?>
<?php foreach(['features'=>$tr('Характеристики','Характеристики'),'benefits'=>$tr('Переваги','Преимущества'),'use_cases'=>$tr('Сценарії використання','Сценарии использования'),'limitations'=>$tr('Обмеження','Ограничения')] as $field=>$heading): if(!empty($it[$field])): ?>
<h2><?php echo esc_html($heading); ?></h2><ul><?php foreach($it[$field] as $v):?><li><?php echo esc_html($v); ?></li><?php endforeach;?></ul>
<?php endif; endforeach;?>
<?php if(!empty($it['faq'])):?><h2>FAQ</h2><div class="uwsb-faq"><?php foreach($it['faq'] as $row):$parts=array_map('trim',explode('::',$row,2));?><details><summary><?php echo esc_html($parts[0]); ?></summary><p><?php echo esc_html($parts[1]??''); ?></p></details><?php endforeach;?></div><?php endif;?>
</div>
<?php if(!empty($it['price'])):?><aside class="uwsb-price"><?php echo esc_html(trim($it['price'].' '.($it['currency']??''))); ?></aside><?php endif;?>
</section>

<?php elseif($type==='comparison'): $items=$plan['items']??[]; ?>
<section class="uwsb-section"><h2><?php echo esc_html($tr('Порівняння','Сравнение')); ?></h2><div class="uwsb-compare"><?php foreach($items as $it):?><article><h3><?php echo esc_html($it['name']??''); ?></h3><?php if(!empty($it['features'])):?><ul><?php foreach(array_slice($it['features'],0,6) as $v):?><li><?php echo esc_html($v); ?></li><?php endforeach;?></ul><?php endif;?></article><?php endforeach;?></div></section>

<?php elseif($type==='coverage'): ?>
<section class="uwsb-section"><h2><?php echo esc_html($tr('Географія роботи','География работы')); ?></h2><p><?php echo esc_html(sprintf($tr('У Site Plan включено %d міст.','В Site Plan включено %d городов.'),(int)($plan['city_count']??0))); ?></p></section>

<?php elseif($type==='contacts'): ?>
<section class="uwsb-section"><h2><?php echo esc_html($tr('Контакти','Контакты')); ?></h2><p><?php echo nl2br(esc_html($plan['contacts']??'')); ?></p></section>
<?php endif; ?>

<?php $links=(array)get_post_meta($post_id,'_uwsb_internal_links',true);if($links):?><section class="uwsb-section uwsb-related"><h2><?php echo esc_html($tr('Також дивіться','Также смотрите')); ?></h2><div class="uwsb-links"><?php foreach($links as $lid):if(get_post_status($lid)):?><a href="<?php echo esc_url(get_permalink($lid)); ?>"><?php echo esc_html(get_the_title($lid)); ?></a><?php endif;endforeach;?></div></section><?php endif;?>
</main>
<footer class="uwsb-footer"><strong><?php echo esc_html($project['name']); ?></strong><span>Site Factory 3.0</span></footer>
<?php wp_footer(); ?>
</body></html>

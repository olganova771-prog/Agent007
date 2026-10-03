<?php
$root=dirname(__DIR__);
$failures=[];

$bootstrap=file_get_contents($root.'/site-factory-content-engine.php');
$planner=file_get_contents($root.'/includes/class-uwsb-planner.php');
$admin=file_get_contents($root.'/includes/class-uwsb-admin.php');
$engine=file_get_contents($root.'/includes/class-uwsb-engine.php');
$seo=file_get_contents($root.'/includes/class-uwsb-seo.php');
$geo=json_decode(file_get_contents($root.'/data/ukraine-cities.json'),true);

if(strpos($bootstrap,"Version: 3.0.1")===false)$failures[]='Plugin version is not 3.0.1.';
if(strpos($bootstrap,"class-uwsb-")===false)$failures[]='UWSB architecture is not loaded.';
if(!is_array($geo)||count($geo['cities']??[])<460)$failures[]='Ukraine city dataset is incomplete.';
if(strpos($planner,"page_type'=>'product_city'")===false)$failures[]='Product+City planning is missing.';
if(strpos($planner,"geo_index_all")===false)$failures[]='Generation/indexability separation is missing.';
if(strpos($planner,"secondary_lang")===false)$failures[]='Secondary language planning is missing.';
if(strpos($admin,'Aurora Mini')===false)$failures[]='Fully populated demo product is missing.';
if(substr_count(file_get_contents($root.'/includes/class-uwsb-profiles.php'),"'")<60)$failures[]='Editorial profile definition looks incomplete.';
if(strpos($engine,'lease_token')===false||strpos($engine,'GET_LOCK')===false)$failures[]='Queue lease/locking safeguards are missing.';
if(strpos($seo,'hreflang')===false||strpos($seo,'_uwsb_indexable')===false)$failures[]='SEO language/indexability controls are missing.';

if($failures){
    fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);
    exit(1);
}
echo "Site Factory 3 regression checks passed.\n";

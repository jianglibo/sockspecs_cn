<?php
/** Install an optional WooCommerce attribute; never assign it to existing products. */
if(!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_woocommerce') || DB_NAME!=='sockspecs_cn' || get_woocommerce_currency()!=='CNY' || !in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true))throw new RuntimeException('Domestic administrator only');
$mode=$args[0]??'verify';if(!in_array($mode,['apply','verify'],true))throw new RuntimeException('Use apply or verify');
$id=wc_attribute_taxonomy_id_by_name('cuff-height');
if(!$id && $mode==='apply'){
 $id=wc_create_attribute(['name'=>'袜筒款式','slug'=>'cuff-height','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);
 if(is_wp_error($id))throw new RuntimeException($id->get_error_message());
}
if(!$id)throw new RuntimeException('Optional attribute missing');
$a=wc_get_attribute($id);if($a->name!=='袜筒款式' || $a->slug!=='pa_cuff-height')throw new RuntimeException('Existing attribute conflicts; review first');
if(!taxonomy_exists('pa_cuff-height'))register_taxonomy('pa_cuff-height',['product'],['public'=>false,'hierarchical'=>false]);
foreach(['low'=>'低筒款','mid'=>'中筒款','high'=>'高筒款'] as $slug=>$name){
 $t=get_term_by('slug',$slug,'pa_cuff-height');
 if(!$t && $mode==='apply'){$r=wp_insert_term($name,'pa_cuff-height',['slug'=>$slug]);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());$t=get_term_by('slug',$slug,'pa_cuff-height');}
 if(!$t || $t->name!==$name)throw new RuntimeException('Attribute term mismatch');
}
WP_CLI::success('Optional 袜筒款式 attribute ready: 低筒款 / 中筒款 / 高筒款. No products, variations, specifications, prices or stock changed.');

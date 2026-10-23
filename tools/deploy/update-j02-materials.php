<?php
/** Domestic J02 material-focused copy; preserve all commerce and evidence data. */
function j02_require($ok,$why){if(!$ok)throw new RuntimeException($why);}
j02_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && get_woocommerce_currency()==='CNY' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Domestic admin CLI only');
$mode=$args[0]??'verify';j02_require(in_array($mode,['apply','verify'],true),'Use apply or verify');
$copy=json_decode(file_get_contents(__DIR__.'/content/j02-materials-20261021.json'),true,512,JSON_THROW_ON_ERROR);
$p=wc_get_product(wc_get_product_id_by_sku($copy['sku']));j02_require($p && $p->is_type('variable') && count($p->get_children())===9,'Unexpected J02/J03 parent');
function j02_preserved($p){
 global $wpdb;$rows=[];
 foreach(array_merge([$p->get_id()],$p->get_children()) as $id){$d=wc_get_product($id)->get_data();if($id===$p->get_id())foreach(['description','short_description','date_modified'] as $k)unset($d[$k]);$d['meta_data']=array_map(fn($m)=>$m->get_data(),$d['meta_data']);$rows[]=$d;}
 $claims=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id",$p->get_id()),ARRAY_A);
 return hash('sha256',wp_json_encode([$rows,$claims,get_option('woocommerce_coming_soon')]));
}
$before=j02_preserved($p);$key='sscn_j02_materials_20261021';$state=get_option($key);
if($mode==='apply'){
 if(!$state){$state=['id'=>$p->get_id(),'short_description'=>$p->get_short_description(),'description'=>$p->get_description()];add_option($key,$state,'',false);}
 foreach(['description','short_description'] as $field){$get='get_'.$field;$set='set_'.$field;j02_require(in_array($p->$get(),[$state[$field],$copy[$field]],true),'Copy changed since snapshot; review before replacing');$p->$set(wp_kses_post($copy[$field]));}
 $p->save();wc_delete_product_transients($p->get_id());
}
$p=wc_get_product($p->get_id());j02_require($p->get_description()===wp_kses_post($copy['description']) && $p->get_short_description()===wp_kses_post($copy['short_description']),'J02 copy mismatch');
j02_require(j02_preserved($p)===$before,'Commerce, children, images, specifications or visibility changed');
WP_CLI::success('J02 copy verified; prices, stock, J03 variations, images, structured claims and visibility preserved. '.get_permalink($p->get_id()));

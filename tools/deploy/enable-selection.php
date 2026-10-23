<?php
/** Domestic selection service/page; no stock, pricing, payment or visibility changes. */
use SockSpecsCN\Commerce\SelectionDraft;
if (!defined('WP_CLI') || !WP_CLI || DB_NAME!=='sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true) || get_woocommerce_currency()!=='CNY') { throw new RuntimeException('Domestic administrator CLI only.'); }
$mode=$args[0] ?? 'plan'; if (!in_array($mode,['plan','apply','verify'],true)) { throw new RuntimeException('Use plan/apply/verify'); }
$protected=[];foreach(['woocommerce_coming_soon','woocommerce_currency','sscn_wechat_login_enabled','woocommerce_sscn_alipay_settings','woocommerce_sscn_wechat_settings'] as $key){$protected[$key]=get_option($key);}
function selection_catalog_fingerprint(): string {
 $data=[];
 foreach(wc_get_products(['type'=>'variable','limit'=>-1,'status'=>['publish','draft','private']]) as $p){
  $data[$p->get_id()]=['packs'=>$p->get_meta('_pc_pack_prices')];
  foreach($p->get_children() as $id){$v=wc_get_product($id);$data[$id]=[$v->get_price(),$v->get_regular_price(),$v->get_sale_price(),$v->get_stock_quantity(),$v->get_stock_status()];}
 }
 ksort($data);return hash('sha256',wp_json_encode($data));
}
$catalog_before=selection_catalog_fingerprint();
$page=get_page_by_path('choose-socks');
if ($page && $page->post_content!=='[sscn_choose_socks]') { throw new RuntimeException('Existing page needs review.'); }
if($mode==='apply'){
 SelectionDraft::install();
 if(!$page){$id=wp_insert_post(['post_title'=>'自由选袜','post_name'=>'choose-socks','post_type'=>'page','post_status'=>'publish','post_content'=>'[sscn_choose_socks]'],true);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());$page=get_post($id);}
 $locations=get_theme_mod('nav_menu_locations');$menu=$locations['primary']??0;
 if($menu){$found=false;foreach(wp_get_nav_menu_items($menu)?:[] as $item){if((int)$item->object_id===$page->ID&&$item->object==='page')$found=true;}
 if(!$found){$id=wp_update_nav_menu_item($menu,0,['menu-item-object-id'=>$page->ID,'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-title'=>'自由选袜','menu-item-status'=>'publish']);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());}}
 if(!wp_next_scheduled('sscn_selection_cleanup'))wp_schedule_event(time()+DAY_IN_SECONDS,'daily','sscn_selection_cleanup');
}
if($mode==='verify'&&(!$page||$page->post_status!=='publish'||get_option('sscn_selection_schema')!=='1'))throw new RuntimeException('Selection setup incomplete');
foreach($protected as $key=>$value){if(get_option($key)!==$value)throw new RuntimeException('Protected setting changed: '.$key);}
if(selection_catalog_fingerprint()!==$catalog_before)throw new RuntimeException('Catalog price or inventory changed');
WP_CLI::success('Selection '.$mode.'; page '.($page->ID??'pending').'; store settings unchanged.');

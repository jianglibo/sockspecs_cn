<?php
/** Domestic unified selection page and small product-page entry point. */
namespace SockSpecsCN\Commerce;
defined('ABSPATH') || exit;
function selection_url(): string {
    $page=get_page_by_path('choose-socks');
    return $page ? get_permalink($page) : '';
}
function selection_page(): bool { return is_page('choose-socks'); }
function selection_request(array $input): array {
    $csrf=$input['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals(SelectionDraft::csrf(),$csrf)) { throw new \RuntimeException('登录状态已变化，请刷新页面后继续。',403); }
    return SelectionDraft::operate($input);
}
add_action('template_redirect',static function(): void {
    if (!selection_page() && !is_product() && !is_cart()) { return; }
    if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE',true); }
    nocache_headers(); SelectionDraft::ensure_cookie();
    if (selection_page() && ($_SERVER['REQUEST_METHOD'] ?? '')==='POST' && isset($_POST['sscn_selection_op'])) {
        try {
            $input=wp_unslash($_POST); $input['op']=$input['sscn_selection_op'];
            $result=selection_request($input);
            if (isset($result['redirect'])) { wp_safe_redirect($result['redirect']); exit; }
            wc_add_notice('选袜清单已保存。');
        } catch (\RuntimeException $e) { wc_add_notice($e->getMessage(),'error'); }
        wp_safe_redirect(selection_url()); exit;
    }
}, 1);
foreach (['wp_ajax_sscn_selection','wp_ajax_nopriv_sscn_selection'] as $hook) {
    add_action($hook,static function(): void {
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { wp_send_json_error(['message'=>'请使用POST请求。'],405); }
        try {
            $raw=file_get_contents('php://input',false,null,0,16385);
            if (strlen($raw)>16384) { throw new \RuntimeException('清单过大。'); }
            $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($input)) { throw new \RuntimeException('无效请求。'); }
            wp_send_json_success(selection_request($input));
        } catch (\Throwable $e) {
            $status=in_array($e->getCode(),[403,409],true) ? $e->getCode() : 400;
            $data=['message'=>$e instanceof \RuntimeException ? $e->getMessage() : '暂时无法保存，请刷新后重试。'];
            if ($status===409) { try {$data['state']=SelectionDraft::operate(['op'=>'load']);} catch (\Throwable $ignored) {} }
            wp_send_json_error($data,$status);
        }
    });
}
add_action('wp_enqueue_scripts',static function(): void {
    if (!selection_url() || (!selection_page() && !is_product() && !is_cart())) { return; }
    wp_enqueue_style('sscn-selection',plugins_url('assets/selection.css',dirname(__DIR__).'/sscn-commerce.php'),[],'1.0.0');
    wp_enqueue_script('sscn-selection',plugins_url('assets/selection.js',dirname(__DIR__).'/sscn-commerce.php'),[],'1.0.0',true);
    wp_localize_script('sscn-selection','sscnSelection',['endpoint'=>add_query_arg('action','sscn_selection',admin_url('admin-ajax.php')),
        'csrf'=>SelectionDraft::csrf(),'url'=>selection_url(),'cart'=>wc_get_cart_url()]);
});
// Keep product-page selection, but send it to the same saved list without tier controls.
add_action('wp',static function(): void {
    if (!selection_url() || !is_product() || !\ParameterCommerce\Pack_Builder::enabled(get_queried_object_id())) { return; }
    remove_action('woocommerce_variable_add_to_cart',[\ParameterCommerce\Pack_Builder::class,'form'],1);
    remove_action('woocommerce_variable_add_to_cart','woocommerce_variable_add_to_cart',30);
    remove_action('wp_enqueue_scripts',[\ParameterCommerce\Pack_Builder::class,'assets']);
    add_action('woocommerce_variable_add_to_cart',__NAMESPACE__.'\\selection_product_form',1);
});
function selection_product_form(): void {
    global $product;
    try { $opts=SelectionCart::options($product->get_id()); $state=SelectionDraft::operate(['op'=>'load']); }
    catch (\RuntimeException $e) { echo '<p>'.esc_html($e->getMessage()).'</p>'; return; }
    echo '<section class="sscn-selection-entry"><h2>选择规格与数量</h2><form class="sscn-product-selection" method="post" action="'.esc_url(selection_url()).'">';
    selection_hidden($state,'add');
    foreach ($opts['items'] as $id=>$v) {
        $stock=max(0,(int)$v->get_stock_quantity()-wc_get_held_stock_quantity($v));
        echo '<label class="sscn-product-selection-row"><span>'.esc_html(wc_get_formatted_variation($v,true,true)).' <small>'.wp_kses_post(wc_price(wc_get_price_to_display($v))).' / 双</small></span><input type="number" name="items['.(int)$id.']" min="0" max="'.$stock.'" step="1" value="0" required '.disabled($stock<1,true,false).'></label>';
    }
    echo '<p>可与其他款一起选，合计至少4双。</p><button type="submit" class="button" data-selection-add>加入选袜清单</button> <a href="'.esc_url(selection_url()).'">查看已选袜子 →</a><p data-selection-message role="status" aria-live="polite"></p></form></section>';
}
add_filter('woocommerce_get_price_html',static function($html,$p) {
    if (!selection_url() || !\ParameterCommerce\Pack_Builder::enabled($p->is_type('variation')?$p->get_parent_id():$p->get_id())) { return $html; }
    if ($p->is_type('variable')) {
        $min=$p->get_variation_price('min',true);$max=$p->get_variation_price('max',true);
        return ($min===$max?wc_price($min):wc_format_price_range($min,$max)).' / 双';
    }
    return wc_price(wc_get_price_to_display($p)).' / 双';
},30,2);
add_filter('woocommerce_get_item_data',static function(array $data,array $line): array {
    if (($line['pc_pack']['kind'] ?? '')==='selection-v1' && selection_url()) {
        $data[]=['key'=>'选袜清单','value'=>'自由选袜','display'=>'<a href="'.esc_url(add_query_arg('edit_selection',$line['pc_pack']['id'],selection_url())).'">修改这份清单</a>'];
    }
    return $data;
},20,2);
add_action('woocommerce_product_options_inventory_product_data',static function(): void {
    woocommerce_wp_checkbox(['id'=>'_sscn_selection_disabled','label'=>'暂停自由选袜','description'=>'暂停此商品加入跨商品选袜清单；原有单款套装不受影响。']);
});
add_action('woocommerce_admin_process_product_object',static function($product): void {
    if (!current_user_can('edit_post',$product->get_id()) || !isset($_POST['woocommerce_meta_nonce']) || !is_string($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])),'woocommerce_save_data')) { return; }
    $product->update_meta_data('_sscn_selection_disabled',isset($_POST['_sscn_selection_disabled'])?'yes':'no');
});
function selection_hidden(array $state,string $op): void {
    echo '<input type="hidden" name="sscn_selection_op" value="'.esc_attr($op).'"><input type="hidden" name="csrf" value="'.esc_attr(SelectionDraft::csrf()).'"><input type="hidden" name="revision" value="'.esc_attr($state['revision']).'">';
}
function selection_sidebar(array $state): void {
    echo '<form method="post" class="sscn-selection-save">'; selection_hidden($state,'save');
    echo '<div class="sscn-selection-lines">';
    foreach ($state['items'] as $line) {
        echo '<div class="sscn-selection-line"><img src="'.esc_url($line['image']).'" width="56" height="56" alt=""><div><a href="'.esc_url($line['url']).'">'.esc_html($line['name']).'</a><small>'.esc_html($line['label']).'</small><label>双数 <input type="number" min="0" step="1" name="items['.esc_attr($line['id']).']" value="'.esc_attr($line['quantity']).'" required></label></div></div>';
    }
    if (!$state['items']) { echo '<p class="sscn-selection-empty">先选喜欢的款式，再选颜色和尺码。</p>'; }
    echo '</div><p class="sscn-selection-total">已选 '.(int)$state['count'].' 双';
    if ($state['total']!==null) { echo ' · '.wp_kses_post(wc_price($state['total'])); }
    echo '</p><p class="sscn-selection-error">'.esc_html($state['error']).'</p><button class="button" type="submit">保存修改</button></form>';
    echo '<form method="post" class="sscn-selection-submit">';selection_hidden($state,'commit');
    echo '<input type="hidden" name="signature" value="'.esc_attr($state['signature']).'"><button class="sscn-button" '.disabled($state['count']<$state['minimum'] || $state['error'] || $state['conflict'],true,false).'>加入购物车</button></form>';
    echo '<form method="post" class="sscn-selection-clear">';selection_hidden($state,'clear');echo '<button class="sscn-selection-text-button">清空清单</button></form>';
}
add_shortcode('sscn_choose_socks',static function(): string {
    try { $state=SelectionDraft::operate(['op'=>'load']); }
    catch (\Throwable $e) { return '<p>选袜服务暂时不可用，请稍后再试。</p>'; }
    $products=wc_get_products(['type'=>'variable','status'=>'publish','limit'=>100,'orderby'=>'menu_order','order'=>'ASC']);
    ob_start(); wc_print_notices();
    echo '<div class="sscn-selection" data-state="'.esc_attr(wp_json_encode($state)).'"><p class="sscn-selection-lead">同款或不同款都可以，颜色、尺码自由选，至少选4双。</p><p class="sscn-selection-help">清单自动保存，稍后可以回来继续。</p><div class="sscn-selection-notice" role="status" aria-live="polite"></div>';
    if ($state['conflict']) {
        echo '<div class="sscn-selection-login-choice"><p>账户与登录前各有一份清单，请选择要继续的那份。</p><form method="post">';selection_hidden($state,'resolve');
        echo '<input type="hidden" name="guest_revision" value="'.esc_attr($state['conflict']['revision']).'"><button name="choice" value="account">继续账户清单（'.$state['count'].'双）</button> <button name="choice" value="guest">继续登录前清单（'.$state['conflict']['count'].'双）</button></form></div>';
    }
    $edit=isset($_GET['edit_selection']) && is_string($_GET['edit_selection']) ? sanitize_text_field(wp_unslash($_GET['edit_selection'])) : '';
    if ($edit && wp_is_uuid($edit)) {
        echo '<form method="post" class="sscn-selection-edit-cart">';selection_hidden($state,'edit_cart');
        echo '<input type="hidden" name="group" value="'.esc_attr($edit).'"><p>将购物车中的这份选择取回编辑，完成后再加入购物车。</p><button class="button">取回这份清单</button></form>';
    }
    echo '<div class="sscn-selection-layout"><div class="sscn-selection-catalog"><label class="sscn-selection-search" hidden>查找款式 <input type="search" placeholder="搜索名称或型号"></label><div class="sscn-selection-products">';
    foreach ($products as $p) {
        try { $opts=SelectionCart::options($p->get_id()); } catch (\RuntimeException $e) { continue; }
        if (!$opts['items']) { continue; }
        echo '<article class="sscn-selection-product"><a href="'.esc_url(get_permalink($p->get_id())).'">'.$p->get_image('woocommerce_thumbnail',['loading'=>'lazy']).'</a><div class="sscn-selection-product-copy"><h2><a href="'.esc_url(get_permalink($p->get_id())).'">'.esc_html($p->get_name()).'</a></h2>';
        $desc=wp_strip_all_tags($p->get_short_description());
        if ($desc) { echo '<p>'.esc_html(mb_strimwidth($desc,0,180,'…')).'</p>'; }
        echo wp_kses_post(\ParameterCommerce\Wear_Profile::render($p,true));
        $display = array_map(static fn($v)=>wc_get_price_to_display($v,['price'=>SelectionCart::unit($v)/100]),$opts['items']);
        $low = min($display); $high = max($display);
        echo '<p class="sscn-selection-rate">'.wp_kses_post($low===$high ? wc_price($low) : wc_format_price_range($low,$high)).' / 双</p>';
        echo '<details><summary>选择规格</summary><form method="post" class="sscn-selection-product-add">';selection_hidden($state,'add');
        echo '<label>颜色、尺码及款式 <select name="variation" required><option value="">请选择规格</option>';
        foreach ($opts['items'] as $id=>$v) {
            $stock=max(0,(int)$v->get_stock_quantity()-wc_get_held_stock_quantity($v));
            echo '<option value="'.(int)$id.'" '.disabled($stock<1,true,false).'>'.esc_html(wc_get_formatted_variation($v,true,true).' — ¥'.number_format(wc_get_price_to_display($v,['price'=>SelectionCart::unit($v)/100]),2)).($stock<1?'（暂时缺货）':'').'</option>';
        }
        echo '</select></label><label>双数 <input name="quantity" type="number" min="1" step="1" value="1" required></label><button class="button" type="submit">加入清单</button></form></details></div></article>';
    }
    echo '</div></div><aside class="sscn-selection-sidebar" id="sscn-selection-chosen"><h2>已选袜子</h2><div class="sscn-selection-sidebar-body">';selection_sidebar($state);echo '</div></aside></div><a class="sscn-selection-mobile" href="#sscn-selection-chosen">查看已选 '.(int)$state['count'].' 双</a></div>';
    return ob_get_clean();
});
// Translate the compact native form into the same strict variation/quantity input.
add_action('template_redirect',static function(): void {
    if (selection_page() && isset($_POST['sscn_selection_op']) && $_POST['sscn_selection_op']==='add' && isset($_POST['variation'],$_POST['quantity']) && is_scalar($_POST['variation']) && is_scalar($_POST['quantity'])) {
        $_POST['items']=[(string)$_POST['variation']=>$_POST['quantity']];
    }
},0);

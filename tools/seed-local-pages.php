<?php
/** First-run Chinese pages. Run through the isolated tools/wp.sh wrapper. */
$sscn_preview = 'local' === wp_get_environment_type() || ( 'staging' === wp_get_environment_type() && defined( 'SSCN_DOMESTIC_SITE' ) && SSCN_DOMESTIC_SITE );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! $sscn_preview || 'sockspecs_cn' !== DB_NAME ) {
	throw new RuntimeException( 'Domestic local WordPress only.' );
}
$default_category = get_term( (int) get_option( 'default_product_cat' ), 'product_cat' );
if ( $default_category && ! is_wp_error( $default_category ) && 'Uncategorized' === $default_category->name ) {
	wp_update_term( $default_category->term_id, 'product_cat', array( 'name' => '未分类' ) );
}
if ( get_option( 'sscn_local_pages_seeded' ) ) {
	WP_CLI::success( 'Domestic pages already exist; preserving editor changes.' );
	return;
}
WC_Install::create_pages();
$home = <<<'HTML'
<section class="hero">
<p class="eyebrow">SOCKSPECS / 从一双袜子开始</p>
<h1>选袜子，<br>先看它是怎么做的。</h1>
<p class="intro">贴身穿着的舒适，来自材料、织法和合适的尺码。我们希望把这些差别说清楚，让你为自己需要的品质付费。</p>
<div class="actions"><a class="sscn-button" href="CATALOG_URL">按参数选袜</a><a href="ABOUT_URL">了解我们的选袜理念 →</a></div>
</section>
<section class="principles" aria-label="选袜子的三个角度">
<div><span class="number">01 / 材料</span><h2>不只看棉或羊毛</h2><p>纤维的种类、含量与品质，都会影响触感和价格。有依据的信息明确展示，未知的部分不凭空补齐。</p></div>
<div><span class="number">02 / 工艺</span><h2>看见穿着的细节</h2><p>袜头接缝、毛圈位置与织造方式，让外观相近的袜子有不同的穿着体验。按你的日常需要选择。</p></div>
<div><span class="number">03 / 合脚</span><h2>从实际穿着出发</h2><p>尺码决定是否合脚，袜筒高度、厚薄与重量帮助理解形态。把参数放在一起，更容易找到适合自己的一双。</p></div>
</section>
<section class="status"><h2>国内站正在准备中</h2><p>我们正在整理商品与参数，确定国内售价和配送方式。当前为预览阶段，商品目录暂为空，尚未开放购买。</p></section>
HTML;
$pages = array(
	'catalog' => array( '选袜目录', '[pc_catalog]' ),
	'compare' => array( '商品对比', '[pc_compare]' ),
	'about' => array( '关于 SockSpecs', '<h2>把钱花在你需要的品质上</h2><p>我们关注袜子本身：用了什么材料、采用什么工艺、穿起来是否合适。相比品牌故事与过度包装，我们更愿意把这些具体的信息讲清楚。</p><p>外观看起来相似的袜子，可能在纤维品质、织造与接缝上有差别。参数不是为了让选择更复杂，而是帮助你理解差异，找到适合自己的产品。</p><p>国内站正在筹备中。商品、价格与配送信息将在确认后逐步完善。</p><p>运营主体：杭州源界星图科技有限公司</p>' ),
);
$ids = array();
foreach ( $pages as $slug => $page ) {
	$existing = get_page_by_path( $slug );
	$ids[ $slug ] = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $page[0], 'post_content' => $page[1] ), true );
	if ( is_wp_error( $ids[ $slug ] ) ) {
		WP_CLI::error( $ids[ $slug ]->get_error_message() );
	}
}
$home = str_replace( array( 'CATALOG_URL', 'ABOUT_URL' ), array( esc_url( get_permalink( $ids['catalog'] ) ), esc_url( get_permalink( $ids['about'] ) ) ), $home );
$existing_home = get_page_by_path( 'home' );
$ids['home'] = $existing_home ? $existing_home->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'home', 'post_title' => '首页', 'post_content' => $home ), true );
if ( is_wp_error( $ids['home'] ) ) {
	WP_CLI::error( $ids['home']->get_error_message() );
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home'] );
update_option( 'blogdescription', '看懂材料与工艺，选适合自己的袜子。' );
foreach ( array( 'shop' => '全部商品', 'cart' => '购物车', 'checkout' => '结算', 'myaccount' => '我的账户' ) as $key => $title ) {
	$id = wc_get_page_id( $key );
	if ( $id > 0 ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
	}
}
$menu = wp_get_nav_menu_object( '国内站导航' );
$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( '国内站导航' );
if ( is_wp_error( $menu_id ) ) {
	WP_CLI::error( $menu_id->get_error_message() );
}
if ( ! wp_get_nav_menu_items( $menu_id ) ) {
	foreach ( array( $ids['home'], $ids['catalog'], $ids['compare'], wc_get_page_id( 'myaccount' ) ) as $id ) {
		wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	}
}
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu_id ) );
update_option( 'sscn_local_pages_seeded', 1 );
WP_CLI::success( 'Chinese preview pages created.' );

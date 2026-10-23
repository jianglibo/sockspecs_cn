<?php
/** Main layout for the domestic preview. */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">跳至内容</a>
<?php if ( in_array( wp_get_environment_type(), array( 'local', 'staging' ), true ) ) : ?>
<div class="notice">国内站预览 · 参数已上线，暂未开放购买</div>
<?php endif; ?>
<header class="site-header"><div class="wrap header-inner">
<div class="site-identity">
<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">索克派 <span class="brand-english">SockSpecs</span><small>看懂一双袜子</small></a>
</div>
<nav aria-label="主导航"><?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'fallback_cb' => false, 'depth' => 1 ) ); ?></nav>
</div></header>

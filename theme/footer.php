<?php
/** Domestic footer. */
defined('ABSPATH') || exit;
?>
<footer class="site-footer"><div class="wrap footer-inner">
<span>索克派 SockSpecs</span>
<span>杭州源界星图科技有限公司</span>
<nav class="sscn-footer-service" aria-label="客户服务">
<?php $about_page = get_page_by_path('about'); ?>
<?php if ($about_page && $about_page->post_status === 'publish') : ?>
<a href="<?php echo esc_url(get_permalink($about_page)); ?>">关于索克派</a>
<?php endif; ?>
<?php if ($support_url = sscn_support_page_url()) : ?>
<a href="<?php echo esc_url($support_url); ?>">联系客服</a>
<?php endif; ?>
<?php if ($returns_url = sscn_returns_policy_url()) : ?>
<a href="<?php echo esc_url($returns_url); ?>">退换货政策</a>
<?php endif; ?>
<?php if ($wear_url = sscn_wear_feedback_url()) : ?>
<a href="<?php echo esc_url($wear_url); ?>">穿着体验记录</a>
<?php endif; ?>
</nav>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>

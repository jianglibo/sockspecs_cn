<?php
/** Assert the domestic bootstrap cannot register international integrations. */
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/parameter-commerce/parameter-commerce.php';
$blocked = ['Brand_Pages','Public_Policies','Storefront_Language','Google_Packs','Google_Login','Referrals','Referral_UI','Shipping_Admin','Shipping'];
foreach ($GLOBALS['pc_test_hooks'] as $hook => $callbacks) {
    foreach ($callbacks as $callback) {
        if (!is_array($callback)) { continue; }
        foreach ($blocked as $class) {
            pc_assert($callback[0] !== 'ParameterCommerce\\' . $class, 'Blocked international hook: ' . $hook);
        }
    }
}
$root = dirname(__DIR__);
$compose = file_get_contents($root . '/compose.yaml');
pc_assert(str_contains($compose, 'name: sockspecs-cn') && str_contains($compose, 'MYSQL_DATABASE: sockspecs_cn') && str_contains($compose, '33089'), 'Domestic database isolation missing.');
$deploy = file_get_contents($root . '/tools/deploy/sync-code.py');
pc_assert(str_contains($deploy, 'raise SystemExit') && !str_contains($deploy, 'subprocess'), 'Unconfigured deployment must stop before connecting.');
pc_assert(!is_dir($root . '/parameter-commerce/content'), 'International publication payloads must not be copied.');
echo "PASS: domestic hooks, database namespace, deployment disabled, no international content payloads.\n";

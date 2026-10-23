<?php
/** Assert the domestic bootstrap cannot register international integrations. */
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/parameter-commerce/parameter-commerce.php';
$blocked = ['Brand_Pages','Public_Policies','Storefront_Language','Google_Packs','Google_Login','Shipping_Admin','Shipping'];
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
pc_assert(str_contains($deploy, "HOST = 'claw-host'") && str_contains($deploy, "REMOTE_ROOT = '/srv/sockspecs-cn'"), 'Deployment must target the independent domestic site.');
pc_assert(str_contains($deploy, 'if not args.apply:') && !str_contains($deploy, 'mail-never-lost'), 'Deployment must require explicit apply and exclude the international target.');
pc_assert(!is_dir($root . '/parameter-commerce/content'), 'International publication payloads must not be copied.');
$referrals = file_get_contents($root . '/parameter-commerce/includes/class-referrals.php');
$ledger = file_get_contents($root . '/parameter-commerce/includes/class-referral-store.php');
pc_assert(str_contains($referrals, "'CNY' === get_woocommerce_currency()") && str_contains($referrals, 'const REWARD_FEN = 500;'), 'Domestic reward must be five yuan.');
pc_assert(str_contains($ledger, 'sscn_referral_ledger') && !str_contains($ledger, 'pc_referral_ledger'), 'Domestic ledger must have its own namespace.');
pc_assert(str_contains($referrals, "'sscn-ref-'") && !str_contains($referrals, "'ss-ref-'"), 'Domestic signed referral codes must be distinct.');
echo "PASS: domestic hooks, database namespace, guarded domestic deployment, no international content payloads.\n";

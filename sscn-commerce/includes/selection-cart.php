<?php
/** Unified minimum-four-pair selections, using native variation inventory/order lines. */
namespace SockSpecsCN\Commerce;
defined('ABSPATH') || exit;

final class SelectionCart {
    private static bool $adding = false;
    public static function adding(): bool { return self::$adding; }
    public static function minimum(): int { return 4; }
    public static function normalize($items): array {
        if (!is_array($items) || count($items) > 500) { throw new \RuntimeException('所选规格过多，请分次购买。'); }
        $result = [];
        foreach ($items as $id => $qty) {
            if (!preg_match('/^[1-9][0-9]{0,10}$/D', (string)$id) || !is_scalar($qty) || !preg_match('/^(0|[1-9][0-9]{0,5})$/D', (string)$qty)) {
                throw new \RuntimeException('请填写有效规格和整数双数。');
            }
            if ((int)$qty) { $result[(int)$id] = (int)$qty; }
        }
        ksort($result); return $result;
    }
    public static function options(int $parent): array {
        $p = wc_get_product($parent);
        if (!$p || $p->get_meta('_sscn_selection_disabled') === 'yes') { throw new \RuntimeException('此商品暂不支持自由选袜。'); }
        if (get_woocommerce_currency() !== 'CNY' || wc_get_price_decimals() !== 2) { throw new \RuntimeException('请核对店铺币种。'); }
        $groups = \ParameterCommerce\Pack_Builder::groups($parent);
        $items = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $id => $v) {
                self::unit($v);
                $items[$id] = $v;
            }
        }
        return ['product'=>$p, 'items'=>$items];
    }
    public static function unit($variation): int {
        $price = (string)$variation->get_price();
        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D',$price)) { throw new \RuntimeException('请为所选规格设置精确到分的单价。'); }
        $parts = explode('.', $price);
        $cents = (int)$parts[0]*100 + (int)str_pad($parts[1] ?? '',2,'0');
        if ($cents <= 0 || $cents > 100000000) { throw new \RuntimeException('商品单价无效。'); }
        return $cents;
    }
    public static function quote(int $size, array $items, bool $complete = false): array {
        $items = self::normalize($items);
        $size = array_sum($items);
        if ($complete && $size < self::minimum()) { throw new \RuntimeException('至少选择4双袜子。'); }
        $cache = []; $lines = []; $sum = 0; $signature = [];
        foreach ($items as $id => $qty) {
            $v = wc_get_product($id);
            if (!$v || !$v->is_type('variation')) { throw new \RuntimeException('所选规格已不存在，请移除后重新选择。'); }
            $pid = $v->get_parent_id();
            $cache[$pid] ??= self::options($pid);
            if (!isset($cache[$pid]['items'][$id])) { throw new \RuntimeException($v->get_name() . ' 已下架或缺货，请调整选择。'); }
            $unit = self::unit($v);
            $numerator = $unit * $qty;
            $sum += $numerator;
            $lines[$id] = ['product'=>$pid,'quantity'=>$qty,'amount'=>$numerator,'unit'=>$unit];
            $signature[$id] = [$pid,$qty,$unit,$v->get_price(),$v->get_tax_status(),$v->get_tax_class()];
        }
        $total = $sum;
        $display = 0;
        foreach ($lines as $id => &$line) {
            $line['display'] = wc_get_price_to_display(wc_get_product($id), ['price'=>$line['amount']/100]);
            $display += $line['display'];
        }
        unset($line);
        return ['size'=>$size,'items'=>$items,'lines'=>$lines,'total'=>$total,'display'=>$display,
            'signature'=>hash('sha256',wp_json_encode([$size,$signature]))];
    }
    public static function stock(array $items, $cart, ?string $exclude = null): void {
        $used = [];
        foreach ($cart->get_cart() as $line) {
            if ($exclude && ($line['pc_pack']['id'] ?? '') === $exclude) { continue; }
            $v = $line['data']; $id = $v->get_stock_managed_by_id();
            $used[$id] = ($used[$id] ?? 0) + $line['quantity'];
        }
        foreach ($items as $id => $qty) {
            $v = wc_get_product($id);
            if (!$v || $v->get_stock_quantity() === null || $v->get_stock_quantity() - wc_get_held_stock_quantity($v) < $qty + ($used[$id] ?? 0)) {
                throw new \RuntimeException('所选规格库存不足，请减少数量或换一个规格。');
            }
        }
    }
    public static function validate(array $pack, array $lines, $cart): array {
        if (($pack['kind'] ?? '') !== 'selection-v1' || !wp_is_uuid($pack['id'] ?? '') || !isset($pack['size'],$pack['items'],$pack['signature'])) {
            throw new \RuntimeException('选袜清单数据不完整，请重新选择。');
        }
        $q = self::quote((int)$pack['size'], (array)$pack['items'], true);
        if ((int)$pack['size'] !== $q['size'] || !hash_equals($q['signature'], (string)$pack['signature'])) { throw new \RuntimeException('商品价格已变化，请修改清单并确认新的价格。'); }
        $actual = [];
        foreach ($lines as $line) {
            $id = (int)$line['variation_id'];
            if ($line['pc_pack'] !== $pack || !isset($q['lines'][$id]) || (int)$line['product_id'] !== $q['lines'][$id]['product'] || (float)$line['quantity'] !== (float)(int)$line['quantity']) {
                throw new \RuntimeException('选袜清单的商品明细不一致。');
            }
            $actual[$id] = ($actual[$id] ?? 0) + (int)$line['quantity'];
        }
        ksort($actual);
        if ($actual !== $q['items']) { throw new \RuntimeException('选袜清单的数量不完整。'); }
        self::stock($q['items'], $cart, $pack['id']);
        return $q;
    }
    public static function add(array $q, string $group): string {
        $cart = WC()->cart;
        $existing = [];
        foreach ($cart->get_cart() as $key => $line) { if (($line['pc_pack']['id'] ?? '') === $group) { $existing[$key] = $line; } }
        if ($existing) { self::validate(reset($existing)['pc_pack'], $existing, $cart); return $group; }
        self::stock($q['items'], $cart);
        $summary=[];
        foreach ($q['items'] as $id=>$qty) {
            $v=wc_get_product($id);
            $summary[] = wc_get_product($v->get_parent_id())->get_name() . ' / ' . wc_get_formatted_variation($v,true,true) . ' × ' . $qty;
        }
        $pack=['id'=>$group,'kind'=>'selection-v1','size'=>$q['size'],'items'=>$q['items'],'signature'=>$q['signature'],
            'amounts'=>array_map(static fn($l)=>$l['amount'],$q['lines']),'contents'=>implode('；',$summary)];
        $added=[]; self::$adding=true;
        try {
            foreach ($q['lines'] as $id=>$line) {
                $v=wc_get_product($id);
                if (!apply_filters('woocommerce_add_to_cart_validation',true,$line['product'],$line['quantity'],$id,$v->get_variation_attributes())) { throw new \RuntimeException('商品暂不能加入购物车。'); }
                $key=$cart->add_to_cart($line['product'],$line['quantity'],$id,$v->get_variation_attributes(),['pc_pack'=>$pack]);
                if (!$key) { throw new \RuntimeException('加入购物车失败，请重试。'); }
                $added[]=$key;
            }
            $cart->calculate_totals();
            $cart->set_session();
            WC()->session->save_data();
        } catch (\Throwable $e) {
            foreach ($added as $key) { $cart->remove_cart_item($key); }
            foreach ($cart->removed_cart_contents as $key=>$line) {
                if (($line['pc_pack']['id'] ?? '') === $group) { unset($cart->removed_cart_contents[$key]); }
            }
            $cart->set_session(); WC()->session->save_data(); throw $e;
        } finally { self::$adding=false; }
        return $group;
    }
    public static function calculate($cart): void {
        $groups=[];
        foreach ($cart->get_cart() as $key=>$line) {
            if (($line['pc_pack']['kind'] ?? '') === 'selection-v1') { $groups[$line['pc_pack']['id']][$key]=$line; }
        }
        foreach ($groups as $lines) {
            try {
                $pack=reset($lines)['pc_pack']; $q=self::validate($pack,$lines,$cart);
                foreach ($lines as $key=>$line) {
                    $v=clone wc_get_product($line['variation_id']);
                    $v->set_price($q['lines'][$line['variation_id']]['amount']/100/$line['quantity']);
                    $cart->cart_contents[$key]['data']=$v;
                }
            } catch (\RuntimeException $e) {
                // Checkout validation rejects stale groups; never charge a stale discounted price.
                foreach ($lines as $key=>$line) { $v=wc_get_product($line['variation_id']); if ($v) { $cart->cart_contents[$key]['data']=clone $v; } }
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', [SelectionCart::class,'calculate'], 21);

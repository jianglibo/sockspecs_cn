<?php
/** Server-owned selection drafts. Anonymous tokens never appear in URLs or public HTML. */
namespace SockSpecsCN\Commerce;
defined('ABSPATH') || exit;

final class SelectionDraft {
    public static function table(): string { global $wpdb; return $wpdb->prefix.'sscn_selection_drafts'; }
    public static function install(): void {
        if (get_option('sscn_selection_schema') === '1') { return; }
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $t=self::table(); $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $t (
            owner_key varchar(64) NOT NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 0,
            size int NOT NULL DEFAULT 4,
            items longtext NOT NULL,
            state varchar(16) NOT NULL DEFAULT 'draft',
            group_id varchar(36) NOT NULL DEFAULT '',
            updated_at datetime NOT NULL,
            PRIMARY KEY  (owner_key),
            KEY updated_at (updated_at)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t))) !== $t) { throw new \RuntimeException('选袜清单保存服务尚未就绪。'); }
        update_option('sscn_selection_schema','1',false);
    }
    public static function cookie(): string {
        $value=$_COOKIE['sscn_selection'] ?? '';
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D',$value) ? $value : '';
    }
    public static function ensure_cookie(): void {
        if (self::cookie()) { return; }
        $value=bin2hex(random_bytes(32));
        setcookie('sscn_selection',$value,['expires'=>time()+30*DAY_IN_SECONDS,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE['sscn_selection']=$value;
    }
    public static function guest(): string { return self::cookie() ? hash('sha256','guest:'.self::cookie()) : ''; }
    public static function owner(): string {
        $id=get_current_user_id();
        if ($id) { return hash('sha256','account:'.$id); }
        $owner=self::guest(); if (!$owner) { throw new \RuntimeException('请允许本站Cookie后重新加载。'); } return $owner;
    }
    public static function csrf(): string { return wp_hash('sscn_selection:'.self::owner(),'nonce'); }
    public static function locks(array $keys, callable $fn) {
        global $wpdb; $keys=array_unique(array_filter($keys)); sort($keys); $held=[];
        try {
            foreach ($keys as $key) {
                $name='sscn_sel_'.md5($wpdb->prefix.$key);
                if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$name))!==1) { throw new \RuntimeException('清单正在保存，请稍后重试。'); }
                $held[]=$name;
            }
            return $fn();
        } finally { foreach (array_reverse($held) as $name) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); } }
    }
    public static function read(string $owner): array {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE owner_key=%s',$owner),ARRAY_A);
        if (!$row) {
            $row=['owner_key'=>$owner,'revision'=>0,'size'=>4,'items'=>'{}','state'=>'draft','group_id'=>'','updated_at'=>gmdate('Y-m-d H:i:s')];
            if ($wpdb->insert(self::table(),$row)===false) { throw new \RuntimeException('暂时无法保存清单。'); }
        }
        $row['revision']=(int)$row['revision']; $row['size']=(int)$row['size']; $row['items']=json_decode($row['items'],true) ?: [];
        return $row;
    }
    public static function write(array $row, array $changes): array {
        global $wpdb; $next=array_merge($row,$changes,['revision'=>$row['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s')]);
        $data=$next; unset($data['owner_key']); $data['items']=wp_json_encode($next['items']);
        if ($wpdb->update(self::table(),$data,['owner_key'=>$row['owner_key'],'revision'=>$row['revision']])!==1) { throw new \RuntimeException('另一页面已修改清单，请重新加载后继续。',409); }
        return $next;
    }
    public static function assert_revision(array $row, $revision): void {
        if (!is_scalar($revision) || !ctype_digit((string)$revision) || (int)$revision !== $row['revision']) { throw new \RuntimeException('另一页面已修改清单，已读取最新内容，请检查后重试。',409); }
    }
    public static function conflict(array $row, ?array $guest): bool { return $guest && $guest['items'] && $row['items'] && $row['state']==='draft' && $guest['state']==='draft'; }
    public static function session_cart(): void {
        if (!WC()->session) { WC()->initialize_session(); }
        // Re-read cart storage inside our owner lock, before hydrating the cart.
        $session=WC()->session;
        wp_cache_delete(\WC_Cache_Helper::get_cache_prefix(WC_SESSION_CACHE_GROUP).$session->get_customer_id(),WC_SESSION_CACHE_GROUP);
        $fresh=$session->get_session($session->get_customer_id(),[]);
        foreach (['cart','cart_totals','applied_coupons','coupon_discount_totals','coupon_discount_tax_totals','removed_cart_contents'] as $key) {
            $value = maybe_unserialize($fresh[$key] ?? []);
            $session->set($key,is_array($value) ? $value : []);
        }
        if (!WC()->cart) { wc_load_cart(); }
        else { WC()->cart->get_cart_from_session(); }
        $session->set_customer_session_cookie(true);
    }
    public static function operate(array $input): array {
        $owner=self::owner(); $guestkey=get_current_user_id() ? self::guest() : '';
        return self::locks([$owner,$guestkey],static function() use($owner,$guestkey,$input) {
            $row=self::read($owner); $guest=$guestkey ? self::read($guestkey) : null;
            $op=$input['op'] ?? 'load';
            if (!is_string($op)) { throw new \RuntimeException('无效操作。'); }
            if ($op==='load') {
                if ($guest && $guest['state']==='draft' && $guest['items'] && !$row['items'] && $row['state']!=='pending') {
                    $row=self::write($row,['size'=>$guest['size'],'items'=>$guest['items'],'state'=>'draft','group_id'=>'']);
                    self::write($guest,['items'=>[],'state'=>'draft']); $guest=null;
                }
                return self::view($row,$guest);
            }
            self::assert_revision($row,$input['revision'] ?? null);
            if ($op==='resolve') {
                if (!self::conflict($row,$guest)) { throw new \RuntimeException('登录前的清单已处理，请重新加载。',409); }
                self::assert_revision($guest,$input['guest_revision'] ?? null);
                if (!in_array($input['choice'] ?? '',['account','guest'],true)) { throw new \RuntimeException('请选择要继续的清单。'); }
                if ($input['choice']==='guest') { $row=self::write($row,['size'=>$guest['size'],'items'=>$guest['items'],'state'=>'draft','group_id'=>'']); }
                else { $row=self::write($row,[]); }
                self::write($guest,['items'=>[],'state'=>'draft']); return self::view($row,null);
            }
            if (self::conflict($row,$guest)) { throw new \RuntimeException('请先选择继续账户清单还是登录前的清单。',409); }
            if ($op==='commit') {
                self::session_cart();
                if ($row['state']==='committed') { return self::view($row,null)+['redirect'=>wc_get_cart_url()]; }
                $q=SelectionCart::quote($row['size'],$row['items'],true);
                if (!is_string($input['signature'] ?? null) || !hash_equals($q['signature'],$input['signature'])) { throw new \RuntimeException('价格或规格已变化，请查看最新价格后再加入购物车。',409); }
                if ($row['state']!=='pending') { $row=self::write($row,['state'=>'pending','group_id'=>wp_generate_uuid4()]); }
                try { SelectionCart::add($q,$row['group_id']); }
                catch (\Throwable $e) { self::write($row,['state'=>'draft','group_id'=>'']); throw $e; }
                $row=self::write($row,['state'=>'committed']);
                return self::view($row,null)+['redirect'=>wc_get_cart_url()];
            }
            if ($row['state']==='pending') { throw new \RuntimeException('上次加入购物车尚在完成，请重新点击加入购物车。',409); }
            if ($op==='edit_cart') {
                self::session_cart(); $group=$input['group'] ?? ''; $lines=[];
                foreach (WC()->cart->get_cart() as $key=>$line) { if (($line['pc_pack']['id'] ?? '')===$group && ($line['pc_pack']['kind'] ?? '')==='selection-v1') { $lines[$key]=$line; } }
                if (!$lines) { throw new \RuntimeException('此选袜清单不在当前购物车中。'); }
                if ($row['state']==='draft' && $row['items']) { throw new \RuntimeException('请先完成或清空当前清单，再修改购物车中的选择。'); }
                $pack=reset($lines)['pc_pack'];
                $row=self::write($row,['items'=>$pack['items'],'size'=>(int)$pack['size'],'state'=>'draft','group_id'=>'']);
                WC()->cart->remove_cart_item(array_key_first($lines));
                foreach (WC()->cart->removed_cart_contents as $key=>$line) { if (($line['pc_pack']['id'] ?? '')===$group) { unset(WC()->cart->removed_cart_contents[$key]); } }
                WC()->cart->calculate_totals(); WC()->cart->set_session(); WC()->session->save_data();
                return self::view($row,null);
            }
            if (!in_array($op,['save','add','clear'],true)) { throw new \RuntimeException('无效操作。'); }
            $items=$row['state']==='committed' ? [] : $row['items'];
            $size=$row['size'];
            if ($op==='clear') { $items=[]; }
            elseif ($op==='save') {
                $items=SelectionCart::normalize($input['items'] ?? []);
                // Unavailable existing rows can remain visible or be removed, never silently substituted.
                foreach ($items as $id=>$qty) { if (!isset($row['items'][$id]) || $qty>$row['items'][$id]) { SelectionCart::quote($size,[$id=>$qty]); } }
            } else {
                $add=SelectionCart::normalize($input['items'] ?? null);
                if (!$add) { throw new \RuntimeException('请先选择至少一双袜子的规格。'); }
                SelectionCart::quote($size,$add);
                foreach ($add as $id=>$qty) { $items[$id]=($items[$id] ?? 0)+$qty; }
                $items=SelectionCart::normalize($items);
            }
            $row=self::write($row,['size'=>array_sum($items),'items'=>$items,'state'=>'draft','group_id'=>'']);
            return self::view($row,null);
        });
    }
    public static function view(array $row, ?array $guest): array {
        $items=[]; $error=''; $q=null;
        if ($row['items']) { try { $q=SelectionCart::quote($row['size'],$row['items']); } catch (\RuntimeException $e) { $error=$e->getMessage(); } }
        foreach ($row['items'] as $id=>$qty) {
            $v=wc_get_product($id); $p=$v && $v->is_type('variation') ? wc_get_product($v->get_parent_id()) : null;
            $stock=$v ? $v->get_stock_quantity() : null;
            $available=$v && $v->is_in_stock() && $stock!==null ? max(0,$stock-wc_get_held_stock_quantity($v)) : 0;
            if ($available<$qty) { $error='部分规格库存不足，请调整数量或移除。'; }
            $items[]=['id'=>(int)$id,'quantity'=>$qty,'name'=>$p ? $p->get_name() : '已不可用的商品','label'=>$v ? wc_get_formatted_variation($v,true,true) : '规格已删除',
                'image'=>$p ? (wp_get_attachment_image_url($p->get_image_id(),'woocommerce_thumbnail') ?: wc_placeholder_img_src()) : '',
                'url'=>$p ? get_permalink($p->get_id()) : '', 'available'=>$available,'unit'=>isset($q['lines'][$id]) ? wc_get_price_to_display($v,['price'=>$q['lines'][$id]['unit']/100]) : null,'amount'=>$q['lines'][$id]['display'] ?? null];
        }
        return ['revision'=>$row['revision'],'minimum'=>SelectionCart::minimum(),'size'=>array_sum($row['items']),'items'=>$items,'count'=>array_sum($row['items']),'state'=>$row['state'],
            'total'=>$q['display'] ?? ($items ? null : 0),'signature'=>$q['signature'] ?? '', 'error'=>$error,
            'conflict'=>self::conflict($row,$guest) ? ['revision'=>$guest['revision'],'size'=>$guest['size'],'count'=>array_sum($guest['items']),
                'items'=>array_map(static function($id,$qty){$v=wc_get_product($id);return ['name'=>$v ? $v->get_name() : '已不可用的规格','quantity'=>$qty];},array_keys($guest['items']),array_values($guest['items']))] : null];
    }
}

// Expire inactive drafts after 180 days.
add_action('sscn_selection_cleanup', static function(): void {
    // Owner keys are deliberately opaque; keep a generous 180-day inactivity expiry for all drafts.
    global $wpdb;
    $wpdb->query($wpdb->prepare('DELETE FROM '.SelectionDraft::table().' WHERE updated_at < %s',gmdate('Y-m-d H:i:s',time()-180*DAY_IN_SECONDS)));
});

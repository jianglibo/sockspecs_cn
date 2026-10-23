<?php
/** Domestic, manually reviewed feedback register. No email or public upload endpoint. */
namespace SockSpecsCN\Commerce\WearFeedback;
defined('ABSPATH') || exit;

function table(string $suffix): string { global $wpdb; return $wpdb->prefix . 'sscn_wear_' . $suffix; }
function install(): void {
    if (get_option('sscn_wear_schema') === '1') { return; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $records = table('records'); $entries = table('entries');
    dbDelta("CREATE TABLE $records (
        order_id bigint(20) unsigned NOT NULL,
        state varchar(20) NOT NULL DEFAULT 'pending',
        amount decimal(10,2) DEFAULT NULL,
        coupon_code varchar(100) DEFAULT NULL,
        coupon_id bigint(20) unsigned DEFAULT NULL,
        reward_email varchar(254) DEFAULT NULL,
        reviewed_by bigint(20) unsigned DEFAULT NULL,
        rewarded_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (order_id),
        UNIQUE KEY coupon_code (coupon_code)
    ) $charset;");
    dbDelta("CREATE TABLE $entries (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint(20) unsigned NOT NULL,
        actor_id bigint(20) unsigned NOT NULL,
        request_key varchar(36) NOT NULL,
        item_id bigint(20) unsigned NOT NULL DEFAULT 0,
        note text NOT NULL,
        attachment longblob DEFAULT NULL,
        attachment_name varchar(255) NOT NULL DEFAULT '',
        attachment_mime varchar(100) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY request_key (request_key),
        KEY order_id (order_id)
    ) $charset;");
    foreach ([$records, $entries] as $name) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name))) !== $name) {
            throw new \RuntimeException('反馈登记表创建失败。');
        }
    }
    update_option('sscn_wear_schema', '1', false);
}
function permitted(): void {
    if (!current_user_can('manage_woocommerce')) { throw new \RuntimeException('无权管理穿着反馈。'); }
}
function order(int $id): \WC_Order {
    $order = wc_get_order($id);
    if (!$order instanceof \WC_Order || $order instanceof \WC_Order_Refund || $order->get_currency() !== 'CNY' || $order->get_status() === 'trash') {
        throw new \RuntimeException('找不到国内人民币订单，请核对订单编号。');
    }
    return $order;
}
function record(int $id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . table('records') . ' WHERE order_id = %d', $id));
}
function lock(int $id, callable $fn) {
    global $wpdb;
    $key = 'sscn_wear_' . md5($wpdb->prefix . ':' . $id);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== 1) { throw new \RuntimeException('该订单正在处理，请稍后重试。'); }
    try { return $fn(); } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }
}
function update(int $id, array $values): void {
    global $wpdb;
    $values['updated_at'] = current_time('mysql', true);
    if ($wpdb->update(table('records'), $values, ['order_id' => $id]) === false) { throw new \RuntimeException('登记保存失败，请重试。'); }
}
function register_entry(int $id, int $item, string $note, string $state, string $request, array $file = []): void {
    permitted(); install(); $order = order($id);
    if (trim($note) === '' || strlen($note) > 20000) { throw new \RuntimeException('请填写反馈摘要（不超过约 6000 个汉字）。'); }
    if (!in_array($state, ['pending', 'needs_info', 'declined'], true) || !wp_is_uuid($request)) { throw new \RuntimeException('无效登记请求。'); }
    if ($item && !isset($order->get_items()[$item])) { throw new \RuntimeException('商品不属于该订单。'); }
    if ($file && (strlen($file['data'] ?? '') > 5 * 1024 * 1024 || !in_array($file['mime'] ?? '', ['application/pdf', 'image/jpeg', 'image/png'], true))) {
        throw new \RuntimeException('只接受 5 MB 以内的 PDF、JPG、PNG。');
    }
    lock($id, static function () use ($id, $item, $note, $state, $request, $file): void {
        global $wpdb; $now = current_time('mysql', true);
        if ($wpdb->query('START TRANSACTION') === false) { throw new \RuntimeException('无法开始保存反馈。'); }
        try {
            if (!$wpdb->get_var($wpdb->prepare('SELECT id FROM ' . table('entries') . ' WHERE request_key = %s', $request))) {
                if (!record($id) && !$wpdb->insert(table('records'), ['order_id'=>$id, 'created_at'=>$now, 'updated_at'=>$now])) { throw new \RuntimeException('无法建立订单反馈记录。'); }
                if (!$wpdb->insert(table('entries'), ['order_id'=>$id, 'actor_id'=>get_current_user_id(), 'request_key'=>$request, 'item_id'=>$item,
                    'note'=>$note, 'attachment'=>$file['data'] ?? null, 'attachment_name'=>$file['name'] ?? '', 'attachment_mime'=>$file['mime'] ?? '', 'created_at'=>$now])) { throw new \RuntimeException('无法保存反馈，请重试。'); }
                if (!record($id)->coupon_code) { update($id, ['state'=>$state]); }
            }
            if ($wpdb->query('COMMIT') === false) { throw new \RuntimeException('反馈提交失败。'); }
        } catch (\Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
    });
}
function issue(int $id, string $amount, bool $verified): int {
    permitted(); install();
    if (!$verified) { throw new \RuntimeException('请先确认购买身份及实际穿着反馈。'); }
    if (!preg_match('/^(?:[2-9](?:\.\d{1,2})?|10(?:\.0{1,2})?)$/D', $amount)) { throw new \RuntimeException('奖励金额必须为 2 至 10 元。'); }
    return lock($id, static function () use ($id, $amount): int {
        global $wpdb;
        $r = record($id);
        if (!$r) { throw new \RuntimeException('请先登记反馈再发放奖励。'); }
        if ($r->state === 'rewarded') { return (int) $r->coupon_id; }
        $order = order($id);
        if (!$order->is_paid() || !is_email($order->get_billing_email())) { throw new \RuntimeException('仅可奖励有效已付款订单，且订单须有下单邮箱。'); }
        if (!$r->coupon_code) {
            update($id, ['state'=>'issuing', 'amount'=>$amount, 'coupon_code'=>'wear-' . bin2hex(random_bytes(12)),
                'reward_email'=>strtolower($order->get_billing_email()), 'reviewed_by'=>get_current_user_id()]);
            $r = record($id);
        }
        // A reservation survives interruption. Retrying always repairs the SAME coupon,
        // including a crash between inserting the coupon post and saving its metadata.
        $coupon_id = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='shop_coupon' AND post_title=%s ORDER BY ID LIMIT 1", $r->coupon_code));
        $coupon = new \WC_Coupon($coupon_id);
        if (!$coupon_id) { $coupon->set_code($r->coupon_code); }
        $coupon->set_discount_type('fixed_cart'); $coupon->set_amount($r->amount);
        $coupon->set_email_restrictions([$r->reward_email]);
        $coupon->set_usage_limit(1); $coupon->set_usage_limit_per_user(1);
        $coupon->set_individual_use(false); $coupon->set_free_shipping(false);
        $coupon->set_date_expires(null); $coupon->set_minimum_amount(''); $coupon->set_maximum_amount('');
        $coupon->set_product_ids([]); $coupon->set_excluded_product_ids([]);
        $coupon->set_product_categories([]); $coupon->set_excluded_product_categories([]); $coupon->set_exclude_sale_items(false);
        $coupon->set_description('穿着反馈奖励；订单 #' . $order->get_order_number() . '；人工回复顾客，禁止重复发放。');
        $coupon->update_meta_data('_sscn_wear_order_id', $id);
        $coupon_id = $coupon->save();
        if (!$coupon_id) { throw new \RuntimeException('发券未完成，请使用恢复按钮重试。'); }
        update($id, ['state'=>'rewarded', 'coupon_id'=>$coupon_id, 'rewarded_at'=>current_time('mysql', true)]);
        return $coupon_id;
    });
}
// A coupon is redeemable only after its durable register confirms completion.
add_filter('woocommerce_coupon_is_valid', static function ($valid, $coupon) {
    if (!str_starts_with($coupon->get_code(), 'wear-')) { return $valid; }
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . table('records') . ' WHERE coupon_code = %s', $coupon->get_code()));
    if (!$r || $r->state !== 'rewarded' || (int) $r->coupon_id !== $coupon->get_id()) { return false; }
    return $valid;
}, 10, 2);
function url(int $id = 0): string { return add_query_arg(['page'=>'sscn-wear-feedback', 'order_id'=>$id], admin_url('admin.php')); }
function labels(): array { return ['pending'=>'待审核', 'needs_info'=>'待补充', 'declined'=>'未奖励', 'issuing'=>'发券待恢复', 'rewarded'=>'已奖励']; }
add_action('admin_menu', static function (): void {
    add_submenu_page('woocommerce', '穿着反馈', '穿着反馈', 'manage_woocommerce', 'sscn-wear-feedback', __NAMESPACE__ . '\\page');
});
add_action('admin_init', static function (): void { if (current_user_can('manage_woocommerce')) { install(); } });
add_action('admin_post_sscn_wear_save', static function (): void {
    try {
        permitted(); check_admin_referer('sscn_wear_save');
        $id = absint($_POST['order_id'] ?? 0);
        $action = sanitize_key($_POST['operation'] ?? '');
        if ($action === 'reward') {
            issue($id, (string) wp_unslash($_POST['amount'] ?? ''), !empty($_POST['verified']));
        } elseif ($action === 'entry') {
            $file = [];
            if (!empty($_FILES['attachment']['name'])) {
                $upload = $_FILES['attachment'];
                if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']) || filesize($upload['tmp_name']) > 5 * 1024 * 1024) { throw new \RuntimeException('附件上传失败或超过 5 MB。'); }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $file = ['data'=>file_get_contents($upload['tmp_name']), 'name'=>sanitize_file_name($upload['name']), 'mime'=>$mime];
            }
            register_entry($id, absint($_POST['item_id'] ?? 0), sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')),
                sanitize_key($_POST['state'] ?? ''), sanitize_text_field(wp_unslash($_POST['request_key'] ?? '')), $file);
        } else { throw new \RuntimeException('未知操作。'); }
        wp_safe_redirect(add_query_arg('saved', '1', url($id))); exit;
    } catch (\Throwable $e) { wp_die(esc_html($e->getMessage()), '反馈未保存', ['response'=>400, 'back_link'=>true]); }
});
add_action('admin_post_sscn_wear_attachment', static function (): void {
    if (!current_user_can('manage_woocommerce')) { wp_die('无权下载反馈附件。', '', ['response'=>403]); }
    $id = absint($_GET['entry'] ?? 0); check_admin_referer('sscn_wear_file_' . $id);
    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare('SELECT attachment, attachment_name, attachment_mime FROM ' . table('entries') . ' WHERE id=%d', $id));
    if (!$r || !$r->attachment) { wp_die('附件不存在。', '', ['response'=>404]); }
    nocache_headers(); header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($r->attachment_name));
    header('Content-Length: ' . strlen($r->attachment)); echo $r->attachment; exit;
});
function page(): void {
    permitted(); install(); global $wpdb;
    $id = absint($_GET['order_id'] ?? 0); $order = null;
    echo '<div class="wrap"><h1>穿着反馈</h1><p>收到邮件 → 查看反馈 → 按订单登记 → 人工确认发券。每个订单最多奖励一次，可继续追加使用记录。附件仅后台可下载。</p>';
    if (!empty($_GET['saved'])) { echo '<div class="notice notice-success"><p>已保存。发券后请复制优惠券，通过原提交渠道回复顾客；系统不会自动发送邮件。</p></div>'; }
    echo '<form method="get"><input type="hidden" name="page" value="sscn-wear-feedback"><label for="wear-order">订单号</label> <input id="wear-order" name="order_id" type="number" min="1" required value="' . esc_attr($id ?: '') . '"> <button class="button">查看订单及反馈</button></form>';
    if ($id) {
        try { $order = order($id); } catch (\Throwable $e) { echo '<p>' . esc_html($e->getMessage()) . '</p>'; }
    }
    if ($order) {
        $r = record($id);
        echo '<h2>订单 #' . esc_html($order->get_order_number()) . '</h2><p><a href="' . esc_url($order->get_edit_order_url()) . '">查看订单</a> · ' . esc_html(wc_get_order_status_name($order->get_status())) . ' · 下单邮箱：' . esc_html($order->get_billing_email()) . '</p><ul>';
        foreach ($order->get_items() as $item) { echo '<li>' . esc_html($item->get_name()) . ' × ' . esc_html($item->get_quantity()) . '</li>'; }
        echo '</ul><p>反馈状态：<strong>' . esc_html($r ? (labels()[$r->state] ?? $r->state) : '尚未登记') . '</strong></p>';
        if ($r && $r->state === 'rewarded') {
            $coupon = new \WC_Coupon((int) $r->coupon_id);
            echo '<div class="notice notice-success inline"><p>已奖励 ¥' . esc_html($r->amount) . '，优惠券：<code>' . esc_html($r->coupon_code) . '</code>，限下单邮箱 ' . esc_html($r->reward_email) . ' 使用。已使用 ' . esc_html($coupon->get_usage_count()) . ' 次。</p><p>请人工回复顾客。即使优惠券被删除或撤销，也不会自动再次发券。</p></div>';
        }
        echo '<h2>登记／追加反馈</h2><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        fields($id); echo '<input type="hidden" name="operation" value="entry"><input type="hidden" name="request_key" value="' . esc_attr(wp_generate_uuid4()) . '">';
        echo '<p><label>对应商品 <select name="item_id"><option value="0">订单整体／多款商品</option>';
        foreach ($order->get_items() as $item_id => $item) { echo '<option value="' . esc_attr($item_id) . '">' . esc_html($item->get_name()) . '</option>'; }
        echo '</select></label></p><p><label for="wear-note">反馈摘要／补充记录（可注明邮件日期、主题及已回复情况）</label><br><textarea id="wear-note" name="note" rows="6" cols="90" required maxlength="6000"></textarea></p><p><label>附件（可选，PDF/JPG/PNG，最多 5 MB） <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label></p><p><label>审核状态 <select name="state" ' . ($r && $r->coupon_code ? 'disabled' : '') . '>';
        if ($r && $r->coupon_code) { echo '<option selected>' . esc_html(labels()[$r->state]) . '</option>'; }
        foreach (['pending','needs_info','declined'] as $state) { echo '<option value="' . esc_attr($state) . '" ' . selected($r->state ?? 'pending', $state, false) . '>' . esc_html(labels()[$state]) . '</option>'; }
        echo '</select></label>' . ($r && $r->coupon_code ? '<input type="hidden" name="state" value="pending">' : '') . ' 已发券的记录追加时保留原奖励状态。</p><button class="button button-primary">保存反馈</button></form>';
        if ($r && $r->state !== 'rewarded') {
            echo '<h2>人工确认奖励</h2><p>¥2–¥10，无最低消费，无固定到期日，全部商品可用；限下单邮箱，单次使用，不兑换现金、不找零。可与允许叠加的优惠同用。</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; fields($id);
            echo '<input type="hidden" name="operation" value="reward"><p><label>金额 ¥ <input name="amount" type="number" min="2" max="10" step="0.01" required value="' . esc_attr($r->amount ?? '') . '" ' . ($r->coupon_code ? 'readonly' : '') . '></label></p><p><label><input name="verified" type="checkbox" value="1" required> 已核对提交人与订单的关系，并确认是实际穿着的有效反馈；金额与好评或差评无关。</label></p><button class="button button-primary">' . ($r->coupon_code ? '恢复原优惠券（不重新发券）' : '确认生成一次性优惠券') . '</button></form>';
        }
        $entries = $wpdb->get_results($wpdb->prepare('SELECT id, actor_id, item_id, note, attachment_name, created_at FROM ' . table('entries') . ' WHERE order_id=%d ORDER BY id DESC', $id));
        echo '<h2>历史记录</h2>';
        foreach ($entries as $e) {
            $actor = get_userdata($e->actor_id);
            $entry_item = $e->item_id ? $order->get_item($e->item_id) : null;
            echo '<article style="border-top:1px solid #ccc;padding:12px 0"><strong>' . esc_html(get_date_from_gmt($e->created_at)) . ' · ' . esc_html($actor ? $actor->display_name : '已删除的工作人员') . '</strong><p>' . esc_html($entry_item ? $entry_item->get_name() : '订单整体／多款商品') . '</p><p>' . nl2br(esc_html($e->note)) . '</p>';
            if ($e->attachment_name) { echo '<a href="' . esc_url(wp_nonce_url(add_query_arg(['action'=>'sscn_wear_attachment','entry'=>$e->id], admin_url('admin-post.php')), 'sscn_wear_file_' . $e->id)) . '">下载附件：' . esc_html($e->attachment_name) . '</a>'; }
            echo '</article>';
        }
    } else {
        $page = max(1, absint($_GET['paged'] ?? 1));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT order_id,state,amount,updated_at FROM ' . table('records') . ' ORDER BY updated_at DESC LIMIT 30 OFFSET %d', ($page-1)*30));
        echo '<h2>反馈登记</h2><table class="widefat striped"><thead><tr><th>订单</th><th>状态</th><th>奖励</th><th>最近更新</th></tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr><td><a href="' . esc_url(url($row->order_id)) . '">#' . esc_html($row->order_id) . '</a></td><td>' . esc_html(labels()[$row->state] ?? $row->state) . '</td><td>' . esc_html($row->amount ? '¥'.$row->amount : '—') . '</td><td>' . esc_html(get_date_from_gmt($row->updated_at)) . '</td></tr>'; }
        echo '</tbody></table><p>';
        if ($page > 1) { echo '<a href="' . esc_url(add_query_arg('paged',$page-1,url())) . '">上一页</a> '; }
        if (count($rows) === 30) { echo '<a href="' . esc_url(add_query_arg('paged',$page+1,url())) . '">下一页</a>'; }
        echo '</p>';
    }
    echo '</div>';
}
function fields(int $id): void {
    wp_nonce_field('sscn_wear_save');
    echo '<input type="hidden" name="action" value="sscn_wear_save"><input type="hidden" name="order_id" value="' . esc_attr($id) . '">';
}

<?php
include '../_base.php';

auth('Admin');

$id = req('id');
$stm = $_db->prepare('SELECT * FROM audit_log WHERE id = ?');
$stm->execute([$id]);
$a = $stm->fetch();

if (!$a) {
    redirect('audit_log.php');
}

$before = [];
$after  = [];
if (!empty($a->before_data)) {
    $decoded = json_decode($a->before_data, true);
    if (is_array($decoded)) $before = $decoded;
}
if (!empty($a->after_data)) {
    $decoded = json_decode($a->after_data, true);
    if (is_array($decoded)) $after = $decoded;
}

$keys = array_unique(array_merge(array_keys($before), array_keys($after)));

$before_voucher_type = null;
$after_voucher_type  = null;

if ($a->module === 'Vouchers') {
    if (isset($before['type'])) {
        $before_voucher_type = $before['type'];
    }
    if (isset($after['type'])) {
        $after_voucher_type = $after['type'];
    }

    $voucher_code = null;
    if (preg_match('/(?:Created|Updated|Deleted|Toggled active status for) voucher:\s*([A-Za-z0-9_-]+)/i', $a->description, $matches)) {
        $voucher_code = $matches[1];
    }

    if ($voucher_code) {
        $stm_v = $_db->prepare('SELECT type FROM voucher WHERE code = ?');
        $stm_v->execute([$voucher_code]);
        $db_type = $stm_v->fetchColumn();
        if ($db_type) {
            if ($before_voucher_type === null && !empty($before)) {
                $before_voucher_type = $db_type;
            }
            if ($after_voucher_type === null && !empty($after)) {
                $after_voucher_type = $db_type;
            }
        }
    }
}

// Prefer a friendly subject label when present
$subject = $after['name']
    ?? $before['name']
    ?? $after['code']
    ?? $before['code']
    ?? $after['email']
    ?? $before['email']
    ?? $after['reward_name']
    ?? $before['reward_name']
    ?? null;

function audit_label($key) {
    $map = [
        'id' => 'ID',
        'user_id' => 'User ID',
        'email' => 'Email',
        'name' => 'Name',
        'role' => 'Role',
        'active' => 'Status',
        'photo' => 'Photo',
        'price' => 'Price',
        'stock' => 'Stock',
        'description' => 'Description',
        'origin' => 'Origin',
        'roast' => 'Roast',
        'tag' => 'Tag',
        'sale_price' => 'Sale Price',
        'sale_start' => 'Sale Start',
        'sale_end' => 'Sale End',
        'points' => 'Points',
        'sort_order' => 'Display Order',
        'code' => 'Code',
        'type' => 'Type',
        'value' => 'Value',
        'min_spend' => 'Min Spend',
        'start_date' => 'Start Date',
        'expiry' => 'Expiry',
        'max_usage' => 'Max Usage',
        'usage_count' => 'Usage Count',
        'order_id' => 'Order ID',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'total' => 'Total',
        'voucher_code' => 'Voucher Code',
        'points_before' => 'Points Before',
        'points_after' => 'Points After',
        'points_changed' => 'Points Changed',
        'points_used' => 'Points Used',
        'points_earned' => 'Points Earned',
        'reward_id' => 'Reward ID',
        'reward_name' => 'Reward Name',
        'reward_stock' => 'Reward Stock',
        'reason' => 'Reason',
    ];
    return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function audit_value($key, $value, $voucher_type = null) {
    if ($value === null || $value === '') {
        return '-';
    }

    if ($key === 'active') {
        return ((int) $value) ? 'Active' : 'Disabled';
    }

    $money_keys = ['price', 'sale_price', 'value', 'min_spend', 'subtotal', 'discount', 'total'];
    if (in_array($key, $money_keys, true) && is_numeric($value)) {
        if ($key === 'value' && $voucher_type === 'percent') {
            return (float) $value . '%';
        }
        return 'RM ' . sprintf('%.2f', $value);
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return (string) $value;
}

$back_qs = http_build_query([
    'search' => req('search'),
    'user'   => req('user'),
    'role'   => req('role'),
    'module' => req('module'),
    'action' => req('action'),
    'from'   => req('from'),
    'to'     => req('to'),
    'sort'   => req('sort'),
    'dir'    => req('dir'),
    'page'   => req('page'),
]);

$_breadcrumbs = [
    'Dashboard' => '/',
    'Audit Log' => 'audit_log.php' . ($back_qs ? "?$back_qs" : ''),
    'Audit Detail' => '',
];
$_title = 'Admin | Audit Detail';
include '../_head.php';
?>

<section class="audit-detail">
    <div class="audit-detail-head">
        <div>
            <h2><?= encode($a->username ?: 'Unknown user') ?></h2>
            <p class="audit-meta">
                <span class="badge-status <?= $a->role == 'Admin' ? 'process' : ($a->role == 'Member' ? 'success' : 'neutral') ?>">
                    <?= encode($a->role ?: 'Guest') ?>
                </span>
                <span><?= encode($a->module) ?> → <?= encode($a->action) ?></span>
            </p>
            <?php if ($subject): ?>
                <p class="audit-subject"><?= encode($subject) ?></p>
            <?php endif ?>
        </div>
        <div class="audit-when">
            <div><?= !empty($a->created_at) ? date('d/m/Y H:i', strtotime($a->created_at)) : '-' ?></div>
            <div class="muted">IP: <?= encode($a->ip_address ?: '-') ?></div>
            <?php if (!empty($a->user_id)): ?>
                <div class="muted">User ID: <?= (int) $a->user_id ?></div>
            <?php endif ?>
        </div>
    </div>

    <?php if (!empty($a->description)): ?>
        <p class="audit-description"><?= encode($a->description) ?></p>
    <?php endif ?>

    <?php if (empty($keys)): ?>
        <div class="empty-state" style="margin-top:18px;">
            <span class="emoji">🔎</span>
            <p class="title">No before/after change data available for this audit record.</p>
        </div>
    <?php else: ?>
        <table class="table detail audit-changes">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Before</th>
                    <th>After</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keys as $key):
                    $b = array_key_exists($key, $before) ? $before[$key] : null;
                    $c = array_key_exists($key, $after) ? $after[$key] : null;
                    $changed = (string) $b !== (string) $c;
                ?>
                <tr class="<?= $changed ? 'is-changed' : '' ?>">
                    <th><?= encode(audit_label($key)) ?></th>
                    <td class="audit-before"><?= encode(audit_value($key, $b, $before_voucher_type)) ?></td>
                    <td class="audit-after"><?= encode(audit_value($key, $c, $after_voucher_type)) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<p>
    <button data-get="audit_log.php<?= $back_qs ? '?' . encode($back_qs) : '' ?>">Back to Audit Log</button>
</p>

<?php include '../_foot.php';

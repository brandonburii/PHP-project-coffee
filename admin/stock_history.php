<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');
// Get sorting, searching and pagination parameters for user orders
$fields = [
    'id'       => 'ID',
    'datetime' => 'Timestamp',
    'items'    => 'Items',
    'total'    => 'Total',
    'status'   => 'Status',
    'username' => 'User',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$status = req('status');

// Query user orders
$params = [];
$query = "SELECT o.*, u.name AS username, (SELECT COUNT(*) FROM item it WHERE it.order_id = o.id) AS items FROM `order` o LEFT JOIN user u ON o.user_id = u.id WHERE 1=1";
if ($search != '') {
    $query .= ' AND (o.id LIKE ? OR u.name LIKE ? OR o.voucher_code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status != '' && in_array($status, ['completed','cancelled','refunded','pending'])) {
    $query .= ' AND o.status = ?';
    $params[] = $status;
}
$query .= " ORDER BY o.$sort $dir";

$limit = 10;
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Order History' => '',
];
$_title = 'Admin | Order History';
include '../_head.php';
?>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Order ID, user, or voucher"') ?>

    <label for="status">Status:</label>
    <?= html_select('status', [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded'  => 'Refunded',
    ], '- All -') ?>

    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📦</span>
        <p class="title">No orders found</p>
        <p class="hint">Orders placed by users will appear here.</p>
    </div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search) . '&status=' . urlencode($status)); ?>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($arr as $h): ?>
            <tr>
                <td><?= $h->id ?></td>
                <td><?= $h->datetime ?></td>
                <td class="right"><?= $h->items ?></td>
                <td class="right">RM <?= sprintf('%.2f', $h->total) ?></td>
                <td>
                    <?php if ($h->status == 'cancelled'): ?>
                        <span class="badge-status danger">Cancelled</span>
                    <?php elseif ($h->status == 'refunded'): ?>
                        <span class="badge-status neutral">Refunded</span>
                    <?php else: ?>
                        <span class="badge-status success"><?= encode(ucfirst($h->status)) ?></span>
                    <?php endif ?>
                </td>
                <td><?= encode($h->username ?? 'Guest') ?></td>
                <td><a href="/order/detail.php?id=<?= urlencode($h->id) ?>">Details</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&status=' . urlencode($status)); ?>

<?php include '../_foot.php';

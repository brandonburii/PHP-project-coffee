<?php
include '../_base.php';

auth('Admin');

$sort = req('sort', 'id');
$dir  = req('dir', 'desc');
$valid = ['id','datetime','created_by','status','received_at'];
if (!in_array($sort, $valid)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');

$query = 'SELECT so.*, u.name AS creator, ru.name AS receiver FROM stock_order so LEFT JOIN user u ON so.created_by = u.id LEFT JOIN user ru ON so.received_by = ru.id WHERE 1=1';
$params = [];
if ($search) {
    $query .= ' AND (so.id LIKE ? OR u.name LIKE ? OR ru.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY so.$sort $dir";

$limit = 10;
$page = req('page', 1);
require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$orders = $pager->result;

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => ''];
$_title = 'Admin | Stock Orders';
include '../_head.php';
?>

<p><a href="stock_order_create.php">Create Stock Order</a></p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Order ID or user"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s)</p>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <p class="title">No stock orders</p>
    </div>
<?php else: ?>
<table class="table">
    <tr>
        <th>ID</th>
        <th>Created</th>
        <th>Creator</th>
        <th>Status</th>
        <th>Received At</th>
        <th>Received By</th>
        <th></th>
    </tr>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <td><?= $o->datetime ?></td>
        <td><?= encode($o->creator ?? '') ?></td>
        <td>
            <?php if ($o->status == 'received'): ?>
                <span class="badge-status success">Received</span>
            <?php else: ?>
                <span class="badge-status neutral">Pending</span>
            <?php endif ?>
        </td>
        <td><?= $o->received_at ?></td>
        <td><?= encode($o->receiver ?? '') ?></td>
        <td><a href="stock_order_view.php?id=<?= urlencode($o->id) ?>">View</a></td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif; ?>

<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search)); ?>

<?php include '../_foot.php';

<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// Get sorting, searching and pagination parameters
$fields = [
    'id'         => 'ID',
    'created_at' => 'Timestamp',
    'product_id' => 'Product',
    'action'     => 'Action',
    'old_stock'  => 'Old',
    'new_stock'  => 'New',
    'change_qty' => 'Change',
    'username'   => 'User',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$action = req('action');

// Search and paging query
$params = [];
$query = '
    SELECT h.*, p.name AS product_name
    FROM stock_history h
    LEFT JOIN product p ON h.product_id = p.id
    WHERE 1=1
';
if ($search != '') {
    $query .= ' AND (h.product_id LIKE ? OR p.name LIKE ? OR h.username LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($action != '' && in_array($action, ['added', 'edited', 'sold'])) {
    $query .= ' AND h.action = ?';
    $params[] = $action;
}
$query .= " ORDER BY h.$sort $dir";

$limit = 10;
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Stock History' => '',
];
$_title = 'Admin | Stock History';
include '../_head.php';
?>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Product ID, name, or user"') ?>

    <label for="action">Action:</label>
    <?= html_select('action', [
        'added'  => 'Added',
        'edited' => 'Edited',
        'sold'   => 'Sold',
    ], '- All -') ?>

    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📦</span>
        <p class="title">No stock movements found</p>
        <p class="hint">Stock changes from create, edit, and sales will appear here.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search) . '&action=' . urlencode($action)); ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $h): ?>
        <tr>
            <td><?= $h->id ?></td>
            <td><?= $h->created_at ?></td>
            <td>
                <a href="product_edit.php?id=<?= urlencode($h->product_id) ?>">
                    <?= encode($h->product_id) ?>
                </a>
                <?php if ($h->product_name): ?>
                    <div style="color:var(--muted); font-size:.82rem;"><?= encode($h->product_name) ?></div>
                <?php endif ?>
            </td>
            <td>
                <?php if ($h->action == 'added'): ?>
                    <span class="badge-status success">Added</span>
                <?php elseif ($h->action == 'edited'): ?>
                    <span class="badge-status process">Edited</span>
                <?php else: ?>
                    <span class="badge-status danger">Sold</span>
                <?php endif ?>
            </td>
            <td class="right"><?= $h->old_stock ?></td>
            <td class="right"><?= $h->new_stock ?></td>
            <td class="right" style="color: <?= $h->change_qty < 0 ? 'var(--red)' : 'var(--green)' ?>; font-weight:600;">
                <?= $h->change_qty > 0 ? '+' : '' ?><?= $h->change_qty ?>
            </td>
            <td><?= $h->username ? encode($h->username) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&action=' . urlencode($action)); ?>

<?php
include '../_foot.php';

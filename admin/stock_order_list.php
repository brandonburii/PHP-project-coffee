<?php
include '../_base.php';

auth('Admin');
ensure_stock_order_columns();

// ----------------------------------------------------------------------------
// Filter / Sort / Paging
// ----------------------------------------------------------------------------

$fields = [
    'id'          => 'PO ID',
    'datetime'    => 'Created',
    'creator'     => 'Created By',
    'supplier'    => 'Supplier',
    'total'       => 'Total (RM)',
    'status'      => 'Status',
    'expected_at' => 'Expected Delivery',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$status = req('status');

$params = [];
$query = '
    SELECT so.*, u.name AS creator, ru.name AS receiver,
           (SELECT COALESCE(SUM(soi.qty * COALESCE(soi.price, 0)), 0)
            FROM stock_order_item soi WHERE soi.stock_order_id = so.id) AS total
    FROM stock_order so
    LEFT JOIN user u ON so.created_by = u.id
    LEFT JOIN user ru ON so.received_by = ru.id
    WHERE 1=1
';

if ($search != '') {
    $query .= ' AND (so.id LIKE ? OR u.name LIKE ? OR ru.name LIKE ? OR so.supplier LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status != '' && in_array($status, ['pending', 'received'])) {
    $query .= ' AND so.status = ?';
    $params[] = $status;
}

// Map creator sort to u.name
$sortCol = ($sort == 'creator') ? 'u.name' : "so.$sort";
$query .= " ORDER BY $sortCol $dir";

$limit = 10;
$page = req('page', 1);
require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$orders = $pager->result;

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => ''];
$_title = 'Admin | Stock Orders (Purchase Orders)';
include '../_head.php';
?>

<p style="color:var(--muted);">
    These are <b>internal purchase orders</b> raised by admins to restock inventory from suppliers —
    they are separate from member purchase orders (see Order Management).
</p>

<p><button data-get="stock_order_create.php">Create Stock Order</button></p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="PO ID, user, or supplier"') ?>

    <label for="status">Status:</label>
    <?= html_select('status', [
        'pending'  => 'Pending',
        'received' => 'Received',
    ], '- All -') ?>

    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s)</p>

<?php if (empty($orders)): ?>
    <div class="empty-state">
        <span class="emoji">📦</span>
        <p class="title">No stock orders</p>
        <p class="hint">Create a purchase order to restock inventory from a supplier.</p>
    </div>
<?php else: ?>
<table class="table">
    <tr>
        <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search) . '&status=' . urlencode($status)); ?>
        <th>Received By</th>
        <th></th>
    </tr>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td>PO-<?= str_pad($o->id, 4, '0', STR_PAD_LEFT) ?></td>
        <td><?= $o->datetime ?></td>
        <td><?= encode($o->creator ?? '') ?></td>
        <td><?= encode($o->supplier ?? '—') ?></td>
        <td class="right">RM <?= sprintf('%.2f', $o->total) ?></td>
        <td>
            <?php if ($o->status == 'received'): ?>
                <span class="badge-status success">Received</span>
            <?php else: ?>
                <span class="badge-status neutral">Pending</span>
            <?php endif ?>
        </td>
        <td>
            <?= $o->expected_at ? date('Y-m-d H:i', strtotime($o->expected_at)) : '—' ?>
            <?php if ($o->status != 'received' && $o->expected_at && strtotime($o->expected_at) < time()): ?>
                <span class="badge-status danger">Overdue</span>
            <?php endif ?>
        </td>
        <td><?= encode($o->receiver ?? '—') ?></td>
        <td><button data-get="stock_order_view.php?id=<?= urlencode($o->id) ?>">View</button></td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif; ?>

<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&status=' . urlencode($status)); ?>

<?php include '../_foot.php';
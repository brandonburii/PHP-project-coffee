<?php
include '../_base.php';

auth('Admin');

// List products similar to reports; allow quick 'Order Stock' action per product
$sort = req('sort', 'name');
$dir  = req('dir', 'asc');
$valid = ['id','name','stock','price'];
if (!in_array($sort, $valid)) $sort = 'name';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

$query = 'SELECT p.* FROM product p WHERE 1=1';
$params = [];
if ($search) {
    $query .= ' AND (p.id LIKE ? OR p.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY p.$sort $dir";

$limit = 20;
$page = req('page', 1);
require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$products = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Order Stock' => '',
];
$_title = 'Admin | Order Stock';
include '../_head.php';
?>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Product ID or name"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> product(s)</p>

<?php if (empty($products)): ?>
    <div class="empty-state">
        <p class="title">No products found</p>
    </div>
<?php else: ?>
    <table class="table">
        <tr>
            <th>Product ID</th>
            <th>Name</th>
            <th>Stock</th>
            <th>Price</th>
            <th></th>
        </tr>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= encode($p->id) ?></td>
            <td><?= encode($p->name) ?></td>
            <td class="right"><?= $p->stock ?></td>
            <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
            <td><a href="stock_order_create.php?product_id=<?= urlencode($p->id) ?>">Order Stock</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search)); ?>

<?php include '../_foot.php';

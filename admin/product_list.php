<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// Get sorting, searching and pagination parameters
$fields = [
    'id'    => 'ID',
    'name'  => 'Name',
    'price' => 'Price (RM)',
    'stock' => 'Stock',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

// Search and paging query
$params = [];
$query = "SELECT * FROM product WHERE 1=1";
if ($search != '') {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

$limit = 5; // Items per page
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Product Maintenance' => '',
];
$_title = 'Admin | Product Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="product_create.php">Create New Product</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search name or description"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📦</span>
        <p class="title">No products found</p>
        <p class="hint">Try another keyword, or add a new product.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>Image</th>
            <?php table_headers($fields, $sort, $dir, "search=" . urlencode($search)); ?>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $p): ?>
        <tr>
            <td>
                <img src="/photos/<?= $p->photo ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ccc;">
            </td>
            <td><?= encode($p->id) ?></td>
            <td><?= encode($p->name) ?></td>
            <td class="right"><?= sprintf('%.2f', $p->price) ?></td>
            <td class="right"><?= $p->stock ?></td>
            <td><?= encode($p->description) ?></td>
            <td>
                <button class="secondary" data-get="product_edit.php?id=<?= $p->id ?>">Edit</button>
                <button class="danger" data-post="product_delete.php?id=<?= $p->id ?>" data-confirm="Delete this product?&#10;This action cannot be undone.">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php
include '../_foot.php';

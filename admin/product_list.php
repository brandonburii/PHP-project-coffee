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

$per_page = req('per_page', '10');
$allowed_per_page = ['10', '20', '50', 'all'];
if (!in_array($per_page, $allowed_per_page, true)) {
    $per_page = '10';
}

$page = req('page', 1);
$pager = null;

if ($per_page === 'all') {
    $stm = $_db->prepare($query);
    $stm->execute($params);
    $arr = $stm->fetchAll();
    $item_count = count($arr);
}
else {
    require_once '../lib/SimplePager.php';
    $pager = new SimplePager($query, $params, (int) $per_page, $page);
    $arr = $pager->result;
    $item_count = $pager->item_count;
}

$list_params = "sort=$sort&dir=$dir&search=" . urlencode($search) . "&per_page=" . urlencode($per_page);

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
    <label for="per_page">Items per page:</label>
    <select name="per_page" id="per_page">
        <option value="10" <?= $per_page === '10' ? 'selected' : '' ?>>10</option>
        <option value="20" <?= $per_page === '20' ? 'selected' : '' ?>>20</option>
        <option value="50" <?= $per_page === '50' ? 'selected' : '' ?>>50</option>
        <option value="all" <?= $per_page === 'all' ? 'selected' : '' ?>>All</option>
    </select>
    <button>Search</button>
</form>

<p><?= $item_count ?> record(s) found.</p>

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
            <?php table_headers($fields, $sort, $dir, $list_params); ?>
            <th>Description</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $p): ?>
        <tr>
            <td>
                <img src="<?= photo_src($p->photo) ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ccc;">
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
<?php if ($pager): ?>
<?php $pager->html($list_params); ?>
<?php endif; ?>

<?php
include '../_foot.php';

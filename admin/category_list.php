<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// Ensure category table exists
$_db->exec("CREATE TABLE IF NOT EXISTS category (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$fields = [
    'id'   => 'ID',
    'name' => 'Name',
    'active' => 'Active',
    'sort_order' => 'Order',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

$params = [];
$query = "SELECT * FROM category WHERE 1=1";
if ($search != '') {
    $query .= " AND (name LIKE ? OR slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

$limit = 10;
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Category Maintenance' => '',
];
$_title = 'Admin | Category Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="category_create.php">Create New Category</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search name or slug"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🏷️</span>
        <p class="title">No categories found</p>
        <p class="hint">Create a new category to organise products.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php table_headers($fields, $sort, $dir, "search=" . urlencode($search)); ?>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $c): ?>
        <tr>
            <td><?= encode($c->id) ?></td>
            <td><?= encode($c->name) ?></td>
            <td><?= $c->active ? 'Yes' : 'No' ?></td>
            <td class="right"><?= encode($c->sort_order) ?></td>
            <td>
                <button data-get="category_edit.php?id=<?= $c->id ?>">Edit</button>
                <button class="danger" data-post="category_delete.php?id=<?= $c->id ?>" data-confirm="Delete this category?">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php include '../_foot.php';

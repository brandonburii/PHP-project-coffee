<?php
include '../_base.php';

auth('Admin');

$fields = [
    'id'    => 'ID',
    'email' => 'Email',
    'name'  => 'Name',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

$params = [];
$query = "SELECT * FROM user WHERE role = 'Admin'";
if ($search != '') {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
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
    'Admin Maintenance' => '',
];
$_title = 'Admin | Admin Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="member_register.php">Create New Admin</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search email or name"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🔑</span>
        <p class="title">No admins found</p>
        <p class="hint">Create a new admin account to manage the site.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>Photo</th>
            <?php table_headers($fields, $sort, $dir, "search=" . urlencode($search)); ?>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $m): ?>
        <tr>
            <td>
                <img src="/photos/<?= $m->photo ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;">
            </td>
            <td><?= $m->id ?></td>
            <td><?= encode($m->email) ?></td>
            <td><?= encode($m->name) ?></td>
            <td>
                <button data-get="member_detail.php?id=<?= $m->id ?>">Details</button>
                <button class="danger" data-post="admin_delete.php?id=<?= $m->id ?>" data-confirm="Delete this admin?">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php include '../_foot.php';

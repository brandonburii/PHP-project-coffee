<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

// Get sorting, searching and pagination parameters
$fields = [
    'id'    => 'ID',
    'email' => 'Email',
    'name'  => 'Name',
    'role'  => 'Role',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');

// Search and paging query
$params = [];
$query = "SELECT * FROM user WHERE 1=1";
if ($search != '') {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
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
    'Member Maintenance' => '',
];
$_title = 'Admin | Member Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="member_register.php">Register New Member</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search name or email"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">👥</span>
        <p class="title">No members found</p>
        <p class="hint">Try another keyword, or register a new member.</p>
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
                <img src="<?= photo_src($m->photo) ?>" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px;">
            </td>
            <td><?= $m->id ?></td>
            <td><?= encode($m->email) ?></td>
            <td><?= encode($m->name) ?></td>
            <td><?= encode($m->role) ?></td>
            <td>
                <button data-get="member_detail.php?id=<?= $m->id ?>">Details</button>
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

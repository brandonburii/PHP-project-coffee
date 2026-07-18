<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

audit('Admin', 'Viewed Audit Log', "Viewed audit log directory listing");

// Get sorting, searching and pagination parameters
$fields = [
    'id'         => 'ID',
    'created_at' => 'Timestamp',
    'module'     => 'Module',
    'action'     => 'Action',
    'username'   => 'User',
    'ip_address' => 'IP Address',
];

$sort = req('sort', 'id');
$dir  = req('dir', 'desc'); // Default to newest first
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');

// Search and paging query
$params = [];
$query = "SELECT * FROM audit_log WHERE 1=1";
if ($search != '') {
    $query .= " AND (action LIKE ? OR module LIKE ? OR description LIKE ? OR user_id LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

$limit = 10; // Items per page
$page = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = [
    'Dashboard' => '/',
    'Audit Log' => '',
];
$_title = 'Admin | Audit Log';
include '../_head.php';
?>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search action, module, desc, or user"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📜</span>
        <p class="title">No audit logs found</p>
        <p class="hint">Try another keyword or clear your search.</p>
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
        <?php foreach ($arr as $l): ?>
        <tr>
            <td><?= $l->id ?></td>
            <td><?= $l->created_at ?></td>
            <td><span class="badge-status neutral"><?= encode($l->module) ?></span></td>
            <td><?= encode($l->action) ?></td>
            <td>
                <?php if ($l->user_id): ?>
                    <?= encode($l->username) ?> (ID: <?= $l->user_id ?>)
                <?php else: ?>
                    Guest
                <?php endif; ?>
            </td>
            <td><span class="mono"><?= encode($l->ip_address) ?></span></td>
            <td>
                <button data-get="audit_detail.php?id=<?= $l->id ?>">Detail</button>
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

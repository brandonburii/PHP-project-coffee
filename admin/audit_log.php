<?php
include '../_base.php';

auth('Admin');

$fields = [
    'created_at' => 'Time',
    'username'   => 'User',
    'role'       => 'Role',
    'module'     => 'Module',
    'action'     => 'Action',
];

$sort = req('sort', 'created_at');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'created_at';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$user   = req('user');
$role   = req('role');
$module = req('module');
$action = req('action');
$from   = req('from');
$to     = req('to');

// Dropdown options from existing audit data
$user_items = [];
$stm = $_db->query("
    SELECT DISTINCT username
    FROM audit_log
    WHERE username IS NOT NULL AND username != ''
    ORDER BY username
");
foreach ($stm->fetchAll() as $row) {
    $user_items[$row->username] = $row->username;
}

$role_items = [];
$stm = $_db->query("
    SELECT DISTINCT role
    FROM audit_log
    WHERE role IS NOT NULL AND role != ''
    ORDER BY role
");
foreach ($stm->fetchAll() as $row) {
    $role_items[$row->role] = $row->role;
}

$module_items = [];
$stm = $_db->query("
    SELECT DISTINCT module
    FROM audit_log
    WHERE module IS NOT NULL AND module != ''
    ORDER BY module
");
foreach ($stm->fetchAll() as $row) {
    $module_items[$row->module] = $row->module;
}

$action_items = [];
$stm = $_db->query("
    SELECT DISTINCT action
    FROM audit_log
    WHERE action IS NOT NULL AND action != ''
    ORDER BY action
");
foreach ($stm->fetchAll() as $row) {
    $action_items[$row->action] = $row->action;
}

$params = [];
$query = 'SELECT * FROM audit_log WHERE 1=1';

if ($search != '') {
    $query .= ' AND (
        username LIKE ? OR role LIKE ? OR module LIKE ? OR action LIKE ?
        OR description LIKE ? OR ip_address LIKE ?
    )';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if ($user != '') {
    $query .= ' AND username = ?';
    $params[] = $user;
}

if ($role != '') {
    $query .= ' AND role = ?';
    $params[] = $role;
}

if ($module != '') {
    $query .= ' AND module = ?';
    $params[] = $module;
}

if ($action != '') {
    $query .= ' AND action = ?';
    $params[] = $action;
}

if ($from != '' && is_date($from)) {
    $query .= ' AND DATE(created_at) >= ?';
    $params[] = $from;
}

if ($to != '' && is_date($to)) {
    $query .= ' AND DATE(created_at) <= ?';
    $params[] = $to;
}

$query .= " ORDER BY $sort $dir, id $dir";

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, 10, req('page', 1));
$arr = $pager->result;

$qs = http_build_query([
    'search' => $search,
    'user'   => $user,
    'role'   => $role,
    'module' => $module,
    'action' => $action,
    'from'   => $from,
    'to'     => $to,
]);

$_breadcrumbs = [
    'Dashboard' => '/',
    'Audit Log' => '',
];
$_title = 'Admin | Audit Log';
include '../_head.php';
?>

<form method="get" class="search-form audit-filters">
    <label for="search">Search</label>
    <?= html_search('search', 'placeholder="User, module, action, description, IP"') ?>

    <label for="user">User</label>
    <?= html_select('user', $user_items, '- All Users -') ?>

    <label for="role">Role</label>
    <?= html_select('role', $role_items, '- All Roles -') ?>

    <label for="module">Module</label>
    <?= html_select('module', $module_items, '- All Modules -') ?>

    <label for="action">Action</label>
    <?= html_select('action', $action_items, '- All Actions -') ?>

    <label for="from">From</label>
    <?= html_date('from') ?>

    <label for="to">To</label>
    <?= html_date('to') ?>

    <section>
        <button>Search</button>
        <button type="button" class="secondary" data-get="audit_log.php">Reset</button>
    </section>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📜</span>
        <p class="title">No audit records found</p>
        <p class="hint">Try another keyword or clear the filters.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php table_headers($fields, $sort, $dir, $qs); ?>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $a): ?>
        <tr>
            <td><?= !empty($a->created_at) ? date('d/m/Y H:i', strtotime($a->created_at)) : '-' ?></td>
            <td><?= encode($a->username ?: '-') ?></td>
            <td>
                <?php if ($a->role == 'Admin'): ?>
                    <span class="badge-status process"><?= encode($a->role) ?></span>
                <?php elseif ($a->role == 'Member'): ?>
                    <span class="badge-status success"><?= encode($a->role) ?></span>
                <?php else: ?>
                    <span class="badge-status neutral"><?= encode($a->role ?: 'Guest') ?></span>
                <?php endif ?>
            </td>
            <td><?= encode($a->module) ?></td>
            <td><?= encode($a->action) ?></td>
            <td>
                <button data-get="audit_detail.php?id=<?= (int) $a->id ?><?= $qs != '' ? '&' . encode($qs) : '' ?>">Detail</button>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<br>
<?php $pager->html($qs . "&sort=$sort&dir=$dir"); ?>

<?php include '../_foot.php';

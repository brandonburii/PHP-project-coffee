<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $id  = req('id');
    $btn = req('btn');

    if ($id != '' && is_exists($id, 'user', 'id')) {
        if ($btn == 'toggle') {
            $stm = $_db->prepare('SELECT * FROM user WHERE id = ?');
            $stm->execute([$id]);
            $u = $stm->fetch();

            if ($u && $u->role == 'Admin') {
                if ($u->id == $_user->id && $u->active) {
                    temp('info', 'You cannot disable your own account');
                    redirect();
                }

                if ($u->active) {
                    $stm = $_db->query("SELECT COUNT(*) FROM user WHERE role = 'Admin' AND active = 1");
                    $active_admins = (int) $stm->fetchColumn();
                    if ($active_admins <= 1) {
                        temp('info', 'Cannot disable the last active Admin account');
                        redirect();
                    }
                }

                $before_active = (int) $u->active;
                $stm = $_db->prepare('UPDATE user SET active = 1 - active WHERE id = ?');
                $stm->execute([$id]);
                $after_active = 1 - $before_active;
                audit(
                    'Admin',
                    'Admin Status Toggled',
                    "Toggled admin ID $id status",
                    ['active' => $before_active],
                    ['active' => $after_active]
                );
                temp('info', 'Admin status updated');
            }
        }
    }
    redirect();
}

$fields = [
    'id'         => 'ID',
    'email'      => 'Email',
    'name'       => 'Name',
    'role'       => 'Role',
    'active'     => 'Status',
    'created_at' => 'Created',
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
    'Admin Management' => '',
];
$_title = 'Admin | Admin Management';
include '../_head.php';
?>

<p>
    <button data-get="admin_create.php">Create New Admin</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search admin name or email"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🛡️</span>
        <p class="title">No admin accounts found</p>
        <p class="hint">Create a new admin account to start managing administrators.</p>
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
        <?php foreach ($arr as $a): ?>
        <tr>
            <td><img src="/photos/<?= photo_url($a->photo) ?>" style="width:50px;height:50px;object-fit:cover;border:1px solid #ccc;border-radius:5px;"></td>
            <td><?= $a->id ?></td>
            <td><?= encode($a->email) ?></td>
            <td><?= encode($a->name) ?></td>
            <td><?= encode($a->role) ?></td>
            <td>
                <?php if ((int) $a->active): ?>
                    <span class="badge-status success">Active</span>
                <?php else: ?>
                    <span class="badge-status neutral">Disabled</span>
                <?php endif ?>
            </td>
            <td><?= !empty($a->created_at) ? $a->created_at : '-' ?></td>
            <td>
                <button data-get="admin_detail.php?id=<?= $a->id ?>">Details</button>
                <button class="secondary" data-get="admin_edit.php?id=<?= $a->id ?>">Edit</button>
                <?php if ($a->id != $_user->id): ?>
                    <button data-post="?btn=toggle&id=<?= $a->id ?>"><?= $a->active ? 'Disable' : 'Enable' ?></button>
                <?php else: ?>
                    <button type="button" disabled>Self</button>
                <?php endif ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php include '../_foot.php';

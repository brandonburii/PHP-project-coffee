<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $id = req('id');
    $btn = req('btn');

    if ($btn == 'toggle' && $id != '') {
        $stm = $_db->prepare('SELECT active FROM reward WHERE id = ?');
        $stm->execute([$id]);
        $before_active = (int) $stm->fetchColumn();

        $stm = $_db->prepare('UPDATE reward SET active = 1 - active WHERE id = ?');
        $stm->execute([$id]);
        audit(
            'Rewards',
            'Reward Toggled',
            "Toggled reward ID $id",
            ['active' => $before_active],
            ['active' => 1 - $before_active]
        );
        temp('info', 'Reward status updated');
        redirect();
    }
}

$fields = [
    'id'         => 'ID',
    'name'       => 'Name',
    'points'     => 'Points',
    'stock'      => 'Stock',
    'sort_order' => 'Order',
    'active'     => 'Status',
];

$sort = req('sort', 'sort_order');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'sort_order';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');
$params = [];
$query = 'SELECT * FROM reward WHERE 1=1';
if ($search != '') {
    $query .= ' AND (name LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, 5, req('page', 1));
$arr = $pager->result;

$_breadcrumbs = ['Dashboard' => '/', 'Reward Maintenance' => ''];
$_title = 'Admin | Reward Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="reward_create.php">Create New Reward</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search reward name"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🎁</span>
        <p class="title">No rewards found</p>
        <p class="hint">Create a reward item for members to redeem with points.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <th>Image</th>
            <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search)); ?>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $r): ?>
        <tr>
            <td><img src="<?= photo_src($r->photo) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--line);"></td>
            <td><?= $r->id ?></td>
            <td><?= encode($r->name) ?></td>
            <td class="right"><?= $r->points ?></td>
            <td class="right">
                <?= $r->stock ?>
                <?php if ($r->stock < 5): ?><span class="badge-status danger">Low</span><?php endif ?>
            </td>
            <td class="right"><?= $r->sort_order ?></td>
            <td>
                <?php if ($r->active): ?>
                    <span class="badge-status success">Active</span>
                <?php else: ?>
                    <span class="badge-status neutral">Disabled</span>
                <?php endif ?>
            </td>
            <td>
                <button class="secondary" data-get="reward_edit.php?id=<?= $r->id ?>">Edit</button>
                <button data-post="?btn=toggle&id=<?= $r->id ?>"><?= $r->active ? 'Disable' : 'Enable' ?></button>
                <button class="danger" data-post="reward_delete.php?id=<?= $r->id ?>" data-confirm="Delete this reward?&#10;This action cannot be undone.">Delete</button>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php include '../_foot.php';

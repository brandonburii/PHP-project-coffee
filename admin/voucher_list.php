<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $code = req('code');
    if ($code != '' && is_exists($code, 'voucher', 'code')) {
        $stm = $_db->prepare('UPDATE voucher SET active = 1 - active WHERE code = ?');
        $stm->execute([$code]);
        audit('Vouchers', 'Voucher Toggled', "Toggled active status for voucher: $code");
        temp('info', 'Voucher status updated');
    }
    redirect();
}

$fields = [
    'code'        => 'Code',
    'description' => 'Description',
    'type'        => 'Type',
    'value'       => 'Value',
    'min_spend'   => 'Min Spend',
    'expiry'      => 'Expiry',
    'usage_count' => 'Usage',
    'active'      => 'Status',
];

$sort = req('sort', 'code');
$dir  = req('dir', 'asc');
if (!array_key_exists($sort, $fields)) $sort = 'code';
if ($dir != 'asc' && $dir != 'desc') $dir = 'asc';

$search = req('search');
$params = [];
$query = 'SELECT * FROM voucher WHERE 1=1';
if ($search != '') {
    $query .= ' AND (code LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY $sort $dir";

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, 5, req('page', 1));
$arr = $pager->result;

$_breadcrumbs = ['Dashboard' => '/', 'Voucher Maintenance' => ''];
$_title = 'Admin | Voucher Maintenance';
include '../_head.php';
?>

<p>
    <button data-get="voucher_create.php">Create New Voucher</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search code or description"') ?>
    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🎟️</span>
        <p class="title">No vouchers found</p>
        <p class="hint">Try another keyword, or create a new voucher.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search)); ?>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $v):
            $expired = strtotime($v->expiry) < strtotime(date('Y-m-d'));
            $usage   = (int) ($v->usage_count ?? 0);
            $max     = $v->max_usage;
        ?>
        <tr>
            <td><span class="voucher-badge"><?= encode($v->code) ?></span></td>
            <td><?= encode($v->description ?? '') ?></td>
            <td><?= $v->type == 'percent' ? 'Percentage' : 'Fixed' ?></td>
            <td>
                <?= $v->type == 'percent'
                    ? rtrim(rtrim(sprintf('%.2f', $v->value), '0'), '.') . '%'
                    : 'RM ' . sprintf('%.2f', $v->value) ?>
            </td>
            <td class="right">RM <?= sprintf('%.2f', $v->min_spend ?? 0) ?></td>
            <td>
                <?= $v->expiry ?>
                <?php if ($expired): ?><span class="badge-status danger">Expired</span><?php endif ?>
            </td>
            <td class="right">
                <?= $usage ?><?= $max !== null && $max !== '' ? ' / ' . (int) $max : '' ?>
            </td>
            <td>
                <?php if ($v->active): ?>
                    <span class="badge-status success">Active</span>
                <?php else: ?>
                    <span class="badge-status neutral">Disabled</span>
                <?php endif ?>
            </td>
            <td>
                <button class="secondary" data-get="voucher_edit.php?code=<?= urlencode($v->code) ?>">Edit</button>
                <button data-post="?code=<?= urlencode($v->code) ?>"><?= $v->active ? 'Disable' : 'Enable' ?></button>
                <button class="danger" data-post="voucher_delete.php?code=<?= urlencode($v->code) ?>" data-confirm="Delete this voucher?&#10;This action cannot be undone.">Delete</button>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<br>
<?php $pager->html("sort=$sort&dir=$dir&search=" . urlencode($search)); ?>

<?php include '../_foot.php';

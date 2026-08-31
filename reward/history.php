<?php
include '../_base.php';
/////
auth('Member');

// ----------------------------------------------------------------------------
// Filter / Sort / Paging
// ----------------------------------------------------------------------------

$fields = [
    'reward_name' => 'Reward',
    'points'      => 'Points Used',
    'created_at'  => 'Date',
    'status'      => 'Status',
];

$sort = req('sort', 'created_at');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'created_at';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$status = req('status');

$params = [];
$query = '
    SELECT rr.*, r.name AS reward_name, r.photo, r.product_id
    FROM reward_redemption rr
    JOIN reward r ON r.id = rr.reward_id
    WHERE rr.user_id = ?
';
$params[] = $_user->id;

if ($search != '') {
    $query .= ' AND r.name LIKE ?';
    $params[] = "%$search%";
}
if ($status != '' && in_array($status, ['completed', 'pending', 'cancelled'])) {
    $query .= ' AND rr.status = ?';
    $params[] = $status;
}
$query .= " ORDER BY rr.$sort $dir";

$limit = 10;
$page  = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

$_breadcrumbs = ['Dashboard' => '/', 'Rewards' => 'list.php', 'My Reward History' => ''];
$_title = 'My Reward History';
include '../_head.php';
?>

<p>
    <button data-get="list.php">← Back to Rewards</button>
</p>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Search reward name"') ?>

    <label for="status">Status:</label>
    <?= html_select('status', [
        'completed' => 'Completed',
        'pending'   => 'Pending',
        'cancelled' => 'Cancelled',
    ], '- All -') ?>

    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">📜</span>
        <p class="title">No redemptions yet</p>
        <p class="hint">Redeem a reward with your points and it will show here.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search) . '&status=' . urlencode($status)); ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arr as $h):
            $reward_img = reward_photo_src((object) ['photo' => $h->photo, 'product_id' => $h->product_id ?? null]);
            $reward_placeholder = reward_photo_placeholder_src();
        ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= $reward_img ?>" alt="<?= encode($h->reward_name) ?>" onerror="this.onerror=null;this.src='<?= $reward_placeholder ?>';" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                    <?= encode($h->reward_name) ?>
                </div>
            </td>
            <td class="right"><?= number_format($h->points) ?></td>
            <td><?= date('d M Y, H:i', strtotime($h->created_at)) ?></td>
            <td><span class="badge-status success"><?= encode($h->status) ?></span></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&status=' . urlencode($status)); ?>

<?php include '../_foot.php';
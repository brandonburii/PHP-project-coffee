<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member/admin)
auth('Member', 'Admin');

// ----------------------------------------------------------------------------
// Filter / Sort / Paging
// ----------------------------------------------------------------------------

$isAdmin = $_user->role == 'Admin';

$fields = [
    'id'       => 'Id',
    'datetime' => 'Datetime',
    'count'    => 'Count',
    'total'    => 'Total (RM)',
    'status'   => 'Status',
];
if ($isAdmin) {
    // Insert member column after id
    $fields = array_merge(['id' => 'Id', 'user_name' => 'Member'], array_slice($fields, 1, null, true));
}

$sort = req('sort', 'id');
$dir  = req('dir', 'desc');
if (!array_key_exists($sort, $fields)) $sort = 'id';
if ($dir != 'asc' && $dir != 'desc') $dir = 'desc';

$search = req('search');
$status = req('status');

$params = [];
if ($isAdmin) {
    $query = '
        SELECT o.*, u.name as user_name
        FROM `order` o
        JOIN user u ON o.user_id = u.id
        WHERE 1=1
    ';
} else {
    $query = 'SELECT * FROM `order` WHERE user_id = ?';
    $params[] = $_user->id;
}

if ($search != '') {
    if ($isAdmin) {
        $query .= ' AND (o.id LIKE ? OR u.name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $query .= ' AND id LIKE ?';
        $params[] = "%$search%";
    }
}
if ($status != '' && in_array($status, ['completed', 'cancelled', 'refunded', 'pending'])) {
    $query .= ' AND status = ?';
    $params[] = $status;
}

// Map sort column to table alias (admin query uses o/u aliases; member query has none)
if ($isAdmin) {
    $sortCol = ($sort == 'user_name') ? 'u.name' : "o.$sort";
} else {
    $sortCol = $sort;
}
$query .= " ORDER BY $sortCol $dir";

$limit = 10;
$page  = req('page', 1);

require_once '../lib/SimplePager.php';
$pager = new SimplePager($query, $params, $limit, $page);
$arr = $pager->result;

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    ($isAdmin ? 'Order Management' : 'Order History') => '',
];
$_title = $isAdmin ? 'Order | All Orders' : 'Order | History';
include '../_head.php';
?>

<?php if ($isAdmin): ?>
<p>
    <button class="danger" data-post="reset.php" data-confirm="Reset all orders?&#10;This deletes every order and item. This action cannot be undone.">Reset Database Orders</button>
</p>
<?php endif ?>

<form method="get" class="search-form">
    <label for="search">Search:</label>
    <?= html_search('search', 'placeholder="Order ID' . ($isAdmin ? ' or member name' : '') . '"') ?>

    <label for="status">Status:</label>
    <?= html_select('status', [
        'pending'   => 'Pending Cancellation',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded'  => 'Refunded',
    ], '- All -') ?>

    <button>Search</button>
</form>

<p><?= $pager->item_count ?> record(s) found.</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🧾</span>
        <p class="title">No orders yet</p>
        <p class="hint"><?= $isAdmin ? 'Orders placed by members will appear here.' : 'Your placed orders will appear here.' ?></p>
        <?php if (!$isAdmin): ?>
            <button data-get="/product/list.php">Start Shopping</button>
        <?php endif ?>
    </div>
<?php else: ?>
<table class="table">
    <tr>
        <?php table_headers($fields, $sort, $dir, 'search=' . urlencode($search) . '&status=' . urlencode($status)); ?>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <?php if ($isAdmin): ?>
            <td><?= encode($o->user_name) ?></td>
        <?php endif ?>
        <td><?= $o->datetime ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
        <td>
            <?php $st = $o->status ?? 'completed'; ?>
            <?php if ($st === 'pending'): ?>
                <span class="badge-status process">Pending Cancellation</span>
            <?php elseif ($st === 'cancelled'): ?>
                <span class="badge-status danger">Cancelled</span>
            <?php elseif ($st === 'refunded'): ?>
                <span class="badge-status neutral">Refunded</span>
            <?php else: ?>
                <span class="badge-status success">Completed</span>
            <?php endif ?>
        </td>
        <td>
            <button data-get="detail.php?id=<?= $o->id ?>">Detail</button>
            <!-- (A) EXTRA: Product photos -->
            <?php
            $stm_photos = $_db->prepare('
                SELECT p.photo
                FROM item i
                JOIN product p ON i.product_id = p.id
                WHERE i.order_id = ?
            ');
            $stm_photos->execute([$o->id]);
            $photos = $stm_photos->fetchAll(PDO::FETCH_COLUMN);
            foreach ($photos as $photo):
                $img = photo_url($photo);
                $imgFolder = is_file(__DIR__ . '/../products/' . $img) ? '/products/' : '/photos/';
            ?>
                <img src="<?= $imgFolder . rawurlencode($img) ?>" style="width:40px; height:40px; border:1px solid #ccc; vertical-align:middle; margin-left:5px;">
            <?php endforeach ?>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>

<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&status=' . urlencode($status)); ?>

<?php
include '../_foot.php';
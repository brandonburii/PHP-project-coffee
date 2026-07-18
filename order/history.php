<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member/admin)
auth('Member', 'Admin');

// (2) Return orders
if ($_user->role == 'Admin') {
    $stm = $_db->prepare('
        SELECT o.*, u.name as user_name
        FROM `order` o
        JOIN user u ON o.user_id = u.id
        ORDER BY o.id DESC
    ');
    $stm->execute();
} else {
    $stm = $_db->prepare('SELECT * FROM `order` WHERE user_id = ? ORDER BY id DESC');
    $stm->execute([$_user->id]);
}
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    ($_user->role == 'Admin' ? 'Order Management' : 'Order History') => '',
];
$_title = $_user->role == 'Admin' ? 'Order | All Orders' : 'Order | History';
include '../_head.php';
?>

<!-- (B) EXTRA: CSS -->
<!-- TODO -->

<?php if ($_user->role == 'Admin'): ?>
<p>
    <button class="danger" data-post="reset.php" data-confirm="Reset all orders?&#10;This deletes every order and item. This action cannot be undone.">Reset Database Orders</button>
</p>
<?php endif ?>

<p><?= count($arr) ?> record(s)</p>

<?php if (empty($arr)): ?>
    <div class="empty-state">
        <span class="emoji">🧾</span>
        <p class="title">No orders yet</p>
        <p class="hint"><?= $_user->role == 'Admin' ? 'Orders placed by members will appear here.' : 'Your placed orders will appear here.' ?></p>
        <?php if ($_user->role != 'Admin'): ?>
            <button data-get="/product/list.php">Start Shopping</button>
        <?php endif ?>
    </div>
<?php else: ?>
<table class="table">
    <tr>
        <th>Id</th>
        <?php if ($_user->role == 'Admin'): ?>
            <th>Member</th>
        <?php endif ?>
        <th>Datetime</th>
        <th>Count</th>
        <th>Total (RM)</th>
        <th></th>
    </tr>

    <?php foreach ($arr as $o): ?>
    <tr>
        <td><?= $o->id ?></td>
        <?php if ($_user->role == 'Admin'): ?>
            <td><?= encode($o->user_name) ?></td>
        <?php endif ?>
        <td><?= $o->datetime ?></td>
        <td class="right"><?= $o->count ?></td>
        <td class="right"><?= $o->total ?></td>
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
            ?>
                <img src="/photos/<?= $photo ?>" style="width:40px; height:40px; border:1px solid #ccc; vertical-align:middle; margin-left:5px;">
            <?php endforeach ?>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>

<?php
include '../_foot.php';
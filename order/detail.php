<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member/admin)
auth('Member', 'Admin');

// (2) Return order (based on id) belong to the user
$id = req('id');
if ($_user->role == 'Admin') {
    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ?');
    $stm->execute([$id]);
} else {
    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ? AND user_id = ?');
    $stm->execute([$id, $_user->id]);
}
$o = $stm->fetch();
if (!$o) {
    if ($_user->role == 'Admin') {
        redirect('/');
    } else {
        redirect('history.php');
    }
}

audit('Orders', 'Viewed order details', "Viewed details for order ID: $id");

// (3) Return items (and products) belong to the order
$stm = $_db->prepare('
    SELECT i.*, p.name, p.photo
    FROM item i
    JOIN product p ON i.product_id = p.id
    WHERE i.order_id = ?
');
$stm->execute([$o->id]);
$arr = $stm->fetchAll();

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    ($_user->role == 'Admin' ? 'Order Management' : 'Order History') => 'history.php',
    'Order Detail' => '',
];
$_title = 'Order | Detail';
include '../_head.php';
?>

<style>
    .popup {
        width: 100px;
        height: 100px;
    }
</style>

<form class="form">
    <label>Order Id</label>
    <b><?= $o->id ?></b>
    <br>

    <label>Datetime</label>
    <div><?= $o->datetime ?></div>
    <br>

    <label>Count</label>
    <div><?= $o->count ?></div>
    <br>

    <label>Total</label>
    <div>RM <?= $o->total ?></div>
    <br>
</form>

<p><?= count($arr) ?> item(s)</p>

<table class="table">
    <tr>
        <th>Product Id</th>
        <th>Product Name</th>
        <th>Price (RM)</th>
        <th>Unit</th>
        <th>Subtotal (RM)</th>
    </tr>

    <?php foreach ($arr as $i): ?>
    <tr>
        <td><?= $i->product_id ?></td>
        <td><?= $i->name ?></td>
        <td class="right"><?= $i->price ?></td>
        <td class="right"><?= $i->unit ?></td>
        <td class="right">
            <?= $i->subtotal ?>
            <img src="/photos/<?= $i->photo ?>" class="popup">
        </td>
    </tr>
    <?php endforeach ?>

    <tr>
        <th colspan="3"></th>
        <th class="right"><?= $o->count ?></th>
        <th class="right"><?= $o->total ?></th>
    </tr>
</table>

<p>
    <button data-get="history.php">History</button>
</p>

<?php
include '../_foot.php';
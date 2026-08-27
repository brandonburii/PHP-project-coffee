<?php
include '../_base.php';

auth('Admin');

$id = req('id');
if (!$id) redirect('stock_order_list.php');

$stm = $_db->prepare('SELECT so.*, u.name AS creator, ru.name AS receiver FROM stock_order so LEFT JOIN user u ON so.created_by = u.id LEFT JOIN user ru ON so.received_by = ru.id WHERE so.id = ?');
$stm->execute([$id]);
$o = $stm->fetch();
if (!$o) redirect('stock_order_list.php');

// Handle receive
if (is_post() && req('action') === 'receive') {
    $res = receive_stock_order($o->id, $_user->id);
    if ($res['ok']) {
        flash('Stock order received');
    } else {
        flash('Failed to receive: ' . ($res['error'] ?? 'unknown'));
    }
    redirect('stock_order_view.php?id=' . $o->id);
}

$istm = $_db->prepare('SELECT soi.*, p.name AS product_name FROM stock_order_item soi LEFT JOIN product p ON soi.product_id = p.id WHERE soi.stock_order_id = ?');
$istm->execute([$o->id]);
$items = $istm->fetchAll();

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => 'stock_order_list.php', 'View' => ''];
$_title = 'Admin | Stock Order';
include '../_head.php';
?>

<form class="form">
    <label>Order ID</label>
    <div><?= $o->id ?></div>

    <label>Created</label>
    <div><?= $o->datetime ?> by <?= encode($o->creator ?? '') ?></div>

    <label>Status</label>
    <div><?= encode($o->status) ?><?php if ($o->status == 'received') echo ' at ' . $o->received_at . ' by ' . encode($o->receiver ?? '') ?></div>

    <?php if (!empty($o->note)): ?>
        <label>Note</label>
        <div><?= encode($o->note) ?></div>
    <?php endif ?>
</form>

<table class="table" style="margin-top:12px;">
    <tr><th>Product</th><th>Qty</th><th>Price</th><th>Current Stock</th></tr>
    <?php foreach ($items as $it): ?>
        <?php $pst = $_db->prepare('SELECT stock FROM product WHERE id = ?'); $pst->execute([$it->product_id]); $cur = $pst->fetchColumn(); ?>
        <tr>
            <td><?= encode($it->product_name ?? $it->product_id) ?> (<?= encode($it->product_id) ?>)</td>
            <td class="right"><?= $it->qty ?></td>
            <td class="right"><?= $it->price ?></td>
            <td class="right"><?= $cur ?></td>
        </tr>
    <?php endforeach ?>
</table>

<?php if ($o->status !== 'received'): ?>
    <form method="post" onsubmit="return confirm('Mark as received and update stock?');">
        <input type="hidden" name="action" value="receive">
        <button type="submit">Mark Received</button>
        <button type="button" data-get="stock_order_list.php">Back</button>
    </form>
<?php else: ?>
    <p><button type="button" data-get="stock_order_list.php">Back</button></p>
<?php endif ?>

<?php include '../_foot.php';

<?php
include '../_base.php';

auth('Admin');
ensure_stock_order_columns();

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
        flash('Stock order received — inventory updated');
    } else {
        flash('Failed to receive: ' . ($res['error'] ?? 'unknown'));
    }
    redirect('stock_order_view.php?id=' . $o->id);
}

$istm = $_db->prepare('SELECT soi.*, p.name AS product_name, p.stock AS current_stock FROM stock_order_item soi LEFT JOIN product p ON soi.product_id = p.id WHERE soi.stock_order_id = ?');
$istm->execute([$o->id]);
$items = $istm->fetchAll();

$total = 0;
foreach ($items as $it) {
    $total += (float) $it->qty * (float) ($it->price ?? 0);
}

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => 'stock_order_list.php', 'View' => ''];
$_title = 'Admin | Stock Order (Purchase Order)';
include '../_head.php';
?>

<div class="card" style="max-width:820px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
        <div>
            <h2 style="margin:0;">Purchase Order</h2>
            <div style="font-size:1.2rem; font-weight:700; color:var(--coffee);">PO-<?= str_pad($o->id, 4, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div style="text-align:right;">
            <?php if ($o->status == 'received'): ?>
                <span class="badge-status success">Received</span>
            <?php else: ?>
                <span class="badge-status neutral">Pending</span>
            <?php endif ?>
        </div>
    </div>

    <p style="color:var(--muted); font-size:.9rem; margin-top:0;">
        Internal restock order to supplier — <b>not</b> a member purchase.
        Receiving this order adds the listed quantities to product inventory.
    </p>

    <form class="form">
        <label>Created</label>
        <div><?= $o->datetime ?> by <?= encode($o->creator ?? '') ?></div>

        <label>Supplier</label>
        <div><?= encode($o->supplier ?? '—') ?></div>

        <label>Expected Delivery</label>
        <div>
            <?= $o->expected_at ? date('Y-m-d H:i', strtotime($o->expected_at)) : '—' ?>
            <?php if ($o->status != 'received' && $o->expected_at && strtotime($o->expected_at) < time()): ?>
                <span class="badge-status danger">Overdue</span>
            <?php endif ?>
        </div>

        <?php if (!empty($o->note)): ?>
            <label>Note</label>
            <div><?= encode($o->note) ?></div>
        <?php endif ?>

        <?php if ($o->status == 'received'): ?>
            <label>Received</label>
            <div><?= $o->received_at ?> by <?= encode($o->receiver ?? '') ?></div>
        <?php endif ?>
    </form>

    <table class="table" style="margin-top:12px;">
        <tr><th>Product</th><th>Qty</th><th>Unit Cost (RM)</th><th class="right">Line Total (RM)</th><th class="right">Current Stock</th></tr>
        <?php foreach ($items as $it): ?>
        <tr>
            <td><?= encode($it->product_name ?? $it->product_id) ?> (<?= encode($it->product_id) ?>)</td>
            <td class="right"><?= $it->qty ?></td>
            <td class="right"><?= sprintf('%.2f', $it->price ?? 0) ?></td>
            <td class="right"><?= sprintf('%.2f', (float)$it->qty * (float)($it->price ?? 0)) ?></td>
            <td class="right"><?= (int)($it->current_stock ?? 0) ?></td>
        </tr>
        <?php endforeach ?>
        <tr>
            <th colspan="3" class="right">Order Total</th>
            <th class="right">RM <?= sprintf('%.2f', $total) ?></th>
            <th></th>
        </tr>
    </table>

    <?php if ($o->status !== 'received'): ?>
        <form method="post" onsubmit="return confirm('Mark as received and add stock to inventory?');">
            <input type="hidden" name="action" value="receive">
            <button type="submit">Mark Received (Add Stock)</button>
            <button type="button" data-get="stock_order_list.php" class="secondary">Back</button>
        </form>
    <?php else: ?>
        <p><button type="button" data-get="stock_order_list.php" class="secondary">Back</button></p>
    <?php endif ?>
</div>

<?php include '../_foot.php';
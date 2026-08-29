<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member/admin)
auth('Member', 'Admin');

// (2) Return order (based on id) belong to the user
$id = req('id');
// Ensure order columns exist (status/cancel fields)
ensure_order_columns();
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

// Handle cancellation actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(req('action'), ['cancel', 'approve_cancel', 'reject_cancel'])) {
    $action = req('action');

    // (User) Request cancellation → status becomes 'pending' awaiting admin approval
    if ($action === 'cancel') {
        if ($_user->role !== 'Admin' && $o->user_id != $_user->id) {
            flash('Unauthorized');
            redirect('history.php');
        }
        if (!is_order_cancellable($o->id)) {
            flash('Order cannot be cancelled');
            redirect(req('return', 'detail.php?id=' . $o->id));
        }
        $reason = req('reason');
        $res = request_cancel_order($o->id, $reason);
        flash($res['ok'] ? 'Cancellation requested — waiting for admin approval' : 'Failed to request cancellation: ' . ($res['error'] ?? 'Unknown'));
    }

    // (Admin) Approve pending cancellation → 'cancelled' + restock + refund points
    if ($action === 'approve_cancel') {
        if ($_user->role !== 'Admin') {
            flash('Unauthorized');
            redirect('history.php');
        }
        // If admin cancels a completed order directly, save the optional reason first
        if (($o->status ?? 'completed') === 'completed') {
            $reason = req('reason');
            if ($reason !== '') {
                $stm = $_db->prepare('UPDATE `order` SET cancel_reason = ? WHERE id = ?');
                $stm->execute([substr($reason, 0, 255), $o->id]);
            }
        }
        $res = approve_cancel_order($o->id);
        flash($res['ok'] ? 'Cancellation approved — order cancelled' : 'Failed to approve: ' . ($res['error'] ?? 'Unknown'));
    }

    // (Admin) Reject pending cancellation → back to 'completed'
    if ($action === 'reject_cancel') {
        if ($_user->role !== 'Admin') {
            flash('Unauthorized');
            redirect('history.php');
        }
        $res = reject_cancel_order($o->id);
        flash($res['ok'] ? 'Cancellation rejected — order restored' : 'Failed to reject: ' . ($res['error'] ?? 'Unknown'));
    }

    redirect(req('return', ($_user->role == 'Admin' ? '/admin/stock_history.php' : 'history.php')));
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

    <label>Subtotal</label>
    <div>RM <?= sprintf('%.2f', ($o->subtotal ?? 0) > 0 ? $o->subtotal : $o->total) ?></div>
    <br>

    <label>Discount</label>
    <div>&minus; RM <?= sprintf('%.2f', $o->discount ?? 0) ?>
        <?php if (!empty($o->voucher_code)): ?>
            <span class="badge-status neutral">Voucher: <?= encode($o->voucher_code) ?></span>
        <?php endif ?>
        <?php if (!empty($o->points_used)): ?>
            <span class="badge-status neutral"><?= $o->points_used ?> points used</span>
        <?php endif ?>
    </div>
    <br>

    <label>Total</label>
    <div><b>RM <?= sprintf('%.2f', $o->total) ?></b></div>
    <br>

    <?php if (!empty($o->points_earned)): ?>
        <label>Points Earned</label>
        <div><span class="badge-status success">+<?= $o->points_earned ?> points</span></div>
        <br>
    <?php endif ?>
    <?php
        // Display order status and cancellation info
        $status = $o->status ?? 'completed';
        $cancellable = is_order_cancellable($o->id);
    ?>
    <label>Status</label>
    <div>
        <?php if ($status === 'pending'): ?>
            <span class="badge-status process">Pending Cancellation</span>
            <?php if (!empty($o->cancel_reason)): ?> — <?= encode($o->cancel_reason) ?><?php endif ?>
            <br><small style="color:var(--muted);">Waiting for admin approval</small>
        <?php elseif ($status === 'cancelled'): ?>
            <span class="badge-status danger">Cancelled</span>
            <?php if (!empty($o->cancelled_at)): ?> at <?= $o->cancelled_at ?><?php endif ?>
            <?php if (!empty($o->cancel_reason)): ?> — <?= encode($o->cancel_reason) ?><?php endif ?>
            <?php if (!empty($o->cancelled_by)): 
                $ustm = $_db->prepare('SELECT name,email FROM user WHERE id = ?'); $ustm->execute([$o->cancelled_by]); $ub = $ustm->fetch();
                if ($ub) { echo ' by ' . encode($ub->name ?? $ub->email); }
            endif ?>
        <?php elseif ($status === 'refunded'): ?>
            <span class="badge-status neutral">Refunded</span>
        <?php else: ?>
            <span class="badge-status success"><?= encode(ucfirst($status)) ?></span>
        <?php endif ?>
    </div>
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

    <?php foreach ($arr as $i):
        $img = photo_url($i->photo);
        $imgFolder = is_file(__DIR__ . '/../products/' . $img) ? '/products/' : '/photos/';
    ?>
    <tr>
        <td><?= $i->product_id ?></td>
        <td><?= $i->name ?></td>
        <td class="right"><?= $i->price ?></td>
        <td class="right"><?= $i->unit ?></td>
        <td class="right">
            <?= $i->subtotal ?>
            <img src="<?= $imgFolder . rawurlencode($img) ?>" class="popup">
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

<?php if ($_user->role == 'Admin' && ($o->status ?? 'completed') === 'pending'): ?>
    <!-- (Admin) Approve or reject the pending cancellation request -->
    <form method="post" onsubmit="return confirm('Approve this cancellation?&#10;Stock will be restocked and points refunded.');">
        <input type="hidden" name="action" value="approve_cancel">
        <button type="submit">Approve Cancellation</button>
        <button type="button" data-get="history.php">Back</button>
    </form>
    <form method="post" onsubmit="return confirm('Reject this cancellation request?&#10;The order will be restored to completed.');">
        <input type="hidden" name="action" value="reject_cancel">
        <button type="submit" class="danger">Reject Cancellation</button>
    </form>
<?php elseif ($_user->role != 'Admin' && $o->user_id == $_user->id && is_order_cancellable($o->id)): ?>
    <!-- (Member) Request cancellation — pending until admin approves -->
    <form method="post" onsubmit="return confirm('Request to cancel this order?&#10;It will be pending until an admin approves.');">
        <input type="hidden" name="action" value="cancel">
        <label>Reason (optional)</label>
        <input type="text" name="reason">
        <button type="submit">Request Cancellation</button>
        <button type="button" data-get="history.php">Back</button>
    </form>
<?php endif ?>
</p>

<?php
include '../_foot.php';
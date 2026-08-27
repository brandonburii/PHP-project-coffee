<?php
include '../_base.php';

auth('Admin');

// Prefill from querystring
$prefill_pid = req('product_id');

if (is_post()) {
    $items = [];
    $pids = post('product_id') ?? [];
    $qtys = post('qty') ?? [];
    $prices = post('price') ?? [];
    for ($i = 0; $i < count($pids); $i++) {
        $pid = trim($pids[$i]);
        $qty = (int)($qtys[$i] ?? 0);
        $price = $prices[$i] ?? null;
        if ($pid && $qty > 0) $items[] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price];
    }
    $note = post('note');
    $res = create_stock_order($_user->id, $items, $note);
    if ($res['ok']) {
        redirect('stock_order_view.php?id=' . $res['id']);
    } else {
        flash('Failed to create: ' . ($res['error'] ?? 'unknown'));
        redirect('stock_order_create.php');
    }
}

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => 'stock_order_list.php', 'Create' => ''];
$_title = 'Admin | Create Stock Order';
include '../_head.php';
?>

<form method="post" class="form">
    <label>Note (optional)</label>
    <input type="text" name="note">

    <label>Items</label>
    <div id="items">
        <div class="row">
            <input name="product_id[]" placeholder="Product ID" value="<?= encode($prefill_pid ?? '') ?>">
            <input name="qty[]" placeholder="Qty" style="width:80px;">
            <input name="price[]" placeholder="Price (optional)" style="width:120px;">
        </div>
    </div>
    <p><button type="button" id="add">Add another</button></p>
    <p><button>Create</button> <button type="button" data-get="stock_order_list.php">Cancel</button></p>
</form>

<script>
    $('#add').on('click', function(){
        $('#items').append('<div class="row"><input name="product_id[]" placeholder="Product ID"> <input name="qty[]" placeholder="Qty" style="width:80px;"> <input name="price[]" placeholder="Price (optional)" style="width:120px;"></div>');
    });
</script>

<?php include '../_foot.php';

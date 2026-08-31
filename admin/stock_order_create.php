<?php
include '../_base.php';

auth('Admin');

// Prefill from querystring (e.g. from Order Stock page)
$prefill_pid = req('product_id');

// Product list for dropdowns
$products = $_db->query('SELECT id, name, stock, price FROM product ORDER BY name')->fetchAll();

if (is_post()) {
    $items = [];
    $pids = post('product_id') ?? [];
    $qtys = post('qty') ?? [];
    $prices = post('price') ?? [];
    for ($i = 0; $i < count($pids); $i++) {
        $pid = trim($pids[$i]);
        $qty = (int)($qtys[$i] ?? 0);
        $price = $prices[$i] !== '' ? (float)$prices[$i] : null;
        if ($pid && $qty > 0 && is_exists($pid, 'product', 'id')) {
            $items[] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price];
        }
    }

    $note        = trim(post('note') ?? '');
    $supplier    = trim(post('supplier') ?? '');
    $expected_at = trim(post('expected_at') ?? '');

    if (empty($items)) {
        flash('Please add at least one valid product with quantity');
        redirect('stock_order_create.php');
    }

    $res = create_stock_order(
        $_user->id,
        $items,
        $note ?: null,
        $supplier ?: null,
        $expected_at !== '' ? date('Y-m-d H:i:s', strtotime($expected_at)) : null
    );
    if ($res['ok']) {
        temp('info', 'Stock order (purchase order) created');
        redirect('stock_order_view.php?id=' . $res['id']);
    } else {
        flash('Failed to create: ' . ($res['error'] ?? 'unknown'));
        redirect('stock_order_create.php');
    }
}

$_breadcrumbs = ['Dashboard' => '/', 'Stock Orders' => 'stock_order_list.php', 'Create' => ''];
$_title = 'Admin | Create Stock Order (Purchase Order)';
include '../_head.php';
?>

<p class="hint" style="color:var(--muted);">
    A <b>Stock Order</b> is an internal purchase order (PO) sent to a supplier to restock inventory.
    It is separate from member purchase orders — receiving it adds stock to products.
</p>

<form method="post" class="form" id="po-form">
    <label for="supplier">Supplier</label>
    <input type="text" id="supplier" name="supplier" maxlength="100" placeholder="e.g. Coffee Bean Traders Sdn Bhd">

    <label for="expected_at">Expected Delivery</label>
    <input type="datetime-local" id="expected_at" name="expected_at">

    <label for="note">Note (optional)</label>
    <input type="text" id="note" name="note" maxlength="255" placeholder="e.g. Monthly restock">

    <label>Items</label>
    <div id="items">
        <div class="row po-row" style="display:flex; gap:8px; align-items:center; margin-bottom:6px; flex-wrap:wrap;">
            <select name="product_id[]" class="po-product" required style="flex:2; min-width:200px;">
                <option value="">- Select Product -</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= encode($p->id) ?>" <?= ($prefill_pid ?? '') == $p->id ? 'selected' : '' ?>>
                        <?= encode($p->name) ?> (<?= encode($p->id) ?>) — stock: <?= (int)$p->stock ?>
                    </option>
                <?php endforeach ?>
            </select>
            <input type="number" name="qty[]" class="po-qty" placeholder="Qty" min="1" required style="width:90px;">
            <input type="number" name="price[]" class="po-price" placeholder="Unit cost (RM)" min="0" step="0.01" style="width:140px;">
            <span class="po-line-total" style="min-width:90px; text-align:right;">RM 0.00</span>
            <button type="button" class="po-remove danger" style="padding:2px 10px;">✕</button>
        </div>
    </div>
    <p><button type="button" id="add" class="secondary">+ Add another item</button></p>

    <div style="margin:12px 0; font-size:1.1rem;">
        <b>Order Total: RM <span id="po-total">0.00</span></b>
    </div>

    <p><button>Create Stock Order</button> <button type="button" data-get="stock_order_list.php" class="secondary">Cancel</button></p>
</form>

<script>
$(function () {
    function recalc() {
        let total = 0;
        $('#items .po-row').each(function () {
            const qty   = parseFloat($(this).find('.po-qty').val()) || 0;
            const price = parseFloat($(this).find('.po-price').val()) || 0;
            const line  = qty * price;
            $(this).find('.po-line-total').text('RM ' + line.toFixed(2));
            total += line;
        });
        $('#po-total').text(total.toFixed(2));
    }

    $('#items').on('input change', '.po-qty, .po-price', recalc);

    $('#add').on('click', function (e) {
        e.preventDefault();
        const $row = $('#items .po-row').first().clone();
        $row.find('select').val('');
        $row.find('input.po-qty').val('');
        $row.find('input.po-price').val('');
        $row.find('.po-line-total').text('RM 0.00');
        $('#items').append($row);
    });

    $('#items').on('click', '.po-remove', function (e) {
        e.preventDefault();
        if ($('#items .po-row').length > 1) {
            $(this).closest('.po-row').remove();
            recalc();
        }
    });

    recalc();
});
</script>

<?php include '../_foot.php';
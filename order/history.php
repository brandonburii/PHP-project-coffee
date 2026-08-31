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
        <td class="order-history-cell">
            <?= order_image_preview_html($o->id) ?>
            <div class="order-history-actions">
                <button data-get="detail.php?id=<?= $o->id ?>">Detail</button>
                <?php if ($_user->role == 'Member'): ?>
                    <button
                        onclick="openReviewModal(<?= $o->id ?>)"
                        class="review-history-button"
                    >
                        ⭐ Rate & Review
                    </button>
                <?php endif ?>
            </div>
        </td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>

<!-- Rate & Review Modal -->
<div id="reviewModalOverlay" class="review-modal-overlay" hidden onclick="closeReviewModal()"></div>
<div id="reviewModal" class="review-modal" hidden role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
    <h3 id="reviewModalTitle">Rate &amp; Review Order #<span id="orderId"></span></h3>
    <form id="reviewModalForm" action="rate-review.php" method="post">
        <input type="hidden" name="order_id" id="modalOrderId">
        <input type="hidden" name="product_id" id="modalProductId">
        <input type="hidden" name="return_to" value="history">
        <input type="hidden" name="ajax" value="1">

        <div class="review-modal-field">
            <label for="modalProductSelect">Product</label>
            <select name="product_id_display" id="modalProductSelect" disabled>
                <option value="">Loading products...</option>
            </select>
        </div>

        <div class="review-modal-field">
            <label for="modalRating">Rating</label>
            <select name="rating" id="modalRating" required>
                <option value="">Select rating</option>
                <option value="5">★★★★★ — Excellent</option>
                <option value="4">★★★★☆ — Good</option>
                <option value="3">★★★☆☆ — Average</option>
                <option value="2">★★☆☆☆ — Poor</option>
                <option value="1">★☆☆☆☆ — Terrible</option>
            </select>
        </div>

        <div class="review-modal-field">
            <label for="modalComment">Review</label>
            <textarea name="comment" id="modalComment" rows="4" placeholder="Share your experience..." required></textarea>
        </div>

        <p id="reviewModalError" class="review-modal-error" hidden></p>

        <div class="review-modal-actions">
            <button type="submit" class="success" id="reviewModalSubmit">Submit Review</button>
            <button type="button" class="secondary" onclick="closeReviewModal()">Cancel</button>
        </div>
    </form>
</div>

<script>
const orderProducts = {};

function openReviewModal(orderId) {
    document.getElementById('orderId').textContent = orderId;
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('reviewModalError').hidden = true;
    document.getElementById('reviewModalError').textContent = '';
    document.getElementById('reviewModalForm').reset();
    document.getElementById('modalOrderId').value = orderId;

    document.getElementById('reviewModal').hidden = false;
    document.getElementById('reviewModalOverlay').hidden = false;
    document.body.style.overflow = 'hidden';

    loadOrderProducts(orderId);
}

function closeReviewModal() {
    document.getElementById('reviewModal').hidden = true;
    document.getElementById('reviewModalOverlay').hidden = true;
    document.body.style.overflow = '';
}

function loadOrderProducts(orderId) {
    const select = document.getElementById('modalProductSelect');
    const hiddenInput = document.getElementById('modalProductId');

    select.disabled = true;
    select.innerHTML = '<option value="">Loading products...</option>';
    hiddenInput.value = '';

    if (orderProducts[orderId]) {
        populateProductSelect(orderProducts[orderId]);
        return;
    }

    fetch('/order/get_order_products.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.products && data.products.length > 0) {
                orderProducts[orderId] = data.products;
                populateProductSelect(data.products);
            } else {
                select.innerHTML = '<option value="">No products found for this order</option>';
            }
        })
        .catch(() => {
            select.innerHTML = '<option value="">Could not load products</option>';
        });
}

function populateProductSelect(products) {
    const select = document.getElementById('modalProductSelect');
    const hiddenInput = document.getElementById('modalProductId');

    select.innerHTML = '';
    products.forEach(product => {
        const option = document.createElement('option');
        option.value = product.id;
        option.textContent = product.name;
        select.appendChild(option);
    });

    if (products.length > 0) {
        select.value = products[0].id;
        hiddenInput.value = products[0].id;
    }

    select.disabled = false;
    select.onchange = function() {
        hiddenInput.value = this.value;
    };
}

document.getElementById('reviewModalForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('reviewModalSubmit');
    const errEl = document.getElementById('reviewModalError');
    const form = e.target;

    submitBtn.disabled = true;
    errEl.hidden = true;

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                closeReviewModal();
                window.location.reload();
                return;
            }
            errEl.textContent = data.error || 'Unable to submit review.';
            errEl.hidden = false;
        })
        .catch(() => {
            errEl.textContent = 'Network error. Please try again.';
            errEl.hidden = false;
        })
        .finally(() => {
            submitBtn.disabled = false;
        });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('reviewModal').hidden) {
        closeReviewModal();
    }
});
</script>
<br>
<?php $pager->html('sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&search=' . urlencode($search) . '&status=' . urlencode($status)); ?>

<?php
include '../_foot.php';
?>
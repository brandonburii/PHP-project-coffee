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
<style>
.modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 25px;
    z-index: 1000;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    min-width: 350px;
    max-width: 90%;
}
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}
.modal h3 {
    margin-top: 0;
}
.modal .form-group {
    margin-bottom: 15px;
}
.modal label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}
.modal select,
.modal textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}
.modal textarea {
    resize: vertical;
}
.modal .btn-group {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.modal .btn-group button {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.modal .btn-submit {
    background: #007bff;
    color: white;
}
.modal .btn-submit:hover {
    background: #0056b3;
}
.modal .btn-cancel {
    background: #6c757d;
    color: white;
}
.modal .btn-cancel:hover {
    background: #5a6268;
}
</style>

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
            <?php if ($_user->role == 'Member'): ?>
                <button 
                    onclick="openReviewModal(<?= $o->id ?>)" 
                    class="review-history-button"
                >
                    ⭐ Rate & Review
                </button>
            <?php endif ?>
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

<!-- Review Modal-->
<div id="reviewModal" class="modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; z-index:1000; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.2); width:400px; max-width:90%;">
    <h3>Rate & Review Order #<span id="orderId"></span></h3>
    <form action="rate-review.php" method="POST">
        <input type="hidden" name="order_id" id="modalOrderId">
        <input type="hidden" name="product_id" id="modalProductId">
        
        <div class="form-group">
            <label>Product:</label>
            <select name="product_id_display" id="modalProductSelect" disabled>
                <option value="">Loading products...</option>
            </select>
            <!-- Hidden input will be set by JavaScript -->
        </div>
        
        <div class="form-group">
            <label>Rating:</label>
            <select name="rating" required>
                <option value="">Select rating</option>
                <option value="5">⭐⭐⭐⭐⭐ - Excellent</option>
                <option value="4">⭐⭐⭐⭐ - Good</option>
                <option value="3">⭐⭐⭐ - Average</option>
                <option value="2">⭐⭐ - Poor</option>
                <option value="1">⭐ - Terrible</option>
            </select>
        </div>
        <div class="form-group">
            <label>Review:</label>
            <textarea name="comment" rows="4" placeholder="Share your experience..." required></textarea>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn-submit">Submit Review</button>
            <button type="button" class="btn-cancel" onclick="closeReviewModal()">Cancel</button>
        </div>
    </form>
</div>

<!-- Overlay -->
<div id="modalOverlay" class="modal-overlay" onclick="closeReviewModal()"></div>

<script>
// Store order products cache
const orderProducts = {};

function openReviewModal(orderId) {
    document.getElementById('orderId').textContent = orderId;
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('reviewModal').style.display = 'block';
    document.getElementById('modalOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Load products for this order
    loadOrderProducts(orderId);
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function loadOrderProducts(orderId) {
    const select = document.getElementById('modalProductSelect');
    const hiddenInput = document.getElementById('modalProductId');
    
    // Check cache first
    if (orderProducts[orderId]) {
        populateProductSelect(orderProducts[orderId]);
        return;
    }
    
    // Fetch products from the order detail page
    fetch('/order/detail.php?id=' + orderId)
        .then(response => response.text())
        .then(html => {
            // Parse the HTML to extract product IDs and names
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Look for product items in the receipt table
            const rows = doc.querySelectorAll('.receipt-items tr');
            const products = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 1) {
                    // Try to find product info
                    const text = cells[0]?.textContent?.trim();
                    if (text && text !== 'Total Items') {
                        // We'll use the text as product name
                        // But we need product IDs too - we'll get them from the URL
                        products.push({ id: 'P' + products.length.toString().padStart(3, '0'), name: text });
                    }
                }
            });
            
            // If no products found, use a fallback
            if (products.length === 0) {
                // Get products from the page using PHP - we'll use AJAX
                fetchProductsForOrder(orderId);
                return;
            }
            
            orderProducts[orderId] = products;
            populateProductSelect(products);
        })
        .catch(() => {
            // Fallback: try to get products via AJAX
            fetchProductsForOrder(orderId);
        });
}

function fetchProductsForOrder(orderId) {
    // Direct AJAX call to get products
    fetch('/order/get_order_products.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.products && data.products.length > 0) {
                orderProducts[orderId] = data.products;
                populateProductSelect(data.products);
            } else {
                // Fallback: use the PHP session variable from checkout
                const products = <?= json_encode($review_items ?? []) ?>;
                if (products && products.length > 0) {
                    orderProducts[orderId] = products;
                    populateProductSelect(products);
                }
            }
        })
        .catch(() => {
            // Final fallback: let user know
            const select = document.getElementById('modalProductSelect');
            select.innerHTML = '<option value="">No products found for this order</option>';
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
    
    // Set the first product as default
    if (products.length > 0) {
        select.value = products[0].id;
        hiddenInput.value = products[0].id;
    }
    
    // Enable the select
    select.disabled = false;
    
    // When selection changes, update hidden input
    select.onchange = function() {
        hiddenInput.value = this.value;
    };
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
    }
});
</script>

<?php
include '../_foot.php';
?>
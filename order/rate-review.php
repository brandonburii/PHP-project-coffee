<?php
include '../_base.php';

// Authorization
auth('Member', 'Admin');

$order_id = req('order_id');
$product_id = req('product_id');
$return_to = req('return_to', 'detail');

// Handle form submission
if (is_post()) {
    $rating = req('rating');
    $comment = req('comment', '');
    $order_id = req('order_id');
    $product_id = req('product_id');
    $return_to = req('return_to', 'detail');

    $errors = [];
    if (!$order_id || !$product_id) {
        $errors[] = 'Missing order or product information.';
    }
    if (!$rating || $rating < 1 || $rating > 5) {
        $errors[] = 'Please select a valid rating (1-5 stars).';
    }
    if (trim($comment) === '') {
        $errors[] = 'Please write a review.';
    }

  // Verify order belongs to member
    if ($_user->role == 'Member' && empty($errors)) {
        $stm = $_db->prepare('SELECT id FROM `order` WHERE id = ? AND user_id = ?');
        $stm->execute([$order_id, $_user->id]);
        if (!$stm->fetch()) {
            $errors[] = 'Order not found.';
        }
    }

    if (empty($errors)) {
        $result = save_product_review($order_id, $product_id, $rating, $comment);

        if (is_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok']) {
                temp('info', 'Review submitted successfully.');
                echo json_encode(['ok' => true, 'message' => 'Review submitted successfully.']);
            } else {
                echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Unable to save review.']);
            }
            exit;
        }

        if ($result['ok']) {
            temp('info', 'Review submitted successfully.');
        } else {
            temp('info', $result['error'] ?? 'Unable to save review.');
        }

        if ($return_to === 'history') {
            redirect('/order/history.php');
        }

        redirect('/order/detail.php?id=' . $order_id);
    }

    if (is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
        exit;
    }
}

// Standalone review page (GET) — requires order + product
if (!$order_id || !$product_id) {
    temp('info', 'Missing order or product information.');
    redirect('/order/history.php');
}

// Verify order belongs to user (if member)
if ($_user->role == 'Member') {
    $stm = $_db->prepare('SELECT * FROM `order` WHERE id = ? AND user_id = ?');
    $stm->execute([$order_id, $_user->id]);
    $order = $stm->fetch();
    if (!$order) {
        temp('info', 'Order not found.');
        redirect('/order/history.php');
    }
}

// Get product details
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$product_id]);
$product = $stm->fetch();

if (!$product) {
    temp('info', 'Product not found.');
    redirect('/order/history.php');
}

// Get existing review if any
$stm = $_db->prepare('SELECT * FROM review WHERE order_id = ? AND product_id = ? AND user_id = ?');
$stm->execute([$order_id, $product_id, $_user->id]);
$existing_review = $stm->fetch();

$review_text = $existing_review ? review_row_text($existing_review) : '';

$_title = 'Rate & Review Product';
include '../_head.php';
?>

<style>
.review-container {
    max-width: 500px;
    margin: 20px auto;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}
.review-container h2 {
    margin-top: 0;
    font-size: 20px;
}
.product-info {
    background: white;
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    display: flex;
    align-items: center;
    gap: 15px;
}
.product-info img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}
.product-info .details {
    flex: 1;
}
.product-info .details h3 {
    margin: 0 0 3px 0;
    font-size: 16px;
}
.product-info .details .price {
    color: #28a745;
    font-weight: bold;
    font-size: 14px;
}
.product-info .details .order-ref {
    font-size: 12px;
    color: #666;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 3px;
}
.form-group select, 
.form-group textarea {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    box-sizing: border-box;
}
.form-group textarea {
    resize: vertical;
}
.rating-stars {
    font-size: 28px;
    cursor: pointer;
    user-select: none;
}
.rating-stars span {
    color: #ddd;
    transition: color 0.2s;
}
.rating-stars span.active {
    color: #ffc107;
}
.rating-hint {
    font-size: 12px;
    color: #666;
    display: block;
    margin-top: 3px;
}
.btn-group {
    display: flex;
    gap: 8px;
}
.btn-group button {
    flex: 1;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}
.btn-submit {
    background: #007bff;
    color: white;
}
.btn-submit:hover {
    background: #0056b3;
}
.btn-cancel {
    background: #6c757d;
    color: white;
}
.btn-cancel:hover {
    background: #5a6268;
}
.error-msg {
    color: #dc3545;
    padding: 10px;
    margin-bottom: 15px;
    background: #f8d7da;
    border-radius: 4px;
    font-size: 13px;
}
.success-msg {
    color: #155724;
    padding: 10px;
    margin-bottom: 15px;
    background: #d4edda;
    border-radius: 4px;
    font-size: 13px;
}
</style>

<div class="review-container">
    <h2>Rate & Review</h2>
    
    <div class="product-info">
        <img src="<?= photo_src($product->photo) ?>" alt="<?= encode($product->name) ?>">
        <div class="details">
            <h3><?= encode($product->name) ?></h3>
            <div class="price">RM <?= number_format(product_price($product), 2) ?></div>
            <div class="order-ref">Order #<?= $order_id ?></div>
        </div>
    </div>
    
    <?php if ($existing_review): ?>
        <div class="success-msg">
            ✅ You have already reviewed this product. You can update your review below.
        </div>
    <?php endif ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error-msg">
            <?php foreach ($errors as $error): ?>
                <div>• <?= $error ?></div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    
    <form method="POST">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <input type="hidden" name="return_to" value="detail">
        
        <div class="form-group">
            <label>Your Rating</label>
            <div class="rating-stars" id="ratingStars">
                <span data-value="1">⭐</span>
                <span data-value="2">⭐</span>
                <span data-value="3">⭐</span>
                <span data-value="4">⭐</span>
                <span data-value="5">⭐</span>
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="<?= $existing_review->rating ?? '' ?>">
            <span class="rating-hint">Click on stars to rate</span>
        </div>
        
        <div class="form-group">
            <label for="comment">Your Review</label>
            <textarea name="comment" id="comment" rows="4" placeholder="Share your experience with this product..."><?= htmlspecialchars($review_text) ?></textarea>
        </div>
        
        <div class="btn-group">
            <button type="submit" class="btn-submit"><?= $existing_review ? 'Update Review' : 'Submit Review' ?></button>
            <button type="button" class="btn-cancel" onclick="window.location.href='/order/detail.php?id=<?= $order_id ?>'">Cancel</button>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('#ratingStars span').forEach(star => {
    star.addEventListener('click', function() {
        const value = parseInt(this.dataset.value);
        document.getElementById('ratingInput').value = value;
        
        document.querySelectorAll('#ratingStars span').forEach(s => {
            const starValue = parseInt(s.dataset.value);
            s.classList.toggle('active', starValue <= value);
        });
    });
});

const existingRating = <?= $existing_review->rating ?? 0 ?>;
if (existingRating > 0) {
    document.querySelectorAll('#ratingStars span').forEach(s => {
        const value = parseInt(s.dataset.value);
        if (value <= existingRating) {
            s.classList.add('active');
        }
    });
}
</script>

<?php include '../_foot.php'; ?>
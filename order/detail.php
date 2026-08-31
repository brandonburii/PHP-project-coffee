<?php

include '../_base.php';

// ----------------------------------------------------------------------------
// Authorization
// ----------------------------------------------------------------------------

auth('Member', 'Admin');


// ----------------------------------------------------------------------------
// Review submission
// ----------------------------------------------------------------------------

if (is_post() && req('btn') == 'review') {

    auth('Member');

    $order_id   = req('order_id');
    $product_id = req('product_id');
    $rating     = (int) req('rating');
    $comment    = trim(req('comment'));


    // ---------------------------------------------------------
    // Validate rating
    // ---------------------------------------------------------

    if (!in_array($rating, [1, 2, 3, 4, 5])) {

        temp(
            'info',
            'Please select a rating from 1 to 5 stars.'
        );

        redirect("detail.php?id=$order_id");
    }


    // ---------------------------------------------------------
    // Validate comment
    // ---------------------------------------------------------

    if ($comment == '') {

        temp(
            'info',
            'Please write a review.'
        );

        redirect("detail.php?id=$order_id");
    }


    // ---------------------------------------------------------
    // Make sure:
    //
    // 1. Order belongs to current user
    // 2. Product belongs to the order
    // ---------------------------------------------------------

    $stm = $_db->prepare('
        SELECT o.id
        FROM `order` o
        JOIN item i
            ON i.order_id = o.id
        WHERE o.id = ?
        AND o.user_id = ?
        AND i.product_id = ?
    ');

    $stm->execute([
        $order_id,
        $_user->id,
        $product_id
    ]);


    if (!$stm->fetch()) {

        temp(
            'info',
            'Invalid product or order.'
        );

        redirect('history.php');
    }


    // ---------------------------------------------------------
    // Check whether this product has already been reviewed
    // ---------------------------------------------------------

    $stm = $_db->prepare('
        SELECT id
        FROM review
        WHERE order_id = ?
        AND product_id = ?
        AND user_id = ?
    ');

    $stm->execute([
        $order_id,
        $product_id,
        $_user->id
    ]);


    if ($stm->fetch()) {

        temp(
            'info',
            'You have already reviewed this product.'
        );

        redirect("detail.php?id=$order_id");
    }


    // ---------------------------------------------------------
    // Insert review
    // ---------------------------------------------------------

    $stm = $_db->prepare('
        INSERT INTO review
        (
            order_id,
            product_id,
            user_id,
            rating,
            review,
            created_at
        )
        VALUES
        (?, ?, ?, ?, ?, NOW())
    ');


    $stm->execute([
        $order_id,
        $product_id,
        $_user->id,
        $rating,
        $comment
    ]);


    temp(
        'info',
        'Thank you! Your review has been submitted.'
    );


    redirect("detail.php?id=$order_id");
}


// ----------------------------------------------------------------------------
// Get order ID
// ----------------------------------------------------------------------------

$id = req('id');


// ----------------------------------------------------------------------------
// Get order
// ----------------------------------------------------------------------------

// Ensure order columns exist (status/cancel fields)
ensure_order_columns();
if ($_user->role == 'Admin') {

    $stm = $_db->prepare('
        SELECT *
        FROM `order`
        WHERE id = ?
    ');

    $stm->execute([
        $id
    ]);

}
else {

    $stm = $_db->prepare('
        SELECT *
        FROM `order`
        WHERE id = ?
        AND user_id = ?
    ');

    $stm->execute([
        $id,
        $_user->id
    ]);
}


$o = $stm->fetch();


// ----------------------------------------------------------------------------
// Order not found
// ----------------------------------------------------------------------------

if (!$o) {

    if ($_user->role == 'Admin') {

        redirect('/');

    }
    else {

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

// ----------------------------------------------------------------------------
// Audit
// ----------------------------------------------------------------------------

audit(
    'Orders',
    'Viewed order details',
    "Viewed details for order ID: $id"
);


// ----------------------------------------------------------------------------
// Get order items
//
// IMPORTANT:
// item table does not have an id column.
// Therefore we do NOT use ORDER BY i.id.
// ----------------------------------------------------------------------------

$stm = $_db->prepare('
    SELECT
        i.*,
        p.name,
        p.photo
    FROM item i
    JOIN product p
        ON i.product_id = p.id
    WHERE i.order_id = ?
');

$stm->execute([
    $o->id
]);

$arr = $stm->fetchAll();


// ----------------------------------------------------------------------------
// Review popup data
// ----------------------------------------------------------------------------

$review_items = [];


// Only Member can review

if ($_user->role == 'Member') {

    $review_prompt_order =
        $_SESSION['review_prompt_order'] ?? null;


    // ---------------------------------------------------------
    // Show popup only for the order stored in session
    // ---------------------------------------------------------

    if (
        $review_prompt_order &&
        (int)$review_prompt_order == (int)$o->id
    ) {

        // Remove session flag so popup only appears once
        unset($_SESSION['review_prompt_order']);


        // -----------------------------------------------------
        // Check each purchased product
        // and see whether it has already been reviewed
        // -----------------------------------------------------

        foreach ($arr as $item) {

            $stm = $_db->prepare('
                SELECT id
                FROM review
                WHERE order_id = ?
                AND product_id = ?
                AND user_id = ?
            ');

            $stm->execute([
                $o->id,
                $item->product_id,
                $_user->id
            ]);


            // Only add products that have NOT been reviewed

            if (!$stm->fetch()) {

                $review_items[] = [
                    'id'    => $item->product_id,
                    'name'  => $item->name,
                    'photo' => $item->photo
                ];
            }
        }
    }
}


// ----------------------------------------------------------------------------
// Breadcrumbs
// ----------------------------------------------------------------------------

$_breadcrumbs = [

    'Dashboard' => '/',

    ($_user->role == 'Admin'
        ? 'Order Management'
        : 'Order History'
    ) => 'history.php',

    'Order Detail' => '',
];


// ----------------------------------------------------------------------------
// Page title
// ----------------------------------------------------------------------------

$_title = 'Order | Detail';


include '../_head.php';

?>


<!-- =========================================================
     APP CSS
========================================================= -->

<link
    rel="stylesheet"
    href="/css/app.css"
>


<div class="receipt-container">

    <div id="print-receipt">

        <div class="receipt">


            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="receipt-header">

                <div class="receipt-logo">
                    ☕
                </div>

                <h1>
                    Coffee Shop
                </h1>

                <p>
                    Thank you for your purchase!
                </p>

            </div>


            <!-- =================================================
                 RECEIPT TITLE
            ================================================== -->

            <div class="receipt-title">

                <h2>
                    E-RECEIPT
                </h2>

                <p>

                    Receipt #

                    <?= encode($o->id) ?>

                </p>

            </div>


            <!-- =================================================
                 ORDER INFORMATION
            ================================================== -->

            <div class="order-info">


                <div>

                    <label>
                        Order ID
                    </label>

                    <span>
                        <?= encode($o->id) ?>
                    </span>

                </div>


                <div>

                    <label>
                        Date & Time
                    </label>

                    <span>
                        <?= encode($o->datetime) ?>
                    </span>

                </div>


                <div>

                    <label>
                        Number of Items
                    </label>

                    <span>
                        <?= encode($o->count) ?>
                    </span>

                </div>


                <?php if (!empty($o->voucher_code)): ?>

                    <div>

                        <label>
                            Voucher
                        </label>

                        <span>
                            <?= encode($o->voucher_code) ?>
                        </span>

                    </div>

                <?php endif; ?>


            </div>


            <!-- =================================================
                 PURCHASED ITEMS
            ================================================== -->

            <h3>
                Purchased Items
            </h3>


            <table class="receipt-items">

                <tr>

                    <th>
                        Product
                    </th>

                    <th>
                        Unit
                    </th>

                    <th>
                        Price (RM)
                    </th>

                    <th>
                        Subtotal (RM)
                    </th>

                </tr>


                <?php foreach ($arr as $i): ?>

                    <tr>

                        <td>
                            <?= encode($i->name) ?>
                        </td>


                        <td>
                            <?= encode($i->unit) ?>
                        </td>


                        <td>
                            <?= sprintf(
                                '%.2f',
                                $i->price
                            ) ?>
                        </td>


                        <td>
                            <?= sprintf(
                                '%.2f',
                                $i->subtotal
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>


                <!-- Total -->

                <tr>

                    <th>
                        Total Items
                    </th>

                    <th>
                        <?= encode($o->count) ?>
                    </th>

                    <th></th>

                    <th>

                        RM

                        <?= sprintf(
                            '%.2f',
                            $o->total
                        ) ?>

                    </th>

                </tr>

            </table>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="receipt-summary">


                <!-- Subtotal -->

                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <span>

                        RM

                        <?= sprintf(
                            '%.2f',
                            ($o->subtotal ?? 0) > 0
                                ? $o->subtotal
                                : $o->total
                        ) ?>

                    </span>

                </div>


                <!-- Discount -->

                <?php if (($o->discount ?? 0) > 0): ?>

                    <div class="summary-row discount">

                        <span>
                            Discount
                        </span>

                        <span>

                            - RM

                            <?= sprintf(
                                '%.2f',
                                $o->discount
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- Voucher -->

                <?php if (!empty($o->voucher_code)): ?>

                    <div class="summary-row">

                        <span>
                            Voucher
                        </span>

                        <span>
                            <?= encode($o->voucher_code) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- Points Used -->

                <?php if (!empty($o->points_used)): ?>

                    <div class="summary-row">

                        <span>
                            Points Used
                        </span>

                        <span>
                            <?= encode($o->points_used) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- Total -->

                <div class="summary-row total">

                    <span>
                        Total
                    </span>

                    <span>

                        RM

                        <?= sprintf(
                            '%.2f',
                            $o->total
                        ) ?>

                    </span>

                </div>


            </div>


            <!-- =================================================
                 POINTS EARNED
            ================================================== -->

            <?php if (!empty($o->points_earned)): ?>

                <div class="points-box">

                    ⭐

                    <strong>
                        Points Earned:
                    </strong>

                    +

                    <?= encode($o->points_earned) ?>

                    points

                </div>

            <?php endif; ?>


            <!-- =================================================
                 FOOTER
            ================================================== -->

            <div class="receipt-footer">

                <p>
                    Thank you for shopping with us!
                </p>

                <p>
                    Please keep this receipt for your records.
                </p>

                <small>
                    This is an electronically generated receipt.
                </small>

            </div>


        </div>

    </div>


    <!-- =========================================================
         BUTTONS
    ========================================================== -->

    <div class="receipt-buttons no-print">


        <!-- Send Email -->

        <button
            type="button"
            class="email-button"
            onclick="openEmailPopup()"
        >

            📧 Send E-Receipt

        </button>


        <!-- Print -->

        <button
            type="button"
            class="print-button"
            onclick="printReceipt()"
        >

            🖨️ Print E-Receipt

        </button>


        <!-- History -->

        <button
            type="button"
            class="history-button"
            data-get="history.php"
        >

            ← History

        </button>


    </div>

</div>


<div
    id="email-modal"
    class="email-modal no-print"
>


    <div class="email-modal-box">


        <!-- Close -->

        <button
            type="button"
            class="email-close"
            onclick="closeEmailPopup()"
        >

            &times;

        </button>


        <!-- Icon -->

        <div class="email-icon">
            📧
        </div>


        <h2>
            Send E-Receipt
        </h2>


        <p class="email-description">

            Enter your email address to send
            your receipt.

        </p>


        <!-- Email -->

        <div class="email-input-group">

            <label for="receipt-email">

                Email Address

            </label>


            <input
                type="email"
                id="receipt-email"
                placeholder="example@gmail.com"
                autocomplete="email"
            >


            <small
                id="email-error"
                class="email-error"
            ></small>

        </div>


        <!-- Buttons -->

        <div class="email-modal-buttons">


            <button
                type="button"
                class="cancel-email"
                onclick="closeEmailPopup()"
            >

                Cancel

            </button>


            <button
                type="button"
                class="send-email"
                onclick="submitEmailReceipt()"
            >

                Send

            </button>


        </div>

    </div>

</div>


<?php if ($_user->role == 'Member'): ?>


<?php if (!empty($review_items)): ?>


<style>

#review-modal {

    position: fixed;

    top: 0;
    left: 0;

    width: 100%;
    height: 100%;

    background: rgba(0, 0, 0, 0.55);

    z-index: 99999;

    display: flex;

    align-items: center;
    justify-content: center;

    animation: reviewFadeIn 0.25s ease;
}


@keyframes reviewFadeIn {

    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }

}


@keyframes reviewSlideUp {

    from {

        opacity: 0;

        transform:
            translateY(30px)
            scale(0.96);

    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);

    }

}


#review-modal .review-modal-box {

    background: white;

    border-radius: 16px;

    padding:
        30px
        32px
        28px;

    max-width: 440px;

    width: 92%;

    max-height: 90vh;

    overflow-y: auto;

    position: relative;

    box-shadow:
        0 24px 60px
        rgba(0, 0, 0, 0.3);

    animation:
        reviewSlideUp
        0.25s ease;
}


#review-modal .review-modal-close {

    position: absolute;

    top: 12px;
    right: 16px;

    background: none;

    border: none;

    font-size: 28px;

    color: #aaa;

    cursor: pointer;

    padding: 0;

    line-height: 1;

    box-shadow: none;

    width: 32px;
    height: 32px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 50%;
}


#review-modal .review-modal-close:hover {

    background: #f5f5f5;

    color: #333;

    transform: none;

    box-shadow: none;
}


#review-modal .review-modal-icon {

    text-align: center;

    font-size: 44px;

    margin-bottom: 6px;
}


#review-modal .review-modal-box h2 {

    text-align: center;

    margin:
        0
        0
        4px
        0;

    font-size: 20px;

    font-weight: 700;

    color: #222;
}


#review-modal .review-description {

    text-align: center;

    color: #888;

    font-size: 14px;

    margin:
        0
        0
        20px
        0;
}


#review-modal .form-group {

    margin-bottom: 16px;
}


#review-modal .form-group label {

    display: block;

    font-weight: 600;

    font-size: 13px;

    margin-bottom: 4px;

    color: #555;
}


#review-modal .form-group select {

    width: 100%;

    padding:
        8px
        12px;

    border:
        1px
        solid
        #ddd;

    border-radius: 8px;

    font-size: 14px;

    background: white;

    box-sizing: border-box;
}


#review-modal .form-group select:focus {

    border-color: #007bff;

    outline: none;

    box-shadow:
        0
        0
        0
        3px
        rgba(0, 123, 255, 0.1);
}


#review-modal .star-rating-wrapper {

    display: flex;

    flex-direction: column;

    align-items: center;
}


#review-modal .star-rating {

    display: flex;

    flex-direction: row-reverse;

    justify-content: flex-end;

    gap: 4px;

    padding: 4px 0;
}


#review-modal .star-rating input {

    display: none;
}


#review-modal .star-rating label {

    font-size: 36px;

    color: #ddd;

    cursor: pointer;

    transition:
        color
        0.15s
        ease;

    margin: 0;

    padding: 2px;

    line-height: 1;
}


#review-modal .star-rating label:hover,
#review-modal .star-rating label:hover ~ label {

    color: #ffc107;
}


#review-modal
.star-rating
input:checked
~ label {

    color: #ffc107;
}


#review-modal .star-rating-text {

    font-size: 14px;

    color: #999;

    margin-top: 4px;

    min-height: 22px;

    font-weight: 500;

    transition:
        color
        0.2s;
}


#review-modal .star-rating-text.active {

    color: #ffc107;
}


#review-modal .form-group textarea {

    width: 100%;

    padding:
        10px
        12px;

    border:
        1px
        solid
        #ddd;

    border-radius: 8px;

    font-size: 14px;

    resize: vertical;

    min-height: 80px;

    box-sizing: border-box;

    font-family: inherit;

    transition:
        border-color
        0.2s;
}


#review-modal .form-group textarea:focus {

    border-color: #007bff;

    outline: none;

    box-shadow:
        0
        0
        0
        3px
        rgba(0, 123, 255, 0.1);
}


#review-modal
.form-group
textarea::placeholder {

    color: #bbb;
}


#review-modal .review-modal-buttons {

    display: flex;

    gap: 10px;

    margin-top: 18px;
}


#review-modal
.review-modal-buttons
button {

    flex: 1;

    padding:
        10px
        16px;

    border: none;

    border-radius: 8px;

    font-size: 14px;

    font-weight: 600;

    cursor: pointer;

    transition:
        background
        0.2s
        ease;

    font-family: inherit;
}


#review-modal
.review-modal-buttons
.btn-secondary {

    background: #f1f1f1;

    color: #555;
}


#review-modal
.review-modal-buttons
.btn-secondary:hover {

    background: #e5e5e5;

    transform: none;
}


#review-modal
.review-modal-buttons
.btn-primary {

    background: #007bff;

    color: white;
}


#review-modal
.review-modal-buttons
.btn-primary:hover {

    background: #0056b3;

    transform: none;
}


@media (max-width: 500px) {

    #review-modal .review-modal-box {

        padding: 20px;

        width: 95%;
    }


    #review-modal
    .star-rating
    label {

        font-size: 30px;
    }


    #review-modal
    .review-modal-buttons {

        flex-direction: column;
    }


    #review-modal
    .review-modal-box
    h2 {

        font-size: 18px;
    }

}

</style>


<div id="review-modal">

    <div class="review-modal-box">


        <!-- Close -->

        <button
            type="button"
            class="review-modal-close"
            onclick="closeReviewPopup()"
            aria-label="Close"
        >

            ✕

        </button>


        <!-- Icon -->

        <div class="review-modal-icon">
            ⭐
        </div>


        <h2>
            How was your purchase?
        </h2>


        <p class="review-description">

            Please rate and review
            the products you purchased.

        </p>


        <!-- =================================================
             REVIEW FORM
        ================================================== -->

        <form
            method="post"
            id="reviewForm"
            onsubmit="return validateReviewForm()"
        >


            <input
                type="hidden"
                name="btn"
                value="review"
            >


            <input
                type="hidden"
                name="order_id"
                value="<?= encode($o->id) ?>"
            >


            <!-- =================================================
                 PRODUCT
            ================================================== -->

            <div class="form-group">

                <label for="review-product">

                    Product

                </label>


                <select
                    name="product_id"
                    id="review-product"
                    required
                >

                    <option value="">

                        Select a product

                    </option>


                    <?php foreach ($review_items as $ri): ?>

                        <option
                            value="<?= encode($ri['id']) ?>"
                        >

                            <?= encode($ri['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- =================================================
                 STAR RATING
            ================================================== -->

            <div class="form-group">

                <label>
                    Rating
                </label>


                <div class="star-rating-wrapper">


                    <div
                        class="star-rating"
                        id="starRating"
                    >


                        <!-- 5 Stars -->

                        <input
                            type="radio"
                            name="rating"
                            id="star5"
                            value="5"
                            required
                        >

                        <label
                            for="star5"
                            title="5 stars - Excellent"
                        >
                            ★
                        </label>


                        <!-- 4 Stars -->

                        <input
                            type="radio"
                            name="rating"
                            id="star4"
                            value="4"
                        >

                        <label
                            for="star4"
                            title="4 stars - Good"
                        >
                            ★
                        </label>


                        <!-- 3 Stars -->

                        <input
                            type="radio"
                            name="rating"
                            id="star3"
                            value="3"
                        >

                        <label
                            for="star3"
                            title="3 stars - Average"
                        >
                            ★
                        </label>


                        <!-- 2 Stars -->

                        <input
                            type="radio"
                            name="rating"
                            id="star2"
                            value="2"
                        >

                        <label
                            for="star2"
                            title="2 stars - Poor"
                        >
                            ★
                        </label>


                        <!-- 1 Star -->

                        <input
                            type="radio"
                            name="rating"
                            id="star1"
                            value="1"
                        >

                        <label
                            for="star1"
                            title="1 star - Terrible"
                        >
                            ★
                        </label>


                    </div>


                    <div
                        class="star-rating-text"
                        id="ratingText"
                    >

                        Tap a star to rate

                    </div>


                </div>

            </div>


            <!-- =================================================
                 COMMENT
            ================================================== -->

            <div class="form-group">

                <label for="review-comment">

                    Your Review

                </label>


                <textarea
                    id="review-comment"
                    name="comment"
                    rows="4"
                    maxlength="1000"
                    placeholder="Tell us what you think about this product..."
                    required
                ></textarea>

            </div>


            <!-- =================================================
                 BUTTONS
            ================================================== -->

            <div class="review-modal-buttons">


                <button
                    type="button"
                    class="btn-secondary"
                    onclick="closeReviewPopup()"
                >

                    Maybe Later

                </button>


                <button
                    type="submit"
                    class="btn-primary"
                >

                    Submit Review

                </button>


            </div>


        </form>

    </div>

</div>


<?php endif; ?>


<?php endif; ?>


<script>

function printReceipt() {

    window.print();

}


function openEmailPopup() {

    const modal =
        document.getElementById(
            'email-modal'
        );


    const input =
        document.getElementById(
            'receipt-email'
        );


    const error =
        document.getElementById(
            'email-error'
        );


    error.textContent = '';

    modal.style.display = 'flex';


    setTimeout(function() {

        input.focus();

    }, 100);

}


function closeEmailPopup() {

    const modal =
        document.getElementById(
            'email-modal'
        );


    const input =
        document.getElementById(
            'receipt-email'
        );


    const error =
        document.getElementById(
            'email-error'
        );


    modal.style.display = 'none';

    input.value = '';

    error.textContent = '';

}


function submitEmailReceipt() {

    const input =
        document.getElementById(
            'receipt-email'
        );


    const error =
        document.getElementById(
            'email-error'
        );


    const email =
        input.value.trim();


    // ---------------------------------------------------------
    // Validate empty email
    // ---------------------------------------------------------

    if (email === '') {

        error.textContent =
            'Please enter your email address.';

        input.focus();

        return;

    }


    // ---------------------------------------------------------
    // Validate email format
    // ---------------------------------------------------------

    const emailPattern =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


    if (!emailPattern.test(email)) {

        error.textContent =
            'Please enter a valid email address.';

        input.focus();

        return;

    }


    // ---------------------------------------------------------
    // Get PHP order information
    // ---------------------------------------------------------

    const orderId =
        <?= json_encode(
            (string)$o->id
        ) ?>;


    const datetime =
        <?= json_encode(
            (string)$o->datetime
        ) ?>;


    const subtotal =
        <?= json_encode(
            sprintf(
                '%.2f',
                ($o->subtotal ?? 0) > 0
                    ? $o->subtotal
                    : $o->total
            )
        ) ?>;


    const discount =
        <?= json_encode(
            sprintf(
                '%.2f',
                $o->discount ?? 0
            )
        ) ?>;


    const total =
        <?= json_encode(
            sprintf(
                '%.2f',
                $o->total
            )
        ) ?>;


    // ---------------------------------------------------------
    // Build receipt
    // ---------------------------------------------------------

    let receipt = '';


    receipt +=
        'COFFEE SHOP\n';

    receipt +=
        '================================\n';

    receipt +=
        'E-RECEIPT\n';

    receipt +=
        '================================\n\n';


    receipt +=
        'Order ID: ' +
        orderId +
        '\n';


    receipt +=
        'Date & Time: ' +
        datetime +
        '\n\n';


    // ---------------------------------------------------------
    // Items
    // ---------------------------------------------------------

    receipt +=
        'PURCHASED ITEMS\n';

    receipt +=
        '--------------------------------\n';


    <?php foreach ($arr as $i): ?>

        receipt +=

            <?= json_encode(
                (string)$i->name
            ) ?>

            + ' x'

            + <?= (int)$i->unit ?>

            + ' - RM '

            + <?= json_encode(
                sprintf(
                    '%.2f',
                    $i->subtotal
                )
            ) ?>

            + '\n';

    <?php endforeach; ?>


    // ---------------------------------------------------------
    // Summary
    // ---------------------------------------------------------

    receipt += '\n';

    receipt +=
        '--------------------------------\n';


    receipt +=
        'Subtotal: RM ' +
        subtotal +
        '\n';


    receipt +=
        'Discount: - RM ' +
        discount +
        '\n';


    <?php if (!empty($o->voucher_code)): ?>

        receipt +=
            'Voucher: ' +

            <?= json_encode(
                (string)$o->voucher_code
            ) ?>

            + '\n';

    <?php endif; ?>


    <?php if (!empty($o->points_used)): ?>

        receipt +=
            'Points Used: ' +

            <?= (int)$o->points_used ?>

            + '\n';

    <?php endif; ?>


    <?php if (!empty($o->points_earned)): ?>

        receipt +=
            'Points Earned: +' +

            <?= (int)$o->points_earned ?>

            + '\n';

    <?php endif; ?>


    receipt += '\n';


    receipt +=
        'TOTAL: RM ' +
        total +
        '\n';


    receipt +=
        '================================\n\n';


    receipt +=
        'Thank you for your purchase!\n';


    receipt +=
        'Please keep this receipt for your records.\n';


    // ---------------------------------------------------------
    // Email subject
    // ---------------------------------------------------------

    const subject =
        'E-Receipt #' +
        orderId +
        ' - Coffee Shop';


    // ---------------------------------------------------------
    // Create mailto
    // ---------------------------------------------------------

    const mailto =

        'mailto:' +

        encodeURIComponent(email) +

        '?subject=' +

        encodeURIComponent(subject) +

        '&body=' +

        encodeURIComponent(receipt);


    // ---------------------------------------------------------
    // Open email application
    // ---------------------------------------------------------

    window.location.href = mailto;


    // ---------------------------------------------------------
    // Close popup
    // ---------------------------------------------------------

    closeEmailPopup();

}


const emailModal =
    document.getElementById(
        'email-modal'
    );


if (emailModal) {

    emailModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeEmailPopup();

            }

        }
    );

}


document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeEmailPopup();

            closeReviewPopup();

        }

    }
);


document
    .querySelectorAll(
        '#starRating input'
    )
    .forEach(
        function(input) {

            input.addEventListener(
                'change',
                function() {

                    const ratingText =
                        document.getElementById(
                            'ratingText'
                        );


                    const labels = {

                        '5':
                            '⭐⭐⭐⭐⭐ Excellent!',

                        '4':
                            '⭐⭐⭐⭐ Good',

                        '3':
                            '⭐⭐⭐ Average',

                        '2':
                            '⭐⭐ Poor',

                        '1':
                            '⭐ Terrible'

                    };


                    ratingText.textContent =
                        labels[this.value]
                        ||
                        'Tap a star to rate';


                    ratingText.className =
                        'star-rating-text active';

                }
            );

        }
    );


function closeReviewPopup() {

    const modal =
        document.getElementById(
            'review-modal'
        );


    if (modal) {

        modal.remove();

    }

}


document.addEventListener(
    'click',
    function(event) {

        const modal =
            document.getElementById(
                'review-modal'
            );


        if (
            modal &&
            event.target === modal
        ) {

            closeReviewPopup();

        }

    }
);


function validateReviewForm() {

    const product =
        document.getElementById(
            'review-product'
        ).value;


    const rating =
        document.querySelector(
            'input[name="rating"]:checked'
        );


    const comment =
        document
            .getElementById(
                'review-comment'
            )
            .value
            .trim();


    // ---------------------------------------------------------
    // Product
    // ---------------------------------------------------------

    if (!product) {

        alert(
            'Please select a product.'
        );

        document
            .getElementById(
                'review-product'
            )
            .focus();

        return false;

    }


    // ---------------------------------------------------------
    // Rating
    // ---------------------------------------------------------

    if (!rating) {

        alert(
            'Please select a rating by clicking on a star.'
        );

        return false;

    }


    // ---------------------------------------------------------
    // Comment
    // ---------------------------------------------------------

    if (!comment) {

        alert(
            'Please write a review.'
        );

        document
            .getElementById(
                'review-comment'
            )
            .focus();

        return false;

    }


    return true;

}


</script>


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

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


<?php
$order_status = $o->status ?? 'completed';
$payment_labels = [
    'completed' => 'Paid',
    'pending'   => 'Pending Cancellation',
    'cancelled' => 'Cancelled',
    'refunded'  => 'Refunded',
];
$payment_label = $payment_labels[$order_status] ?? ucfirst($order_status);
$item_placeholder_src = photo_src('0.jpg', '0.jpg');
?>

<div class="order-detail-page">

<header class="order-detail-header">
    <div class="order-detail-header-top">
        <h1>Order #<?= encode($o->id) ?></h1>
        <?php if ($order_status === 'pending'): ?>
            <span class="badge-status process">Pending Cancellation</span>
        <?php elseif ($order_status === 'cancelled'): ?>
            <span class="badge-status danger">Cancelled</span>
        <?php elseif ($order_status === 'refunded'): ?>
            <span class="badge-status neutral">Refunded</span>
        <?php else: ?>
            <span class="badge-status success">Completed</span>
        <?php endif ?>
    </div>
    <dl class="order-detail-meta">
        <div>
            <dt>Date &amp; Time</dt>
            <dd><?= encode($o->datetime) ?></dd>
        </div>
        <div>
            <dt>Payment Status</dt>
            <dd><?= encode($payment_label) ?></dd>
        </div>
        <div>
            <dt>Number of Items</dt>
            <dd><?= encode($o->count) ?></dd>
        </div>
    </dl>
</header>

<div class="receipt-container order-detail-card">

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

            <h3 class="order-items-heading">Purchased Items</h3>

            <section class="order-items" aria-label="Purchased items">

                <?php foreach ($arr as $i):
                    $photo_name = trim((string) ($i->photo ?? ''));
                    $has_thumb = false;
                    if ($photo_name !== '' && $photo_name !== 'null') {
                        foreach (['products', 'rewards', 'photos'] as $folder) {
                            if (is_file(__DIR__ . '/../' . $folder . '/' . $photo_name)) {
                                $has_thumb = true;
                                break;
                            }
                        }
                    }
                    $thumb_src = $has_thumb ? photo_src($i->photo, '0.jpg') : $item_placeholder_src;
                ?>
                <article class="order-item">
                    <div class="order-item-thumb">
                        <?php if ($has_thumb): ?>
                            <img
                                src="<?= $thumb_src ?>"
                                alt="<?= encode($i->name) ?>"
                                onerror="this.onerror=null;this.remove();this.parentElement.querySelector('.order-item-thumb-placeholder').hidden=false;"
                            >
                        <?php endif ?>
                        <span class="order-item-thumb-placeholder" <?= $has_thumb ? 'hidden' : '' ?>>No Image</span>
                    </div>
                    <div class="order-item-body">
                        <div class="order-item-name"><?= encode($i->name) ?></div>
                        <div class="order-item-meta">
                            RM <?= sprintf('%.2f', $i->price) ?> &times; <?= encode($i->unit) ?>
                        </div>
                    </div>
                    <div class="order-item-subtotal">
                        RM <?= sprintf('%.2f', $i->subtotal) ?>
                    </div>
                </article>
                <?php endforeach ?>

            </section>


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


    <div class="order-detail-actions no-print">

        <button
            type="button"
            class="email-button"
            onclick="openEmailPopup()"
        >
            Send E-Receipt
        </button>

        <button
            type="button"
            class="print-button"
            onclick="printReceipt()"
        >
            Print E-Receipt
        </button>

        <button
            type="button"
            class="history-button btn-history"
            data-get="history.php"
        >
            &larr; Back to Order History
        </button>

    </div>

</div>

<?php if ($_user->role == 'Admin' && ($order_status === 'pending')): ?>
<section class="order-actions-card no-print">
    <h2>Order Actions</h2>
    <form method="post" onsubmit="return confirm('Approve this cancellation?&#10;Stock will be restocked and points refunded.');">
        <input type="hidden" name="action" value="approve_cancel">
        <div class="order-actions-buttons">
            <button type="submit">Approve Cancellation</button>
        </div>
    </form>
    <form method="post" onsubmit="return confirm('Reject this cancellation request?&#10;The order will be restored to completed.');" style="margin-top:12px;">
        <input type="hidden" name="action" value="reject_cancel">
        <div class="order-actions-buttons">
            <button type="submit" class="danger">Reject Cancellation</button>
        </div>
    </form>
</section>
<?php elseif ($_user->role != 'Admin' && $o->user_id == $_user->id && is_order_cancellable($o->id)): ?>
<section class="order-actions-card no-print">
    <h2>Order Actions</h2>
    <form method="post" onsubmit="return confirm('Request to cancel this order?&#10;It will be pending until an admin approves.');">
        <input type="hidden" name="action" value="cancel">
        <label for="cancel-reason">Reason for cancellation (optional)</label>
        <input type="text" id="cancel-reason" name="reason" maxlength="255" placeholder="Optional reason">
        <div class="order-actions-buttons">
            <button type="submit" class="danger">Request Cancellation</button>
        </div>
    </form>
</section>
<?php endif ?>


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

    modal.classList.add('is-open');
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


    modal.classList.remove('is-open');
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

</div><!-- .order-detail-page -->

<?php
include '../_foot.php';

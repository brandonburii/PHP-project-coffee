<?php
include '../_base.php';

// ----------------------------------------------------------------------------
// POST HANDLING
// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');

    // ------------------------------------------------------------
    // Add / Remove Favourite
    // ------------------------------------------------------------
    if ($btn == 'wishlist') {
        auth('Member');

        $id = req('id');

        if ($id != '' && is_exists($id, 'product', 'id')) {
            if (is_wishlisted($id)) {
                remove_wishlist($id);
                temp('info', 'Removed from favourites');
            }
            else {
                add_wishlist($id);
                temp('info', 'Added to favourites ❤️');
            }
        }

        redirect();
    }

    // ------------------------------------------------------------
    // Compare
    // ------------------------------------------------------------
    if ($btn == 'compare') {
        $id = req('id');

        if ($id != '' && is_exists($id, 'product', 'id')) {
            if (!toggle_compare($id)) {
                temp('info', 'You can compare up to 3 products only');
            }
        }

        redirect();
    }

    // ------------------------------------------------------------
    // Add to Cart
    // ------------------------------------------------------------
    $id   = req('id');
    $unit = req('unit');

    $stm = $_db->prepare('SELECT stock FROM product WHERE id = ?');
    $stm->execute([$id]);

    if ((int) $stm->fetchColumn() < 1) {
        temp('info', 'This product is out of stock');
        redirect();
    }

    audit(
        'Cart',
        'Added product to cart',
        "Added product ID $id with quantity $unit from product detail page"
    );

    update_cart($id, $unit);
    redirect();
}

// ----------------------------------------------------------------------------
// GET PRODUCT
// ----------------------------------------------------------------------------

$id = req('id');

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);

$p = $stm->fetch();

if (!$p) {
    redirect('list.php');
}

audit(
    'Products',
    'Viewed Product',
    "Viewed product ID: {$p->id}, Name: {$p->name}"
);

add_recent($p->id);

// ----------------------------------------------------------------------------
// GET PRODUCT IMAGES FOR SLIDER
// ----------------------------------------------------------------------------
$product_images = get_product_images($p->name);
if (empty($product_images)) {
    $product_images = [$p->photo];
}
$images = array_map(function($path) {
    return (object)['image_path' => $path];
}, $product_images);

// ----------------------------------------------------------------------------
// CUSTOMERS ALSO BOUGHT
// ----------------------------------------------------------------------------

$stm = $_db->prepare('
    SELECT p.*, COUNT(*) AS bought_together
    FROM item i1
    JOIN item i2
        ON i1.order_id = i2.order_id
        AND i2.product_id != i1.product_id
    JOIN product p
        ON p.id = i2.product_id
    WHERE i1.product_id = ?
        AND p.stock > 0
    GROUP BY p.id
    ORDER BY bought_together DESC
    LIMIT 4
');

$stm->execute([$p->id]);
$also_bought = $stm->fetchAll();

// Fallback products
if (empty($also_bought)) {
    $stm = $_db->prepare('
        SELECT *
        FROM product
        WHERE id != ?
            AND stock > 0
        ORDER BY id
        LIMIT 4
    ');

    $stm->execute([$p->id]);
    $also_bought = $stm->fetchAll();
}

// ----------------------------------------------------------------------------
// PAGE SETUP
// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => 'list.php',
    'Product Detail' => '',
];

$_title = 'Product | Detail';

include '../_head.php';

// ----------------------------------------------------------------------------
// PRODUCT VARIABLES
// ----------------------------------------------------------------------------

$cart = get_cart();

$unit = $cart[$p->id] ?? 0;

$max_unit = min($p->stock, 10);

$in_stock = $max_unit >= 1;

$on_sale = is_on_sale($p);

$price = product_price($p);

$in_compare = in_array($p->id, get_compare());
$rating_info = get_product_rating($p->id);
$product_reviews = function_exists('get_product_reviews')
    ? get_product_reviews($p->id)
    : [];

// Check favourite status
$in_wishlist = is_wishlisted($p->id);

// Count total images
$total_images = count($images);
?>
 <style>
.product-rating-summary {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 10px 0;
    padding: 8px 0;
}
.rating-stars {
    color: #ffc107;
    font-size: 18px;
}
.rating-count {
    color: #666;
    font-size: 14px;
}
.pd-meta {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}
.pd-meta .row {
    display: flex;
    padding: 4px 0;
    font-size: 14px;
}
.pd-meta .label {
    width: 120px;
    font-weight: bold;
    color: #666;
}
.pd-buy {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 20px 0;
}
.pd-buy select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.pd-buy button {
    padding: 8px 24px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.pd-buy button:hover {
    background: #0056b3;
}
.pd-buy button:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.secondary {
    padding: 8px 16px;
    background: #f8f9fa;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}
.secondary:hover {
    background: #e9ecef;
}

/* Product Reviews Styles */
.product-reviews {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #eee;
}
.product-reviews h2 {
    font-size: 20px;
    margin: 0 0 20px 0;
}
.empty-reviews {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 8px;
}
.review-big-star {
    font-size: 48px;
    margin-bottom: 10px;
}
.empty-reviews h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
}
.empty-reviews p {
    margin: 0;
    color: #666;
}
.review-summary {
    margin-bottom: 25px;
}
.review-average {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}
.review-average strong {
    font-size: 28px;
    color: #333;
}
.rating-stars.large {
    font-size: 22px;
    color: #ffc107;
}
.review-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.review-card {
    background: #f8f9fa;
    padding: 15px 20px;
    border-radius: 6px;
    border-left: 3px solid #ffc107;
}
.review-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.review-card-header strong {
    font-size: 14px;
}
.review-date {
    font-size: 12px;
    color: #999;
}
.review-stars {
    color: #ffc107;
    font-size: 14px;
    margin-bottom: 6px;
}
.review-card p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
}

/* Products Grid */
#products {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.product {
    background: white;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.product:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.thumb {
    position: relative;
    padding-top: 100%;
    background: #f8f9fa;
}
.thumb img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
}
.badge {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}
.tag-badge {
    background: #0d47a1;
}
.sale-badge {
    background: #dc3545;
}
.info {
    padding: 12px 14px;
}
.name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.price {
    font-weight: bold;
    font-size: 16px;
    color: #28a745;
}

/* Polished layout for Product Detail page */
.product-detail {
    display: flex;
    align-items: flex-start;
    gap: 40px;
    margin-top: 20px;
    width: 100%;
}
.product-detail .gallery {
    flex: 0 0 400px;
    max-width: 400px;
    width: 400px;
}
.product-detail .pd-info {
    flex: 1;
    min-width: 0;
}
.product-detail .pd-info h2 {
    font-size: 2rem;
    margin-top: 0;
    margin-bottom: 12px;
}
.product-detail .pd-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--line);
}
.product-detail .pd-price {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--coffee);
}
.product-detail .avail {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2e7d32;
    background: #e8f5e9;
    padding: 4px 12px;
    border-radius: 12px;
}
.product-detail .avail.out {
    color: var(--red);
    background: #ffebee;
}
.product-detail .pd-meta {
    background: #FAF8F5;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--line);
    margin-bottom: 24px;
}
.product-detail .pd-meta .row {
    display: flex;
    padding: 6px 0;
    border-bottom: 1px solid #f3eff0;
}
.product-detail .pd-meta .row:last-child {
    border-bottom: none;
}
.product-detail .pd-meta .label {
    width: 120px;
    font-weight: 700;
    color: var(--coffee-dark);
}
.product-detail .pd-buy {
    margin-bottom: 16px;
}
@media (max-width: 900px) {
    .product-detail {
        flex-direction: column;
        gap: 30px;
    }
    .product-detail .gallery {
        flex: 0 0 auto;
        max-width: 100%;
        width: 100%;
    }
}
</style>

<!-- ====================================================================== -->
<!-- PRODUCT DETAIL -->
<!-- ====================================================================== -->

<div class="product-detail">

    <!-- PRODUCT GALLERY WITH SLIDER -->
    <div class="gallery">

        <!-- Main Slider -->
        <div class="product-slider swiper-container">
            <div class="swiper-wrapper">
                
                <?php foreach ($images as $index => $img): ?>
                    <div class="swiper-slide">
                        <img 
                            src="<?= photo_src($img->image_path) ?>" 
                            alt="<?= encode($p->name) . ($index > 0 ? ' - view ' . ($index + 1) : '') ?>"
                            data-src="<?= photo_src($img->image_path) ?>"
                            loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                        >
                    </div>
                <?php endforeach ?>
                
            </div>
            
            <!-- Navigation Arrows (only show if more than 1 image) -->
            <?php if ($total_images > 1): ?>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            <?php endif ?>
            
            <!-- Pagination Dots (only show if more than 1 image) -->
            <?php if ($total_images > 1): ?>
                <div class="swiper-pagination"></div>
            <?php endif ?>
        </div>

        <!-- Thumbnail Strip (only show if more than 1 image) -->
        <?php if ($total_images > 1): ?>
            <div class="thumbnails swiper-thumb-container">
                <div class="swiper-wrapper">
                    <?php foreach ($images as $index => $img): ?>
                        <div class="swiper-slide thumb-slide">
                            <img 
                                src="<?= photo_src($img->image_path) ?>" 
                                alt="Thumbnail <?= $index + 1 ?>"
                            >
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>


    <!-- PRODUCT INFORMATION -->
    <div class="pd-info">

        <h2>

            <?= encode($p->name) ?>

            <?php if (!empty($p->tag)): ?>

                <span class="badge-status process">
                    <?= encode($p->tag) ?>
                </span>

            <?php endif ?>


            <?php if ($on_sale && $in_stock): ?>

                <span class="badge-status danger">
                    Flash Sale
                </span>

            <?php endif ?>

        </h2>


        <!-- PRICE -->
        <div class="price-row pd-price-row">

            <div class="pd-price">

                <?php if ($on_sale && $in_stock): ?>

                    <span
                        style="
                            text-decoration:line-through;
                            color:var(--muted);
                            font-size:1rem;
                            font-weight:500;
                            margin-right:8px;
                        "
                    >
                        RM <?= sprintf('%.2f', $p->price) ?>
                    </span>

                <?php endif ?>

                RM <?= sprintf('%.2f', $price) ?>

            </div>


            <span class="avail <?= $in_stock ? '' : 'out' ?>">

                <?= $in_stock
                    ? $p->stock . ' available'
                    : 'Unavailable'
                ?>

            </span>

        </div>

        <div class="product-rating-summary">

        <!-- RATING STARS -->
            <span class="rating-stars">
                <?php
                $rounded_rating = round($rating_info['average']);

                for ($i = 1; $i <= 5; $i++):
                ?>
                    <?= $i <= $rounded_rating ? '★' : '☆' ?>
                <?php endfor ?>
            </span>

            <strong>
                <?= sprintf('%.1f', $rating_info['average']) ?>
            </strong>

            <span class="rating-count">
                (<?= $rating_info['total'] ?> review<?= $rating_info['total'] == 1 ? '' : 's' ?>)
            </span>

        </div>

        <!-- DESCRIPTION -->
        <?php if (!empty($p->description)): ?>

            <p style="color:var(--muted); line-height:1.6;">

                <?= encode($p->description) ?>

            </p>

        <?php endif ?>


        <!-- PRODUCT META -->
        <div class="pd-meta">

            <div class="row">

                <span class="label">
                    Product ID
                </span>

                <span>
                    <?= encode($p->id) ?>
                </span>

            </div>


            <?php if (!empty($p->origin)): ?>

                <div class="row">

                    <span class="label">
                        Origin
                    </span>

                    <span>
                        <?= encode($p->origin) ?>
                    </span>

                </div>

            <?php endif ?>


            <?php if (!empty($p->roast)): ?>

                <div class="row">

                    <span class="label">
                        Roast
                    </span>

                    <span>
                        <?= encode($p->roast) ?>
                    </span>

                </div>

            <?php endif ?>


            <?php if ($on_sale && $in_stock): ?>

                <div class="row">

                    <span class="label">
                        Sale Ends
                    </span>

                    <span>
                        <?= date(
                            'Y-m-d H:i',
                            strtotime($p->sale_end)
                        ) ?>
                    </span>

                </div>

            <?php endif ?>

        </div>


        <!-- ================================================================ -->
        <!-- ADD TO CART -->
        <!-- ================================================================ -->

        <?php if ($in_stock): ?>

            <form
                method="post"
                class="pd-buy ajax-cart"
            >

                <?php html_hidden(
                    'id',
                    "id='id_$p->id'"
                ) ?>


                <?php

                $row_units = array_combine(
                    range(1, $max_unit),
                    range(1, $max_unit)
                );

                html_select(
                    'unit',
                    $row_units,
                    null,
                    "id='unit_$p->id'"
                );

                ?>


                <button type="submit">

                    <?= icon('cart') ?>

                    Add to Cart

                </button>


                <?php if ($unit): ?>

                    <span class="badge-status success">

                        <?= $unit ?> in cart

                    </span>

                <?php endif ?>

            </form>

        <?php else: ?>

            <div class="pd-buy">

                <select disabled aria-label="Quantity unavailable">

                    <option>0</option>

                </select>


                <button type="button" disabled>

                    Sold Out

                </button>

            </div>

        <?php endif ?>


        <!-- ================================================================ -->
        <!-- FAVOURITE BUTTON -->
        <!-- ================================================================ -->

        <form
            method="post"
            style="margin-top:12px;"
        >

            <input
                type="hidden"
                name="btn"
                value="wishlist"
            >


            <input
                type="hidden"
                name="id"
                value="<?= encode($p->id) ?>"
            >


            <?php if ($in_wishlist): ?>

                <!-- Already Favourite -->

                <button
                    type="submit"
                    class="secondary"
                    style="
                        width:100%;
                        border-color:#c1856d;
                    "
                >

                    ♥ Remove from Favourites

                </button>

            <?php else: ?>

                <!-- Not Favourite -->

                <button
                    type="submit"
                    class="secondary"
                    style="width:100%;"
                >

                    ♡ Add to Favourites

                </button>

            <?php endif ?>

        </form>


        <!-- ================================================================ -->
        <!-- COMPARE -->
        <!-- ================================================================ -->

        <form
            method="post"
            style="margin-top:12px;"
        >

            <input
                type="hidden"
                name="btn"
                value="compare"
            >


            <input
                type="hidden"
                name="id"
                value="<?= encode($p->id) ?>"
            >


            <button
                type="submit"
                class="secondary"
            >

                <?= $in_compare
                    ? '✓ Remove from Compare'
                    : '+ Add to Compare'
                ?>

            </button>

        </form>


        <!-- ================================================================ -->
        <!-- NAVIGATION -->
        <!-- ================================================================ -->

        <p style="margin-top:22px;">

            <button
                class="secondary"
                data-get="list.php"
            >

                &larr; Back to Products

            </button>


            <?php if (get_compare()): ?>

                <button
                    data-get="compare.php"
                >

                    View Compare
                    (<?= count(get_compare()) ?>)

                </button>

            <?php endif ?>


            <?php if ($in_wishlist): ?>

                <button
                    data-get="wishlist.php"
                >

                    ❤️ View Favourites

                </button>

            <?php endif ?>

        </p>

    </div>

</div>


<!-- ====================================================================== -->
<!-- CUSTOMERS ALSO BOUGHT -->
<!-- ====================================================================== -->

<?php if (!empty($also_bought)): ?>

    <h2 style="margin-top:40px;">

        Customers Also Bought

    </h2>


    <div id="products">

        <?php foreach ($also_bought as $ap): ?>

            <?php
            $ap_on_sale = is_on_sale($ap);
            ?>


            <div class="product">

                <!-- PRODUCT IMAGE -->
                <div class="thumb">

                    <?php if (!empty($ap->tag)): ?>

                        <span class="badge tag-badge">

                            <?= encode($ap->tag) ?>

                        </span>

                    <?php endif ?>


                    <?php if ($ap_on_sale): ?>

                        <span class="badge sale-badge">

                            SALE

                        </span>

                    <?php endif ?>


                    <a href="/product/detail.php?id=<?= $ap->id ?>">
                        <img src="<?= photo_src($ap->photo) ?>" alt="<?= encode($ap->name) ?>">
                    </a>
                </div>


                <!-- PRODUCT INFO -->
                <div class="info">

                    <div class="name">
                        <a href="/product/detail.php?id=<?= $ap->id ?>" style="text-decoration:none; color:inherit;">
                            <?= encode($ap->name) ?>
                        </a>
                    </div>


                    <div class="price-row">

                        <div class="price">

                            RM
                            <?= sprintf(
                                '%.2f',
                                product_price($ap)
                            ) ?>

                        </div>

                    </div>


                    <button
                        class="secondary"
                        data-get="/product/detail.php?id=<?= $ap->id ?>"
                        style="width:100%;"
                    >

                        View

                    </button>

                </div>

            </div>

        <?php endforeach ?>

    </div>

<?php endif ?>

<!-- =========================================================
     CUSTOMER REVIEWS

<section class="product-reviews">

    <h2>
        Customer Reviews
    </h2>

    <?php if (empty($product_reviews)): ?>

        <div class="empty-reviews">
            <div class="review-big-star">⭐</div>

            <h3>No reviews yet</h3>

            <p>
                Be the first customer to review this product.
            </p>
        </div>

    <?php else: ?>

        <div class="review-summary">

            <div class="review-average">

                <strong>
                    <?= sprintf('%.1f', $rating_info['average']) ?>
                </strong>

                <div class="rating-stars large">
                    <?php
                    $rounded_rating = round($rating_info['average']);

                    for ($i = 1; $i <= 5; $i++):
                    ?>
                        <?= $i <= $rounded_rating ? '★' : '☆' ?>
                    <?php endfor ?>
                </div>

                <span>
                    <?= $rating_info['total'] ?> review<?= $rating_info['total'] == 1 ? '' : 's' ?>
                </span>

            </div>

        </div>


        <div class="review-list">

            <?php foreach ($product_reviews as $review): ?>

                <article class="review-card">

                    <div class="review-card-header">

                        <strong>
                            <?= encode($review->user_name) ?>
                        </strong>

                        <span class="review-date">
                            <?= date(
                                'd M Y',
                                strtotime($review->created_at)
                            ) ?>
                        </span>

                    </div>

                    <div class="review-stars">

                        <?php for ($i = 1; $i <= 5; $i++): ?>

                            <?= $i <= $review->rating
                                ? '★'
                                : '☆'
                            ?>

                        <?php endfor ?>

                    </div>

                    <p>
                        <?= nl2br(
                            encode($review->review_text ?? $review->comment ?? '')
                        ) ?>
                    </p>

                </article>

            <?php endforeach ?>

        </div>

    <?php endif ?>

</section>

<!-- ====================================================================== -->
<!-- SWIPER.JS SCRIPTS -->
<!-- ====================================================================== -->

<!-- Include Swiper.js CSS & JS from CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    <?php if ($total_images > 1): ?>
    // --- Thumbnail Slider ---
    const thumbSwiper = new Swiper('.thumbnails', {
        slidesPerView: 'auto',
        spaceBetween: 10,
        freeMode: true,
        watchSlidesProgress: true,
        breakpoints: {
            768: {
                spaceBetween: 12,
            }
        }
    });
    <?php endif; ?>
    
    // --- Main Slider ---
    const mainSwiper = new Swiper('.product-slider', {
        loop: <?= $total_images > 1 ? 'true' : 'false' ?>,
        slidesPerView: 1,
        spaceBetween: 0,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
            dynamicBullets: <?= $total_images > 5 ? 'true' : 'false' ?>,
        },
        <?php if ($total_images > 1): ?>
        thumbs: {
            swiper: thumbSwiper,
        },
        <?php endif; ?>
        // Auto-play disabled (better for conversion)
        autoplay: false,
        // Speed of transition
        speed: 300,
        // Zoom on click
        on: {
            click: function (swiper, event) {
                const slide = swiper.slides[swiper.activeIndex];
                const img = slide.querySelector('img');
                if (img) {
                    openZoom(img.src);
                }
            }
        }
    });

    // --- Zoom/Lightbox Function ---
    let zoomOverlay = null;
    let currentZoomIndex = 0;

    function openZoom(src) {
        // Find current image index
        <?php if ($total_images > 1): ?>
        const slides = document.querySelectorAll('.product-slider .swiper-slide img');
        slides.forEach((img, index) => {
            if (img.src === src) {
                currentZoomIndex = index;
            }
        });
        <?php endif; ?>

        if (!zoomOverlay) {
            zoomOverlay = document.createElement('div');
            zoomOverlay.className = 'zoom-overlay';
            zoomOverlay.innerHTML = `
                <span class="close-zoom">&times;</span>
                <img src="${src}" alt="Zoomed view">
                <?php if ($total_images > 1): ?>
                <div class="zoom-counter">${currentZoomIndex + 1} / <?= $total_images ?></div>
                <?php endif; ?>
            `;
            document.body.appendChild(zoomOverlay);
            
            // Close on click outside image
            zoomOverlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeZoom();
                }
            });
            
            // Close with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && zoomOverlay.classList.contains('active')) {
                    closeZoom();
                }
                // Navigate with arrow keys in zoom
                <?php if ($total_images > 1): ?>
                if (e.key === 'ArrowLeft' && zoomOverlay.classList.contains('active')) {
                    navigateZoom(-1);
                }
                if (e.key === 'ArrowRight' && zoomOverlay.classList.contains('active')) {
                    navigateZoom(1);
                }
                <?php endif; ?>
            });
            
            // Close button
            zoomOverlay.querySelector('.close-zoom').addEventListener('click', function(e) {
                e.stopPropagation();
                closeZoom();
            });
        } else {
            zoomOverlay.querySelector('img').src = src;
            <?php if ($total_images > 1): ?>
            zoomOverlay.querySelector('.zoom-counter').textContent = (currentZoomIndex + 1) + ' / <?= $total_images ?>';
            <?php endif; ?>
        }
        zoomOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeZoom() {
        if (zoomOverlay) {
            zoomOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    <?php if ($total_images > 1): ?>
    function navigateZoom(direction) {
        const slides = document.querySelectorAll('.product-slider .swiper-slide img');
        const totalSlides = slides.length;
        currentZoomIndex = (currentZoomIndex + direction + totalSlides) % totalSlides;
        const newSrc = slides[currentZoomIndex].src;
        zoomOverlay.querySelector('img').src = newSrc;
        zoomOverlay.querySelector('.zoom-counter').textContent = (currentZoomIndex + 1) + ' / ' + totalSlides;
        
        // Also update main slider to match
        mainSwiper.slideTo(currentZoomIndex);
    }
    <?php endif; ?>

});
</script>
<?php
include '../_foot.php';

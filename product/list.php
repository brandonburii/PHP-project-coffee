<?php

include '../_base.php';

// ============================================================================
// POST
// ============================================================================

if (is_post()) {

    $btn = req('btn');


    // ========================================================================
    // Toggle Compare
    // ========================================================================

    if ($btn == 'compare') {

        $id = req('id');

        if ($id != '' && is_exists($id, 'product', 'id')) {

            if (!toggle_compare($id)) {
                temp(
                    'info',
                    'You can compare up to 3 products only'
                );
            }
        }

        redirect();
    }


    // ========================================================================
    // Toggle Wishlist
    // ========================================================================

    if ($btn == 'wishlist') {

        $id = req('id');


        // Check product exists
        if ($id != '' && is_exists($id, 'product', 'id')) {


            // Check login
            if (!isset($_user) || !$_user) {

                temp(
                    'info',
                    'Please login to add products to your favourites'
                );

                redirect();
            }


            // Only members
            if (($_user->role ?? '') != 'Member') {

                temp(
                    'info',
                    'Only members can use favourites'
                );

                redirect();
            }


            // Check current status
            $was_wishlisted = is_wishlisted($id);


            // Toggle
            toggle_wishlist($id);


            // Message
            if ($was_wishlisted) {

                temp(
                    'info',
                    'Product removed from favourites'
                );

            }
            else {

                temp(
                    'info',
                    'Product added to favourites ❤️'
                );
            }
        }

        redirect();
    }


    // ========================================================================
    // Add to Cart
    // ========================================================================

    $id   = req('id');
    $unit = req('unit');


    // Check product exists
    if ($id == '' || !is_exists($id, 'product', 'id')) {

        temp(
            'info',
            'Invalid product'
        );

        redirect();
    }


    // Check stock
    $stm = $_db->prepare(
        'SELECT stock FROM product WHERE id = ?'
    );

    $stm->execute([$id]);

    $stock = (int) $stm->fetchColumn();


    // Out of stock
    if ($stock < 1) {

        temp(
            'info',
            'This product is out of stock'
        );

        redirect();
    }


    // Add to cart
    audit(
        'Cart',
        'Added product to cart',
        "Added product ID $id with quantity $unit from product list page"
    );

    update_cart($id, $unit);

    redirect();
}


// ============================================================================
// GET PRODUCTS
// ============================================================================

$arr = $_db->query(
    'SELECT * FROM product'
);

$compare = get_compare();

$wishlist = get_wishlist();


// ============================================================================
// Page Settings
// ============================================================================

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products'  => '',
];

$_title = 'Product | List';

include '../_head.php';


// ============================================================================
// Compare Bar
// ============================================================================

?>

<?php if ($compare): ?>

<div
    class="card"
    style="
        margin-bottom:18px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
    "
>

    <div>

        <b><?= count($compare) ?></b>

        product(s) selected for comparison

        <span
            style="
                color:var(--muted);
                font-size:.85rem;
            "
        >
            (max 3)
        </span>

    </div>


    <button data-get="compare.php">

        Compare Now

    </button>

</div>

<?php endif ?>


<!-- =======================================================================
     PRODUCTS
     ======================================================================= -->

<div id="products">

<?php foreach ($arr as $p): ?>

    <?php

    // Cart
    $cart = get_cart();

    $id = $p->id;

    $unit = $cart[$p->id] ?? 0;


    // Stock
    $max_unit = min($p->stock, 10);

    $in_stock = $max_unit >= 1;


    // Sale
    $on_sale = is_on_sale($p);

    $price = product_price($p);


    // Compare
    $in_compare = in_array(
        $p->id,
        $compare
    );


    // Wishlist
    $in_wishlist = in_array(
        $p->id,
        $wishlist
    );


    // Photo
    $img = photo_url(
        $p->photo
    );

    ?>


    <div
        class="product <?= $in_stock ? '' : 'is-soldout' ?>"
    >


        <!-- ===============================================================
             PRODUCT IMAGE
             =============================================================== -->

        <div class="thumb">


            <?php if ($unit): ?>

                <span class="badge in-cart">

                    <?= $unit ?> in cart

                </span>

            <?php endif ?>


            <?php if (!empty($p->tag)): ?>

                <span class="badge tag-badge">

                    <?= encode($p->tag) ?>

                </span>

            <?php endif ?>


            <?php if ($on_sale && $in_stock): ?>

                <span class="badge sale-badge">

                    SALE

                </span>

            <?php endif ?>

            <a href="/product/detail.php?id=<?= $p->id ?>">
                <img
                    src="/photos/<?= $img ?>"
                    alt="<?= encode($p->name) ?>"
                >
            </a>

        </div>


        <!-- ===============================================================
             PRODUCT INFORMATION
             =============================================================== -->

        <div class="info">


            <!-- Product Name -->

            <div class="name">
                <a href="/product/detail.php?id=<?= $p->id ?>" style="text-decoration:none; color:inherit;">
                    <?= encode($p->name) ?>
                </a>
            </div>


            <!-- Origin / Roast -->

            <?php if (
                !empty($p->origin) ||
                !empty($p->roast)
            ): ?>

                <div class="meta-line">

                    <?= encode(
                        trim(
                            ($p->origin ?? '') .

                            (
                                !empty($p->origin) &&
                                !empty($p->roast)
                                ? ' · '
                                : ''
                            ) .

                            ($p->roast ?? '')
                        )
                    ) ?>

                </div>

            <?php endif ?>


            <!-- Price -->

            <div class="price-row">


                <div class="price">


                    <?php if (
                        $on_sale &&
                        $in_stock
                    ): ?>

                        <span class="price-was">

                            RM
                            <?= sprintf(
                                '%.2f',
                                $p->price
                            ) ?>

                        </span>

                    <?php endif ?>


                    RM
                    <?= sprintf(
                        '%.2f',
                        $price
                    ) ?>


                </div>


                <!-- Stock -->

                <span
                    class="avail <?= $in_stock ? '' : 'out' ?>"
                >

                    <?= $in_stock
                        ? $p->stock . ' available'
                        : 'Unavailable'
                    ?>

                </span>

            </div>


            <!-- ===========================================================
                 ADD TO CART
                 =========================================================== -->

            <?php if ($in_stock): ?>

                <form
                    method="post"
                    class="actions ajax-cart"
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

                        Add to Cart

                    </button>


                </form>

            <?php else: ?>


                <div class="actions">


                    <select
                        disabled
                        aria-label="Quantity unavailable"
                    >

                        <option>
                            0
                        </option>

                    </select>


                    <button
                        type="button"
                        disabled
                    >

                        Sold Out

                    </button>


                </div>

            <?php endif ?>


            <!-- ===========================================================
                 WISHLIST
                 =========================================================== -->

            <form
                method="post"
                class="wishlist-form"
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


                <button
                    type="submit"
                    class="wishlist-btn <?= $in_wishlist ? 'active' : '' ?>"
                >

                    <?php if ($in_wishlist): ?>

                        ♥ Remove from Favourites

                    <?php else: ?>

                        ♡ Add to Favourites

                    <?php endif ?>

                </button>


            </form>


            <!-- ===========================================================
                 COMPARE
                 =========================================================== -->

            <form
                method="post"
                style="margin-top:6px;"
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
                    style="
                        width:100%;
                        font-size:.8rem;
                    "
                >

                    <?= $in_compare
                        ? '✓ In Compare'
                        : '+ Compare'
                    ?>

                </button>


            </form>


        </div>

    </div>


<?php endforeach ?>

</div>


<?php

include '../_foot.php';

?>
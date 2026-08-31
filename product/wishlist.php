<?php

include '../_base.php';

// ============================================================================
// Authorization
// ============================================================================

auth('Member');


// ============================================================================
// Get Wishlist
// ============================================================================

$stm = $_db->prepare('

    SELECT
        w.id AS wishlist_id,
        w.created_at,

        p.id,
        p.name,
        p.price,
        p.sale_price,
        p.sale_start,
        p.sale_end,
        p.photo,
        p.description,
        p.origin,
        p.roast,
        p.tag,
        p.stock

    FROM wishlist AS w

    INNER JOIN product AS p
        ON w.product_id = p.id

    WHERE w.user_id = ?

    ORDER BY w.created_at DESC

');

$stm->execute([
    $_user->id
]);

$arr = $stm->fetchAll();


// ============================================================================
// Page Settings
// ============================================================================

$_breadcrumbs = [
    'Dashboard'  => '/',
    'Products'   => '/product/list.php',
    'Favourites' => '',
];

$_title = 'My Favourites';

include '../_head.php';

?>


<!-- ========================================================================
     PAGE
     ======================================================================== -->

<div class="wishlist-page">


    <!-- Header -->

    <div class="wishlist-title">

        <h2>
            My Favourites ❤️
        </h2>

        <p>
            Products you have saved for later.
        </p>

    </div>


    <?php if (!$arr): ?>


        <!-- ================================================================
             EMPTY WISHLIST
             ================================================================ -->

        <div class="card wishlist-empty">


            <div class="empty-heart">

                ♡

            </div>


            <h3>

                Your favourites list is empty

            </h3>


            <p>

                You haven't added any products
                to your favourites yet.

            </p>


            <button
                data-get="list.php"
            >

                Browse Products

            </button>


        </div>


    <?php else: ?>


        <!-- ================================================================
             WISHLIST PRODUCTS
             ================================================================ -->

        <div class="wishlist-list">


        <?php foreach ($arr as $p): ?>


            <?php

            // Product price
            $price = product_price($p);

            // Sale status
            $on_sale = is_on_sale($p);

            // Stock
            $in_stock = $p->stock > 0;

            // Image
            $img = photo_url(
                $p->photo
            );

            ?>


            <div class="wishlist-item">


                <!-- ========================================================
                     IMAGE
                     ======================================================== -->

                <div class="wishlist-image">

                    <img
                        src="/photos/<?= $img ?>"
                        alt="<?= encode($p->name) ?>"
                        data-get="/product/detail.php?id=<?= $p->id ?>"
                    >

                </div>


                <!-- ========================================================
                     INFORMATION
                     ======================================================== -->

                <div class="wishlist-info">


                    <h3>

                        <?= encode($p->name) ?>

                    </h3>


                    <?php if (!empty($p->tag)): ?>

                        <span class="wishlist-tag">

                            <?= encode($p->tag) ?>

                        </span>

                    <?php endif ?>


                    <?php if (!empty($p->description)): ?>

                        <p>

                            <?= encode(
                                $p->description
                            ) ?>

                        </p>

                    <?php endif ?>


                    <!-- Price -->

                    <div class="wishlist-price">


                        <?php if ($on_sale): ?>

                            <span class="price-was">

                                RM
                                <?= sprintf(
                                    '%.2f',
                                    $p->price
                                ) ?>

                            </span>

                        <?php endif ?>


                        <strong>

                            RM
                            <?= sprintf(
                                '%.2f',
                                $price
                            ) ?>

                        </strong>


                    </div>


                    <!-- Stock -->

                    <?php if ($in_stock): ?>

                        <span class="stock-available">

                            <?= $p->stock ?>
                            available

                        </span>

                    <?php else: ?>

                        <span class="stock-unavailable">

                            Out of stock

                        </span>

                    <?php endif ?>


                </div>


                <!-- ========================================================
                     ACTIONS
                     ======================================================== -->

                <div class="wishlist-actions">


                    <!-- Add to cart -->

                    <?php if ($in_stock): ?>

                        <form
                            method="post"
                            action="../order/cart.php"
                        >


                            <input
                                type="hidden"
                                name="id"
                                value="<?= encode($p->id) ?>"
                            >


                            <input
                                type="hidden"
                                name="unit"
                                value="1"
                            >


                            <button type="submit">

                                Add to Cart

                            </button>


                        </form>

                    <?php endif ?>


                    <!-- Remove -->

                    <form
                        method="post"
                        action="list.php"
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
                            class="remove-wishlist"
                        >

                            ♥ Remove

                        </button>


                    </form>


                </div>


            </div>


        <?php endforeach ?>


        </div>


    <?php endif ?>


</div>


<?php

include '../_foot.php';

?>
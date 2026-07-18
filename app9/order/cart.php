<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    // TODO
}

// ----------------------------------------------------------------------------

$_title = 'Order | Shopping Cart';
include '../_head.php';
?>

<style>
    .popup {
        width: 100px;
        height: 100px;
    }
</style>

<table class="table">
    <tr>
        <th>Id</th>
        <th>Name</th>
        <th>Price (RM)</th>
        <th>Unit</th>
        <th>Subtotal (RM)</th>
    </tr>

    <?php
        // TODO
        foreach ([] as $TODO):
            // TODO
    ?>
        <tr>
            <td><?= $p->id ?></td>
            <td><?= $p->name ?></td>
            <td class="right"><?= $p->price ?></td>
            <td>
                <form method="post">
                    <!-- TODO -->
                </form>            
            </td>
            <td class="right">
                <?= 'TODO' ?>
                <img src="/products/<?= $p->photo ?>" class="popup">
            </td>
        </tr>
    <?php endforeach ?>

    <tr>
        <th colspan="3"></th>
        <th class="right"><?= 'TODO' ?></th>
        <th class="right"><?= 'TODO' ?></th>
    </tr>
</table>

<p>
    <!-- TODO -->
    <?php if (1): ?>
        <button data-post="?btn=clear">Clear</button>

        <?php if (1): ?>
            <button data-post="checkout.php">Checkout</button>
        <?php  ?>
            Please <a href="/login.php">login</a> as member to checkout
        <?php endif ?>
    <?php endif ?>
</p>

<script>
    // TODO
</script>

<?php
include '../_foot.php';
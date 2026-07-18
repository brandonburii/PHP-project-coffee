<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$id = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();

if (!$p) {
    redirect('product_list.php');
}

if (is_get()) {
    $name        = $p->name;
    $description = $p->description;
    $price       = $p->price;
    $stock       = $p->stock;
}

if (is_post()) {
    $name        = req('name');
    $description = req('description');
    $price       = req('price');
    $stock       = req('stock');
    $photo       = get_file('photo');

    // Validate Name
    if ($name == '') {
        $_err['name'] = 'Required';
    }

    // Validate Description
    if ($description == '') {
        $_err['description'] = 'Required';
    }

    // Validate Price
    if ($price == '') {
        $_err['price'] = 'Required';
    }
    else if (!is_numeric($price) || $price < 0) {
        $_err['price'] = 'Must be a positive number';
    }

    // Validate Stock
    if ($stock == '') {
        $_err['stock'] = 'Required';
    }
    else if (filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
        $_err['stock'] = 'Must be a non-negative integer';
    }

    // Validate Photo (optional update)
    if ($photo && !str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = $p->photo;

        if ($photo) {
            // Delete old photo if it exists and is not the default
            if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
                unlink("../photos/$photo_name");
            }
            $photo_name = save_photo($photo, '../photos');
        }

        // Update database
        $stm = $_db->prepare('UPDATE product SET name = ?, description = ?, price = ?, photo = ?, stock = ? WHERE id = ?');
        $stm->execute([$name, $description, $price, $photo_name, $stock, $id]);

        audit('Products', 'Product Updated', "Updated product ID: $id, Name: $name, Price: RM$price, Stock: $stock");

        temp('info', 'Product updated successfully');
        redirect('product_list.php');
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Product Maintenance' => 'product_list.php',
    'Edit Product' => '',
];
$_title = 'Admin | Edit Product';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="id">Product ID</label>
    <b><?= $p->id ?></b>
    <?= err('id') ?>

    <label for="name">Product Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="description">Description</label>
    <?= html_textarea('description', 'maxlength="1000"') ?>
    <?= err('description') ?>

    <label for="price">Price (RM)</label>
    <?= html_text('price', 'maxlength="10"') ?>
    <?= err('price') ?>

    <label for="stock">Stock Quantity</label>
    <?= html_number('stock', 0, 9999, 1) ?>
    <?= err('stock') ?>

    <label for="photo">Product Image</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="/photos/<?= $p->photo ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Update Product</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';

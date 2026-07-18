<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

if (is_post()) {
    $id          = req('id');
    $name        = req('name');
    $description = req('description');
    $price       = req('price');
    $stock       = req('stock');
    $photo       = get_file('photo');

    // Validate ID
    if ($id == '') {
        $_err['id'] = 'Required';
    }
    else if (!preg_match('/^P\d{3}$/', $id)) {
        $_err['id'] = 'Invalid format (use P000 format)';
    }
    else if (!is_unique($id, 'product', 'id')) {
        $_err['id'] = 'ID already exists';
    }

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

    // Validate Photo
    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        // Save photo inside app/photos/
        $photo_name = save_photo($photo, '../photos');

        // Insert into database
        $stm = $_db->prepare('INSERT INTO product (id, name, description, price, photo, stock) VALUES (?, ?, ?, ?, ?, ?)');
        $stm->execute([$id, $name, $description, $price, $photo_name, $stock]);

        audit('Products', 'Product Created', "Created product ID: $id, Name: $name, Price: RM$price, Stock: $stock");

        temp('info', 'Product created successfully');
        redirect('product_list.php');
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Product Maintenance' => 'product_list.php',
    'Create Product' => '',
];
$_title = 'Admin | Create Product';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="id">Product ID</label>
    <?= html_text('id', 'maxlength="4" placeholder="P000"') ?>
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
        <img src="/photos/0.jpg">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Create Product</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';

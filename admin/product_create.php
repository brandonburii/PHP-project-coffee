<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

$tag_items = [
    'NEW'        => 'NEW',
    'BEST VALUE' => 'BEST VALUE',
    'LIMITED'    => 'LIMITED',
];
// Replace tag items with categories from DB when available
try {
    $cats = $_db->query("SELECT name FROM category WHERE active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);
    if ($cats) {
        $tag_items = array_combine($cats, $cats);
    }
} catch (Exception $e) {
    // Category table may not exist yet — ignore
}
$roast_items = [
    'Light'  => 'Light',
    'Medium' => 'Medium',
    'Dark'   => 'Dark',
];

if (is_post()) {
    $id          = req('id');
    $name        = req('name');
    $description = req('description');
    $origin      = req('origin');
    $roast       = req('roast');
    $tag         = req('tag');
    $price       = req('price');
    $stock       = req('stock');
    $sale_price  = req('sale_price');
    $sale_start  = req('sale_start');
    $sale_end    = req('sale_end');
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

    // Validate tag (optional)
    if ($tag != '' && !array_key_exists($tag, $tag_items)) {
        $_err['tag'] = 'Invalid tag';
    }

    // Validate roast (optional)
    if ($roast != '' && !array_key_exists($roast, $roast_items)) {
        $_err['roast'] = 'Invalid roast';
    }

    // Validate flash sale (optional — all or nothing)
    if ($sale_price != '' || $sale_start != '' || $sale_end != '') {
        if ($sale_price == '' || !is_numeric($sale_price) || $sale_price < 0) {
            $_err['sale_price'] = 'Required (positive number) when setting a sale';
        }
        else if (is_numeric($price) && $sale_price >= $price) {
            $_err['sale_price'] = 'Must be less than normal price';
        }
        if ($sale_start == '') {
            $_err['sale_start'] = 'Required when setting a sale';
        }
        if ($sale_end == '') {
            $_err['sale_end'] = 'Required when setting a sale';
        }
        else if ($sale_start != '' && strtotime($sale_end) < strtotime($sale_start)) {
            $_err['sale_end'] = 'Must be after start datetime';
        }
    }
    else {
        $sale_price = null;
        $sale_start = null;
        $sale_end   = null;
    }

    // Validate Photo
    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = save_photo($photo, '../photos');

        $stm = $_db->prepare('
            INSERT INTO product
                (id, name, description, origin, roast, tag, price, sale_price, sale_start, sale_end, photo, stock)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stm->execute([
            $id, $name, $description,
            $origin ?: null, $roast ?: null, $tag ?: null,
            $price,
            $sale_price,
            $sale_start ? date('Y-m-d H:i:s', strtotime($sale_start)) : null,
            $sale_end   ? date('Y-m-d H:i:s', strtotime($sale_end))   : null,
            $photo_name, $stock,
        ]);

        log_stock($id, 'added', 0, $stock);

        audit(
            'Products',
            'Product Created',
            "Created product ID: $id, Name: $name, Price: RM$price, Stock: $stock",
            null,
            [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'origin' => $origin ?: null,
                'roast' => $roast ?: null,
                'tag' => $tag ?: null,
                'price' => (float) $price,
                'sale_price' => $sale_price !== null ? (float) $sale_price : null,
                'sale_start' => $sale_start ? date('Y-m-d H:i:s', strtotime($sale_start)) : null,
                'sale_end' => $sale_end ? date('Y-m-d H:i:s', strtotime($sale_end)) : null,
                'stock' => (int) $stock,
            ]
        );

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

    <label for="origin">Origin</label>
    <?= html_text('origin', 'maxlength="100" placeholder="e.g. Ethiopia"') ?>
    <?= err('origin') ?>

    <label for="roast">Roast</label>
    <?= html_select('roast', $roast_items) ?>
    <?= err('roast') ?>

    <label for="tag">Tag</label>
    <?= html_select('tag', $tag_items) ?>
    <?= err('tag') ?>

    <label for="price">Price (RM)</label>
    <?= html_text('price', 'maxlength="10"') ?>
    <?= err('price') ?>

    <label for="stock">Stock Quantity</label>
    <?= html_number('stock', 0, 9999, 1) ?>
    <?= err('stock') ?>

    <label for="sale_price">Flash Sale Price (RM)</label>
    <?= html_text('sale_price', 'maxlength="10" placeholder="Optional"') ?>
    <?= err('sale_price') ?>

    <label for="sale_start">Sale Start</label>
    <input type="datetime-local" id="sale_start" name="sale_start" value="<?= encode($sale_start ?? '') ?>">
    <?= err('sale_start') ?>

    <label for="sale_end">Sale End</label>
    <input type="datetime-local" id="sale_end" name="sale_end" value="<?= encode($sale_end ?? '') ?>">
    <?= err('sale_end') ?>

    <label for="photo">Product Image</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="<?= photo_src('0.jpg') ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Create Product</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';

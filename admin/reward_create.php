<?php
include '../_base.php';

auth('Admin');

if (is_get()) {
    $active = 1;
    $sort_order = 0;
    $stock = 0;
}

if (is_post()) {
    $name        = req('name');
    $description = req('description');
    $points      = req('points');
    $stock       = req('stock');
    $sort_order  = req('sort_order');
    $active      = req('active');
    $photo       = get_file('photo');

    if ($name == '') $_err['name'] = 'Required';
    if ($description == '') $_err['description'] = 'Required';

    if ($points == '') {
        $_err['points'] = 'Required';
    }
    else if (filter_var($points, FILTER_VALIDATE_INT) === false || $points < 1) {
        $_err['points'] = 'Must be a positive integer';
    }

    if ($stock === '' || filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
        $_err['stock'] = 'Must be a non-negative integer';
    }

    if ($sort_order === '' || filter_var($sort_order, FILTER_VALIDATE_INT) === false) {
        $_err['sort_order'] = 'Must be an integer';
    }

    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = save_photo($photo, '../photos');
        $stm = $_db->prepare('
            INSERT INTO reward (name, description, photo, points, stock, active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stm->execute([$name, $description, $photo_name, $points, $stock, $active ? 1 : 0, $sort_order]);

        audit('Rewards', 'Reward Created', "Created reward: $name ($points pts)");
        temp('info', 'Reward created successfully');
        redirect('reward_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Reward Maintenance' => 'reward_list.php',
    'Create Reward' => '',
];
$_title = 'Admin | Create Reward';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="name">Reward Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="description">Description</label>
    <?= html_textarea('description', 'maxlength="500"') ?>
    <?= err('description') ?>

    <label for="points">Points Required</label>
    <?= html_number('points', 1, 999999, 1) ?>
    <?= err('points') ?>

    <label for="stock">Stock</label>
    <?= html_number('stock', 0, 9999, 1) ?>
    <?= err('stock') ?>

    <label for="sort_order">Display Order</label>
    <?= html_number('sort_order', 0, 9999, 1) ?>
    <?= err('sort_order') ?>

    <label for="active">Status</label>
    <?= html_checkbox('active', 'Active (enabled)') ?>
    <?= err('active') ?>

    <label for="photo">Image</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="<?= photo_src('0.jpg') ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Create Reward</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';

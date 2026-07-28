<?php
include '../_base.php';

auth('Admin');

$id = req('id');
$stm = $_db->prepare('SELECT * FROM reward WHERE id = ?');
$stm->execute([$id]);
$r = $stm->fetch();
if (!$r) redirect('reward_list.php');

if (is_get()) {
    $name        = $r->name;
    $description = $r->description;
    $points      = $r->points;
    $stock       = $r->stock;
    $sort_order  = $r->sort_order;
    $active      = $r->active;
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

    if ($points == '' || filter_var($points, FILTER_VALIDATE_INT) === false || $points < 1) {
        $_err['points'] = 'Must be a positive integer';
    }
    if ($stock === '' || filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
        $_err['stock'] = 'Must be a non-negative integer';
    }
    if ($sort_order === '' || filter_var($sort_order, FILTER_VALIDATE_INT) === false) {
        $_err['sort_order'] = 'Must be an integer';
    }
    if ($photo && !str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = $r->photo;
        if ($photo) {
            if ($photo_name && $photo_name !== '0.jpg' && file_exists("../photos/$photo_name")) {
                unlink("../photos/$photo_name");
            }
            $photo_name = save_photo($photo, '../photos');
        }

        $stm = $_db->prepare('
            UPDATE reward SET name=?, description=?, photo=?, points=?, stock=?, active=?, sort_order=?
            WHERE id=?
        ');
        $stm->execute([$name, $description, $photo_name, $points, $stock, $active ? 1 : 0, $sort_order, $id]);

        audit('Rewards', 'Reward Updated', "Updated reward ID $id: $name ($points pts)");
        temp('info', 'Reward updated successfully');
        redirect('reward_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Reward Maintenance' => 'reward_list.php',
    'Edit Reward' => '',
];
$_title = 'Admin | Edit Reward';
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
        <img src="/photos/<?= $r->photo ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Update Reward</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';

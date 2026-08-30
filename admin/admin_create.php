<?php
include '../_base.php';

auth('Admin');

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');
    $photo    = get_file('photo');

    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 6) {
        $_err['password'] = 'Min 6 characters';
    }

    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if ($name == '') {
        $_err['name'] = 'Required';
    }

    if (!$photo) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($photo->type, 'image/')) {
        $_err['photo'] = 'Invalid image type';
    }

    if (!$_err) {
        $photo_name = save_photo($photo, '../photos');

        $stm = $_db->prepare('INSERT INTO user (email, password, name, photo, role, active) VALUES (?, SHA1(?), ?, ?, ?, 1)');
        $stm->execute([$email, $password, $name, $photo_name, 'Admin']);
        $new_id = (int) $_db->lastInsertId();

        audit(
            'Admin',
            'Admin Created',
            "Created new Admin: $email, Name: $name",
            null,
            [
                'id' => $new_id,
                'email' => $email,
                'name' => $name,
                'role' => 'Admin',
                'active' => 1,
            ]
        );
        temp('info', 'Admin account created successfully');
        redirect('admin_list.php');
    }
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Admin Management' => 'admin_list.php',
    'Create Admin' => '',
];
$_title = 'Admin | Create Admin';
include '../_head.php';
?>

<form method="post" class="form" enctype="multipart/form-data">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <label for="password">Password</label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>

    <label for="confirm">Confirm Password</label>
    <?= html_password('confirm', 'maxlength="100"') ?>
    <?= err('confirm') ?>

    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label>Role</label>
    <b>Admin</b>
    <span></span>

    <label for="photo">Photo</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="/photos/0.jpg">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Create Admin</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php include '../_foot.php';

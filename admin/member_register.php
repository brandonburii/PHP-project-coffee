<?php
include '../_base.php';

// Authorization check (Admin only)
auth('Admin');

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');
    $role     = req('role');
    $photo    = get_file('photo');

    // Validate email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Email already exists';
    }

    // Validate password
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 6) {
        $_err['password'] = 'Min 6 characters';
    }

    // Validate confirm password
    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    // Validate name
    if ($name == '') {
        $_err['name'] = 'Required';
    }

    // Validate role
    if ($role != 'Member' && $role != 'Admin') {
        $_err['role'] = 'Invalid role';
    }

    // Validate photo
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
        $stm = $_db->prepare('INSERT INTO user (email, password, name, photo, role, active) VALUES (?, SHA1(?), ?, ?, ?, 1)');
        $stm->execute([$email, $password, $name, $photo_name, $role]);
        $new_id = (int) $_db->lastInsertId();

        if ($role == 'Admin') {
            audit(
                'Admin',
                'Admin Created',
                "Registered new Admin: $email, Name: $name",
                null,
                ['id' => $new_id, 'email' => $email, 'name' => $name, 'role' => 'Admin', 'active' => 1]
            );
        } else {
            audit(
                'Members',
                'Member Created',
                "Registered new Member: $email, Name: $name",
                null,
                ['id' => $new_id, 'email' => $email, 'name' => $name, 'role' => 'Member', 'active' => 1]
            );
        }

        temp('info', 'Member registered successfully');
        redirect('member_list.php');
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Member Maintenance' => 'member_list.php',
    'Register Member' => '',
];
$_title = 'Admin | Register Member';
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

    <label for="role">Role</label>
    <?= html_select('role', ['Member' => 'Member', 'Admin' => 'Admin'], null) ?>
    <?= err('role') ?>

    <label for="photo">Photo</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="<?= photo_src('0.jpg') ?>">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Register Member</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';

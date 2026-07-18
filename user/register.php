<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email = req('email');
    $password = req('password');
    $confirm = req('confirm');
    $name = req('name');
    $photo = get_file('photo');

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
        $stm = $_db->prepare('INSERT INTO user (email, password, name, photo, role) VALUES (?, SHA1(?), ?, ?, ?)');
        $stm->execute([$email, $password, $name, $photo_name, 'Member']);

        audit('Member', 'Registration', "New member registered: $email, Name: $name");

        temp('info', 'Registration successful! Please login.');
        redirect('/login.php');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Register Member';
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

    <label for="photo">Photo</label>
    <label class="upload">
        <?= html_file('photo', 'image/*') ?>
        <img src="/photos/0.jpg">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Register</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email    = req('email');
    $password = req('password');
    $confirm  = req('confirm');
    $name     = req('name');
    $f = get_file('photo');

    // Validate: email
    if (!$email) {
        $_err['email'] = 'Required';
    }
    else if (strlen($email) > 100) {
        $_err['email'] = 'Maximum 100 characters';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_unique($email, 'user', 'email')) {
        $_err['email'] = 'Duplicated';
    }

    // Validate: password
    if (!$password) {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 5 || strlen($password) > 100) {
        $_err['password'] = 'Between 5-100 characters';
    }

    // Validate: confirm
    if (!$confirm) {
        $_err['confirm'] = 'Required';
    }
    else if (strlen($confirm) < 5 || strlen($confirm) > 100) {
        $_err['confirm'] = 'Between 5-100 characters';
    }
    else if ($confirm != $password) {
        $_err['confirm'] = 'Not matched';
    }

    // Validate: name
    if (!$name) {
        $_err['name'] = 'Required';
    }
    else if (strlen($name) > 100) {
        $_err['name'] = 'Maximum 100 characters';
    }

    // Validate: photo (file)
    if (!$f) {
        $_err['photo'] = 'Required';
    }
    else if (!str_starts_with($f->type, 'image/')) {
        $_err['photo'] = 'Must be image';
    }
    else if ($f->size > 1 * 1024 * 1024) {
        $_err['photo'] = 'Maximum 1MB';
    }

    // DB operation
    if (!$_err) {
        // (1) Save photo
        $photo = save_photo($f, '../photos');
        
        // (2) Insert user (member)
        $stm = $_db->prepare('
            INSERT INTO user (email, password, name, photo, role)
            VALUES (?, SHA1(?), ?, ?, "Member")
        ');
        $stm->execute([$email, $password, $name, $photo]);

        temp('info', 'Record inserted');
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

    <label for="confirm">Confirm</label>
    <?= html_password('confirm', 'maxlength="100"') ?>
    <?= err('confirm') ?>

    <label for="name">Name</label>
    <?= html_text('name', 'maxlength="100"') ?>
    <?= err('name') ?>

    <label for="photo">Photo</label>
    <label class="upload" tabindex="0">
        <?= html_file('photo', 'image/*', 'hidden') ?>
        <img src="/images/photo.jpg">
    </label>
    <?= err('photo') ?>

    <section>
        <button>Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
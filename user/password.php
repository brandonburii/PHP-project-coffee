<?php
include '../_base.php';

// ----------------------------------------------------------------------------

auth();

if (is_post()) {
    $current  = req('current');
    $password = req('password');
    $confirm  = req('confirm');

    // Validate: current password
    if ($current == '') {
        $_err['current'] = 'Required';
    }
    else {
        $stm = $_db->prepare('SELECT password FROM user WHERE id = ?');
        $stm->execute([$_user->id]);
        $real_password = $stm->fetchColumn();

        if (sha1($current) !== $real_password) {
            $_err['current'] = 'Incorrect password';
        }
    }

    // Validate: new password
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 6) {
        $_err['password'] = 'Min 6 characters';
    }
    else if ($password == $current) {
        $_err['password'] = 'Cannot be same as current password';
    }

    // Validate: confirm password
    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE id = ?');
        $stm->execute([$password, $_user->id]);

        audit('Auth', 'Password Change', "Changed password successfully");

        temp('info', 'Password updated successfully');
        redirect('/');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Password';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="current">Current Password</label>
    <?= html_password('current', 'maxlength="100"') ?>
    <?= err('current') ?>

    <label for="password">New Password</label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>

    <label for="confirm">Confirm Password</label>
    <?= html_password('confirm', 'maxlength="100"') ?>
    <?= err('confirm') ?>

    <section>
        <button>Change Password</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
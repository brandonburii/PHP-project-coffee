<?php
include '../_base.php';

// ----------------------------------------------------------------------------

$token_id = req('id');

// Validate token and get user
$stm = $_db->prepare('
    SELECT t.*, u.email, u.name
    FROM token t
    JOIN user u ON t.user_id = u.id
    WHERE t.id = ? AND t.expire > NOW()
');
$stm->execute([$token_id]);
$t = $stm->fetch();

if (!$t) {
    temp('info', 'Invalid or expired token.');
    redirect('/login.php');
}

if (is_post()) {
    $password = req('password');
    $confirm  = req('confirm');

    // Validate: new password
    if ($password == '') {
        $_err['password'] = 'Required';
    }
    else if (strlen($password) < 6) {
        $_err['password'] = 'Min 6 characters';
    }

    // Validate: confirm password
    if ($confirm == '') {
        $_err['confirm'] = 'Required';
    }
    else if ($confirm !== $password) {
        $_err['confirm'] = 'Passwords do not match';
    }

    if (!$_err) {
        // Update password
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE id = ?');
        $stm->execute([$password, $t->user_id]);

        // Attribute audit log to the target user temporarily
        $_SESSION['user'] = (object)['id' => $t->user_id, 'name' => $t->name, 'role' => 'Member'];
        $_user = $_SESSION['user'];
        audit('Auth', 'Password Reset', "Password reset successfully via token for email: {$t->email}");
        unset($_SESSION['user']);
        $_user = null;

        // Delete token
        $stm = $_db->prepare('DELETE FROM token WHERE id = ?');
        $stm->execute([$token_id]);

        temp('info', 'Password reset successfully. Please login.');
        redirect('/login.php');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Reset Password';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="password">New Password</label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>

    <label for="confirm">Confirm Password</label>
    <?= html_password('confirm', 'maxlength="100"') ?>
    <?= err('confirm') ?>

    <section>
        <button>Reset Password</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
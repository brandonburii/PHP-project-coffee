<?php
include '_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email = req('email');
    $password = req('password');

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }

    // Validate: password
    if ($password == '') {
        $_err['password'] = 'Required';
    }

    // Login user
    if (!$_err) {
        $stm = $_db->prepare('
            SELECT * FROM user
            WHERE email = ? AND password = SHA1(?) AND active = 1
        ');
        $stm->execute([$email, $password]);
        $u = $stm->fetch();

        if ($u) {
            $_user = $u; // temporary assignment for audit logging
            audit('Auth', 'Login', "Logged in successfully as $email");
            temp('info', 'Login successfully');
            login($u);
        }
        else {
            $stm = $_db->prepare('
                SELECT * FROM user
                WHERE email = ? AND password = SHA1(?) AND active = 0
            ');
            $stm->execute([$email, $password]);
            if ($stm->fetch()) {
                $_err['password'] = 'Account disabled';
            }
            else {
                $_err['password'] = 'Not matched';
            }
            audit('Auth', 'Failed Login', "Failed login attempt for email: $email");
        }
    }
}

// ----------------------------------------------------------------------------

$_title = 'Login';
include '_head.php';
?>

<form method="post" class="form">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <label for="password">Password</label>
    <?= html_password('password', 'maxlength="100"') ?>
    <?= err('password') ?>

    <section>
        <button>Login</button>
        <button type="reset">Reset</button>
        <button type="button" class="secondary" data-get="/user/reset.php">Forgot Password?</button>
    </section>
</form>

<?php
include '_foot.php';
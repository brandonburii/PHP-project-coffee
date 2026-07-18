<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $email = req('email');

    // Validate: email
    if ($email == '') {
        $_err['email'] = 'Required';
    }
    else if (!is_email($email)) {
        $_err['email'] = 'Invalid email';
    }
    else if (!is_exists($email, 'user', 'email')) {
        $_err['email'] = 'Not exists';
    }

    // Send reset token (if valid)
    if (!$_err) {
        // TODO: (1) Select user
        $stm = $_db->prepare('SELECT 0 AS name');
        $stm->execute([]);
        $u = $stm->fetch();

        // TODO: (2) Generate token id
        $id = 0;

        // TODO: (3) Delete old and insert new token
        $stm = $_db->prepare('
            SELECT 0
        ');
        $stm->execute([]);

        // TODO: (4) Generate token url
        $url = 0;

        // TODO: (5) Send email
        $m = new stdClass();
        $m->Body = "
            <img src='cid:photo'
                 style='width: 200px; height: 200px;
                        border: 1px solid #333'>
            <p>Dear $u->name,<p>
            <h1 style='color: red'>Reset Password</h1>
            <p>
                Please click <a href='$url'>here</a>
                to reset your password.
            </p>
            <p>From, 😺 Admin</p>
        ";

        temp('info', 'Email sent');
        redirect('/');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Reset Password';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100"') ?>
    <?= err('email') ?>

    <section>
        <button>Submit</button>
        <button type="reset">Reset</button>
    </section>
</form>

<?php
include '../_foot.php';
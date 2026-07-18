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
    else {
        $stm = $_db->prepare('SELECT * FROM user WHERE email = ?');
        $stm->execute([$email]);
        $u = $stm->fetch();

        if (!$u) {
            $_err['email'] = 'Email not found';
        }
    }

    if (!$_err) {
        $token_id = sha1(uniqid(rand(), true));
        $expire = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Delete any existing tokens for this user
        $stm = $_db->prepare('DELETE FROM token WHERE user_id = ?');
        $stm->execute([$u->id]);

        // Insert new token
        $stm = $_db->prepare('INSERT INTO token (id, expire, user_id) VALUES (?, ?, ?)');
        $stm->execute([$token_id, $expire, $u->id]);

        audit('Auth', 'Password Reset Request', "Password reset token generated for email: $email");

        // Send email
        $url = base("user/token.php?id=$token_id");

        try {
            $m = get_mail();
            $m->addAddress($u->email, $u->name);
            $m->isHTML(true);
            $m->Subject = 'Reset Password';
            $m->Body = "
                <h1>Reset Password</h1>
                <p>Dear {$u->name},</p>
                <p>Please click the link below to reset your password:</p>
                <p><a href='$url'>$url</a></p>
                <p>This link will expire in 10 minutes.</p>
            ";
            $m->send();
            temp('info', 'Password reset email sent. Please check your inbox.');
            redirect('/');
        }
        catch (Exception $e) {
            // Fallback for development/offline mode
            temp('info', "SMTP failed. Reset Link: <a href='$url'>$url</a>");
            redirect('/');
        }
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
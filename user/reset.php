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
        // 1. Generate a 6-digit OTP instead of a long token string
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expire = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Delete any existing tokens for this user
        $stm = $_db->prepare('DELETE FROM token WHERE user_id = ?');
        $stm->execute([$u->id]);

        // Insert new OTP into the database
        $stm = $_db->prepare('INSERT INTO token (id, expire, user_id) VALUES (?, ?, ?)');
        $stm->execute([$otp, $expire, $u->id]);

        audit('Auth', 'Password Reset Request', "OTP generated for email: $email");

        // 2. Save the email in a session so the next page knows who is verifying the code
        $_SESSION['reset_email'] = $email;

        // 3. Send email containing the OTP
        try {
            $m = get_mail();
            $m->addAddress($u->email, $u->name);
            $m->isHTML(true);
            $m->Subject = 'Reset Password OTP';
            $m->Body = "
                <h1>Reset Password</h1>
                <p>Dear {$u->name},</p>
                <p>Your One-Time Password (OTP) to reset your password is:</p>
                <h2 style='letter-spacing: 5px; color: #5c7785;'>$otp</h2>
                <p>This code will expire in 10 minutes. Do not share this code with anyone.</p>
            ";
            $m->send();
            temp('info', 'An OTP has been sent to your email.');
            
            // Redirect to the new OTP verification page
            redirect('/user/verify_otp.php'); 
        }
        catch (Exception $e) {
            // Fallback for development/offline mode: shows the OTP on screen
            temp('info', "SMTP failed. Your OTP is: <b>$otp</b>");
            
            // Redirect to the new OTP verification page
            redirect('/user/verify_otp.php'); 
        }
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Reset Password';
include '../_head.php';
?>

<form method="post" class="form">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100" placeholder="you@email.com"') ?>
    <?= err('email') ?>

    <section>
        <button>Send OTP</button>
        <button type="reset">Reset</button>
        <button type="button" class="secondary" data-get="/login.php">Cancel</button>
    </section>
</form>

<?php
include '../_foot.php';
?>
<?php
include '../_base.php';

// Secure the page
$email = $_SESSION['reset_email'] ?? '';
if (!$email) {
    temp('err', 'Session lost or expired. Please request a new OTP.');
    redirect('reset.php');
}

// ----------------------------------------------------------------------------

if (is_post()) {
    $otp = req('otp');

    if ($otp == '') {
        $_err['otp'] = 'Required';
    } 
    else {
        // Fetch user ID
        $stm = $_db->prepare('SELECT id FROM user WHERE email = ?');
        $stm->execute([$email]);
        $u_id = $stm->fetchColumn();

        // Validate OTP existence
        $stm = $_db->prepare('SELECT * FROM token WHERE id = ? AND user_id = ?');
        $stm->execute([$otp, $u_id]);
        $token = $stm->fetch();

        if (!$token) {
            $_err['otp'] = 'Invalid OTP code.';
            audit('Auth', 'Failed OTP', "Failed OTP attempt for email: $email");
        } 
        // Manually check expiration in PHP to avoid MySQL timezone mismatches
        else if (strtotime($token->expire) < time()) {
            $_err['otp'] = 'This OTP has expired. Please request a new one.';
        }
    }

    if (!$_err) {
        // Mark session as OTP verified
        $_SESSION['otp_verified'] = true;
        temp('info', 'Code verified! You can now set your new password.');
        
        // Redirect to your specific file name
        redirect('password.php');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Verify OTP';
include '../_head.php';
?>

<div class="auth-card">
    <div class="auth-card-head">
        <h2>Verify Reset Code</h2>
        <p>We sent a 6-digit code to <strong><?= htmlspecialchars($email) ?></strong>.</p>
    </div>

    <form method="post" class="form auth-form">
        <label for="otp">6-Digit Code</label>
        <?= html_text('otp', 'maxlength="6" pattern="\d{6}" required placeholder="123456" style="letter-spacing: 4px; text-align: center; font-size: 20px; font-weight: bold;"') ?>
        <?= err('otp') ?>

        <section style="margin-top: 24px;">
            <button>Verify Code</button>
            <button type="button" class="secondary" data-get="reset.php">Cancel</button>
        </section>
    </form>
</div>

<?php
include '../_foot.php';
?>
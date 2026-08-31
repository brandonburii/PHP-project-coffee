<?php
include '../_base.php';

// Secure the page
$email = $_SESSION['reset_email'] ?? '';
if (!$email) {
    temp('err', 'Session lost or expired. Please request a new OTP.');
    redirect('reset.php');
}

if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// ----------------------------------------------------------------------------

if (is_post()) {
    $otp = req('otp');
    $token = null;

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
        } 
        // Manually check expiration in PHP to avoid MySQL timezone mismatches
        else if (strtotime($token->expire) < time()) {
            $_err['otp'] = 'This OTP has expired. Please request a new one.';
        }

        if ($_err) {
            $_SESSION['otp_attempts']++;
            audit('Auth', 'Failed OTP', "Failed OTP verification attempt for email: $email (Attempt " . $_SESSION['otp_attempts'] . " of 3)");

            if ($_SESSION['otp_attempts'] >= 3) {
                // Delete the OTP token so it cannot be used at all
                if ($u_id) {
                    $stm = $_db->prepare('DELETE FROM token WHERE user_id = ?');
                    $stm->execute([$u_id]);
                }
                
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_attempts']);
                
                audit('Auth', 'OTP Lockout', "OTP attempts exceeded lockout for email: $email");
                temp('err', 'Too many failed OTP attempts. Please request a new code.');
                redirect('reset.php');
            } else {
                $rem = 3 - $_SESSION['otp_attempts'];
                $_err['otp'] = $_err['otp'] . " ($rem attempt(s) left)";
            }
        }
    }

    if (!$_err && $token) {
        // Delete the token immediately so it cannot be re-used/re-verified
        $stm = $_db->prepare('DELETE FROM token WHERE id = ?');
        $stm->execute([$otp]);

        // Mark session as OTP verified
        $_SESSION['otp_verified'] = true;
        unset($_SESSION['otp_attempts']);
        
        audit('Auth', 'OTP Verified', "OTP successfully verified for email: $email");
        temp('info', 'Code verified! You can now set your new password.');
        
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
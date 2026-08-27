<?php
include '../_base.php';

// Secure the page
$email = $_SESSION['reset_email'] ?? '';
$verified = $_SESSION['otp_verified'] ?? false;

if (!$email || !$verified) {
    temp('err', 'Unauthorized access to password reset.');
    redirect('../login.php'); // Relative path
}

// ----------------------------------------------------------------------------

if (is_post()) {
    $password = req('password');
    $confirm = req('confirm');

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

    if (!$_err) {
        // 1. Update the password
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE email = ?');
        $stm->execute([$password, $email]);

        // 2. Fetch User ID to clear tokens
        $stm = $_db->prepare('SELECT id FROM user WHERE email = ?');
        $stm->execute([$email]);
        $u_id = $stm->fetchColumn();

        // 3. Delete the used OTP token
        $stm = $_db->prepare('DELETE FROM token WHERE user_id = ?');
        $stm->execute([$u_id]);

        // 4. Destroy the reset session variables for security
        unset($_SESSION['reset_email']);
        unset($_SESSION['otp_verified']);

        audit('Auth', 'Password Reset', "Password successfully reset for: $email");

        temp('info', 'Password has been updated successfully. Please login.');
        redirect('../login.php'); // Relative path
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Set New Password';
include '../_head.php';
?>

<!-- Load Alpine.js for real-time reactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div class="auth-card" x-data="resetPasswordForm()">
    <div class="auth-card-head">
        <h2>Set New Password</h2>
        <p>Create a secure password for your account.</p>
    </div>

    <form method="post" class="form auth-form">
        
        <label for="password">New Password <span class="req">*</span></label>
        <?= html_password('password', 'x-model="password" @input="validatePassword" maxlength="20" required placeholder="Min. 6 characters"') ?>
        <?= err('password') ?>
        <span class="err" x-show="errors.password" x-text="errors.password" style="display: none; color: red;"></span>

        <label for="confirm">Confirm Password <span class="req">*</span></label>
        <?= html_password('confirm', 'x-model="confirm" @input="validateConfirm" maxlength="20" required placeholder="Re-enter password"') ?>
        <?= err('confirm') ?>
        <span class="err" x-show="errors.confirm" x-text="errors.confirm" style="display: none; color: red;"></span>

        <section style="margin-top: 24px;">
            <button :disabled="Object.keys(errors).length > 0" :style="Object.keys(errors).length > 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''">Save Password</button>
        </section>
    </form>
</div>

<!-- Alpine.js Logic -->
<script>
function resetPasswordForm() {
    return {
        password: '',
        confirm: '',
        errors: {},

        validatePassword() {
            if (this.password === '') {
                this.errors.password = 'Required';
            } else if (this.password.length < 6) {
                this.errors.password = 'Min 6 characters';
            } else {
                delete this.errors.password;
            }
            if (this.confirm !== '') this.validateConfirm();
        },

        validateConfirm() {
            if (this.confirm === '') {
                this.errors.confirm = 'Required';
            } else if (this.confirm !== this.password) {
                this.errors.confirm = 'Passwords do not match';
            } else {
                delete this.errors.confirm;
            }
        }
    }
}
</script>

<?php
include '../_foot.php';
?>
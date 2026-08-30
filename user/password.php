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

<style>
[x-cloak] { display: none !important; }

/* Form container - forces block display to prevent global flex conflicts */
.auth-form-fixed {
    display: block;
    width: 100%;
}

/* Forces the label and input wrapper to sit side-by-side */
.form-row {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    width: 100%; /* Ensures the row takes full width and stacks vertically */
}

/* Fixed width for the label column so they align nicely */
.form-row > label {
    flex: 0 0 150px; 
    padding-top: 10px; /* Vertically aligns label text with the input box */
    font-weight: bold;
}

/* The empty div used to offset the submit button */
.label-spacer {
    flex: 0 0 150px;
}

/* The right-hand column containing inputs and errors */
.password-field-wrapper {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.password-row-inner {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.input-with-eye {
    position: relative;
    flex: 1;
    min-width: 0;
}

.input-with-eye input {
    width: 100%;
    padding-right: 38px;
    box-sizing: border-box;
}

.eye-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #888;
    line-height: 0;
}

.eye-toggle:hover {
    color: #333;
}

.generate-btn {
    flex-shrink: 0;
    font-size: 13px;
    padding: 8px 12px;
    border: 1px solid #ccc;
    background: #5c7785;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
}

.generate-btn:hover {
    background: #46606c;
}

/* Password strength meter */
.strength-meter {
    display: flex;
    gap: 4px;
    margin-top: 8px;
}

.strength-block {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: #e0e0e0;
    transition: background-color 0.2s ease;
}

.strength-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    margin-top: 4px;
    min-height: 15px;
}

.generate-hint {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #27ae60;
    min-height: 15px;
    line-height: 1.3;
}

.err-msg {
    display: block;
    color: #d32f2f; /* Red error text */
    font-size: 13px;
    margin-top: 4px;
}
</style>

<div class="auth-card" x-data="resetPasswordForm()">
    <div class="auth-card-head">
        <h2>Set New Password</h2>
        <p>Create a secure password for your account.</p>
    </div>

    <!-- Removed generic class="form" to prevent layout collapse -->
    <form method="post" class="auth-form-fixed">

        <!-- ROW 1: New Password -->
        <div class="form-row">
            <label for="password">New Password <span class="req">*</span></label>
            <div class="password-field-wrapper">
                <div class="password-row-inner">
                    <div class="input-with-eye">
                        <input type="password" name="password" id="password-field" x-model="password" @input="validatePassword(); updateStrengthMeter($event.target.value)" maxlength="20" required placeholder="Min. 6 characters">
                        <button type="button" class="eye-toggle" id="toggle-password" title="Show/Hide password">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="generate-btn" @click.prevent="generatePassword">🎲 Generate</button>
                </div>
                
                <div class="strength-meter" id="strength-meter">
                    <div class="strength-block"></div>
                    <div class="strength-block"></div>
                    <div class="strength-block"></div>
                    <div class="strength-block"></div>
                    <div class="strength-block"></div>
                </div>
                
                <span class="strength-label" id="strength-label"></span>
                <span class="generate-hint" x-show="passwordGenerated" x-cloak>✓ Strong password generated and filled in below</span>
                
                <?= err('password') ?>
                <span class="err-msg" x-show="errors.password" x-text="errors.password" x-cloak></span>
            </div>
        </div>

        <!-- ROW 2: Confirm Password -->
        <div class="form-row">
            <label for="confirm">Confirm Password <span class="req">*</span></label>
            <div class="password-field-wrapper">
                <div class="password-row-inner">
                    <div class="input-with-eye">
                        <input type="password" name="confirm" id="confirm-field" x-model="confirm" @input="validateConfirm" maxlength="20" required placeholder="Re-enter password">
                        <button type="button" class="eye-toggle" id="toggle-confirm" title="Show/Hide password">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <?= err('confirm') ?>
                <span class="err-msg" x-show="errors.confirm" x-text="errors.confirm" x-cloak></span>
            </div>
        </div>

        <!-- ROW 3: Submit Button -->
        <div class="form-row">
            <div class="label-spacer"></div> <!-- Spacer to align button perfectly under inputs -->
            <div class="password-field-wrapper">
                <button type="submit" :disabled="Object.keys(errors).length > 0" :style="Object.keys(errors).length > 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''">Save Password</button>
            </div>
        </div>
        
    </form>
</div>

<!-- Alpine.js Logic -->
<script>
function resetPasswordForm() {
    return {
        password: '',
        confirm: '',
        errors: {},
        passwordGenerated: false,

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
        },

        updateStrengthMeter(value) {
            const blocks = document.querySelectorAll('#strength-meter .strength-block');
            const label = document.getElementById('strength-label');
            if (!blocks.length || !label) return;

            if (value.length === 0) {
                blocks.forEach(block => block.style.background = '#e0e0e0');
                label.textContent = '';
                return;
            }

            let score = 0;
            if (value.length >= 6) score++;
            if (value.length >= 10) score++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
            if (/[0-9]/.test(value)) score++;
            if (/[^A-Za-z0-9]/.test(value)) score++;

            let color, text;
            if (score <= 2) {
                color = '#e74c3c'; text = 'Weak';
            } else if (score === 3) {
                color = '#f39c12'; text = 'Medium';
            } else {
                color = '#27ae60'; text = 'Strong';
            }

            blocks.forEach((block, i) => {
                block.style.background = i < score ? color : '#e0e0e0';
            });
            label.textContent = text;
            label.style.color = color;
        },

        generatePassword() {
            const length = 12;
            const lowercase = 'abcdefghijklmnopqrstuvwxyz';
            const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const numbers = '0123456789';
            const symbols = '!@#$%^&*';
            const all = lowercase + uppercase + numbers + symbols;

            let chars = [
                lowercase[Math.floor(Math.random() * lowercase.length)],
                uppercase[Math.floor(Math.random() * uppercase.length)],
                numbers[Math.floor(Math.random() * numbers.length)],
                symbols[Math.floor(Math.random() * symbols.length)],
            ];

            for (let i = chars.length; i < length; i++) {
                chars.push(all[Math.floor(Math.random() * all.length)]);
            }

            chars = chars.sort(() => Math.random() - 0.5);
            const generated = chars.join('');

            this.password = generated;
            this.confirm = generated;
            this.validatePassword();
            this.validateConfirm();
            this.updateStrengthMeter(generated);

            this.passwordGenerated = true;
            setTimeout(() => { this.passwordGenerated = false; }, 3000);
        }
    }
}

// Eye toggle logic (plain JS)
document.addEventListener('DOMContentLoaded', function() {
    function setupEyeToggle(toggleId, fieldId) {
        const toggleBtn = document.getElementById(toggleId);
        const field = document.getElementById(fieldId);
        if (!toggleBtn || !field) return;
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            field.type = field.type === 'password' ? 'text' : 'password';
        });
    }
    setupEyeToggle('toggle-password', 'password-field');
    setupEyeToggle('toggle-confirm', 'confirm-field');
});
</script>

<?php
include '../_foot.php';
?>
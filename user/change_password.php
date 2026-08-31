<?php
include '../_base.php';

// Secure the page - Only logged-in users can access this
auth();

if (is_post()) {
    $current_password = req('current_password');
    $new_password = req('new_password');
    $confirm_password = req('confirm_password');

    // 1. Validate Current Password
    if ($current_password == '') {
        $_err['current_password'] = 'Required';
    } 
    else if (sha1($current_password) !== $_user->password) {
        $_err['current_password'] = 'Incorrect current password';
    }

    // 2. Validate New Password
    if ($new_password == '') {
        $_err['new_password'] = 'Required';
    } 
    else if (strlen($new_password) < 6) {
        $_err['new_password'] = 'Minimum 6 characters required';
    }

    // 3. Validate Confirm Password
    if ($confirm_password == '') {
        $_err['confirm_password'] = 'Required';
    } 
    else if ($confirm_password !== $new_password) {
        $_err['confirm_password'] = 'Passwords do not match';
    }

    // Process the update if there are no errors
    if (!$_err) {
        $stm = $_db->prepare('UPDATE user SET password = SHA1(?) WHERE id = ?');
        $stm->execute([$new_password, $_user->id]);

        $_SESSION['user']->password = sha1($new_password);

        audit('Member', 'Password Change', "User {$_user->email} updated their password.");
        temp('info', 'Password updated successfully!');
        
        redirect('profile.php');
    }
}

// ----------------------------------------------------------------------------

$_title = 'User | Change Password';
include '../_head.php';
?>

<!-- Load Alpine.js for real-time reactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
    /* Kill the native browser eye icon (Edge/IE) */
    input::-ms-reveal,
    input::-ms-clear {
        display: none !important;
    }

    /* UI Styles for the requested structure */
    .auth-form-fixed { display: flex; flex-direction: column; gap: 20px; }
    .form-row { display: flex; flex-direction: column; gap: 5px; }
    .password-field-wrapper { display: flex; flex-direction: column; gap: 5px; }
    .password-row-inner { display: flex; gap: 10px; align-items: center; }
    
    .input-with-eye { position: relative; flex: 1; }
    .input-with-eye input { width: 100%; padding-right: 40px; box-sizing: border-box; margin: 0; }
    
    /* Locked in place: fixed width/height and no outlines */
    .eye-toggle { 
        position: absolute; 
        right: 10px; 
        top: 50%; 
        transform: translateY(-50%); 
        width: 24px;
        height: 24px;
        background: transparent !important; 
        border: none !important; 
        box-shadow: none !important;
        cursor: pointer; 
        color: #888; 
        padding: 0; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        outline: none;
    }
    
    /* Ensure clicking doesn't add an outline */
    .eye-toggle:focus, .eye-toggle:active {
        outline: none !important;
    }
    
    .generate-btn { padding: 9px 14px; background: #5c7785; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap; font-size: 0.9em; height: 100%; display: flex; align-items: center; gap: 5px;}
    .generate-btn:hover { background: #4a626f; }
    
    .strength-meter { display: flex; gap: 4px; height: 6px; margin-top: 5px; }
    .strength-block { flex: 1; border-radius: 3px; background-color: #e0e0e0; transition: background-color 0.3s; }
    
    .generate-hint { color: #27ae60; font-size: 0.85em; font-weight: 500; }
    .strength-label { font-size: 0.85em; font-weight: bold; }
    .err-msg { color: #e74c3c; font-size: 0.85em; }
    [x-cloak] { display: none !important; }
</style>

<div class="card" style="max-width: 550px; padding: 30px;" x-data="changePasswordForm()">
    <h2 style="margin-top: 0;">Change Password</h2>
    <p style="color: #666; margin-bottom: 25px;">Please enter your current password to set a new one.</p>

    <form method="post" class="auth-form-fixed" @submit.prevent="submitForm($event)">
        
        <!-- Current Password -->
        <div class="form-row">
            <label for="current_password">Current Password <span class="req">*</span></label>
            <div class="password-field-wrapper">
                <div class="input-with-eye">
                    <input :type="showCurrent ? 'text' : 'password'" name="current_password" x-model="current_password" @input="validateCurrent" maxlength="100">
                    <button type="button" class="eye-toggle" @click="showCurrent = !showCurrent" title="Show/Hide password" tabindex="-1">
                        <!-- Eye Open -->
                        <svg x-show="!showCurrent" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <!-- Eye Crossed -->
                        <svg x-show="showCurrent" x-cloak viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <?= err('current_password') ?>
                <span class="err-msg" x-show="errors.current_password" x-text="errors.current_password" x-cloak></span>
            </div>
        </div>

        <!-- ROW 1: New Password -->
        <div class="form-row">
            <label for="new_password">New Password <span class="req">*</span></label>
            <div class="password-field-wrapper">
                <div class="password-row-inner">
                    <div class="input-with-eye">
                        <input :type="showNew ? 'text' : 'password'" name="new_password" id="password-field" x-model="new_password" @input="validateNew(); updateStrengthMeter($event.target.value)" maxlength="100" required placeholder="Min. 6 characters">
                        <button type="button" class="eye-toggle" id="toggle-password" @click="showNew = !showNew" title="Show/Hide password" tabindex="-1">
                            <!-- Eye Open -->
                            <svg x-show="!showNew" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <!-- Eye Crossed -->
                            <svg x-show="showNew" x-cloak viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="generate-btn" @click.prevent="generatePassword">🎲 Generate</button>
                </div>
                
                <div class="strength-meter" id="strength-meter">
                    <div class="strength-block" :style="strengthScore >= 1 ? 'background-color: ' + strengthColor : ''"></div>
                    <div class="strength-block" :style="strengthScore >= 2 ? 'background-color: ' + strengthColor : ''"></div>
                    <div class="strength-block" :style="strengthScore >= 3 ? 'background-color: ' + strengthColor : ''"></div>
                    <div class="strength-block" :style="strengthScore >= 4 ? 'background-color: ' + strengthColor : ''"></div>
                    <div class="strength-block" :style="strengthScore >= 5 ? 'background-color: ' + strengthColor : ''"></div>
                </div>
                
                <span class="strength-label" id="strength-label" x-text="strengthText" :style="'color: ' + strengthColor"></span>
                <span class="generate-hint" x-show="passwordGenerated" x-cloak>✓ Strong password generated and filled in below</span>
                
                <?= err('new_password') ?>
                <span class="err-msg" x-show="errors.new_password" x-text="errors.new_password" x-cloak></span>
            </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-row">
            <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
            <div class="password-field-wrapper">
                <div class="input-with-eye">
                    <input :type="showConfirm ? 'text' : 'password'" name="confirm_password" x-model="confirm_password" @input="validateConfirm" maxlength="100">
                    <button type="button" class="eye-toggle" @click="showConfirm = !showConfirm" title="Show/Hide password" tabindex="-1">
                        <!-- Eye Open -->
                        <svg x-show="!showConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <!-- Eye Crossed -->
                        <svg x-show="showConfirm" x-cloak viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <?= err('confirm_password') ?>
                <span class="err-msg" x-show="errors.confirm_password" x-text="errors.confirm_password" x-cloak></span>
            </div>
        </div>

        <section style="margin-top: 10px; display: flex; gap: 10px;">
            <button type="submit" :disabled="Object.keys(errors).length > 0 || !isFormFilled()" :style="(Object.keys(errors).length > 0 || !isFormFilled()) ? 'opacity: 0.5; cursor: not-allowed;' : ''">Update Password</button>
            <button type="button" class="secondary" onclick="window.location.href='profile.php'">Cancel</button>
        </section>
    </form>
</div>

<!-- Alpine.js Logic -->
<script>
function changePasswordForm() {
    return {
        current_password: '',
        new_password: '',
        confirm_password: '',
        errors: {},
        
        // Visibility Toggles
        showCurrent: false,
        showNew: false,
        showConfirm: false,

        // Strength variables (5 blocks)
        strengthScore: 0,
        strengthText: '',
        strengthColor: '#e0e0e0',
        passwordGenerated: false,

        isFormFilled() {
            return this.current_password !== '' && this.new_password !== '' && this.confirm_password !== '';
        },

        validateCurrent() {
            if (this.current_password === '') {
                this.errors.current_password = 'Required';
            } else {
                delete this.errors.current_password;
            }
        },

        updateStrengthMeter(value) {
            let score = 0;
            
            if (!value) {
                this.strengthScore = 0;
                this.strengthText = '';
                return;
            }

            // 5 Criteria checks
            if (value.length > 5) score += 1;
            if (value.length >= 8) score += 1;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
            if (/[0-9]/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;

            this.strengthScore = score;

            if (score <= 1) {
                this.strengthText = 'Very Weak';
                this.strengthColor = '#e74c3c'; // Red
            } else if (score === 2) {
                this.strengthText = 'Weak';
                this.strengthColor = '#e67e22'; // Dark Orange
            } else if (score === 3) {
                this.strengthText = 'Fair';
                this.strengthColor = '#f1c40f'; // Yellow
            } else if (score === 4) {
                this.strengthText = 'Good';
                this.strengthColor = '#3498db'; // Blue
            } else if (score === 5) {
                this.strengthText = 'Very Strong';
                this.strengthColor = '#27ae60'; // Green
            }
        },

        validateNew() {
            this.passwordGenerated = false; 
            
            if (this.new_password === '') {
                this.errors.new_password = 'Required';
            } else if (this.new_password.length < 6) {
                this.errors.new_password = 'Min 6 characters';
            } else {
                delete this.errors.new_password;
            }
            
            if (this.confirm_password !== '') this.validateConfirm();
        },

        validateConfirm() {
            if (this.confirm_password === '') {
                this.errors.confirm_password = 'Required';
            } else if (this.confirm_password !== this.new_password) {
                this.errors.confirm_password = 'Passwords do not match';
            } else {
                delete this.errors.confirm_password;
            }
        },

        generatePassword() {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*_-";
            let generated = "";
            
            generated += "abcdefghijklmnopqrstuvwxyz"[Math.floor(Math.random() * 26)];
            generated += "ABCDEFGHIJKLMNOPQRSTUVWXYZ"[Math.floor(Math.random() * 26)];
            generated += "0123456789"[Math.floor(Math.random() * 10)];
            generated += "!@#$%^&*_-"[Math.floor(Math.random() * 10)];
            
            for (let i = 0; i < 8; i++) {
                generated += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            generated = generated.split('').sort(() => 0.5 - Math.random()).join('');

            this.new_password = generated;
            this.confirm_password = generated;
            this.passwordGenerated = true;
            this.showNew = true; 
            this.showConfirm = true;
            
            this.validateNew();
            this.updateStrengthMeter(generated);
        },

        submitForm(event) {
            this.validateCurrent();
            this.validateNew();
            this.validateConfirm();

            if (Object.keys(this.errors).length === 0) {
                event.target.submit();
            }
        }
    }
}
</script>

<?php
include '../_foot.php';
?>
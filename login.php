<?php
include '_base.php';

// ----------------------------------------------------------------------------
// Initialize session variables for login tracking if they don't exist
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$lockout_duration = 180; // 3 minutes in seconds
$remaining_seconds = 0;

// 1. Always check if user is currently locked out
if (isset($_SESSION['lockout_time'])) {
    $time_passed = time() - $_SESSION['lockout_time'];
    
    if ($time_passed < $lockout_duration) {
        $remaining_seconds = $lockout_duration - $time_passed;
    } else {
        unset($_SESSION['lockout_time']);
        $_SESSION['login_attempts'] = 0;
    }
}

// Redirect if already logged in (e.g., from 'Remember Me')
if (isset($_SESSION['user'])) {
    redirect('/'); // Redirect to home/dashboard if already logged in
}

if (is_post()) {
    $email = req('email');
    $password = req('password');
    $remember = req('remember'); // Capture the remember me checkbox

    // 2. Only proceed with validation if they are NOT locked out
    if ($remaining_seconds > 0) {
        $mins = floor($remaining_seconds / 60);
        $secs = $remaining_seconds % 60;
        $time_str = sprintf("%d:%02d", $mins, $secs);
        $_err['email'] = "Account locked. Please wait <strong id='lockout-timer'>$time_str</strong> to try again. Or Reset Password.";
    } else {
        // Validate: email
        if ($email == '') {
            $_err['email'] = 'Required';
        }
        else if (!is_email($email)) {
            $_err['email'] = 'Invalid email';
        }
    }
        // Validate: password
        if ($password == '') {
            $_err['password'] = 'Required';
        }

        // Login user
        if (!$_err) {
            $stm = $_db->prepare('
                SELECT * FROM user
                WHERE email = ? AND password = SHA1(?)
            ');
            $stm->execute([$email, $password]);
            $u = $stm->fetch();

            if ($u) {
                // Verify account status before allowing login
                if (isset($u->status) && $u->status === 'Pending') {
                    $_err['email'] = 'Please verify your email address before logging in.';
                    audit('Auth', 'Failed Login', "Attempted to log in to pending account: $email");
                } 
                // NEW: Blocked Account Check
                else if (isset($u->status) && $u->status === 'Blocked') {
                    $_err['email'] = 'Your account has been blocked by an administrator.';
                    audit('Auth', 'Failed Login', "Attempted to log in to blocked account: $email");
                }
                else {
                    // SUCCESS: Reset login attempts and clear lockout
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['lockout_time']);
                    
                    // --- REMEMBER ME LOGIC ---
                    if ($remember) {
                        // Create a secure, random token
                        $token = sha1(uniqid(rand(), true));
                        
                        // Save token in the user's browser for exactly 1 DAY (86400 seconds)
                        setcookie('remember_token', $token, time() + 86400, '/'); 
                        
                        // Save token in the database
                        $update_stm = $_db->prepare('UPDATE user SET remember_token = ? WHERE id = ?');
                        $update_stm->execute([$token, $u->id]);
                    }
                    
                    $_user = $u; // temporary assignment for audit logging
                    audit('Auth', 'Login', "Logged in successfully as $email");
                    temp('info', 'Login successfully');
                    login($u);
                }
            }
            else {
                // FAILED ATTEMPT: Increase the counter
                $_SESSION['login_attempts']++;
                
                if ($_SESSION['login_attempts'] >= 3) {
                    $_SESSION['lockout_time'] = time();
                    $remaining_seconds = $lockout_duration;
                    $_err['email'] = "Too many failed attempts. Account locked for <strong id='lockout-timer'>3:00</strong>. Please Reset Password or Register.";
                    audit('Auth', 'Lockout', "User locked out after 3 failed attempts for email: $email");
                } else {
                    $remaining = 3 - $_SESSION['login_attempts'];
                    $_err['password'] = "Incorrect email or password. You have $remaining attempt(s) left.";
                    audit('Auth', 'Failed Login', "Failed login attempt for email: $email");
                }
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
} else {
    // If it is a normal page load (GET) but the user is still locked out
    if ($remaining_seconds > 0) {
        $mins = floor($remaining_seconds / 60);
        $secs = $remaining_seconds % 60;
        $time_str = sprintf("%d:%02d", $mins, $secs);
        $_err['email'] = "Account locked. Please wait <strong id='lockout-timer'>$time_str</strong> to try again. Or Reset Password.";
    }
}
}  
// ----------------------------------------------------------------------------

$_title = 'Login';
include '_head.php';

$is_locked_out = ($remaining_seconds > 0);
?>

<form method="post" class="form">
    <label for="email">Email</label>
    <?= html_text('email', 'maxlength="100" ' . ($is_locked_out ? 'readonly style="background-color: #f0f0f0;"' : '')) ?>
    <?= err('email') ?>

    <label for="password">Password</label>
    <?= html_password('password', 'maxlength="100" ' . ($is_locked_out ? 'readonly style="background-color: #f0f0f0;"' : '')) ?>
    <?= err('password') ?>

    <!-- Remember Me Checkbox -->
    <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; margin-top: 10px; cursor: pointer;">
        <input type="checkbox" name="remember" value="1" <?= $is_locked_out ? 'disabled' : '' ?>>
        Remember me for 1 day
    </label>

    <section>
        <button <?= $is_locked_out ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>Login</button>
        <button type="reset" <?= $is_locked_out ? 'disabled style="opacity: 0.5;"' : '' ?>>Reset</button>
        <button type="button" class="secondary" data-get="/user/reset.php">Forgot Password?</button>
    </section>
    
    <?php if ($is_locked_out): ?>
        <div style="margin-top: 15px; text-align: center; font-size: 14px;">
            Don't have an account? <a href="/register.php" style="color: #5c7785; font-weight: bold;">Register here</a>
        </div>
    <?php endif; ?>
</form>

<?php if ($is_locked_out): ?>
<!-- Live JavaScript Timer -->
<script>
    let timeLeft = <?= $remaining_seconds ?>;
    const timerDisplay = document.getElementById('lockout-timer');
    
    if (timerDisplay) {
        const countdown = setInterval(() => {
            timeLeft--;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerDisplay.innerText = "0:00";
                window.location.reload(); 
            } else {
                let m = Math.floor(timeLeft / 60);
                let s = timeLeft % 60;
                let s_formatted = s < 10 ? '0' + s : s;
                timerDisplay.innerText = m + ':' + s_formatted;
            }
        }, 1000); 
    }
</script>
<?php endif; ?>

<?php
include '_foot.php';
?>
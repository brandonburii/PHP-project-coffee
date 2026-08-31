<?php
include '_base.php';

// ----------------------------------------------------------------------------

// 1. Clear the Remember Me cookie from the user's browser
setcookie('remember_token', '', time() - 3600, '/');

// 2. Clear the token from the database so the old cookie is completely invalidated
if (isset($_SESSION['user'])) {
    $_db->prepare('UPDATE user SET remember_token = NULL WHERE id = ?')->execute([$_SESSION['user']->id]);
    audit('Auth', 'Remember Me Revoked', "Remember token revoked on logout for user ID: " . $_SESSION['user']->id);
}

audit('Auth', 'Logout', 'Logged out successfully');
temp('info', 'Logout successfully');
logout();

// ----------------------------------------------------------------------------
?>
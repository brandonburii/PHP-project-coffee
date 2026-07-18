<?php
include '_base.php';

// ----------------------------------------------------------------------------

audit('Auth', 'Logout', 'Logged out successfully');
temp('info', 'Logout successfully');
logout();

// ----------------------------------------------------------------------------

<?php
require_once 'includes/config.php';
logActivity('Logout', 'Auth', 'User logged out');
session_unset();
session_destroy();
header('Location: ' . SITE_URL . '/index.php');
exit;

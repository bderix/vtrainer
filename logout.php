<?php
/**
 * Logout page
 *
 * PHP version 8.0
 */

// Include configuration and classes
require_once 'config.php';
require_once 'UserAuthentication.php';

// Get database connection
$db = getDbConnection();
$auth = new UserAuthentication($db);

// Perform logout
$auth->logout();

// Redirect to landing page or login page
header('Location: landing.php');
exit;
?>
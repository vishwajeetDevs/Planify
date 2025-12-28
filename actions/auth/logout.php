<?php
session_start();
require_once '../../config/db.php';
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
session_destroy();
header('Location: ' . $basePath . '/public/login.php');
exit;
?>
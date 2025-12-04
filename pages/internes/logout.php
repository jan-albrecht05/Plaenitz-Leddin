<?php
require_once '../../includes/session-config.php';
startSecureSession();

require_once '../../includes/log-data.php';

logAction(date('Y-m-d H:i:s'), 'logout', $_SESSION['name'] . ' logged out', $_SERVER['REMOTE_ADDR'], $_SESSION['user_id']);

session_destroy();
header("Location: ../../index.php");
exit();
?>
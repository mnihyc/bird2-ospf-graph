<?php
// url to get.php of backend
define('API_URL', 'backend/get.php?m=par&t=');
// site name to be shown
define('SITE_NAME', 'DEMO');

$token = substr(trim($_REQUEST['token']), 0, 32);
if(!preg_match('/^[a-z0-9]*$/i', $_REQUEST['token']))
	$token = '';
?>
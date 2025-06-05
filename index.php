<?php

require_once __DIR__.'/includes/config.php';
session_start();

$_SESSION['return_to'] = isset($_GET['return_to']) ? $_GET['return_to'] : null;

header('Location: login.php');
exit();

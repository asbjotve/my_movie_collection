<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';

auth_logout();

header('Location: ' . BASE_PATH . '/index.php');
exit;

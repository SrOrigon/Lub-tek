<?php
/**
 * LOGIN PROCESS — formulario HTML tradicional (fallback)
 */

require_once 'config.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$auth = new AuthSystem();
$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

$result = $auth->login($username, $password);

$wantsJson = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($result['success']) {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true, 'ok' => true], $result));
    } else {
        $home = $result['home_page'] ?? Permissions::defaultHomePage($result['user']['role'] ?? '');
        header('Location: index.php?page=' . urlencode($home));
    }
    exit;
}

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode($result);
    exit;
}

header('Location: login.php?error=1&user=' . rawurlencode($username));
exit;

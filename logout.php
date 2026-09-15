<?php
/**
 * LUB-TEK - Logout Page
 * Destroys user session and redirects to login
 *
 * Só executa o logout em POST (evita CSRF via <img>/<a> de terceiros forçando
 * logout por GET). Qualquer GET apenas encaminha para a tela de login sem
 * efeito colateral.
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthSystem();
    $auth->logout();
}

header('Location: login.php');
exit;

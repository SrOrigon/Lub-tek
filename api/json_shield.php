<?php
/**
 * Impede que avisos do PHP ou lixo de buffer virem HTML no meio do JSON.
 * Incluído por api.php e por cada controller.
 */
if (!defined('LUBTEK_JSON_SHIELD')) {
    define('LUBTEK_JSON_SHIELD', true);
    if (ob_get_level() === 0) {
        ob_start();
    }
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

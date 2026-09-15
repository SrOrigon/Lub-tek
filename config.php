<?php
/**
 * LUB-TEK Configuracoes
 */

// Carrega chaves locais (nao versionadas) se existirem
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Chave da API do Google Gemini — defina em config.local.php ou variavel de ambiente
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

// Modelo Gemini (Flash = rapido e economico)
if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash');
}

// TTL do cache da IA (segundos). A chave também muda quando os dados da planta mudam.
if (!defined('AI_CACHE_TTL')) {
    define('AI_CACHE_TTL', (int) (getenv('AI_CACHE_TTL') ?: 21600));
}
if (!defined('AI_CACHE_TTL_JSON')) {
    define('AI_CACHE_TTL_JSON', (int) (getenv('AI_CACHE_TTL_JSON') ?: 14400));
}
if (!defined('AI_CACHE_TTL_ASSISTANT')) {
    define('AI_CACHE_TTL_ASSISTANT', (int) (getenv('AI_CACHE_TTL_ASSISTANT') ?: 43200));
}

// IA remota (Gemini): habilitada por padrão quando há chave; desligue com AI_GEMINI_ENABLED=0
if (!defined('AI_GEMINI_ENABLED')) {
    $envAi = getenv('AI_GEMINI_ENABLED');
    define('AI_GEMINI_ENABLED', $envAi === false || $envAi === '' ? true : filter_var($envAi, FILTER_VALIDATE_BOOLEAN));
}

// Limite de chamadas Gemini por usuário/sessão por minuto (protege quota global)
if (!defined('AI_GEMINI_RATE_LIMIT')) {
    define('AI_GEMINI_RATE_LIMIT', (int) (getenv('AI_GEMINI_RATE_LIMIT') ?: 12));
}

define('APP_NAME', 'LUB-TEK');
define('APP_VERSION', '3.2.0');

/** URL canônica do site (PWA, sitemap, e-mails). Auto-detecta pelo host HTTP. */
if (!defined('SITE_URL')) {
    $envSite = getenv('SITE_URL');
    if ($envSite !== false && $envSite !== '') {
        define('SITE_URL', rtrim($envSite, '/'));
    } elseif (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https' : 'http';
        define('SITE_URL', $scheme . '://' . $_SERVER['HTTP_HOST']);
    } else {
        define('SITE_URL', 'https://lubteksystem.com');
    }
}

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: '');
}

if (!defined('TRUSTED_PROXY')) {
    define('TRUSTED_PROXY', getenv('TRUSTED_PROXY') === '1');
}

// Trava obrigatória de troca de senha no 1º acesso (apenas gestor/dono do tenant)
if (!defined('PASSWORD_RESET_LOCK_ENABLED')) {
    define('PASSWORD_RESET_LOCK_ENABLED', true);
}

// Preço de referência (R$) quando o ponto tem quantidade mas o catálogo/oferta não tem preço.
if (!defined('LUBE_REF_PRICE_PER_LITER')) {
    define('LUBE_REF_PRICE_PER_LITER', (float) (getenv('LUBE_REF_PRICE_PER_LITER') ?: 48));
}
if (!defined('LUBE_REF_PRICE_PER_KG')) {
    define('LUBE_REF_PRICE_PER_KG', (float) (getenv('LUBE_REF_PRICE_PER_KG') ?: 86));
}

if (!defined('CBM_ALERT_EMAIL')) {
    define('CBM_ALERT_EMAIL', getenv('CBM_ALERT_EMAIL') ?: 'manutencao@lubteksystem.com');
}

require_once __DIR__ . '/includes/bootstrap_dirs.php';
BootstrapDirs::ensure();

// Global mbstring polyfills if extension is disabled on host
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) {
        return strtolower($string);
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return strpos($haystack, $needle, $offset);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null) {
        return strtoupper($string);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen($string);
    }
}

require_once __DIR__ . '/includes/session_bootstrap.php';
SessionBootstrap::ensure();


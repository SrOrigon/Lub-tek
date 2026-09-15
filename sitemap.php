<?php
// LUB-TEK - Dynamic Sitemap Generator
// V2.1: Robust Error Handling & Buffer Cleansing

// 1. Output Buffering: Catch any accidental whitespace/text from included files
ob_start();

// 2. Error Handling — nunca expor erros via ?debug= (vazamento de info)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once 'db.php';

// 3. Buffer Cleanse: Discard any previous output (whitespace, notices) before XML header
ob_clean();

// 4. Set XML Headers
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// --- STATIC PAGES ---
$base = rtrim(SITE_URL, '/');
$static_pages = [
    $base . '/',
];

foreach ($static_pages as $page) {
    echo '
  <url>
    <loc>' . $page . '</loc>
    <priority>1.0</priority>
    <changefreq>daily</changefreq>
  </url>';
}

// --- DYNAMIC CONTENT (CATALOG) ---
try {
    $pdo = DB::getInstance();

    // Safety check: Ensure table exists and limit results 
    $stmt = $pdo->query("SELECT id, nome FROM catalogo ORDER BY id DESC LIMIT 1000");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Construct Dynamic URL
        $url = $base . '/?view=catalogo&id=' . $row['id'];

        echo '<url>';
        echo '<loc>' . htmlspecialchars($url) . '</loc>';
        echo '<priority>0.8</priority>';
        echo '<changefreq>weekly</changefreq>';
        echo '</url>';
    }

} catch (Exception $e) {
    // If DB fails, we still output the static pages above.
    // In debug mode, you can see the error by inspecting the source or ?debug=1
    if (isset($_GET['debug'])) {
        echo "<!-- DB Error: " . $e->getMessage() . " -->";
    }
}

echo '</urlset>';
?>
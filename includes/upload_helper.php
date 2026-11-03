<?php
/**
 * Utilitário seguro para gerenciamento e ciclo de vida de imagens/mídias em uploads/
 * Implementa integridade referencial, cascade delete e prevenção de órfãos.
 */

// Garante que TenantResolver esteja sempre disponível para a checagem de
// isolamento multi-tenant abaixo, independente da ordem de include do chamador.
require_once __DIR__ . '/tenant.php';

class UploadHelper
{
    /**
     * Normaliza e higieniza caminho relativo de upload.
     */
    public static function normalizePath($path): string
    {
        if (empty($path) || !is_string($path)) {
            return '';
        }
        $normalized = str_replace('\\', '/', trim($path));
        // Remove query parameters se houver (ex: ?t=1234567)
        if (($pos = strpos($normalized, '?')) !== false) {
            $normalized = substr($normalized, 0, $pos);
        }
        // Remove prefixos indesejados
        $normalized = ltrim($normalized, '/');
        if (strpos($normalized, './') === 0) {
            $normalized = substr($normalized, 2);
        }
        return $normalized;
    }

    /**
     * Extrai o caminho do thumbnail correspondente a uma imagem.
     */
    public static function getThumbnailPath(string $path): string
    {
        $normalized = self::normalizePath($path);
        if ($normalized === '' || strpos($normalized, 'thumb_') !== false) {
            return $normalized;
        }
        $dir = dirname($normalized);
        $base = basename($normalized);
        $prefix = ($dir === '.' || $dir === '') ? '' : ($dir . '/');
        return $prefix . 'thumb_' . $base;
    }

    /**
     * Verifica se o arquivo ainda é referenciado por OUTRO registro no sistema.
     * Garante que imagens compartilhadas ou clonadas NUNCA sejam perdidas acidentalmente.
     */
    public static function isPathReferencedElsewhere(?PDO $pdo, string $path, $excludeAssetIds = null, $excludeCatalogIds = null): bool
    {
        $normalized = self::normalizePath($path);
        if ($normalized === '') {
            return false;
        }

        if ($pdo === null && class_exists('DB')) {
            try {
                $pdo = DB::getInstance();
            } catch (\Throwable $t) {
                return false;
            }
        }
        if ($pdo === null) {
            return false;
        }

        $baseName = basename($normalized);
        // Remove 'thumb_' se estiver verificando um thumbnail
        if (strpos($baseName, 'thumb_') === 0) {
            $baseName = substr($baseName, 6);
        }

        // 1. Checagem em ATIVOS (imagem, imagem_3d, dados_tecnicos)
        $assetExclude = [];
        if (!empty($excludeAssetIds)) {
            $assetExclude = is_array($excludeAssetIds) ? array_map('intval', $excludeAssetIds) : [intval($excludeAssetIds)];
            $assetExclude = array_values(array_filter($assetExclude));
        }

        $sqlAssets = "SELECT COUNT(*) FROM ativos WHERE (imagem = ? OR imagem LIKE ? OR imagem_3d = ? OR dados_tecnicos LIKE ?)";
        $paramsAssets = [
            $normalized,
            '%' . $baseName . '%',
            $normalized,
            '%' . $baseName . '%'
        ];
        if (!empty($assetExclude)) {
            $inClause = implode(',', $assetExclude);
            $sqlAssets .= " AND id NOT IN ($inClause)";
        }
        try {
            $stmt = $pdo->prepare($sqlAssets);
            $stmt->execute($paramsAssets);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        } catch (\Throwable $t) {}

        // 2. Checagem em CATALOGO (imagem, specs)
        $catExclude = [];
        if (!empty($excludeCatalogIds)) {
            $catExclude = is_array($excludeCatalogIds) ? array_map('intval', $excludeCatalogIds) : [intval($excludeCatalogIds)];
            $catExclude = array_values(array_filter($catExclude));
        }

        $sqlCat = "SELECT COUNT(*) FROM catalogo WHERE (imagem = ? OR imagem LIKE ?)";
        $paramsCat = [$normalized, '%' . $baseName . '%'];
        if (!empty($catExclude)) {
            $inClause = implode(',', $catExclude);
            $sqlCat .= " AND id NOT IN ($inClause)";
        }
        try {
            $stmt = $pdo->prepare($sqlCat);
            $stmt->execute($paramsCat);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        } catch (\Throwable $t) {}

        // 3. Checagem em ORDENS (obs_exec)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordens WHERE obs_exec LIKE ?");
            $stmt->execute(['%' . $baseName . '%']);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        } catch (\Throwable $t) {}

        return false;
    }

    /**
     * Remove um arquivo apenas se estiver dentro de uploads/ com nome seguro
     * e caso não esteja sendo utilizado por nenhum outro registro.
     * Também remove automaticamente o thumbnail correspondente (thumb_{nome}.webp).
     */
    public static function deleteIfUploaded($path, ?PDO $pdo = null, $excludeAssetId = null, $excludeCatalogId = null)
    {
        $normalized = self::normalizePath($path);
        if ($normalized === '' || strpos($normalized, '..') !== false) {
            return false;
        }

        if (!preg_match('#^uploads/([a-z0-9_-]+/)?((?:thumb_)?[a-z0-9_.-]+)\.(jpg|jpeg|png|webp|gif|glb|gltf|obj|stl)$#i', $normalized, $matches)) {
            return false;
        }

        // ISOLAMENTO MULTI-TENANT: garante que o arquivo pertence à pasta do
        // tenant autenticado no momento.
        if (class_exists('TenantResolver')) {
            $currentTenant = TenantResolver::getCurrentTenant();
            $fileTenant = isset($matches[1]) ? strtolower(rtrim($matches[1], '/')) : '';

            if ($currentTenant !== null) {
                // Usuário de tenant só pode mexer na própria subpasta.
                if ($fileTenant === '' || $fileTenant !== strtolower($currentTenant)) {
                    return false;
                }
            }
        }

        // INTEGRIDADE REFERENCIAL: impede exclusão se ainda referenciado em outro registro
        if (self::isPathReferencedElsewhere($pdo, $normalized, $excludeAssetId, $excludeCatalogId)) {
            return false;
        }

        $uploadsDir = realpath(__DIR__ . '/../uploads');
        $full = realpath(__DIR__ . '/../' . $normalized);

        $deleted = false;
        if ($full && $uploadsDir && strpos($full, $uploadsDir) === 0 && is_file($full)) {
            $deleted = @unlink($full);
        }

        // Remove também o thumbnail vinculado, se existir
        $thumbRel = self::getThumbnailPath($normalized);
        if ($thumbRel !== $normalized) {
            $fullThumb = realpath(__DIR__ . '/../' . $thumbRel);
            if ($fullThumb && $uploadsDir && strpos($fullThumb, $uploadsDir) === 0 && is_file($fullThumb)) {
                @unlink($fullThumb);
            }
        }

        return $deleted;
    }

    /**
     * Coleta caminhos de mídia vinculados a um ativo (inclusive thumbnails e modelos 3D).
     */
    public static function collectAssetMediaPaths(array $asset)
    {
        $paths = [];

        if (!empty($asset['imagem'])) {
            $paths[] = $asset['imagem'];
            $paths[] = self::getThumbnailPath($asset['imagem']);
        }
        if (!empty($asset['imagem_3d'])) {
            $paths[] = $asset['imagem_3d'];
        }
        if (!empty($asset['dados_tecnicos'])) {
            $json = is_array($asset['dados_tecnicos']) ? $asset['dados_tecnicos'] : json_decode($asset['dados_tecnicos'], true);
            if (is_array($json)) {
                if (!empty($json['model_3d'])) {
                    $paths[] = $json['model_3d'];
                }
                if (!empty($json['foto'])) {
                    $paths[] = $json['foto'];
                    $paths[] = self::getThumbnailPath($json['foto']);
                }
                if (!empty($json['imagem'])) {
                    $paths[] = $json['imagem'];
                    $paths[] = self::getThumbnailPath($json['imagem']);
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * Remove lista de arquivos de upload com segurança e integridade referencial.
     */
    public static function deleteMany(array $paths, ?PDO $pdo = null, $excludeAssetIds = null, $excludeCatalogIds = null)
    {
        $count = 0;
        foreach (array_unique($paths) as $path) {
            if (self::deleteIfUploaded($path, $pdo, $excludeAssetIds, $excludeCatalogIds)) {
                $count++;
            }
        }
        return $count;
    }
}

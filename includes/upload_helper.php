<?php
/**
 * Utilitário seguro para gerenciamento de arquivos em uploads/
 */

// Garante que TenantResolver esteja sempre disponível para a checagem de
// isolamento multi-tenant abaixo, independente da ordem de include do chamador.
require_once __DIR__ . '/tenant.php';

class UploadHelper
{
    /**
     * Remove um arquivo apenas se estiver dentro de uploads/ com nome seguro.
     */
    public static function deleteIfUploaded($path)
    {
        if (empty($path) || !is_string($path)) {
            return false;
        }

        $normalized = str_replace('\\', '/', trim($path));
        if (strpos($normalized, '..') !== false) {
            return false;
        }

        if (!preg_match('#^uploads/([a-z0-9-]+/)?(model_\d+_[a-f0-9]{16}|[a-f0-9]{32})\.(jpg|jpeg|png|webp|gif|glb|gltf|obj|stl)$#i', $normalized, $matches)) {
            return false;
        }

        // ISOLAMENTO MULTI-TENANT: garante que o arquivo pertence à pasta do
        // tenant autenticado no momento (evita que um usuário de um tenant
        // apague arquivos de outro tenant informando um path alheio, mesmo
        // que o caminho "pareça" válido pelo padrão acima).
        if (class_exists('TenantResolver')) {
            $currentTenant = TenantResolver::getCurrentTenant();
            $fileTenant = isset($matches[1]) ? strtolower(rtrim($matches[1], '/')) : '';

            if ($currentTenant !== null) {
                // Usuário de tenant só pode mexer na própria subpasta.
                if ($fileTenant === '' || $fileTenant !== strtolower($currentTenant)) {
                    return false;
                }
            }
            // $currentTenant === null → admin global (developer): sem restrição de pasta,
            // mantém o comportamento já existente para o painel developer.
        }

        $full = realpath(__DIR__ . '/../' . $normalized);
        $uploadsDir = realpath(__DIR__ . '/../uploads');

        if ($full && $uploadsDir && strpos($full, $uploadsDir) === 0 && is_file($full)) {
            return @unlink($full);
        }

        return false;
    }

    /**
     * Coleta caminhos de mídia vinculados a um ativo.
     */
    public static function collectAssetMediaPaths(array $asset)
    {
        $paths = [];

        if (!empty($asset['imagem'])) {
            $paths[] = $asset['imagem'];
        }
        if (!empty($asset['imagem_3d'])) {
            $paths[] = $asset['imagem_3d'];
        }
        if (!empty($asset['dados_tecnicos'])) {
            $json = json_decode($asset['dados_tecnicos'], true);
            if (is_array($json) && !empty($json['model_3d'])) {
                $paths[] = $json['model_3d'];
            }
        }

        return array_unique(array_filter($paths));
    }

    /**
     * Remove lista de arquivos de upload com segurança.
     */
    public static function deleteMany(array $paths)
    {
        foreach ($paths as $path) {
            self::deleteIfUploaded($path);
        }
    }
}

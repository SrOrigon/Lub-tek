<?php
require_once __DIR__ . '/../includes/lubrication_plan_builder.php';
require_once __DIR__ . '/../includes/lubrication_plan_pattern_guard.php';
require_once __DIR__ . '/../includes/lubrication_plan_renderer.php';
require_once __DIR__ . '/../includes/gemini_service.php';

class PdfController
{
    /** @var PDO */
    private $db;

    /** @var array */
    private $user;

    /** @var array */
    private $input;

    public function __construct($db, $user, $input)
    {
        $this->db = $db;
        $this->user = is_array($user) ? $user : [];
        $this->input = is_array($input) ? $input : [];
    }

    public function getLubricationPlanMeta()
    {
        $assetId = (int) ($this->input['id'] ?? $this->input['asset_id'] ?? 0);
        if ($assetId <= 0) {
            return ['ok' => false, 'error' => 'ID do ativo é obrigatório.'];
        }

        $frequency = trim((string) ($this->input['frequency'] ?? ''));
        $builder = new LubricationPlanBuilder($this->db);
        $plan = $builder->build($assetId, ['frequency' => $frequency]);

        $renderer = new LubricationPlanRenderer($this->companyInfo());
        $previewHtml = $renderer->renderFull($plan, [
            'show_toolbar' => false,
            'use_ai' => false,
        ]);
        $plan['meta']['stats']['slides_count'] = LubricationPlanPatternGuard::countSlides($previewHtml);

        return [
            'ok' => true,
            'meta' => $plan['meta'],
            'company' => $this->companyInfo(),
            'ia_configured' => GeminiService::getInstance()->isConfigured(),
            'ai_enabled_default' => false,
            'export_url' => $this->buildExportUrl($assetId, $frequency, false),
        ];
    }

    public function getLubricationPlanChunk()
    {
        $assetId = (int) ($this->input['id'] ?? $this->input['asset_id'] ?? 0);
        $offset = max(0, (int) ($this->input['offset'] ?? 0));
        $limit = max(1, min(25, (int) ($this->input['limit'] ?? 10)));
        $frequency = trim((string) ($this->input['frequency'] ?? ''));

        if ($assetId <= 0) {
            return ['ok' => false, 'error' => 'ID do ativo é obrigatório.'];
        }

        $builder = new LubricationPlanBuilder($this->db);
        $sliceData = $builder->buildEquipmentSlice($assetId, $offset, $limit, ['frequency' => $frequency]);
        $renderer = new LubricationPlanRenderer($this->companyInfo());

        return [
            'ok' => true,
            'offset' => $sliceData['offset'],
            'limit' => $sliceData['limit'],
            'total' => $sliceData['total'],
            'done' => $sliceData['done'],
            'meta' => $sliceData['meta'],
            'html' => $renderer->renderEquipmentSlice($sliceData['slice'], $offset),
        ];
    }

  public function renderLubricationPlanCover()
    {
        $assetId = (int) ($this->input['id'] ?? $this->input['asset_id'] ?? 0);
        $frequency = trim((string) ($this->input['frequency'] ?? ''));
        $title = trim((string) ($this->input['title'] ?? 'PLANO DE LUBRIFICAÇÃO'));

        if ($assetId <= 0) {
            return ['ok' => false, 'error' => 'ID do ativo é obrigatório.'];
        }

        $builder = new LubricationPlanBuilder($this->db);
        $plan = $builder->build($assetId, ['frequency' => $frequency]);
        $renderer = new LubricationPlanRenderer($this->companyInfo());

        return [
            'ok' => true,
            'html' => $renderer->renderCoverOnly($plan, [
                'title' => $title,
                'subtitle' => (string) ($plan['meta']['root_name'] ?? ''),
                'show_toolbar' => false,
            ]),
        ];
    }

    private function companyInfo(): array
    {
        $tenant = $this->user['tenant'] ?? null;
        $name = 'LUB-TEK';
        $logo = 'assets/img/system/img_69581d7fcdbbf.jpeg';

        try {
            $customName = DB::getSystemMeta('company_name', $tenant);
            $customLogo = DB::getSystemMeta('company_logo', $tenant);
            if ($customName) {
                $name = $customName;
            } elseif ($tenant) {
                $name = ucfirst((string) $tenant);
            }
            if ($customLogo) {
                $logo = $customLogo;
            }
        } catch (Throwable $e) {
            // defaults
        }

        if (!empty($this->user['tenant_label'])) {
            $name = (string) $this->user['tenant_label'];
        }

        return ['name' => $name, 'logo' => $logo];
    }

    private function buildExportUrl(int $assetId, string $frequency = '', bool $useAi = false): string
    {
        $qs = http_build_query(array_filter([
            'id' => $assetId,
            'frequency' => $frequency !== '' ? $frequency : null,
            'use_ai' => $useAi ? '1' : '0',
        ]));
        return 'export_lubrication_plan.php?' . $qs;
    }
}

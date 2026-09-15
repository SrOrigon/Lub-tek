<?php
/**
 * Base de conhecimento do Motor Neural LUB-TEK
 * A IA usa este conteúdo para orientar usuários em qualquer módulo.
 */

class NeuralKnowledge
{
    public static function getModules()
    {
        return [
            'home' => [
                'nome' => 'Painel Principal (Home)',
                'descricao' => 'Portal de entrada com cards para todos os módulos do sistema.',
                'como_usar' => [
                    'Clique em qualquer card para abrir o módulo desejado.',
                    'O botão "Início" na barra superior sempre volta para esta tela.',
                    'O assistente IA (canto inferior direito) está disponível em todo o sistema.'
                ],
                'atalhos' => ['Visualizador 3D', 'Assistente IA', 'Ordens de Serviço']
            ],
            'dash' => [
                'nome' => 'Ordens de Serviço (Dashboard)',
                'descricao' => 'Painel central de gestão de Ordens de Serviço (O.S.) com KPIs e Motor Neural preditivo.',
                'como_usar' => [
                    'Veja contadores: Pendentes, Críticas e Concluídas no topo.',
                    'Clique em "Nova Ordem" para criar uma O.S. manualmente.',
                    'Use os filtros: Todas, Pendentes, Concluídas na tabela.',
                    'Clique em uma linha da tabela para abrir e editar a O.S.',
                    'Botão "Preventivas" gera ordens preventivas automaticamente (admin).',
                    'Seção "Motor Neural" mostra sugestões de risco — use Aceitar ou Recusar; a IA não cria O.S. sozinha.',
                    'Menu SAP ERP permite importar/exportar ordens.',
                    'Use a busca "Localizar O.S." para filtrar por texto.'
                ],
                'atalhos' => ['Nova Ordem', 'Preventivas', 'Motor Neural', 'Rotas', 'Meus Ativos']
            ],
            'assets' => [
                'nome' => 'Meus Ativos (Digital Twin)',
                'descricao' => 'Árvore hierárquica de ativos: unidade → setor → equipamento → componente → ponto de lubrificação.',
                'como_usar' => [
                    'Árvore à esquerda: clique em um nó para ver detalhes à direita.',
                    'Cadastre novos ativos com o botão "+" ou formulário lateral.',
                    'Pontos de lubrificação têm dados técnicos: material, frequência, método, condição de serviço.',
                    'Importe estrutura via Excel com o botão de importação.',
                    'Use templates de máquina para criar hierarquias rapidamente.',
                    'Atalhos no topo: Nova O.S., Engenharia, KPIs, Inventário (quando um ativo está selecionado).',
                    'Admin: "Limpar Tudo" apaga ativos e ordens; "Salvar Estado" grava snapshot.',
                    'QR Code disponível para identificação em campo.'
                ],
                'atalhos' => ['Nova O.S.', 'Engenharia', 'KPIs', 'Importar Excel', 'Templates']
            ],
            'catalog' => [
                'nome' => 'Inventário / Catálogo',
                'descricao' => 'Estoque de lubrificantes, rolamentos, equipamentos e materiais.',
                'como_usar' => [
                    'Liste, busque e filtre itens do catálogo.',
                    'Cadastre novos materiais com código, fabricante e estoque.',
                    'Exporte para Excel quando necessário.',
                    'Vincule materiais aos pontos de lubrificação em Meus Ativos.'
                ],
                'atalhos' => ['Meus Ativos', 'Ordens de Serviço']
            ],
            'calc' => [
                'nome' => 'Engenharia (Calculadoras)',
                'descricao' => 'Calculadoras técnicas: rolamentos, fator Kappa (κ = ν/ν₁), viscosidade ASTM, bucha e fator DN.',
                'como_usar' => [
                    'Selecione a calculadora: Rolamento, Viscosidade, Fator Kappa, Bucha ou Fator DN.',
                    'No Kappa, informe d/D (ou código ISO), RPM, temperatura e o ISO VG do óleo ou óleo-base da graxa.',
                    'κ entre 1 e 4 é o filme ideal; abaixo de 1 use EP; acima de 4 a viscosidade está excessiva.',
                    'A dose de graxa usa a regra SKF Gp = 0,005 × D × B.',
                ],
                'atalhos' => ['Voltar aos Ativos']
            ],
            'reports' => [
                'nome' => 'Exportar Documentos / Relatórios',
                'descricao' => 'Geração de relatórios gerenciais em Excel e PDF, incluindo consumo de lubrificantes por equipamento.',
                'como_usar' => [
                    'Escolha o tipo: Catálogo, Ordens de Serviço, Estrutura da Planta, SAP ou Consumo por Equipamento.',
                    'No consumo, use Ver relatório para o demonstrativo em tela; Excel e PDF exportam o mesmo dado.',
                    'O consumo individual usa dose × frequência de cada ponto cadastrado em Meus Ativos.',
                    'Relatórios usam dados atuais do banco.'
                ],
                'atalhos' => []
            ],
            'kpi' => [
                'nome' => 'Gestão de KPIs',
                'descricao' => 'Dashboard de indicadores: OEE, aderência, saúde dos ativos, investimento.',
                'como_usar' => [
                    'Visualize gráficos de OEE, aderência ao plano e saúde dos ativos.',
                    'Barra "Motor Neural" no topo traz insight e recomendação automática.',
                    'Use para apresentações gerenciais e tomada de decisão.'
                ],
                'atalhos' => ['Meus Ativos', 'Ordens de Serviço']
            ],
            'routes' => [
                'nome' => 'Rotas de Lubrificação',
                'descricao' => 'Checklist de campo para execução de rotas de lubrificação.',
                'como_usar' => [
                    'Lista todos os pontos de lubrificação cadastrados em Meus Ativos.',
                    'Filtre por setor no dropdown.',
                    'Mobile: deslize o card para direita = OK, para esquerda = ALERTA.',
                    'Desktop: use botões "Marcar OK" ou "Reportar Alerta".',
                    'Alerta pode capturar foto da anomalia.',
                    'Status sincroniza com o ativo no sistema.'
                ],
                'atalhos' => ['Meus Ativos', 'Ordens de Serviço']
            ],
            'pi' => [
                'nome' => 'Portal PI System (Telemetria / CBM)',
                'descricao' => 'Monitoramento de sensores industriais com manutenção baseada em condição (CBM).',
                'como_usar' => [
                    'Visualize tags de temperatura, pressão e vibração em tempo real.',
                    'Gráfico mostra histórico de telemetria.',
                    'Simulador: ajuste o slider para injetar valores e testar alertas.',
                    'Quando valor ultrapassa limite crítico, O.S. é gerada automaticamente (CBM).',
                    'Botão "Diagnóstico IA" analisa o sensor selecionado.'
                ],
                'atalhos' => ['Ordens de Serviço', 'Meus Ativos']
            ],
            'sap' => [
                'nome' => 'Integração SAP ERP',
                'descricao' => 'Importação e exportação de dados no formato SAP PM/MM.',
                'como_usar' => [
                    'Aba Importar: envie arquivos Excel/CSV de O.S. (IW39), ativos (IH01) ou materiais (MM60).',
                    'Aba Exportar: gere retorno IW41 para ordens concluídas.',
                    'Não é conexão RFC live — trabalha com arquivos exportados do SAP.'
                ],
                'atalhos' => ['Ordens de Serviço']
            ],
            '3d' => [
                'nome' => 'Visualizador 3D',
                'descricao' => 'Ambiente imersivo para inspeção de modelos 3D (GLB, GLTF, OBJ, STL).',
                'como_usar' => [
                    'Envie um arquivo 3D do seu equipamento (GLB, GLTF, OBJ ou STL).',
                    'Use o mouse para rotacionar, zoom e pan.',
                    'Vincule modelos a ativos em Meus Ativos.'
                ],
                'atalhos' => ['Voltar ao Início']
            ]
        ];
    }

    public static function getModuleGuide($page)
    {
        $modules = self::getModules();
        return $modules[$page] ?? $modules['home'];
    }

    public static function getSystemOverview()
    {
        return "LUB-TEK 3.2 é uma plataforma de gestão industrial com: Ordens de Serviço, Árvore de Ativos (Digital Twin), Inventário, Calculadoras de Engenharia, KPIs, Rotas de Lubrificação, Portal PI (telemetria/CBM), Integração SAP e Visualizador 3D. Todos os módulos estão conectados via barra superior com atalhos contextuais.";
    }

    public static function getPagePrompts($page)
    {
        $prompts = [
            'home' => ['Quais módulos o LUB-TEK tem?', 'Como navego entre as telas?', 'Como começo a cadastrar ativos?'],
            'dash' => ['Como criar uma nova ordem de serviço?', 'O que é o Motor Neural?', 'Como filtrar ordens pendentes?', 'Como gerar preventivas automáticas?'],
            'assets' => ['Como cadastrar um novo ativo?', 'Como importar ativos do Excel?', 'O que é um ponto de lubrificação?', 'Como criar O.S. para este ativo?'],
            'catalog' => ['Como adicionar material ao inventário?', 'Como exportar o catálogo?'],
            'calc' => ['Como calcular viscosidade?', 'O que é fator DN?', 'Como usar a calculadora de rolamentos?'],
            'reports' => ['Como exportar relatório em PDF?', 'Quais relatórios posso gerar?'],
            'kpi' => ['O que é OEE?', 'Como interpretar os KPIs?', 'O que significa a barra do Motor Neural?'],
            'routes' => ['Como marcar um ponto como OK?', 'Como reportar alerta na rota?', 'O que significa o swipe?'],
            'pi' => ['Como funciona o simulador de sensores?', 'O que é CBM?', 'Quando uma O.S. é gerada automaticamente?'],
            'sap' => ['Como importar ordens do SAP?', 'Qual formato de arquivo usar?'],
            '3d' => ['Como carregar modelo 3D?', 'Quais formatos são aceitos?', 'Como vincular modelo a um ativo?']
        ];
        return $prompts[$page] ?? $prompts['home'];
    }

    private static function toLower($str)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
    }

    private static function strPos($haystack, $needle)
    {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle) : strpos($haystack, $needle);
    }

    /**
     * Detecta perguntas que podem ser respondidas localmente (sem gastar tokens Gemini).
     */
    public static function shouldUseLocalFirst($prompt)
    {
        if (empty($prompt)) {
            return true;
        }

        // Sem chave Gemini → sempre local (evita dependência circular com GeminiService)
        if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
            return true;
        }

        $q = self::toLower(trim($prompt));

        // Palavras-chave de navegação, ajuda e comandos operacionais respondidos localmente
        $localKeywords = [
            'como', 'onde', 'ajuda', 'usar', 'funciona', 'fazer',
            'criar', 'cadastrar', 'exportar', 'importar', 'ordem', 'ordens',
            'os', 'limpar', 'relatório', 'relatorio',
            'kpi', 'oee', 'rotas'
        ];

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $localKeywords)) . ')\b/ui';
        return (bool) preg_match($pattern, $q);
    }

    /**
     * Resposta local quando Gemini não está disponível.
     */
    public static function getLocalAnswer($prompt, $context)
    {
        $page = $context['page'] ?? 'home';
        $guide = self::getModuleGuide($page);
        $q = self::toLower($prompt);

        $moduleName = $guide['nome'];
        $steps = implode("\n• ", $guide['como_usar']);

        // Perguntas sobre módulo atual / "como usar" / "o que é"
        if (preg_match('/(como|onde|o que|qual|ajuda|usar|funciona|fazer|criar|cadastrar|exportar|importar)/u', $q)) {
            foreach ($guide['como_usar'] as $hint) {
                $hintLower = self::toLower($hint);
                $words = preg_split('/\s+/', $q);
                foreach ($words as $w) {
                    if (strlen($w) > 4 && self::strPos($hintLower, $w) !== false) {
                        return "**{$moduleName}**\n\n{$hint}\n\n**Outras ações neste módulo:**\n• {$steps}";
                    }
                }
            }
            return "**Você está em: {$moduleName}**\n\n{$guide['descricao']}\n\n**Como usar:**\n• {$steps}";
        }

        // Perguntas sobre ordens
        if (preg_match('/(ordem|os|serviço|servico|pendente|crítica|critica)/u', $q)) {
            $pend = $context['os_pendentes'] ?? 0;
            $crit = $context['os_criticas'] ?? 0;
            return "**Ordens de Serviço**\n\nNo sistema há {$pend} ordens pendentes e {$crit} críticas.\n\n• Para criar: vá em Ordens de Serviço → **Nova Ordem**\n• Para ver sugestões: seção **Motor Neural** no dashboard\n• A Lúbria só sugere: clique **Aceitar** ou **Recusar** em cada predição";
        }

        // Perguntas sobre ativos
        if (preg_match('/(ativo|equipamento|árvore|arvore|ponto|lubrific)/u', $q)) {
            $total = $context['total_ativos'] ?? 0;
            return "**Meus Ativos** ({$total} cadastrados)\n\n• Árvore hierárquica à esquerda\n• Selecione um nó para ver/editar dados\n• Pontos de lubrificação ficam no nível mais baixo da árvore\n• Use atalhos no topo para criar O.S. ou abrir Engenharia";
        }

        // Perguntas sobre IA / neural
        if (preg_match('/(ia|inteligência|neural|gemini|assistente)/u', $q)) {
            return "Sou o **Assistente Neural LUB-TEK**. Estou aqui para tirar dúvidas sobre o sistema e sobre manutenção industrial.\n\n**Neste momento você está em:** {$moduleName}\n\nPergunte coisas como:\n• \"Como criar uma ordem de serviço?\"\n• \"Como cadastrar um ativo?\"\n• \"O que é o Motor Neural?\"";
        }

        // Resposta padrão contextualizada
        return "**{$moduleName}**\n\n{$guide['descricao']}\n\n**Passo a passo:**\n• {$steps}\n\nSe precisar de ajuda técnica sobre lubrificação ou manutenção, descreva sua dúvida com mais detalhes.";
    }
}

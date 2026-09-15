<?php
/**
 * Conteúdo SSMA / treinamento — padrão Pet160 + boas práticas industriais alimentícias.
 */
class LubricationPlanSsmaContent
{
    /**
     * @param array<int, string> $lubricants Nomes de lubrificantes do plano
     * @return array<int, array{title:string,subtitle:string,sections:array<int,array{heading:string,items:array<int,string>}>}>
     */
    public static function trainingSlides(array $lubricants = []): array
    {
        $lubList = array_slice(array_values(array_unique(array_filter($lubricants))), 0, 12);
        $lubBlock = empty($lubList)
            ? 'Consulte a tabela SAP de cada equipamento para códigos e lubrificantes homologados.'
            : implode(' · ', $lubList);

        return [
            [
                'title' => 'SSMA — Segurança na Lubrificação',
                'subtitle' => 'Procedimentos obrigatórios em campo',
                'sections' => [
                    [
                        'heading' => 'Antes de iniciar',
                        'items' => [
                            'Verificar permissão de trabalho e comunicar PCM/liderança.',
                            'Confirmar LOTO (bloqueio elétrico/mecânico) se houver contato com partes móveis.',
                            'Inspecionar EPIs: óculos, luvas químicas/nitrílicas, calçado de segurança.',
                            'Identificar pontos de lubrificação conforme numeração nas fotos e tabelas SAP.',
                        ],
                    ],
                    [
                        'heading' => 'Durante a execução',
                        'items' => [
                            'Utilizar bomba manual ou sistema centralizado conforme método indicado — não exceder volume.',
                            'Evitar respingos em áreas de produto; usar panos absorventes e proteção de bancadas.',
                            'Não misturar lubrificantes incompatíveis; respeitar especificação KHS/OEM.',
                            'Registrar não conformidades (vazamento, ponto inacessível, tag ilegível).',
                        ],
                    ],
                    [
                        'heading' => 'Encerramento',
                        'items' => [
                            'Remover resíduos e embalagens; descartar em coletor classe I sinalizado.',
                            'Comunicar conclusão à liderança; atualizar checklist/CMMS se aplicável.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Meio Ambiente & Resíduos',
                'subtitle' => 'Contenção e descarte',
                'sections' => [
                    [
                        'heading' => 'Vazamentos e respingos',
                        'items' => [
                            'Interromper atividade em vazamento severo; acionar equipe de contenção.',
                            'Absorver com material inerte; não lavar para rede pluvial ou piso sem contenção.',
                            'Panos contaminados: acondicionar em saco identificado para descarte correto.',
                        ],
                    ],
                    [
                        'heading' => 'Produtos e embalagens',
                        'items' => [
                            'Armazenar lubrificantes em local seco, ventilado, identificado e trancado.',
                            'Embalagens vazias: tríplice lavagem ou descarte conforme procedimento da planta.',
                            'Manter FISPQ/SDS acessível para todos os produtos utilizados na linha.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Lubrificantes NSF-H1 / Food Grade',
                'subtitle' => 'Ambiente alimentício — Coca-Cola Solar',
                'sections' => [
                    [
                        'heading' => 'Requisitos',
                        'items' => [
                            'Utilizar somente lubrificantes registrados NSF-H1 ou equivalente onde houver contato incidental.',
                            'Graxas KHS Multi Grease 01/02, óleos Gear Fluid e sprays NSF conforme tabela SAP.',
                            'Proibir graxas/óleos industriais não homologados em zona de processo.',
                        ],
                    ],
                    [
                        'heading' => 'Produtos deste plano',
                        'items' => [$lubBlock],
                    ],
                ],
            ],
            [
                'title' => 'Treinamento Synthoil / KHS',
                'subtitle' => 'Identificação e aplicação correta',
                'sections' => [
                    [
                        'heading' => 'Tipos de produto',
                        'items' => [
                            'Graxa (NLGI): mancais, rolamentos, guias — aplicar quantidade mínima eficaz.',
                            'Óleo de engrenagem: redutores — nível e viscosidade conforme especificação.',
                            'Spray: correntes, guias secas — curta distância; evitar overspray em sensores.',
                        ],
                    ],
                    [
                        'heading' => 'Periodicidade',
                        'items' => [
                            'Respeitar colunas Freq. Troca / Freq. Inspeção (dias) da tabela SAP.',
                            'Inspeção visual: vazamento, cor, temperatura, ruído anormal.',
                            'Troca programada: drenar, limpar, encher; registrar lote se exigido pela planta.',
                        ],
                    ],
                    [
                        'heading' => 'Recomendação KHS',
                        'items' => [
                            'Priorizar lubrificantes KHS homologados indicados na coluna Recomendação.',
                            'Em dúvida, consultar engenharia de confiabilidade antes de substituir produto.',
                        ],
                    ],
                ],
            ],
        ];
    }
}

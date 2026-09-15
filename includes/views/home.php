<div id="view-home" class="view-container active view-flex"
    style="padding: 0; flex-direction: column; align-items: center; justify-content: flex-start; background: #f8fafc;">

    <!-- HEADER (Enlarged for Impact) -->
    <div style="text-align: center; margin: 40px 0 40px 0; width: 100%; max-width: 1200px; padding: 0 20px;">
        <img src="<?php echo htmlspecialchars($companyLogo); ?>"
            alt="<?php echo htmlspecialchars($companyName); ?>"
            style="width: 100px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); margin-bottom: 25px; object-fit: contain;">
        <h1 style="font-size: 3rem; margin: 0; color: #0f172a; font-weight: 800; letter-spacing: -1.5px;">
            <?php echo htmlspecialchars($companyName); ?>
        </h1>
        <p style="font-size: 1.1rem; color: #64748b; margin-top: 15px; opacity: 0.9; font-weight: 500;">
            Gestão de manutenção e lubrificação industrial — simples e inteligente.
        </p>
        <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 10px; max-width: 520px; margin-left: auto; margin-right: auto;">
            Todos os módulos estão conectados: abra um ativo e use os atalhos no topo para criar O.S., calcular lubrificação ou ver KPIs.
        </p>
    </div>

    <!-- MAIN GRID (Unified) -->
    <div class="cards-grid">

        <!-- CARD 1: DASHBOARD -->
        <div onclick="nav('dash')" class="lub-card" data-perm="dashboard">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_dash.png');">
                <div class="overlay"></div>
                <!-- <div class="icon-badgev2"><i data-lucide="layout-grid"></i></div> -->
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle"><i data-lucide="layout-grid"></i></div>
                    <h3>Ordens de Serviço</h3>
                </div>
                <p>Veja tarefas pendentes, crie novas ordens e acompanhe o que foi concluído.</p>
                <div class="card-footer">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 2: ASSETS -->
        <div onclick="nav('assets')" class="lub-card" data-perm="assets">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_assets.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle success"><i data-lucide="network"></i></div>
                    <h3>Máquinas e Equipamentos</h3>
                </div>
                <p>Cadastre fábricas, equipamentos e pontos de lubrificação.</p>
                <div class="card-footer success">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 3: INVENTORY -->
        <div onclick="nav('catalog')" class="lub-card" data-perm="catalog">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_inv.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle warning"><i data-lucide="package-search"></i></div>
                    <h3>Almoxarifado de Óleos</h3>
                </div>
                <p>Estoque de lubrificantes, peças e movimentação.</p>
                <div class="card-footer warning">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 4: ENGINEERING -->
        <div onclick="nav('calc')" class="lub-card" data-perm="engineering">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_eng.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle purple"><i data-lucide="calculator"></i></div>
                    <h3>Engenharia</h3>
                </div>
                <p>Cálculos técnicos, viscosidade e vida útil.</p>
                <div class="card-footer purple">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 5: REPORTS -->
        <div onclick="nav('reports')" class="lub-card" data-perm="reports">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_reports.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle pink"><i data-lucide="file-bar-chart"></i></div>
                    <h3>Exportar Documentos</h3>
                </div>
                <p>Relatórios gerenciais e análise de dados.</p>
                <div class="card-footer pink">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 6: KPI -->
        <div onclick="nav('kpi')" class="lub-card" data-perm="kpi">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_kpi.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle indigo"><i data-lucide="monitor"></i></div>
                    <h3>Resumo da Fábrica</h3>
                </div>
                <p>Veja o que está em dia, pendências e custos do mês em linguagem simples.</p>
                <div class="card-footer indigo">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD: ROTAS DE LUBRIFICAÇÃO -->
        <div onclick="nav('routes')" class="lub-card" data-perm="routes">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_routes.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle" style="background:#fef3c7; color:#d97706;"><i data-lucide="map"></i></div>
                    <h3>Rotas de Lubrificação</h3>
                </div>
                <p>Checklist de campo: veja o ponto, o óleo e conclua com um toque.</p>
                <div class="card-footer" style="color:#d97706;">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD PI: TELEMETRIA PI SYSTEM -->
        <div onclick="nav('pi')" class="lub-card" data-perm="pi">
            <div class="card-img" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); display: flex; align-items: center; justify-content: center;">
                <div class="overlay"></div>
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(56, 189, 248, 0.1); border: 2px solid rgba(56, 189, 248, 0.3); display: flex; align-items: center; justify-content: center; z-index: 1; animation: pulse 2s infinite;">
                    <i data-lucide="activity" style="width: 30px; height: 30px; color: #38bdf8;"></i>
                </div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle" style="background: #e0f2fe; color: #0284c7;"><i data-lucide="database"></i></div>
                    <h3>Sensores em Tempo Real</h3>
                </div>
                <p>Monitoramento de sensores industriais e alertas automáticos.</p>
                <div class="card-footer" style="color: #0284c7;">
                    <span>Acessar Portal</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD SAP: INTEGRACAO SAP ERP -->
        <div onclick="nav('sap')" class="lub-card" data-perm="sap">
            <div class="card-img" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center;">
                <div class="overlay"></div>
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; z-index: 1;">
                    <i data-lucide="refresh-cw" style="width: 30px; height: 30px; color: white;"></i>
                </div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle" style="background: #eff6ff; color: #1d4ed8;"><i data-lucide="database"></i></div>
                    <h3>Integração SAP ERP</h3>
                </div>
                <p>Importação e exportação de ordens, planos e materiais no formato padrão SAP PM/MM.</p>
                <div class="card-footer" style="color: #1d4ed8;">
                    <span>Acessar Integração</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD 7: 3D -->
        <div onclick="window.location.href='?page=3d'" class="lub-card" data-perm="digital_twin">
            <div class="card-img" style="background-image: url('assets/img/system/thumb_3d.png');">
                <div class="overlay"></div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle dark"><i data-lucide="box"></i></div>
                    <h3>Visualizador 3D</h3>
                </div>
                <p>Ambiente imersivo para inspeção espacial.</p>
                <div class="card-footer dark">
                    <span>Acessar</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD: AUDIT LOGS -->
        <div onclick="nav('audit')" class="lub-card" data-perm="audit_logs">
            <div class="card-img" style="background: linear-gradient(135deg, #475569 0%, #64748b 100%); display: flex; align-items: center; justify-content: center;">
                <div class="overlay"></div>
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; z-index: 1;">
                    <i data-lucide="shield-check" style="width: 30px; height: 30px; color: white;"></i>
                </div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle" style="background: #f1f5f9; color: #475569;"><i data-lucide="shield-check"></i></div>
                    <h3>Auditoria & Compliance</h3>
                </div>
                <p>Monitore ações de usuários, alterações de ativos e logs de conformidade em tempo real.</p>
                <div class="card-footer" style="color: #475569;">
                    <span>Acessar Logs</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

        <!-- CARD: EQUIPE / USUÁRIOS -->
        <div onclick="nav('users')" class="lub-card" data-perm="manage_users">
            <div class="card-img" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); display: flex; align-items: center; justify-content: center;">
                <div class="overlay"></div>
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,0.12); border: 2px solid rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; z-index: 1;">
                    <i data-lucide="users" style="width: 30px; height: 30px; color: white;"></i>
                </div>
            </div>
            <div class="card-content">
                <div class="card-header">
                    <div class="icon-circle" style="background: #e0f2fe; color: #0284c7;"><i data-lucide="user-plus"></i></div>
                    <h3>Equipe e Acessos</h3>
                </div>
                <p>Cadastre lubrificadores e gestores da sua empresa em poucos cliques.</p>
                <div class="card-footer" style="color: #0284c7;">
                    <span>Gerenciar Equipe</span>
                    <i data-lucide="arrow-right" style="width: 14px;"></i>
                </div>
            </div>
        </div>

    </div>

    <style>
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 25px;
            width: 100%;
            max-width: 1200px;
            padding: 0 20px 60px 20px;
            margin: 0 auto;
        }

        .lub-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .lub-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
        }

        .card-img {
            height: 140px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.4), transparent);
        }

        .card-content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #e0f2fe;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle.success {
            background: #dcfce7;
            color: #16a34a;
        }

        .icon-circle.warning {
            background: #fef3c7;
            color: #d97706;
        }

        .icon-circle.purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        .icon-circle.pink {
            background: #fce7f3;
            color: #db2777;
        }

        .icon-circle.indigo {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .icon-circle.dark {
            background: #f1f5f9;
            color: #334155;
        }

        .card-content p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
            flex: 1;
        }

        .card-footer {
            margin-top: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #0284c7;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.8;
            transition: gap 0.2s;
        }

        .lub-card:hover .card-footer {
            gap: 8px;
            opacity: 1;
        }

        .card-footer.success {
            color: #16a34a;
        }

        .card-footer.warning {
            color: #d97706;
        }

        .card-footer.purple {
            color: #9333ea;
        }

        .card-footer.pink {
            color: #db2777;
        }

        .card-footer.indigo {
            color: #4f46e5;
        }

        .card-footer.dark {
            color: #334155;
        }

        /* Responsive Home */
        @media (max-width: 600px) {
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 0 15px 40px 15px;
            }

            .lub-card:hover {
                transform: none;
            }

            .card-img {
                height: 100px;
            }

            .card-content {
                padding: 15px;
            }

            .card-header h3 {
                font-size: 0.9rem;
            }
        }
    </style>

</div>
<?php
/**
 * Banco SQLite mínimo para eval do agente de O.S. (memória).
 */
function eval_bootstrap_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE ativos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT, tag TEXT, tipo TEXT, pai_id INTEGER,
        dados_tecnicos TEXT, user_id INTEGER DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE ordens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT, situacao TEXT, ativo_id INTEGER,
        data_planejada TEXT, data_emissao TEXT, data_execucao TEXT,
        prioridade TEXT, tipo_manutencao TEXT, rota TEXT
    )");
    $pdo->exec("CREATE TABLE ativos_lubrificacao (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ativo_id INTEGER, lubrificante TEXT, quantidade REAL,
        unidade TEXT, ponto_lub TEXT, rota TEXT, frequencia TEXT
    )");
    $pdo->exec("CREATE TABLE system_meta (mkey TEXT PRIMARY KEY, mval TEXT)");

    $yesterday = date('Y-m-d', strtotime('-3 days'));
    $tech = json_encode([
        'material' => 'Graxa EP2',
        'periodo' => '7 dias',
        'data_proxima_intervencao' => $yesterday,
        'rota' => 'R-A',
        'servico' => 'Engraxar rolamento',
    ], JSON_UNESCAPED_UNICODE);

    $pdo->prepare('INSERT INTO ativos (nome, tag, tipo, dados_tecnicos) VALUES (?,?,?,?)')
        ->execute(['Bomba Eval #1', 'TAG-EVAL-1', 'ponto', $tech]);

    $techFuture = json_encode([
        'material' => 'Óleo ISO 68',
        'periodo' => '30 dias',
        'data_proxima_intervencao' => date('Y-m-d', strtotime('+20 days')),
        'rota' => 'R-B',
    ], JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO ativos (nome, tag, tipo, dados_tecnicos) VALUES (?,?,?,?)')
        ->execute(['Motor Futuro', 'TAG-FUT', 'ponto', $techFuture]);

    return $pdo;
}

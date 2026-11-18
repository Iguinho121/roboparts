<?php
require_once __DIR__ . '/init.php';

// Busca os dados principais usando o crud.php
$componentes = readAll($pdo, 'componentes');
$projetos = readAll($pdo, 'projetos');
$kits = readAll($pdo, 'kits');
$movimentacoes = readAll($pdo, 'movimentacoes');

// Calcula totais de estoque e filtra itens com estoque baixo
$totalUnidades = 0;
$disponiveisUnidades = 0;
$itensEstoqueBaixo = [];

foreach ($componentes as $comp) {
    $totalUnidades += (int) $comp['quantidade_total'];
    $disponiveisUnidades += (int) $comp['quantidade_disponivel'];

    if ($comp['quantidade_disponivel'] < $comp['estoque_minimo']) {
        $itensEstoqueBaixo[] = $comp;
    }
}

// Conta projetos em andamento
$projetosAtivos = 0;
foreach ($projetos as $proj) {
    if ($proj['status'] === 'Em andamento') {
        $projetosAtivos++;
    }
}

// Ordena movimentações da mais recente para a mais antiga e pega as 5 últimas
usort($movimentacoes, function ($a, $b) {
    return strtotime($b['data_movimentacao']) - strtotime($a['data_movimentacao']);
});
$ultimasMovimentacoes = array_slice($movimentacoes, 0, 5);
$projetosRecentes = array_slice($projetos, 0, 4);

// Mapas de cores para os badges
$badgesMov = [
    'Entrada'    => 'badge-entrada',
    'Saida'      => 'badge-saida',
    'Emprestimo' => 'badge-emprestimo',
    'Devolucao'  => 'badge-devolucao',
];

$badgesProjeto = [
    'Em andamento' => 'badge-entrada',
    'Planejamento' => 'badge-devolucao',
    'Concluido'    => 'badge-concluido',
];
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="css/dashboard.css">
    <title>Dashboard - Flux</title>
</head>

<body>

    <div class="app-robo">

        <!-- Menu lateral -->
        <nav class="sidebar">

            <div class="sidebar-logo">
                <img src="imagens/novo_logo.png" alt="Logo Flux">
            </div>

            <ul class="sidebar-menu">

                <li class="active">
                    <a href="index.php">
                        <span class="material-symbols-outlined menu-icon">dashboard</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="componentes.php">
                        <span class="material-symbols-outlined menu-icon">memory</span>
                        <span>Componentes</span>
                    </a>
                </li>

                <li>
                    <a href="adicionar_componente.php">
                        <span class="material-symbols-outlined menu-icon">add_circle</span>
                        <span>Cadastro</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="material-symbols-outlined menu-icon">swap_horiz</span>
                        <span>Empréstimo</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="material-symbols-outlined menu-icon">folder</span>
                        <span>Projetos</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="material-symbols-outlined menu-icon">history</span>
                        <span>Histórico</span>
                    </a>
                </li>

                <li>
                    <a href="kits.php">
                        <span class="material-symbols-outlined menu-icon">inventory_2</span>
                        <span>Kits e Catálogos</span>
                    </a>
                </li>

            </ul>

            <div class="sidebar-card">
                <img src="imagens/robozin.png" alt="Robo do menu">
                <h3>Flux</h3>
                <span class="robo-descricao">
                    Inovação em<br>cada componente.
                </span>
            </div>

            <div class="sidebar-status">
                <span class="status-bolinha"></span>
                <p>Sistema Operacional <strong>v1.0</strong></p>
            </div>

        </nav>

        <!-- Conteúdo Principal -->
        <main class="main">

            <header class="titulo-principal">
                <div class="header-imagem">
                    <span class="material-symbols-outlined header-icon">dashboard</span>
                </div>
                <div class="linha-header"></div>

                <div class="header-texto">
                    <h1>Dashboard</h1>
                    <p>Visão Geral do Inventário, Projetos e Movimentações</p>
                </div>
            </header>

            <!-- Cards de Resumo (KPIs) -->
            <section class="cards-resumo">

                <div class="kpi-card">
                    <div class="kpi-topo">
                        <span class="kpi-titulo">Unidades Disponíveis</span>
                        <span class="material-symbols-outlined kpi-icone azul">memory</span>
                    </div>
                    <strong class="kpi-valor"><?= $disponiveisUnidades ?> <small>/ <?= $totalUnidades ?></small></strong>
                    <span class="kpi-sub"><?= count($componentes) ?> tipos de componentes cadastrados</span>
                </div>

                <a href="componentes.php?estoque_baixo=1" class="kpi-card kpi-link <?= count($itensEstoqueBaixo) > 0 ? 'kpi-alerta' : '' ?>">
                    <div class="kpi-topo">
                        <span class="kpi-titulo">Estoque Baixo</span>
                        <span class="material-symbols-outlined kpi-icone amarelo">warning</span>
                    </div>
                    <strong class="kpi-valor"><?= count($itensEstoqueBaixo) ?></strong>
                    <span class="kpi-sub">
                        <?= count($itensEstoqueBaixo) > 0 ? 'Clique para ver itens críticos' : 'Todos os níveis normais' ?>
                    </span>
                </a>

                <div class="kpi-card">
                    <div class="kpi-topo">
                        <span class="kpi-titulo">Projetos Ativos</span>
                        <span class="material-symbols-outlined kpi-icone verde">folder</span>
                    </div>
                    <strong class="kpi-valor"><?= $projetosAtivos ?> <small>/ <?= count($projetos) ?></small></strong>
                    <span class="kpi-sub">Em andamento no laboratório</span>
                </div>

                <a href="kits.php" class="kpi-card kpi-link">
                    <div class="kpi-topo">
                        <span class="kpi-titulo">Kits Configurados</span>
                        <span class="material-symbols-outlined kpi-icone roxo">inventory_2</span>
                    </div>
                    <strong class="kpi-valor"><?= count($kits) ?></strong>
                    <span class="kpi-sub">Prontos para requisição rápida</span>
                </a>

            </section>

            <section class="painel-grid">

                <div class="coluna-principal">

                    <div class="painel-box">
                        <div class="painel-header">
                            <h2>
                                <span class="material-symbols-outlined">history</span>
                                Últimas Movimentações
                            </h2>
                            <a href="componentes.php" class="link-ver-todos">Ver estoque →</a>
                        </div>

                        <?php if (count($ultimasMovimentacoes) == 0): ?>
                            <p class="texto-vazio">Nenhuma movimentação registrada ainda.</p>
                        <?php else: ?>
                            <div class="lista-movimentacoes">
                                <?php foreach ($ultimasMovimentacoes as $mov):
                                    $comp = read($pdo, 'componentes', 'id = ?', [$mov['componente_id']]);
                                    $proj = $mov['projeto_id'] ? read($pdo, 'projetos', 'id = ?', [$mov['projeto_id']]) : null;
                                    $classeBadge = $badgesMov[$mov['tipo_movimentacao']] ?? 'badge-entrada';
                                ?>
                                    <div class="mov-item">
                                        <div class="mov-info">
                                            <strong><?= htmlspecialchars($comp['nome'] ?? 'Componente removido') ?></strong>
                                            <span>
                                                <?= $proj ? 'Projeto: ' . htmlspecialchars($proj['nome']) : htmlspecialchars($mov['observacao'] ?? 'Movimentação avulsa') ?>
                                            </span>
                                        </div>

                                        <div class="mov-meta">
                                            <span class="badge <?= $classeBadge ?>"><?= htmlspecialchars($mov['tipo_movimentacao']) ?></span>
                                            <strong class="mov-qtd"><?= $mov['quantidade'] ?> un.</strong>
                                            <small><?= date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Projetos do Laboratório -->
                    <div class="painel-box">
                        <div class="painel-header">
                            <h2>
                                <span class="material-symbols-outlined">precision_manufacturing</span>
                                Projetos do Laboratório
                            </h2>
                            <span class="link-ver-todos"><?= count($projetos) ?> cadastrados</span>
                        </div>

                        <?php if (count($projetosRecentes) == 0): ?>
                            <p class="texto-vazio">Nenhum projeto cadastrado no momento.</p>
                        <?php else: ?>
                            <div class="lista-movimentacoes">
                                <?php foreach ($projetosRecentes as $proj):
                                    $itensProj = readAll($pdo, 'projeto_componentes', 'projeto_id = ?', [$proj['id']]);
                                    $classeStatus = $badgesProjeto[$proj['status']] ?? 'badge-devolucao';
                                    $dataInicio = $proj['data_inicio'] ? date('d/m/Y', strtotime($proj['data_inicio'])) : 'Sem data';
                                ?>
                                    <div class="mov-item">
                                        <div class="mov-info">
                                            <strong><?= htmlspecialchars($proj['nome']) ?></strong>
                                            <span><?= htmlspecialchars($proj['descricao'] ?? 'Sem descrição') ?></span>
                                        </div>

                                        <div class="mov-meta">
                                            <span class="badge <?= $classeStatus ?>"><?= htmlspecialchars($proj['status']) ?></span>
                                            <strong class="mov-qtd"><?= count($itensProj) ?> itens</strong>
                                            <small>Início: <?= $dataInicio ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Alertas e Atalhos -->
                <div class="coluna-lateral">

                    <!-- Alerta de Estoque Baixo -->
                    <div class="painel-box">
                        <div class="painel-header">
                            <h2>
                                <span class="material-symbols-outlined icone-aviso">warning</span>
                                Atenção no Estoque
                            </h2>
                        </div>

                        <?php if (count($itensEstoqueBaixo) == 0): ?>
                            <p class="texto-vazio">Nenhum componente abaixo do estoque mínimo.</p>
                        <?php else: ?>
                            <ul class="lista-alertas">
                                <?php foreach ($itensEstoqueBaixo as $item): ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                            <small>Mínimo exigido: <?= $item['estoque_minimo'] ?> un.</small>
                                        </div>
                                        <span class="pill-critico"><?= $item['quantidade_disponivel'] ?> un.</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Atalhos Rápidos -->
                    <div class="painel-box">
                        <div class="painel-header">
                            <h2>
                                <span class="material-symbols-outlined">bolt</span>
                                Ações Rápidas
                            </h2>
                        </div>

                        <div class="atalhos-grid">
                            <a href="adicionar_componente.php" class="btn-atalho primario">
                                <span class="material-symbols-outlined">add_circle</span>
                                Cadastrar Peça
                            </a>
                            <a href="kits.php" class="btn-atalho">
                                <span class="material-symbols-outlined">inventory_2</span>
                                Requisitar Kit
                            </a>
                            <a href="componentes.php" class="btn-atalho">
                                <span class="material-symbols-outlined">search</span>
                                Consultar Estoque
                            </a>
                        </div>
                    </div>

                </div>

            </section>

        </main>

    </div>

</body>

</html>
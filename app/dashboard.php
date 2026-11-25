<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

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

// Mapas de cores para os badges (aceita com e sem acento)
$badgesMov = [
    'Entrada'    => 'badge-entrada',
    'Saida'      => 'badge-saida',
    'Saída'      => 'badge-saida',
    'Emprestimo' => 'badge-emprestimo',
    'Empréstimo' => 'badge-emprestimo',
    'Devolucao'  => 'badge-devolucao',
    'Devolução'  => 'badge-devolucao',
];

$badgesProjeto = [
    'Em andamento' => 'badge-entrada',
    'Planejamento' => 'badge-devolucao',
    'Concluido'    => 'badge-concluido',
    'Concluído'    => 'badge-concluido',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | RoboParts</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..400,0..1,0">
</head>

<body>
    <nav class="sidebar" id="glassSidebar" aria-label="Navegação principal">
        <div class="sidebar-glass-sheen" aria-hidden="true"></div>

        <a class="sidebar-logo" href="dashboard.php" aria-label="Flux - início">
            <img src="imagens/logoflux.png" alt="Logo Flux">
            <span>LUX</span>
        </a>

        <ul class="sidebar-menu">
            <li class="active"><a href="dashboard.php" aria-current="page"><span class="material-symbols-outlined menu-icon">dashboard</span><span>Dashboard</span></a></li>
            <li><a href="componentes.php"><span class="material-symbols-outlined menu-icon">memory</span><span>Componentes</span></a></li>
            <?php if (isAdmin()): ?>
                <li><a href="adicionar_componente.php"><span class="material-symbols-outlined menu-icon">add_circle</span><span>Cadastro</span></a></li>
            <?php endif; ?>
            <li><a href="projetos.php"><span class="material-symbols-outlined menu-icon">folder</span><span>Projetos</span></a></li>
            <li><a href="historico.php"><span class="material-symbols-outlined menu-icon">history</span><span>Histórico</span></a></li>
            <li><a href="kits.php"><span class="material-symbols-outlined menu-icon">inventory_2</span><span>Kits e Catálogos</span></a></li>
        </ul>

        <a class="sidebar-logout" href="logout.php" aria-label="Sair do sistema">
            <span class="material-symbols-outlined menu-icon">logout</span>
            <span>Sair</span>
        </a>
    </nav>

    <main class="pagina">
        <header class="cabecalho">
            <div>
                <p class="identificacao">ROBOPARTS / VISÃO GERAL</p>
                <h1>Dashboard</h1>
                <p class="descricao">Visão geral do inventário, projetos e movimentações do laboratório.</p>
            </div>
        </header>

        <!-- Cards de Resumo (KPIs) -->
        <section class="resumo" <?= !isAdmin() ? 'style="grid-template-columns: repeat(3, minmax(0, 1fr));"' : '' ?>>
            <article class="cartao">
                <div class="kpi-topo">
                    <span>Unidades Disponíveis</span>
                    <span class="material-symbols-outlined kpi-icone azul">memory</span>
                </div>
                <strong><?= $disponiveisUnidades ?> <small>/ <?= $totalUnidades ?></small></strong>
                <p class="kpi-sub"><?= count($componentes) ?> tipos de componentes cadastrados</p>
            </article>

            <?php if (isAdmin()): ?>
                <a href="componentes.php?estoque_baixo=1" class="cartao kpi-link <?= count($itensEstoqueBaixo) > 0 ? 'kpi-alerta' : '' ?>">
                    <div class="kpi-topo">
                        <span>Estoque Baixo</span>
                        <span class="material-symbols-outlined kpi-icone amarelo">warning</span>
                    </div>
                    <strong class="<?= count($itensEstoqueBaixo) > 0 ? 'texto-emprestimo' : '' ?>"><?= count($itensEstoqueBaixo) ?></strong>
                    <p class="kpi-sub">
                        <?= count($itensEstoqueBaixo) > 0 ? 'Clique para ver itens críticos' : 'Todos os níveis normais' ?>
                    </p>
                </a>
            <?php endif; ?>

            <article class="cartao">
                <div class="kpi-topo">
                    <span>Projetos Ativos</span>
                    <span class="material-symbols-outlined kpi-icone verde">folder</span>
                </div>
                <strong class="texto-entrada"><?= $projetosAtivos ?> <small>/ <?= count($projetos) ?></small></strong>
                <p class="kpi-sub">Em andamento no laboratório</p>
            </article>

            <a href="kits.php" class="cartao kpi-link">
                <div class="kpi-topo">
                    <span>Kits Configurados</span>
                    <span class="material-symbols-outlined kpi-icone roxo">inventory_2</span>
                </div>
                <strong class="texto-devolucao"><?= count($kits) ?></strong>
                <p class="kpi-sub">Prontos para requisição rápida</p>
            </a>
        </section>

        <section class="painel-grid">
            <div class="coluna-principal">
                <!-- Últimas Movimentações -->
                <section class="painel">
                    <div class="titulo-painel titulo-lista">
                        <div>
                            <h2>Últimas movimentações</h2>
                            <p>Registros recentes de entrada, saída e empréstimo.</p>
                        </div>
                        <a href="historico.php" class="contador">Ver histórico →</a>
                    </div>

                    <?php if (count($ultimasMovimentacoes) == 0): ?>
                        <div class="mensagem-vazia">
                            <p>Nenhuma movimentação registrada ainda.</p>
                        </div>
                    <?php else: ?>
                        <div class="lista-movimentacoes">
                            <?php foreach ($ultimasMovimentacoes as $mov):
                                $comp = read($pdo, 'componentes', 'id = ?', [$mov['componente_id']]);
                                $proj = $mov['projeto_id'] ? read($pdo, 'projetos', 'id = ?', [$mov['projeto_id']]) : null;
                                $classeBadge = $badgesMov[$mov['tipo_movimentacao']] ?? 'badge-entrada';
                            ?>
                                <article class="movimentacao-bolha">
                                    <div class="mov-info">
                                        <strong><?= htmlspecialchars($comp['nome'] ?? 'Componente removido') ?></strong>
                                        <span>
                                            <?= $proj ? 'Projeto: ' . htmlspecialchars($proj['nome']) : htmlspecialchars($mov['observacao'] ?? 'Movimentação avulsa') ?>
                                        </span>
                                    </div>

                                    <div class="mov-meta">
                                        <span class="etiqueta <?= $classeBadge ?>"><?= htmlspecialchars($mov['tipo_movimentacao']) ?></span>
                                        <span class="bolha-qtd"><?= $mov['quantidade'] ?> un.</span>
                                        <time class="bolha-data"><?= date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) ?></time>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Projetos do Laboratório -->
                <section class="painel">
                    <div class="titulo-painel titulo-lista">
                        <div>
                            <h2>Projetos do laboratório</h2>
                            <p>Acompanhe os projetos em desenvolvimento.</p>
                        </div>
                        <span class="contador"><?= count($projetos) ?> cadastrado(s)</span>
                    </div>

                    <?php if (count($projetosRecentes) == 0): ?>
                        <div class="mensagem-vazia">
                            <p>Nenhum projeto cadastrado no momento.</p>
                        </div>
                    <?php else: ?>
                        <div class="lista-movimentacoes">
                            <?php foreach ($projetosRecentes as $proj):
                                $itensProj = readAll($pdo, 'projeto_componentes', 'projeto_id = ?', [$proj['id']]);
                                $classeStatus = $badgesProjeto[$proj['status']] ?? 'badge-devolucao';
                                $dataInicio = $proj['data_inicio'] ? date('d/m/Y', strtotime($proj['data_inicio'])) : 'Sem data';
                            ?>
                                <article class="movimentacao-bolha">
                                    <div class="mov-info">
                                        <strong><?= htmlspecialchars($proj['nome']) ?></strong>
                                        <span><?= htmlspecialchars($proj['descricao'] ?? 'Sem descrição') ?></span>
                                    </div>

                                    <div class="mov-meta">
                                        <span class="etiqueta <?= $classeStatus ?>"><?= htmlspecialchars($proj['status']) ?></span>
                                        <span class="bolha-qtd"><?= count($itensProj) ?> itens</span>
                                        <time class="bolha-data">Início: <?= $dataInicio ?></time>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Coluna Lateral: Alertas e Atalhos -->
            <div class="coluna-lateral">
                <?php if (isAdmin()): ?>
                    <section class="painel">
                        <div class="titulo-painel">
                            <h2>Atenção no estoque</h2>
                            <p>Componentes abaixo do limite mínimo.</p>
                        </div>

                        <?php if (count($itensEstoqueBaixo) == 0): ?>
                            <div class="mensagem-vazia">
                                <p>Nenhum componente abaixo do estoque mínimo.</p>
                            </div>
                        <?php else: ?>
                            <ul class="lista-alertas">
                                <?php foreach ($itensEstoqueBaixo as $item): ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                            <small>Mínimo exigido: <?= $item['estoque_minimo'] ?> un.</small>
                                        </div>
                                        <span class="etiqueta badge-emprestimo"><?= $item['quantidade_disponivel'] ?> un.</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <section class="painel">
                        <div class="titulo-painel titulo-lista">
                            <div>
                                <h2>Kits disponíveis</h2>
                                <p>Conjuntos prontos para uso.</p>
                            </div>
                            <a href="kits.php" class="contador">Ver todos →</a>
                        </div>

                        <?php if (count($kits) == 0): ?>
                            <div class="mensagem-vazia">
                                <p>Nenhum kit configurado no momento.</p>
                            </div>
                        <?php else: ?>
                            <ul class="lista-alertas">
                                <?php foreach (array_slice($kits, 0, 3) as $kit): ?>
                                    <li>
                                        <div>
                                            <strong><?= htmlspecialchars($kit['nome']) ?></strong>
                                            <small><?= htmlspecialchars($kit['descricao'] ?? 'Pronto para requisição') ?></small>
                                        </div>
                                        <a href="kits.php" class="etiqueta badge-entrada" style="text-decoration: none;">Requisitar</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- Ações Rápidas -->
                <section class="painel">
                    <div class="titulo-painel">
                        <h2>Ações rápidas</h2>
                        <p>Atalhos diretos do sistema.</p>
                    </div>

                    <div class="atalhos-grid">
                        <?php if (isAdmin()): ?>
                            <a href="adicionar_componente.php" class="btn-atalho primario">
                                <span class="material-symbols-outlined">add_circle</span>
                                Cadastrar Peça
                            </a>
                        <?php else: ?>
                            <a href="kits.php" class="btn-atalho primario">
                                <span class="material-symbols-outlined">inventory_2</span>
                                Requisitar Kit
                            </a>
                        <?php endif; ?>

                        <a href="componentes.php" class="btn-atalho">
                            <span class="material-symbols-outlined">search</span>
                            Consultar Estoque
                        </a>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <script>
        (() => {
            const sidebar = document.getElementById('glassSidebar');
            if (!sidebar) return;

            sidebar.addEventListener('pointermove', (e) => {
                const rect = sidebar.getBoundingClientRect();
                sidebar.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
                sidebar.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
            });
        })();
    </script>
</body>

</html>
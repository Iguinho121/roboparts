<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

// =========================================================================
// PROCESSA DEVOLUÇÃO DE EMPRÉSTIMO NO PRÓPRIO DASHBOARD
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'devolver_emprestimo') {
    $movimentacaoId = (int) ($_POST['movimentacao_id'] ?? 0);

    if ($movimentacaoId <= 0) {
        $_SESSION['flash_erro'] = 'Empréstimo inválido para devolução.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtEmprestimo = $pdo->prepare(
                "SELECT id, componente_id, projeto_id, quantidade, observacao
                 FROM movimentacoes
                 WHERE id = ? AND tipo_movimentacao = 'Emprestimo' AND devolvido = 0
                 FOR UPDATE"
            );
            $stmtEmprestimo->execute([$movimentacaoId]);
            $emprestimo = $stmtEmprestimo->fetch();

            if (!$emprestimo) {
                throw new RuntimeException('Este empréstimo não foi encontrado ou já foi devolvido.');
            }

            $stmtComp = $pdo->prepare('SELECT id, nome FROM componentes WHERE id = ? FOR UPDATE');
            $stmtComp->execute([(int) $emprestimo['componente_id']]);
            $componente = $stmtComp->fetch();

            if (!$componente) {
                throw new RuntimeException('O componente vinculado a este empréstimo não existe mais no inventário.');
            }

            // Marca o empréstimo original como devolvido para sair da lista de abertos
            $stmtMarcarDevolvido = $pdo->prepare('UPDATE movimentacoes SET devolvido = 1 WHERE id = ?');
            $stmtMarcarDevolvido->execute([(int) $emprestimo['id']]);

            // Restaura a quantidade disponível no estoque
            $qtdDevolvida = (int) $emprestimo['quantidade'];
            $stmtEstoque = $pdo->prepare(
                'UPDATE componentes
                 SET quantidade_disponivel = LEAST(quantidade_total, quantidade_disponivel + ?)
                 WHERE id = ?'
            );
            $stmtEstoque->execute([$qtdDevolvida, (int) $emprestimo['componente_id']]);

            // Registra a devolução para aparecer no Histórico
            $usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';
            $obsDevolucao = "Devolvido por: {$usuarioNome} (ref. empréstimo #{$emprestimo['id']})";
            if (!empty($emprestimo['observacao'])) {
                $obsDevolucao .= " — {$emprestimo['observacao']}";
            }

            $stmtHistorico = $pdo->prepare(
                'INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao, devolvido)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmtHistorico->execute([
                (int) $emprestimo['componente_id'],
                $emprestimo['projeto_id'] !== null ? (int) $emprestimo['projeto_id'] : null,
                'Devolucao',
                $qtdDevolvida,
                $obsDevolucao,
            ]);

            $pdo->commit();
            $_SESSION['flash_sucesso'] = "Devolução de {$qtdDevolvida}x {$componente['nome']} registrada com sucesso!";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_erro'] = $e instanceof PDOException
                ? 'Erro ao registrar devolução no banco: ' . $e->getMessage()
                : $e->getMessage();
        }
    }

    header('Location: dashboard.php');
    exit;
}

$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

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

// Filtra empréstimos ainda em aberto (devolvido = 0) para exibição no card de devolução
$emprestimosAbertos = array_values(array_filter($movimentacoes, function ($mov) {
    return ($mov['tipo_movimentacao'] ?? '') === 'Emprestimo'
        && (int) ($mov['devolvido'] ?? 0) === 0;
}));

// Mapas de cores para os badges (aceita com e sem acento)
$badgesMov = [
    'Entrada'         => 'badge-entrada',
    'Saida'           => 'badge-saida',
    'Saída'           => 'badge-saida',
    'Emprestimo'      => 'badge-emprestimo',
    'Empréstimo'      => 'badge-emprestimo',
    'Kit Requisitado' => 'badge-emprestimo',
    'Devolucao'       => 'badge-devolucao',
    'Devolução'       => 'badge-devolucao',
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

        <?php if ($flashSucesso): ?>
            <div class="alerta-feedback sucesso" role="status">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?= htmlspecialchars($flashSucesso) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($flashErro): ?>
            <div class="alerta-feedback erro" role="alert">
                <span class="material-symbols-outlined">error</span>
                <span><?= htmlspecialchars($flashErro) ?></span>
            </div>
        <?php endif; ?>

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

            <!-- Coluna Lateral: Alertas e Empréstimos para Devolução -->
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

                <!-- Empréstimos em Aberto (Devolução) -->
                <section class="painel">
                    <div class="titulo-painel titulo-lista">
                        <div>
                            <h2>Empréstimos em aberto</h2>
                            <p>Itens retirados pendentes de devolução.</p>
                        </div>
                        <span class="contador"><?= count($emprestimosAbertos) ?> ativo(s)</span>
                    </div>

                    <?php if (count($emprestimosAbertos) === 0): ?>
                        <div class="mensagem-vazia">
                            <p>Nenhum empréstimo pendente de devolução.</p>
                        </div>
                    <?php else: ?>
                        <ul class="lista-alertas lista-emprestimos-abertos">
                            <?php foreach ($emprestimosAbertos as $emp):
                                $compEmp = read($pdo, 'componentes', 'id = ?', [$emp['componente_id']]);
                                $nomeCompEmp = $compEmp['nome'] ?? 'Componente #' . (int) $emp['componente_id'];
                                $qtdEmp = (int) $emp['quantidade'];
                                $dataEmp = date('d/m/Y H:i', strtotime($emp['data_movimentacao']));
                            ?>
                                <li class="item-emprestimo-aberto">
                                    <div class="emp-detalhes">
                                        <strong title="<?= htmlspecialchars($nomeCompEmp) ?>">
                                            <?= $qtdEmp ?>x <?= htmlspecialchars($nomeCompEmp) ?>
                                        </strong>
                                        <small title="<?= htmlspecialchars(($emp['observacao'] ?? '') . ' • ' . $dataEmp) ?>">
                                            <?= $dataEmp ?><?= !empty($emp['observacao']) ? ' • ' . htmlspecialchars($emp['observacao']) : '' ?>
                                        </small>
                                    </div>

                                    <form method="POST" action="dashboard.php"
                                          data-qtd="<?= $qtdEmp ?>"
                                          data-nome="<?= htmlspecialchars($nomeCompEmp, ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirmarDevolucao(event, this)">
                                        <input type="hidden" name="acao" value="devolver_emprestimo">
                                        <input type="hidden" name="movimentacao_id" value="<?= (int) $emp['id'] ?>">
                                        <button type="submit" class="btn-devolver-item">
                                            <span class="material-symbols-outlined">assignment_return</span>
                                            Devolver
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>

    <!-- MODAL DE CONFIRMAÇÃO UNIVERSAL (MESMO PADRÃO DE COMPONENTES.PHP E KITS.PHP) -->
    <div id="modalConfirmacao" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalConfirmacao')">
        <div class="modal-box modal-confirm">
            <div class="confirm-icone-box azul" id="confirm_icone_box">
                <span class="material-symbols-outlined">assignment_return</span>
            </div>
            <h3 id="confirm_titulo">Confirmar Devolução</h3>
            <p id="confirm_mensagem">Tem certeza que deseja continuar?</p>

            <div class="modal-rodape centralizado">
                <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalConfirmacao')">Voltar</button>
                <button type="button" id="btn_confirmar_sim" class="btn-modal-confirmar azul">Sim, Confirmar</button>
            </div>
        </div>
    </div>

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

        let formPendente = null;

        function abrirModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('ativo');
        }

        function fecharModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('ativo');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function confirmarDevolucao(event, form) {
            event.preventDefault();
            formPendente = form;

            const qtd = form.dataset.qtd || '1';
            const nome = escapeHtml(form.dataset.nome || 'este componente');

            document.getElementById('confirm_titulo').textContent = 'Confirmar Devolução';
            document.getElementById('confirm_mensagem').innerHTML =
                `Deseja confirmar a devolução de <strong>${qtd}x ${nome}</strong> ao estoque?`;
            document.getElementById('confirm_icone_box').className = 'confirm-icone-box azul';
            document.getElementById('confirm_icone_box').innerHTML =
                '<span class="material-symbols-outlined">assignment_return</span>';

            const btnSim = document.getElementById('btn_confirmar_sim');
            btnSim.className = 'btn-modal-confirmar azul';
            abrirModal('modalConfirmacao');
            return false;
        }

        document.getElementById('btn_confirmar_sim').addEventListener('click', () => {
            fecharModal('modalConfirmacao');
            if (formPendente) {
                const alvo = formPendente;
                formPendente = null;
                alvo.submit();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                fecharModal('modalConfirmacao');
            }
        });
    </script>
</body>

</html>
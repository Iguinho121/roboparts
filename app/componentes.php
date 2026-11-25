<?php
// Carrega a conexão com o banco ($pdo), as funções prontas de CRUD e as regras de login
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';

// Trava de segurança: se ninguém estiver logado na sessão, expulsa para a tela de login
exigirLogin();

// =========================================================================
// 1. RECEBENDO AS AÇÕES DOS FORMULÁRIOS (CADASTRAR, EMPRESTAR, EDITAR E EXCLUIR)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    try {
        // -----------------------------------------------------------------
        // AÇÃO 0: CADASTRAR COMPONENTE (Exclusivo para Administradores)
        // -----------------------------------------------------------------
        if ($acao === 'cadastrar') {
            if (!isAdmin()) {
                $_SESSION['flash_erro'] = 'Acesso negado. Apenas administradores podem cadastrar componentes.';
            } else {
                $nome = trim($_POST['nome'] ?? '');
                $codigo = trim($_POST['codigo'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
                $fornecedorId = !empty($_POST['fornecedor_id']) ? (int) $_POST['fornecedor_id'] : null;
                $condicaoId = (int) ($_POST['condicao_id'] ?? 1);
                $qtdTotal = (int) ($_POST['quantidade_total'] ?? 0);
                $estMin = (int) ($_POST['estoque_minimo'] ?? 5);

                if ($nome === '' || $categoriaId <= 0) {
                    $_SESSION['flash_erro'] = 'Nome e categoria são obrigatórios.';
                } elseif ($qtdTotal < 0 || $estMin < 0) {
                    $_SESSION['flash_erro'] = 'Quantidade total e estoque mínimo não podem ser negativos.';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'INSERT INTO componentes (nome, codigo, descricao, categoria_id, fornecedor_id, condicao_id, quantidade_total, quantidade_disponivel, estoque_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $nome,
                        $codigo !== '' ? $codigo : null,
                        $descricao !== '' ? $descricao : null,
                        $categoriaId,
                        $fornecedorId,
                        $condicaoId,
                        $qtdTotal,
                        $qtdTotal,
                        $estMin
                    ]);

                    $novoId = (int) $pdo->lastInsertId();
                    $nomeUsuario = $_SESSION['usuario_nome'] ?? 'Administrador';
                    $obsCadastro = "Cadastrado por: {$nomeUsuario}" . ($codigo !== '' ? " — SKU: {$codigo}" : '');

                    // Registra como 'Entrada' para aparecer no historico.php e nos indicadores de Entrada
                    $stmtMov = $pdo->prepare(
                        'INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmtMov->execute([$novoId, null, 'Entrada', $qtdTotal, $obsCadastro]);

                    $pdo->commit();
                    $_SESSION['flash_sucesso'] = "Componente '{$nome}' cadastrado e registrado no histórico!";
                }
            }
        }

        // -----------------------------------------------------------------
        // AÇÃO A: EMPRESTAR COMPONENTE (Qualquer usuário logado pode fazer)
        // -----------------------------------------------------------------
        elseif ($acao === 'emprestar') {
            $id = (int) ($_POST['componente_id'] ?? 0);
            $qtd = (int) ($_POST['quantidade'] ?? 0);
            // Se não escolher projeto, salva como NULL no banco
            $projetoId = !empty($_POST['projeto_id']) ? (int) $_POST['projeto_id'] : null;
            $obsUsuario = trim($_POST['observacao'] ?? '');

            // Busca a peça no banco para conferir se existe e quanto tem em estoque
            $comp = read($pdo, 'componentes', 'id = ?', [$id]);

            if (!$comp) {
                $_SESSION['flash_erro'] = 'Componente não encontrado.';
            } elseif ($qtd <= 0 || $qtd > (int) $comp['quantidade_disponivel']) {
                // Impede retirada negativa, zerada ou maior do que realmente existe no armário
                $_SESSION['flash_erro'] = 'Quantidade inválida ou maior que o estoque disponível (' . $comp['quantidade_disponivel'] . ' un.).';
            } else {
                // beginTransaction: garante que só salva se AS DUAS coisas funcionarem (descontar estoque + gravar histórico).
                // Se der erro no meio, o banco desfaz tudo para não furar o estoque.
                $pdo->beginTransaction();

                // Desconta a quantidade retirada da coluna quantidade_disponivel
                $novaQtd = (int) $comp['quantidade_disponivel'] - $qtd;
                $stmtUpd = $pdo->prepare('UPDATE componentes SET quantidade_disponivel = ? WHERE id = ?');
                $stmtUpd->execute([$novaQtd, $id]);

                // Monta a observação juntando o nome de quem está logado com o que ele digitou
                $nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
                $observacaoFinal = "Retirado por: {$nomeUsuario}" . ($obsUsuario !== '' ? " — {$obsUsuario}" : '');

                // Grava na tabela 'movimentacoes' para aparecer na Dashboard e no Histórico
                $stmtMov = $pdo->prepare('INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES (?, ?, ?, ?, ?)');
                $stmtMov->execute([$id, $projetoId, 'Emprestimo', $qtd, $observacaoFinal]);

                // Confirma e grava definitivamente as alterações no banco
                $pdo->commit();
                $_SESSION['flash_sucesso'] = "Empréstimo de {$qtd}x {$comp['nome']} registrado com sucesso!";
            }
        }

        // -----------------------------------------------------------------
        // AÇÃO B: EDITAR COMPONENTE (Exclusivo para Administradores)
        // -----------------------------------------------------------------
        elseif ($acao === 'editar') {
            // Segurança no back-end: mesmo que um usuário comum tente burlar o HTML, o PHP bloqueia aqui
            if (!isAdmin()) {
                $_SESSION['flash_erro'] = 'Acesso negado. Apenas administradores podem editar componentes.';
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $nome = trim($_POST['nome'] ?? '');
                $codigo = trim($_POST['codigo'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
                $fornecedorId = !empty($_POST['fornecedor_id']) ? (int) $_POST['fornecedor_id'] : null;
                $condicaoId = (int) ($_POST['condicao_id'] ?? 1);
                $qtdTotal = (int) ($_POST['quantidade_total'] ?? 0);
                $qtdDisp = (int) ($_POST['quantidade_disponivel'] ?? 0);
                $estMin = (int) ($_POST['estoque_minimo'] ?? 5);

                // Validações básicas antes de atualizar o banco
                if ($nome === '' || $categoriaId <= 0) {
                    $_SESSION['flash_erro'] = 'Nome e categoria são obrigatórios.';
                } elseif ($qtdDisp > $qtdTotal) {
                    $_SESSION['flash_erro'] = 'A quantidade disponível não pode ser maior que a quantidade total.';
                } else {
                    // Prepared statement (com '?') protege contra SQL Injection
                    $stmt = $pdo->prepare('UPDATE componentes SET nome = ?, codigo = ?, descricao = ?, categoria_id = ?, fornecedor_id = ?, condicao_id = ?, quantidade_total = ?, quantidade_disponivel = ?, estoque_minimo = ? WHERE id = ?');
                    $stmt->execute([
                        $nome,
                        $codigo !== '' ? $codigo : null,
                        $descricao !== '' ? $descricao : null,
                        $categoriaId,
                        $fornecedorId,
                        $condicaoId,
                        $qtdTotal,
                        $qtdDisp,
                        $estMin,
                        $id
                    ]);
                    $_SESSION['flash_sucesso'] = "Componente '{$nome}' atualizado com sucesso!";
                }
            }
        }

        // -----------------------------------------------------------------
        // AÇÃO C: EXCLUIR COMPONENTE (Exclusivo para Administradores)
        // -----------------------------------------------------------------
        elseif ($acao === 'excluir') {
            // Bloqueia exclusão caso não seja Admin
            if (!isAdmin()) {
                $_SESSION['flash_erro'] = 'Acesso negado. Apenas administradores podem excluir componentes.';
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $comp = read($pdo, 'componentes', 'id = ?', [$id]);

                if ($comp) {
                    $stmt = $pdo->prepare('DELETE FROM componentes WHERE id = ?');
                    $stmt->execute([$id]);
                    $_SESSION['flash_sucesso'] = "Componente '{$comp['nome']}' removido do inventário.";
                } else {
                    $_SESSION['flash_erro'] = 'Componente não encontrado para exclusão.';
                }
            }
        }
    } catch (PDOException $e) {
        // Se acontecer qualquer erro inesperado de banco durante a transação, desfaz tudo
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_erro'] = 'Erro ao processar operação: ' . $e->getMessage();
    }

    // Padrão PRG (Post-Redirect-Get): redireciona a página logo após salvar.
    // Isso evita que, se o usuário apertar F5, o formulário seja enviado duas vezes.
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: componentes.php' . $queryString);
    exit;
}

// =========================================================================
// 2. PREPARAÇÃO DOS DADOS PARA EXIBIR NA TELA
// =========================================================================

// Pega as mensagens de sucesso/erro salvas na sessão e já limpa para não aparecerem de novo no próximo F5
$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

// Busca listas auxiliares para preencher os <select> dos filtros e dos modais
$categorias = readAll($pdo, 'categorias');
$fornecedores = readAll($pdo, 'fornecedores');
$projetos = readAll($pdo, 'projetos');

// Lê os filtros que vieram pela URL (método GET, quando o usuário usa a barra de pesquisa)
$busca = $_GET['busca'] ?? '';
$categoriaFiltro = $_GET['categoria_id'] ?? '';
$apenasBaixo = isset($_GET['estoque_baixo']);

// Monta dinamicamente as condições do WHERE conforme o que foi filtrado
$filtros = [];
$params = [];

if ($busca != '') {
    $filtros[] = '(nome LIKE ? OR codigo LIKE ? OR descricao LIKE ?)';
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($categoriaFiltro != '') {
    $filtros[] = 'categoria_id = ?';
    $params[] = $categoriaFiltro;
}

if ($apenasBaixo) {
    $filtros[] = 'quantidade_disponivel < estoque_minimo';
}

// Junta todos os filtros ativos com AND (ou deixa null para trazer tudo)
$where = count($filtros) > 0 ? implode(' AND ', $filtros) : null;
$componentes = readAll($pdo, 'componentes', $where, $params);

// Dicionário visual para transformar o ID da condição (1, 2, 3) no texto e na cor do badge
$condicoes = [
    1 => ['texto' => 'Novo', 'classe' => 'distintivo_excelente'],
    2 => ['texto' => 'Usado - Bom', 'classe' => 'distintivo_bom'],
    3 => ['texto' => 'Danificado', 'classe' => 'distintivo_usado'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Componentes | Flux</title>
    <link rel="stylesheet" href="css/componentes.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..400,0..1,0">
</head>

<body>
    <nav class="sidebar" id="glassSidebar" aria-label="Navegação principal">
        <div class="sidebar-glass-sheen" aria-hidden="true"></div>

        <a class="sidebar-logo" href="dashboard.php" aria-label="Flux - início">
            <img src="imagens/logoflux.png" alt="Logo Flux">
            <span>LUX</span>
        </a>

        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><span class="material-symbols-outlined menu-icon">dashboard</span><span>Dashboard</span></a></li>
            <li class="active"><a href="componentes.php" aria-current="page"><span class="material-symbols-outlined menu-icon">memory</span><span>Componentes</span></a></li>
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
                <p class="identificacao">Flux / INVENTÁRIO</p>
                <h1>Gerenciar componentes</h1>
                <p class="descricao">Consulte, cadastre, filtre, empreste e gerencie os componentes e condições do estoque.</p>
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

        <section class="painel painel-filtros">
            <div class="titulo-painel">
                <h2>Consultar inventário</h2>
                <p>Busque por nome, código ou descrição, filtre por categoria ou destaque itens em estoque baixo.</p>
            </div>

            <form method="GET" action="componentes.php" class="barra-acoes">
                <div class="campo caixa-procurar">
                    <span class="material-symbols-outlined procurar-icone">search</span>
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                        placeholder="Buscar por nome, código ou descrição...">
                </div>

                <div class="campo categoria">
                    <select name="categoria_id" aria-label="Filtrar por categoria">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoriaFiltro == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="acoes-barra">
                    <button type="submit" class="btn-filtrar">Filtrar</button>

                    <?php if ($apenasBaixo): ?>
                        <a href="componentes.php" class="btn-filtro-ativo">
                            <span class="material-symbols-outlined icone-aviso">warning</span>
                            Estoque Baixo (Limpar)
                        </a>
                    <?php else: ?>
                        <a href="componentes.php?estoque_baixo=1" class="btn-filtro">
                            <span class="material-symbols-outlined icone-aviso">warning</span>
                            Apenas Estoque Baixo
                        </a>
                    <?php endif; ?>

                    <?php if (isAdmin()): ?>
                        <button type="button" class="btn-cadastrar" onclick="abrirModalCadastrar()">+ Cadastrar Componente</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="painel painel-tabela">
            <div class="titulo-painel titulo-lista">
                <div>
                    <h2>Componentes cadastrados</h2>
                    <p>Visão geral de disponibilidade, fornecedor, estado e ações.</p>
                </div>
                <span class="contador"><?= count($componentes) ?> item(ns)</span>
            </div>

            <?php if (count($componentes) == 0): ?>
                <div class="mensagem-vazia">
                    <h3>Nenhum componente encontrado</h3>
                    <p>Não existem componentes com os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <div class="tabela-cabecalho">
                        <div class="coluna">SKU / ITEM</div>
                        <div class="coluna">DESCRIÇÃO</div>
                        <div class="coluna">CATEGORIA</div>
                        <div class="coluna">FORNECEDOR</div>
                        <div class="coluna">DISPONÍVEL / TOTAL</div>
                        <div class="coluna">ESTADO</div>
                        <div class="coluna">AÇÕES</div>
                    </div>

                    <div class="lista-componentes">
                        <?php foreach ($componentes as $c):
                            $categoria = read($pdo, 'categorias', 'id = ?', [$c['categoria_id']]);
                            $fornecedor = $c['fornecedor_id']
                                ? read($pdo, 'fornecedores', 'id = ?', [$c['fornecedor_id']])
                                : null;
                            $condicao = $condicoes[$c['condicao_id']] ?? ['texto' => 'Não informada', 'classe' => 'distintivo_usado'];
                            $estoqueBaixo = $c['quantidade_disponivel'] < $c['estoque_minimo'];
                        ?>
                            <article class="componente-bolha">
                                <div class="coluna_item">
                                    <strong><?= htmlspecialchars($c['nome']) ?></strong>
                                    <small><?= htmlspecialchars($c['codigo'] ?? 'S/N') ?></small>
                                </div>

                                <div class="coluna_descricao">
                                    <?= htmlspecialchars($c['descricao'] ?? '-') ?>
                                </div>

                                <div class="coluna_categoria">
                                    <span><?= htmlspecialchars($categoria['nome'] ?? 'Sem categoria') ?></span>
                                </div>

                                <div class="coluna_fornecedor">
                                    <?= htmlspecialchars($fornecedor['nome'] ?? 'Não informado') ?>
                                </div>

                                <div class="coluna_disponivel">
                                    <span><?= $c['quantidade_disponivel'] ?> / <?= $c['quantidade_total'] ?> un.</span>
                                    <?php if ($estoqueBaixo): ?>
                                        <span class="distintivo_alerta">Baixo</span>
                                    <?php endif; ?>
                                </div>

                                <div class="coluna_estado">
                                    <span class="<?= $condicao['classe'] ?>"><?= $condicao['texto'] ?></span>
                                </div>

                                <div class="coluna_acoes">
                                    <?php if ((int) $c['quantidade_disponivel'] > 0): ?>
                                        <button type="button" class="btn-acao btn-emprestar"
                                            onclick='abrirModalEmprestar(<?= json_encode([
                                                "id" => $c["id"],
                                                "nome" => $c["nome"],
                                                "codigo" => $c["codigo"] ?? "S/N",
                                                "disponivel" => (int) $c["quantidade_disponivel"]
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Emprestar
                                        </button>
                                    <?php else: ?>
                                        <span class="badge-indisponivel">Indisponível</span>
                                    <?php endif; ?>

                                    <?php if (isAdmin()): ?>
                                        <button type="button" class="btn-acao btn-editar"
                                            onclick='abrirModalEditar(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Editar
                                        </button>

                                        <button type="button" class="btn-acao btn-excluir"
                                            onclick='abrirModalExcluir(<?= (int) $c["id"] ?>, <?= json_encode($c["nome"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Excluir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- MODAL 0: CADASTRAR COMPONENTE (Apenas Admin) -->
    <?php if (isAdmin()): ?>
        <div id="modalCadastrar" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalCadastrar')">
            <div class="modal-box modal-grande">
                <div class="modal-header">
                    <h3><span class="material-symbols-outlined">add_circle</span> Cadastrar Componente</h3>
                    <button type="button" class="modal-fechar" onclick="fecharModal('modalCadastrar')" aria-label="Fechar">×</button>
                </div>

                <form id="formCadastrar" method="POST" action="componentes.php"
                      onsubmit="return pedirConfirmacao(event, 'Confirmar Cadastro', 'Deseja cadastrar este componente e registrar a entrada no histórico?', 'verde')">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="modal-grid-2">
                        <div class="modal-campo">
                            <label for="cad_nome">Nome da Peça *</label>
                            <input type="text" name="nome" id="cad_nome" required placeholder="Ex: Arduino Uno R3">
                        </div>

                        <div class="modal-campo">
                            <label for="cad_codigo">Código / SKU</label>
                            <input type="text" name="codigo" id="cad_codigo" placeholder="Ex: ARD-001">
                        </div>
                    </div>

                    <div class="modal-campo">
                        <label for="cad_descricao">Descrição</label>
                        <textarea name="descricao" id="cad_descricao" rows="2" placeholder="Detalhes técnicos, encapsulamento, uso..."></textarea>
                    </div>

                    <div class="modal-grid-3">
                        <div class="modal-campo">
                            <label for="cad_categoria">Categoria *</label>
                            <select name="categoria_id" id="cad_categoria" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-campo">
                            <label for="cad_fornecedor">Fornecedor</label>
                            <select name="fornecedor_id" id="cad_fornecedor">
                                <option value="">Não informado</option>
                                <?php foreach ($fornecedores as $forn): ?>
                                    <option value="<?= $forn['id'] ?>"><?= htmlspecialchars($forn['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-campo">
                            <label for="cad_condicao">Estado / Condição *</label>
                            <select name="condicao_id" id="cad_condicao" required>
                                <option value="1" selected>Novo</option>
                                <option value="2">Usado - Bom</option>
                                <option value="3">Danificado</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-grid-2">
                        <div class="modal-campo">
                            <label for="cad_qtd_total">Quantidade Inicial (Total e Disponível) *</label>
                            <input type="number" name="quantidade_total" id="cad_qtd_total" min="0" value="1" required>
                        </div>

                        <div class="modal-campo">
                            <label for="cad_est_min">Estoque Mínimo *</label>
                            <input type="number" name="estoque_minimo" id="cad_est_min" min="0" value="5" required>
                        </div>
                    </div>

                    <div class="modal-rodape">
                        <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalCadastrar')">Cancelar</button>
                        <button type="submit" class="btn-modal-confirmar verde">Salvar e Registrar Entrada</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL 1: EMPRESTAR COMPONENTE (Admin e Comum) -->
    <div id="modalEmprestar" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalEmprestar')">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined">swap_horiz</span> Registrar Empréstimo</h3>
                <button type="button" class="modal-fechar" onclick="fecharModal('modalEmprestar')" aria-label="Fechar">×</button>
            </div>

            <form id="formEmprestar" method="POST" action="componentes.php"
                  onsubmit="return pedirConfirmacao(event, 'Confirmar Empréstimo', 'Deseja confirmar a retirada deste componente do estoque?', 'verde')">
                <input type="hidden" name="acao" value="emprestar">
                <input type="hidden" name="componente_id" id="emp_id">

                <div class="modal-resumo-item">
                    <strong id="emp_nome_item">Nome do Componente</strong>
                    <span id="emp_disp_texto">Disponível: 0 un.</span>
                </div>

                <div class="modal-campo">
                    <label for="emp_qtd">Quantidade para retirada *</label>
                    <input type="number" name="quantidade" id="emp_qtd" min="1" value="1" required>
                </div>

                <div class="modal-campo">
                    <label for="emp_projeto">Vincular a um Projeto (opcional)</label>
                    <select name="projeto_id" id="emp_projeto">
                        <option value="">Uso avulso / Sem projeto específico</option>
                        <?php foreach ($projetos as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['nome']) ?> (<?= $proj['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-campo">
                    <label for="emp_obs">Observação (opcional)</label>
                    <textarea name="observacao" id="emp_obs" rows="2" placeholder="Ex: Bancada 2, teste de sensor..."></textarea>
                </div>

                <div class="modal-rodape">
                    <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalEmprestar')">Cancelar</button>
                    <button type="submit" class="btn-modal-confirmar verde">Confirmar Empréstimo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: EDITAR COMPONENTE (Apenas Admin) -->
    <?php if (isAdmin()): ?>
        <div id="modalEditar" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalEditar')">
            <div class="modal-box modal-grande">
                <div class="modal-header">
                    <h3><span class="material-symbols-outlined">edit</span> Editar Componente</h3>
                    <button type="button" class="modal-fechar" onclick="fecharModal('modalEditar')" aria-label="Fechar">×</button>
                </div>

                <form id="formEditar" method="POST" action="componentes.php"
                      onsubmit="return pedirConfirmacao(event, 'Salvar Alterações', 'Deseja salvar as modificações feitas neste componente?', 'azul')">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="modal-grid-2">
                        <div class="modal-campo">
                            <label for="edit_nome">Nome da Peça *</label>
                            <input type="text" name="nome" id="edit_nome" required>
                        </div>

                        <div class="modal-campo">
                            <label for="edit_codigo">Código / SKU</label>
                            <input type="text" name="codigo" id="edit_codigo" placeholder="Ex: ARD-001">
                        </div>
                    </div>

                    <div class="modal-campo">
                        <label for="edit_descricao">Descrição</label>
                        <textarea name="descricao" id="edit_descricao" rows="2"></textarea>
                    </div>

                    <div class="modal-grid-3">
                        <div class="modal-campo">
                            <label for="edit_categoria">Categoria *</label>
                            <select name="categoria_id" id="edit_categoria" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-campo">
                            <label for="edit_fornecedor">Fornecedor</label>
                            <select name="fornecedor_id" id="edit_fornecedor">
                                <option value="">Não informado</option>
                                <?php foreach ($fornecedores as $forn): ?>
                                    <option value="<?= $forn['id'] ?>"><?= htmlspecialchars($forn['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-campo">
                            <label for="edit_condicao">Estado / Condição *</label>
                            <select name="condicao_id" id="edit_condicao" required>
                                <option value="1">Novo</option>
                                <option value="2">Usado - Bom</option>
                                <option value="3">Danificado</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-grid-3">
                        <div class="modal-campo">
                            <label for="edit_qtd_disp">Qtd. Disponível *</label>
                            <input type="number" name="quantidade_disponivel" id="edit_qtd_disp" min="0" required>
                        </div>

                        <div class="modal-campo">
                            <label for="edit_qtd_total">Qtd. Total *</label>
                            <input type="number" name="quantidade_total" id="edit_qtd_total" min="0" required>
                        </div>

                        <div class="modal-campo">
                            <label for="edit_est_min">Estoque Mínimo *</label>
                            <input type="number" name="estoque_minimo" id="edit_est_min" min="0" required>
                        </div>
                    </div>

                    <div class="modal-rodape">
                        <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalEditar')">Cancelar</button>
                        <button type="submit" class="btn-modal-confirmar azul">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Formulário oculto para exclusão -->
        <form id="formExcluir" method="POST" action="componentes.php" style="display: none;">
            <input type="hidden" name="acao" value="excluir">
            <input type="hidden" name="id" id="del_id">
        </form>
    <?php endif; ?>

    <!-- MODAL 3: CONFIRMAÇÃO UNIVERSAL -->
    <div id="modalConfirmacao" class="modal-overlay" style="z-index: 1100;">
        <div class="modal-box modal-confirm">
            <div class="confirm-icone-box azul" id="confirm_icone_box">
                <span class="material-symbols-outlined">help</span>
            </div>
            <h3 id="confirm_titulo">Confirmar Ação</h3>
            <p id="confirm_mensagem">Tem certeza que deseja continuar?</p>

            <div class="modal-rodape centralizado">
                <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalConfirmacao')">Voltar</button>
                <button type="button" id="btn_confirmar_sim" class="btn-modal-confirmar azul">Sim, Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        /* Brilho interativo na sidebar */
        (() => {
            const sidebar = document.getElementById('glassSidebar');
            if (!sidebar) return;

            sidebar.addEventListener('pointermove', (e) => {
                const rect = sidebar.getBoundingClientRect();
                sidebar.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
                sidebar.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
            });
        })();

        /* Dropdown Liquid Glass sincronizado com qualquer <select> da página */
        document.querySelectorAll('select').forEach((select) => {
            select.classList.add('glass-select-native');

            const wrapper = document.createElement('div');
            wrapper.className = 'glass-select';

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'glass-select-trigger';
            trigger.innerHTML = `
                <span>${select.options[select.selectedIndex]?.text || 'Selecione'}</span>
                <svg class="glass-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            `;

            const menu = document.createElement('div');
            menu.className = 'glass-select-menu';

            function rebuildOptions() {
                menu.innerHTML = '';
                const idxAtual = select.selectedIndex >= 0 ? select.selectedIndex : 0;
                trigger.querySelector('span').textContent = select.options[idxAtual]?.text || 'Selecione';

                Array.from(select.options).forEach((opt, idx) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'glass-select-option' + (idx === idxAtual ? ' selected' : '');
                    item.textContent = opt.text;

                    item.addEventListener('click', () => {
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        trigger.querySelector('span').textContent = opt.text;
                        menu.querySelectorAll('.glass-select-option').forEach((el) => el.classList.remove('selected'));
                        item.classList.add('selected');
                        wrapper.classList.remove('open');
                    });

                    menu.appendChild(item);
                });
            }

            rebuildOptions();
            select._glassSync = rebuildOptions;

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.glass-select.open').forEach((el) => {
                    if (el !== wrapper) el.classList.remove('open');
                });
                wrapper.classList.toggle('open');
            });

            wrapper.appendChild(trigger);
            wrapper.appendChild(menu);
            select.parentNode.insertBefore(wrapper, select.nextSibling);
        });

        function sincronizarSelect(id, valor) {
            const el = document.getElementById(id);
            if (!el) return;
            el.value = valor ?? '';
            if (typeof el._glassSync === 'function') {
                el._glassSync();
            }
        }

        document.addEventListener('click', () => {
            document.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));
        });

        /* Controle dos modais e confirmação */
        let formPendente = null;

        function abrirModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('ativo');
        }

        function fecharModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('ativo');
        }

        function abrirModalCadastrar() {
            const formCad = document.getElementById('formCadastrar');
            if (!formCad) return;
            formCad.reset();
            sincronizarSelect('cad_categoria', '');
            sincronizarSelect('cad_fornecedor', '');
            sincronizarSelect('cad_condicao', '1');
            abrirModal('modalCadastrar');
        }

        function abrirModalEmprestar(item) {
            document.getElementById('emp_id').value = item.id;
            document.getElementById('emp_nome_item').textContent = `${item.nome} (${item.codigo})`;
            document.getElementById('emp_disp_texto').textContent = `Disponível: ${item.disponivel} un.`;
            const qtdInput = document.getElementById('emp_qtd');
            qtdInput.max = item.disponivel;
            qtdInput.value = 1;
            sincronizarSelect('emp_projeto', '');
            document.getElementById('emp_obs').value = '';
            abrirModal('modalEmprestar');
        }

        function abrirModalEditar(comp) {
            document.getElementById('edit_id').value = comp.id;
            document.getElementById('edit_nome').value = comp.nome ?? '';
            document.getElementById('edit_codigo').value = comp.codigo ?? '';
            document.getElementById('edit_descricao').value = comp.descricao ?? '';
            sincronizarSelect('edit_categoria', comp.categoria_id ?? '');
            sincronizarSelect('edit_fornecedor', comp.fornecedor_id ?? '');
            sincronizarSelect('edit_condicao', comp.condicao_id ?? 1);
            document.getElementById('edit_qtd_disp').value = comp.quantidade_disponivel ?? 0;
            document.getElementById('edit_qtd_total').value = comp.quantidade_total ?? 0;
            document.getElementById('edit_est_min').value = comp.estoque_minimo ?? 5;
            abrirModal('modalEditar');
        }

        function abrirModalExcluir(id, nome) {
            const formDel = document.getElementById('formExcluir');
            if (!formDel) return;
            document.getElementById('del_id').value = id;
            formPendente = formDel;

            document.getElementById('confirm_titulo').textContent = 'Excluir Componente';
            document.getElementById('confirm_mensagem').innerHTML = `Tem certeza que deseja excluir <strong>${nome}</strong> do inventário?`;
            document.getElementById('confirm_icone_box').className = 'confirm-icone-box vermelho';
            document.getElementById('confirm_icone_box').innerHTML = '<span class="material-symbols-outlined">delete</span>';

            const btnSim = document.getElementById('btn_confirmar_sim');
            btnSim.className = 'btn-modal-confirmar vermelho';
            abrirModal('modalConfirmacao');
        }

        function pedirConfirmacao(event, titulo, mensagem, cor) {
            event.preventDefault();
            formPendente = event.target;

            document.getElementById('confirm_titulo').textContent = titulo;
            document.getElementById('confirm_mensagem').innerHTML = mensagem;
            document.getElementById('confirm_icone_box').className = 'confirm-icone-box ' + cor;

            const icones = { verde: 'check_circle', azul: 'save', vermelho: 'delete' };
            document.getElementById('confirm_icone_box').innerHTML =
                `<span class="material-symbols-outlined">${icones[cor] || 'help'}</span>`;

            const btnSim = document.getElementById('btn_confirmar_sim');
            btnSim.className = 'btn-modal-confirmar ' + cor;
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
                ['modalConfirmacao', 'modalEditar', 'modalEmprestar', 'modalCadastrar'].forEach(fecharModal);
            }
        });
    </script>
</body>

</html>
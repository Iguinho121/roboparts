<?php
// Carrega a conexão com o banco ($pdo), as funções prontas de CRUD e as regras de login
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';

// Trava de segurança: se ninguém estiver logado na sessão, expulsa para a tela de login
exigirLogin();

// =========================================================================
// 1. RECEBENDO AS AÇÕES DOS FORMULÁRIOS (EMPRESTAR, EDITAR E EXCLUIR)
// =========================================================================
// Só entra aqui quando o usuário envia algum formulário via POST (clicando nos botões dos modais)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    try {
        // -----------------------------------------------------------------
        // AÇÃO A: EMPRESTAR COMPONENTE (Qualquer usuário logado pode fazer)
        // -----------------------------------------------------------------
        if ($acao === 'emprestar') {
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

                // Grava na tabela 'movimentacoes' para aparecer na Dashboard e no futuro Histórico
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
    <!-- Ícones do Google Material Symbols -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="css/componentes.css">
    <title>Componentes - Flux</title>
</head>

<body>

    <div class="app-robo">

        <!-- Menu lateral fixo -->
        <nav class="sidebar">

            <div class="sidebar-logo">
                <img src="imagens/novo_logo.png" alt="Logo Flux">
            </div>

            <ul class="sidebar-menu">

                <li>
                    <a href="dashboard.php">
                        <span class="material-symbols-outlined menu-icon">dashboard</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="active">
                    <a href="componentes.php">
                        <span class="material-symbols-outlined menu-icon">memory</span>
                        <span>Componentes</span>
                    </a>
                </li>

                <!-- Só mostra a opção Cadastro no menu se o usuário logado for Admin -->
                <?php if (isAdmin()): ?>
                    <li>
                        <a href="adicionar_componente.php">
                            <span class="material-symbols-outlined menu-icon">add_circle</span>
                            <span>Cadastro</span>
                        </a>
                    </li>
                <?php endif; ?>

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

                <li>
                    <a href="logout.php">
                        <span class="material-symbols-outlined menu-icon">logout</span>
                        <span>Sair</span>
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

            <div class="corpo-conteudo">

                <header class="titulo-principal">
                    <div class="header-imagem">
                        <span class="material-symbols-outlined header-icon">memory</span>
                    </div>
                    <div class="linha-header"></div>

                    <div class="header-texto">
                        <h1>Gerenciar Componentes</h1>
                        <p>Portal de Gestão Inteligente de Inventário</p>
                    </div>
                </header>

                <!-- Faixas de aviso verde/vermelha após empréstimo, edição ou exclusão -->
                <?php if ($flashSucesso): ?>
                    <div class="alerta-feedback sucesso">
                        <span class="material-symbols-outlined">check_circle</span>
                        <!-- htmlspecialchars evita ataques XSS caso algum texto tenha tags HTML -->
                        <span><?= htmlspecialchars($flashSucesso) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($flashErro): ?>
                    <div class="alerta-feedback erro">
                        <span class="material-symbols-outlined">error</span>
                        <span><?= htmlspecialchars($flashErro) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Barra de pesquisa e filtros (envia via GET na própria URL) -->
                <form method="GET" action="componentes.php" class="barra-acoes">

                    <div class="caixa-procurar">
                        <span class="material-symbols-outlined procurar-icone">search</span>
                        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
                            placeholder="Buscar por nome, código ou descrição...">
                    </div>

                    <div class="categoria">
                        <select name="categoria_id">
                            <option value="">Todas as Categorias</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoriaFiltro == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-filtrar">Filtrar</button>

                    <!-- Alterna o filtro rápido de estoque crítico -->
                    <div class="btn-aviso-estoque">
                        <?php if ($apenasBaixo): ?>
                            <a href="componentes.php" class="btn-filtro-ativo">
                                <span class="material-symbols-outlined icone-aviso">warning</span>
                                Mostrando Estoque Baixo (Limpar)
                            </a>
                        <?php else: ?>
                            <a href="componentes.php?estoque_baixo=1" class="btn-filtro">
                                <span class="material-symbols-outlined icone-aviso">warning</span>
                                Apenas Estoque Baixo
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Botão de cadastrar peça nova só aparece para Admin -->
                    <?php if (isAdmin()): ?>
                        <div class="cadastrar-componente">
                            <a href="adicionar_componente.php">+ Cadastrar Componente</a>
                        </div>
                    <?php endif; ?>

                </form>

                <!-- Tabela de listagem dos componentes -->
                <div class="tabela">

                    <div class="tabela-cabecalho">
                        <div class="coluna">SKU / ITEM</div>
                        <div class="coluna">DESCRIÇÃO</div>
                        <div class="coluna">CATEGORIA</div>
                        <div class="coluna">FORNECEDOR</div>
                        <div class="coluna">DISPONÍVEL / TOTAL</div>
                        <div class="coluna">ESTADO</div>
                        <div class="coluna">AÇÕES</div>
                    </div>

                    <?php if (count($componentes) == 0): ?>
                        <div class="tabela-linha">
                            <div class="coluna_item" style="grid-column: 1 / -1; text-align: center; padding: 20px 0;">
                                Nenhum componente encontrado com os filtros selecionados.
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Percorre cada componente retornado do banco e cria uma linha na tabela -->
                        <?php foreach ($componentes as $c):
                            // Busca os nomes da categoria e do fornecedor pelos IDs relacionados
                            $categoria = read($pdo, 'categorias', 'id = ?', [$c['categoria_id']]);
                            $fornecedor = $c['fornecedor_id']
                                ? read($pdo, 'fornecedores', 'id = ?', [$c['fornecedor_id']])
                                : null;
                            $condicao = $condicoes[$c['condicao_id']] ?? ['texto' => 'Não informada', 'classe' => 'distintivo_usado'];
                            $estoqueBaixo = $c['quantidade_disponivel'] < $c['estoque_minimo'];
                        ?>
                            <div class="tabela-linha">
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
                                    <?= $c['quantidade_disponivel'] ?> / <?= $c['quantidade_total'] ?> un.
                                    <?php if ($estoqueBaixo): ?>
                                        <span class="distintivo_alerta">Baixo</span>
                                    <?php endif; ?>
                                </div>

                                <div class="coluna_estado">
                                    <span class="<?= $condicao['classe'] ?>"><?= $condicao['texto'] ?></span>
                                </div>

                                <div class="coluna_acoes">
                                    <!-- Emprestar: disponível para Admin e Comum, desde que tenha pelo menos 1 unidade -->
                                    <?php if ((int) $c['quantidade_disponivel'] > 0): ?>
                                        <!-- json_encode passa os dados da peça do PHP direto para a função JavaScript -->
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

                                    <!-- Editar e Excluir: renderizados apenas se o usuário for Admin -->
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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

            </div>

        </main>

    </div>

    <!-- JANELAS FLUTUANTES NA MESMA PÁGINA)-->

   <!-- MODAL 1: EMPRESTAR COMPONENTE (Admin e Comum) -->
    <div id="modalEmprestar" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalEmprestar')">
        <div class="modal-box">
            <div class="modal-header">
                <h3><span class="material-symbols-outlined">swap_horiz</span> Registrar Empréstimo</h3>
                <button type="button" class="modal-fechar" onclick="fecharModal('modalEmprestar')">×</button>
            </div>

            <!-- onsubmit chama o modal de confirmação antes de enviar o formulário -->
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
                    <button type="button" class="modal-fechar" onclick="fecharModal('modalEditar')">×</button>
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

    <!-- MODAL 3: CONFIRMAÇÃO UNIVERSAL (Janelinha de "Tem certeza?") -->
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

<!-- JAVASCRIPT ENXUTO (APENAS O ESSENCIAL PARA ABRIR E CONFIRMAR OS MODAIS) -->
    <script>
        // Guarda temporariamente qual formulário será enviado depois que o usuário clicar em "Sim"
        let formPendente = null;

        // Funções simples para abrir e fechar qualquer janela (modal) trocando a classe CSS 'ativo'
        function abrirModal(id)  { document.getElementById(id).classList.add('ativo'); }
        function fecharModal(id) { document.getElementById(id).classList.remove('ativo'); }

        // Intercepta o envio do formulário, mostra o aviso de confirmação e segura o envio
        function pedirConfirmacao(event, titulo, mensagem, cor) {
            event.preventDefault(); // Impede que a página recarregue antes do usuário confirmar
            formPendente = event.target; // Salva qual formulário tentou ser enviado (Emprestar ou Editar)

            // Preenche o título, o texto e a cor (verde, azul ou vermelho) do modal de aviso
            document.getElementById('confirm_titulo').textContent = titulo;
            document.getElementById('confirm_mensagem').innerHTML = mensagem;
            document.getElementById('confirm_icone_box').className = 'confirm-icone-box ' + cor;
            document.getElementById('btn_confirmar_sim').className = 'btn-modal-confirmar ' + cor;

            abrirModal('modalConfirmacao');
            return false;
        }

        // Quando o usuário clica no botão "Sim, Confirmar" do aviso, dispara o envio de verdade para o PHP
        document.getElementById('btn_confirmar_sim').onclick = () => {
            if (formPendente) formPendente.submit();
        };

        // 1. Preenche os dados da peça clicada e abre o modal de Empréstimo
        function abrirModalEmprestar(item) {
            document.getElementById('emp_id').value = item.id;
            document.getElementById('emp_nome_item').textContent = `${item.nome} (${item.codigo})`;
            document.getElementById('emp_disp_texto').textContent = `Disponível: ${item.disponivel} un.`;
            document.getElementById('emp_qtd').max = item.disponivel; // Trava o máximo no estoque atual
            document.getElementById('emp_qtd').value = 1;
            abrirModal('modalEmprestar');
        }

        // 2. Preenche todos os campos do componente e abre o modal de Edição (Admin)
        function abrirModalEditar(c) {
            document.getElementById('edit_id').value = c.id;
            document.getElementById('edit_nome').value = c.nome || '';
            document.getElementById('edit_codigo').value = c.codigo || '';
            document.getElementById('edit_descricao').value = c.descricao || '';
            document.getElementById('edit_categoria').value = c.categoria_id;
            document.getElementById('edit_fornecedor').value = c.fornecedor_id || '';
            document.getElementById('edit_condicao').value = c.condicao_id;
            document.getElementById('edit_qtd_disp').value = c.quantidade_disponivel;
            document.getElementById('edit_qtd_total').value = c.quantidade_total;
            document.getElementById('edit_est_min').value = c.estoque_minimo;
            abrirModal('modalEditar');
        }

        // 3. Coloca o ID no formulário oculto e abre direto o aviso vermelho de exclusão (Admin)
        function abrirModalExcluir(id, nome) {
            const form = document.getElementById('formExcluir');
            document.getElementById('del_id').value = id;
            pedirConfirmacao(
                { preventDefault: () => {}, target: form },
                'Excluir Componente',
                `Tem certeza que deseja excluir <strong>${nome}</strong>? Essa ação não pode ser desfeita.`,
                'vermelho'
            );
        }
    </script>

</body>
</html>
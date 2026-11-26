<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

// =========================================================================
// 1. AÇÕES VIA POST (CADASTRAR KIT, EXCLUIR KIT E REQUISITAR KIT COMPLETO)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    try {
        // -----------------------------------------------------------------
        // AÇÃO A: CADASTRAR KIT (Exclusivo para Administradores)
        // -----------------------------------------------------------------
        if ($acao === 'cadastrar_kit') {
            if (!isAdmin()) {
                $_SESSION['flash_erro'] = 'Acesso negado. Apenas administradores podem cadastrar kits.';
            } else {
                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $tipoProjeto = trim($_POST['tipo_projeto'] ?? 'Educacional');
                $compIds = $_POST['componente_id'] ?? [];
                $qtds = $_POST['quantidade'] ?? [];

                $niveisValidos = ['Educacional', 'Intermediário', 'Avançado'];
                if (!in_array($tipoProjeto, $niveisValidos, true)) {
                    $tipoProjeto = 'Educacional';
                }

                // Agrupa peças para evitar duplicidade na chave única (kit_id, componente_id)
                $itensAgrupados = [];
                if (is_array($compIds) && is_array($qtds)) {
                    foreach ($compIds as $idx => $rawCompId) {
                        $cId = (int) $rawCompId;
                        $qItem = (int) ($qtds[$idx] ?? 0);
                        if ($cId > 0 && $qItem > 0) {
                            $itensAgrupados[$cId] = ($itensAgrupados[$cId] ?? 0) + $qItem;
                        }
                    }
                }

                if ($nome === '') {
                    $_SESSION['flash_erro'] = 'O nome do kit é obrigatório.';
                } elseif (count($itensAgrupados) === 0) {
                    $_SESSION['flash_erro'] = 'Adicione pelo menos um componente com quantidade maior que zero ao kit.';
                } else {
                    $pdo->beginTransaction();

                    $componentesMap = [];
                    $stmtBuscaComp = $pdo->prepare('SELECT id, nome, codigo FROM componentes WHERE id = ?');
                    foreach (array_keys($itensAgrupados) as $cId) {
                        $stmtBuscaComp->execute([$cId]);
                        $compRow = $stmtBuscaComp->fetch();
                        if (!$compRow) {
                            throw new RuntimeException('Um dos componentes selecionados não foi encontrado no estoque.');
                        }
                        $componentesMap[$cId] = $compRow;
                    }

                    $stmtKit = $pdo->prepare(
                        'INSERT INTO kits (nome, descricao, tipo_projeto) VALUES (?, ?, ?)'
                    );
                    $stmtKit->execute([
                        $nome,
                        $descricao !== '' ? $descricao : null,
                        $tipoProjeto,
                    ]);
                    $kitId = (int) $pdo->lastInsertId();

                    $stmtItem = $pdo->prepare(
                        'INSERT INTO kit_componentes (kit_id, componente_id, quantidade) VALUES (?, ?, ?)'
                    );

                    $resumoPecas = [];
                    foreach ($itensAgrupados as $cId => $qItem) {
                        $stmtItem->execute([$kitId, $cId, $qItem]);
                        $resumoPecas[] = "{$qItem}x {$componentesMap[$cId]['nome']}";
                    }

                    // 1 único registro no histórico por cadastro de kit
                    $primeiroCompId = (int) array_key_first($itensAgrupados);
                    $usuarioNome = $_SESSION['usuario_nome'] ?? 'Administrador';
                    $obsHistorico = "Cadastro do kit '{$nome}' ({$tipoProjeto}) por {$usuarioNome} — Peças: " . implode(', ', $resumoPecas);

                    $stmtMov = $pdo->prepare(
                        'INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmtMov->execute([$primeiroCompId, null, 'Entrada', 0, $obsHistorico]);

                    $pdo->commit();
                    $_SESSION['flash_sucesso'] = "Kit '{$nome}' cadastrado e registrado no histórico!";
                }
            }
        }

        // -----------------------------------------------------------------
        // AÇÃO B: EXCLUIR KIT (Exclusivo para Administradores)
        // -----------------------------------------------------------------
        elseif ($acao === 'excluir_kit') {
            if (!isAdmin()) {
                $_SESSION['flash_erro'] = 'Acesso negado. Apenas administradores podem excluir kits.';
            } else {
                $kitId = (int) ($_POST['kit_id'] ?? 0);
                $kit = read($pdo, 'kits', 'id = ?', [$kitId]);

                if (!$kit) {
                    $_SESSION['flash_erro'] = 'Kit não encontrado para exclusão.';
                } else {
                    $itensKit = readAll($pdo, 'kit_componentes', 'kit_id = ?', [$kitId]);
                    $refCompId = !empty($itensKit) ? (int) $itensKit[0]['componente_id'] : null;

                    if ($refCompId === null) {
                        $stmtAny = $pdo->query('SELECT id FROM componentes ORDER BY id ASC LIMIT 1');
                        $anyRow = $stmtAny ? $stmtAny->fetch() : null;
                        $refCompId = $anyRow ? (int) $anyRow['id'] : null;
                    }

                    $pdo->beginTransaction();

                    $stmtDel = $pdo->prepare('DELETE FROM kits WHERE id = ?');
                    $stmtDel->execute([$kitId]);

                    if ($refCompId !== null) {
                        $usuarioNome = $_SESSION['usuario_nome'] ?? 'Administrador';
                        $obsExclusao = "Exclusão do kit '{$kit['nome']}' por {$usuarioNome}";
                        $stmtMov = $pdo->prepare(
                            'INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES (?, ?, ?, ?, ?)'
                        );
                        $stmtMov->execute([$refCompId, null, 'Saida', 0, $obsExclusao]);
                    }

                    $pdo->commit();
                    $_SESSION['flash_sucesso'] = "Kit '{$kit['nome']}' excluído com sucesso!";
                }
            }
        }

        // -----------------------------------------------------------------
        // AÇÃO C: REQUISITAR KIT COMPLETO (Qualquer usuário logado)
        // -----------------------------------------------------------------
        elseif ($acao === 'requisitar_kit') {
            $kitId = (int) ($_POST['kit_id'] ?? 0);
            $kit = read($pdo, 'kits', 'id = ?', [$kitId]);

            if (!$kit) {
                $_SESSION['flash_erro'] = 'Kit não encontrado.';
            } else {
                $itensKit = readAll($pdo, 'kit_componentes', 'kit_id = ?', [$kitId]);

                if (count($itensKit) === 0) {
                    $_SESSION['flash_erro'] = 'Este kit não possui componentes vinculados.';
                } else {
                    $pdo->beginTransaction();

                    $stmtComp = $pdo->prepare('SELECT id, nome, quantidade_disponivel FROM componentes WHERE id = ? FOR UPDATE');
                    $stmtUpd = $pdo->prepare('UPDATE componentes SET quantidade_disponivel = ? WHERE id = ?');

                    $usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';
                    $totalUnidadesKit = 0;
                    $resumoItens = [];
                    $primeiroCompId = null;

                    foreach ($itensKit as $item) {
                        $cId = (int) $item['componente_id'];
                        $qtdNecessaria = (int) $item['quantidade'];

                        $stmtComp->execute([$cId]);
                        $comp = $stmtComp->fetch();

                        if (!$comp) {
                            throw new RuntimeException('Componente vinculado ao kit não encontrado.');
                        }
                        if ((int) $comp['quantidade_disponivel'] < $qtdNecessaria) {
                            throw new RuntimeException(
                                "Estoque insuficiente para '{$comp['nome']}' (necessário: {$qtdNecessaria}, disponível: {$comp['quantidade_disponivel']})."
                            );
                        }

                        if ($primeiroCompId === null) {
                            $primeiroCompId = $cId;
                        }

                        $novaQtd = (int) $comp['quantidade_disponivel'] - $qtdNecessaria;
                        $stmtUpd->execute([$novaQtd, $cId]);

                        $totalUnidadesKit += $qtdNecessaria;
                        $resumoItens[] = "{$qtdNecessaria}x {$comp['nome']}";
                    }

                    // 1 único registro consolidado no histórico por requisição de kit
                    $obsMov = "Kit '{$kit['nome']}' requisitado por {$usuarioNome} — Itens: " . implode(', ', $resumoItens);
                    $stmtMov = $pdo->prepare(
                        'INSERT INTO movimentacoes (componente_id, projeto_id, tipo_movimentacao, quantidade, observacao) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmtMov->execute([$primeiroCompId, null, 'Saida', $totalUnidadesKit, $obsMov]);

                    $pdo->commit();
                    $_SESSION['flash_sucesso'] = "Kit '{$kit['nome']}' requisitado com sucesso! Estoque atualizado e registrado no histórico.";
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_erro'] = $e instanceof PDOException
            ? 'Erro ao processar operação no banco: ' . $e->getMessage()
            : $e->getMessage();
    }

    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: kits.php' . $queryString);
    exit;
}

// =========================================================================
// 2. PREPARAÇÃO DOS DADOS PARA EXIBIR NA TELA
// =========================================================================
$flashSucesso = $_SESSION['flash_sucesso'] ?? null;
$flashErro = $_SESSION['flash_erro'] ?? null;
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

$kits = readAll($pdo, 'kits');
$componentesDisponiveis = readAll($pdo, 'componentes');
$filtroNivel = $_GET['nivel'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kits e Catálogos | Flux</title>
    <link rel="stylesheet" href="css/kits.css">
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
            <li><a href="componentes.php"><span class="material-symbols-outlined menu-icon">memory</span><span>Componentes</span></a></li>
            <li><a href="projetos.php"><span class="material-symbols-outlined menu-icon">folder</span><span>Projetos</span></a></li>
            <li><a href="historico.php"><span class="material-symbols-outlined menu-icon">history</span><span>Histórico</span></a></li>
            <li class="active"><a href="kits.php" aria-current="page"><span class="material-symbols-outlined menu-icon">inventory_2</span><span>Kits e Catálogos</span></a></li>
        </ul>

        <a class="sidebar-logout" href="logout.php" aria-label="Sair do sistema">
            <span class="material-symbols-outlined menu-icon">logout</span>
            <span>Sair</span>
        </a>
    </nav>

    <main class="pagina">
        <header class="cabecalho">
            <div>
                <p class="identificacao">Flux / KITS E CATÁLOGOS</p>
                <h1>Produtos | Robóticas</h1>
                <p class="descricao">Selecione um kit pré-configurado por tipo de projeto para requisição rápida.</p>
            </div>

            <div class="filtros-nivel">
                <a href="kits.php" class="btn-filtro <?= $filtroNivel === '' ? 'active' : '' ?>">Todos</a>
                <a href="?nivel=Educacional" class="btn-filtro <?= $filtroNivel === 'Educacional' ? 'active' : '' ?>" data-cor="Educacional">Educacional</a>
                <a href="?nivel=Intermediário" class="btn-filtro <?= $filtroNivel === 'Intermediário' ? 'active' : '' ?>" data-cor="Intermediário">Intermediário</a>
                <a href="?nivel=Avançado" class="btn-filtro <?= $filtroNivel === 'Avançado' ? 'active' : '' ?>" data-cor="Avançado">Avançado</a>

                <?php if (isAdmin()): ?>
                    <button type="button" class="btn-cadastrar" onclick="abrirModalCadastrarKit()">+ Cadastrar Kit</button>
                <?php endif; ?>
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

        <section class="painel painel-kits">
            <div class="titulo-painel titulo-lista">
                <div>
                    <h2>Kits configurados</h2>
                    <p>Confira os componentes incluídos e a disponibilidade atual no estoque.</p>
                </div>
                <span class="contador"><?= count($kits) ?> kit(s)</span>
            </div>

            <div class="cards">
                <?php if (count($kits) == 0): ?>
                    <div class="mensagem-vazia">
                        <h3>Nenhum kit cadastrado</h3>
                        <p>Não há kits cadastrados no momento.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $exibidos = 0;
                    foreach ($kits as $kit):
                        if ($filtroNivel !== '' && ($kit['tipo_projeto'] ?? '') !== $filtroNivel) {
                            continue;
                        }
                        $exibidos++;

                        $itensKit = readAll($pdo, 'kit_componentes', 'kit_id = ?', [$kit['id']]);
                        $kitDisponivel = true;
                        $nivelAtual = $kit['tipo_projeto'] ?? 'Geral';
                    ?>
                        <article class="card-kit">
                            <div class="card-org">
                                <span class="border border-01" data-nivel="<?= htmlspecialchars($nivelAtual) ?>">
                                    <?= htmlspecialchars($nivelAtual) ?>
                                </span>

                                <div class="card-org-direita">
                                    <span class="border border-02">Kit montável</span>
                                    <?php if (isAdmin()): ?>
                                        <button type="button" class="btn-acao btn-excluir"
                                            onclick='abrirModalExcluirKit(<?= (int) $kit["id"] ?>, <?= json_encode($kit["nome"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Excluir
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <h3 class="card-title"><?= htmlspecialchars($kit['nome']) ?></h3>
                            <p class="p-card"><?= htmlspecialchars($kit['descricao'] ?? 'Sem descrição.') ?></p>

                            <hr class="hr">

                            <h4 class="card-pecas">Peças Incluídas:</h4>
                            <ul class="pecas-lista">
                                <?php foreach ($itensKit as $item):
                                    $comp = read($pdo, 'componentes', 'id = ?', [$item['componente_id']]);
                                    if (!$comp) {
                                        continue;
                                    }

                                    $emEstoque = (int) $comp['quantidade_disponivel'];
                                    if ($emEstoque < (int) $item['quantidade']) {
                                        $kitDisponivel = false;
                                    }
                                ?>
                                    <li>
                                        <span class="item-nome"><?= htmlspecialchars($comp['nome']) ?></span>
                                        <span class="item-info">
                                            <strong class="qtd"><?= (int) $item['quantidade'] ?>x</strong>
                                            <span class="estoque">(<?= $emEstoque ?> em est.)</span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($kitDisponivel && count($itensKit) > 0): ?>
                                <button type="button" class="botao" popovertarget="modal-kit-<?= (int) $kit['id'] ?>">
                                    Requisitar kit completo
                                </button>

                                <div id="modal-kit-<?= (int) $kit['id'] ?>" popover class="modal-flux">
                                    <h3>Confirmar Requisição</h3>
                                    <p>Deseja realmente requisitar o <strong><?= htmlspecialchars($kit['nome']) ?></strong>?</p>

                                    <form method="POST" action="kits.php" class="modal-acoes">
                                        <input type="hidden" name="acao" value="requisitar_kit">
                                        <input type="hidden" name="kit_id" value="<?= (int) $kit['id'] ?>">

                                        <button type="button" class="botao botao-cancelar"
                                            popovertarget="modal-kit-<?= (int) $kit['id'] ?>" popovertargetaction="hide">
                                            Cancelar
                                        </button>

                                        <button type="submit" class="botao botao-confirmar">
                                            Confirmar
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <button type="button" class="botao botao-indisponivel" disabled>Estoque insuficiente</button>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($exibidos === 0): ?>
                        <div class="mensagem-vazia">
                            <h3>Nenhum kit encontrado neste nível</h3>
                            <p>Tente selecionar outro filtro ou clique em "Todos".</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- MODAL CADASTRAR KIT (MESMO PADRÃO DO MODAL CADASTRAR COMPONENTE) -->
    <?php if (isAdmin()): ?>
        <div id="modalCadastrarKit" class="modal-overlay" onclick="if(event.target===this) fecharModal('modalCadastrarKit')">
            <div class="modal-box modal-grande">
                <div class="modal-header">
                    <h3><span class="material-symbols-outlined">add_circle</span> Cadastrar Kit</h3>
                    <button type="button" class="modal-fechar" onclick="fecharModal('modalCadastrarKit')" aria-label="Fechar">×</button>
                </div>

                <form id="formCadastrarKit" method="POST" action="kits.php"
                      onsubmit="return pedirConfirmacao(event, 'Confirmar Cadastro', 'Deseja cadastrar este kit e registrar no histórico?', 'verde')">
                    <input type="hidden" name="acao" value="cadastrar_kit">

                    <div class="modal-grid-2">
                        <div class="modal-campo">
                            <label for="kit_nome">Nome do Kit *</label>
                            <input type="text" name="nome" id="kit_nome" required placeholder="Ex: Kit Robô Seguidor">
                        </div>

                        <div class="modal-campo">
                            <label for="kit_tipo">Tipo / Nível do Projeto *</label>
                            <select name="tipo_projeto" id="kit_tipo" required>
                                <option value="Educacional">Educacional</option>
                                <option value="Intermediário">Intermediário</option>
                                <option value="Avançado">Avançado</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-campo">
                        <label for="kit_descricao">Descrição</label>
                        <textarea name="descricao" id="kit_descricao" rows="2" placeholder="Objetivo e aplicação do kit..."></textarea>
                    </div>

                    <div class="kit-itens-bloco">
                        <div class="kit-itens-topo">
                            <label>Peças incluídas no kit *</label>
                            <button type="button" class="btn-add-linha" onclick="adicionarLinhaPeca()">
                                <span class="material-symbols-outlined">add</span>
                                Adicionar peça
                            </button>
                        </div>

                        <div id="listaLinhasPecas" class="kit-linhas-pecas">
                            <div class="kit-linha-peca">
                                <select name="componente_id[]" required>
                                    <option value="">Selecione um componente...</option>
                                    <?php foreach ($componentesDisponiveis as $comp): ?>
                                        <option value="<?= (int) $comp['id'] ?>">
                                            <?= htmlspecialchars($comp['nome']) ?> (<?= (int) $comp['quantidade_disponivel'] ?> disp.)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="quantidade[]" min="1" value="1" required aria-label="Quantidade">
                                <button type="button" class="btn-remover-linha" onclick="removerLinhaPeca(this)" aria-label="Remover peça">×</button>
                            </div>
                        </div>
                    </div>

                    <div class="modal-rodape">
                        <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalCadastrarKit')">Cancelar</button>
                        <button type="submit" class="btn-modal-confirmar verde">Salvar e Registrar no Histórico</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Formulário oculto para exclusão de kit -->
        <form id="formExcluirKit" method="POST" action="kits.php" style="display: none;">
            <input type="hidden" name="acao" value="excluir_kit">
            <input type="hidden" name="kit_id" id="del_kit_id">
        </form>
    <?php endif; ?>

    <!-- MODAL DE CONFIRMAÇÃO UNIVERSAL (MESMO PADRÃO DE COMPONENTES.PHP) -->
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

        const opcoesComponentesHtml = `
            <option value="">Selecione um componente...</option>
            <?php foreach ($componentesDisponiveis as $comp): ?>
                <option value="<?= (int) $comp['id'] ?>"><?= htmlspecialchars($comp['nome'], ENT_QUOTES) ?> (<?= (int) $comp['quantidade_disponivel'] ?> disp.)</option>
            <?php endforeach; ?>
        `;

        /* Dropdown Liquid Glass sincronizado com qualquer <select> */
        function aplicarGlassSelect(select) {
            if (!select || select.classList.contains('glass-select-native')) return;
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
        }

        document.querySelectorAll('select').forEach(aplicarGlassSelect);

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

        function abrirModalCadastrarKit() {
            const formCad = document.getElementById('formCadastrarKit');
            const container = document.getElementById('listaLinhasPecas');
            if (!formCad || !container) return;

            formCad.reset();
            sincronizarSelect('kit_tipo', 'Educacional');

            container.innerHTML = `
                <div class="kit-linha-peca">
                    <select name="componente_id[]" required>${opcoesComponentesHtml}</select>
                    <input type="number" name="quantidade[]" min="1" value="1" required aria-label="Quantidade">
                    <button type="button" class="btn-remover-linha" onclick="removerLinhaPeca(this)" aria-label="Remover peça">×</button>
                </div>
            `;
            container.querySelectorAll('select').forEach(aplicarGlassSelect);
            abrirModal('modalCadastrarKit');
        }

        function adicionarLinhaPeca() {
            const container = document.getElementById('listaLinhasPecas');
            if (!container) return;

            const linha = document.createElement('div');
            linha.className = 'kit-linha-peca';
            linha.innerHTML = `
                <select name="componente_id[]" required>${opcoesComponentesHtml}</select>
                <input type="number" name="quantidade[]" min="1" value="1" required aria-label="Quantidade">
                <button type="button" class="btn-remover-linha" onclick="removerLinhaPeca(this)" aria-label="Remover peça">×</button>
            `;
            container.appendChild(linha);
            aplicarGlassSelect(linha.querySelector('select'));
        }

        function removerLinhaPeca(btn) {
            const container = document.getElementById('listaLinhasPecas');
            if (!container) return;
            const linhas = container.querySelectorAll('.kit-linha-peca');
            if (linhas.length <= 1) return;
            btn.closest('.kit-linha-peca')?.remove();
        }

        function abrirModalExcluirKit(id, nome) {
            const formDel = document.getElementById('formExcluirKit');
            if (!formDel) return;
            document.getElementById('del_kit_id').value = id;
            formPendente = formDel;

            document.getElementById('confirm_titulo').textContent = 'Excluir Kit';
            document.getElementById('confirm_mensagem').innerHTML = `Tem certeza que deseja excluir o kit <strong>${nome}</strong>?`;
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
                ['modalConfirmacao', 'modalCadastrarKit'].forEach(fecharModal);
            }
        });
    </script>
</body>

</html>
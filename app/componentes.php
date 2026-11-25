<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

// Busca as categorias do banco para preencher o <select>
$categorias = readAll($pdo, 'categorias');

// Pega o que veio na URL (GET)
$busca = $_GET['busca'] ?? '';
$categoriaFiltro = $_GET['categoria_id'] ?? '';
$apenasBaixo = isset($_GET['estoque_baixo']);

// Monta a consulta única com JOIN (elimina o N+1)
$filtros = [];
$params = [];

if ($busca != '') {
    $filtros[] = '(c.nome LIKE ? OR c.codigo LIKE ? OR c.descricao LIKE ?)';
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($categoriaFiltro != '') {
    $filtros[] = 'c.categoria_id = ?';
    $params[] = $categoriaFiltro;
}

if ($apenasBaixo) {
    $filtros[] = 'c.quantidade_disponivel < c.estoque_minimo';
}

$sql = "
    SELECT 
        c.*,
        cat.nome AS categoria_nome,
        f.nome AS fornecedor_nome
    FROM componentes c
    LEFT JOIN categorias cat ON c.categoria_id = cat.id
    LEFT JOIN fornecedores f ON c.fornecedor_id = f.id
";

if (count($filtros) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $filtros);
}

$sql .= ' ORDER BY c.nome ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$componentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mapa fixo das condições já cadastradas no banco (1, 2 e 3)
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
    <title>Componentes | RoboParts</title>
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
                <p class="identificacao">ROBOPARTS / INVENTÁRIO</p>
                <h1>Gerenciar componentes</h1>
                <p class="descricao">Consulte, filtre e gerencie os componentes, quantidades e condições do estoque.</p>
            </div>
        </header>

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
                        <a href="adicionar_componente.php" class="btn-cadastrar">+ Cadastrar Componente</a>
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
                                    <span><?= htmlspecialchars($c['categoria_nome'] ?? 'Sem categoria') ?></span>
                                </div>

                                <div class="coluna_fornecedor">
                                    <?= htmlspecialchars($c['fornecedor_nome'] ?? 'Não informado') ?>
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
                                    <?php if (isAdmin()): ?>
                                        <a href="editar_componente.php?id=<?= $c['id'] ?>">Editar</a>
                                        <a href="excluir_componente.php?id=<?= $c['id'] ?>" class="btn-excluir">Excluir</a>
                                    <?php else: ?>
                                        <span class="sem-acao">-</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
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

        /* Dropdown Liquid Glass sincronizado com o <select> */
        document.querySelectorAll('.campo select').forEach((select) => {
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

            Array.from(select.options).forEach((opt, idx) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'glass-select-option' + (idx === select.selectedIndex ? ' selected' : '');
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

        /* Fecha o menu se clicar fora */
        document.addEventListener('click', () => {
            document.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));
        });
    </script>
</body>

</html>
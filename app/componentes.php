<?php
require_once __DIR__ . '/init.php';

// Busca as categorias do banco para preencher o <select>
$categorias = readAll($pdo, 'categorias');

// Pega o que veio na URL (GET)
$busca = $_GET['busca'] ?? '';
$categoriaFiltro = $_GET['categoria_id'] ?? '';
$apenasBaixo = isset($_GET['estoque_baixo']);

// Monta a consulta de acordo com o que foi preenchido
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

// Junta os filtros com AND se existir algum
$where = null;
if (count($filtros) > 0) {
    $where = implode(' AND ', $filtros);
}

$componentes = readAll($pdo, 'componentes', $where, $params);

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
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="css/componentes.css">
    <title>Componentes - Flux</title>
</head>

<body>

    <div class="app-robo">

        <!-- Menu lateral -->
        <nav class="sidebar">

            <div class="sidebar-logo">
                <img src="imagens/Logotipo futurista LUX em ciano.png" alt="Logo Flux">
            </div>

            <ul class="sidebar-menu">

                <li>
                    <a href="index.php">
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
                    <a href="#">
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

                <p>
                    Sistema Operacional <strong>v1.0</strong>
                </p>
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

                    <div class="cadastrar-componente">
                        <a href="adicionar_componente.php">+ Cadastrar Componente</a>
                    </div>

                </form>

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
                        <?php foreach ($componentes as $c):
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
                                    <a href="editar_componente.php?id=<?= $c['id'] ?>">Editar</a>
                                    <a href="excluir_componente.php?id=<?= $c['id'] ?>" class="btn-excluir">Excluir</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

            </div>

        </main>

    </div>

</body>

</html>
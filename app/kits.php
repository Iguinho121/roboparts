<?php
require_once __DIR__ . '/init.php';

// Busca todos os kits cadastrados no banco
$kits = readAll($pdo, 'kits');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <title>Kits e Catálogos - Flux</title>
    <link rel="stylesheet" href="css/kits.css">
</head>

<body>

    <div class="app-robo">
        <!-- Menu lateral -->
        <nav class="sidebar">

            <div class="sidebar-logo">
                <img src="imagens/novo_logo.png" alt="Logo Flux">
            </div>

            <ul class="sidebar-menu">

                <li>
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

                <li class="active">
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

            <header class="cabecalho">
                <h1 class="text-cabecalho">Produtos | Robóticas</h1>
                <h2 class="text2-cabecalho">Selecione um kit pré-configurado por tipo de projeto.</h2>
            </header>

            <div class="cards">

                <?php if (count($kits) == 0): ?>
                    <p class="sem-kits">Nenhum kit cadastrado no momento.</p>
                <?php else: ?>
                    <?php foreach ($kits as $kit):
                        // Busca as peças vinculadas a este kit
                        $itensKit = readAll($pdo, 'kit_componentes', 'kit_id = ?', [$kit['id']]);
                        $kitDisponivel = true;
                    ?>
                        <div class="card-kit">
                            <div class="card-org">
                                <span class="border border-01"><?= htmlspecialchars($kit['tipo_projeto'] ?? 'Geral') ?></span>
                                <span class="border border-02">Kit montável</span>
                            </div>

                            <h2 class="card-title"><?= htmlspecialchars($kit['nome']) ?></h2>
                            <p class="p-card"><?= htmlspecialchars($kit['descricao'] ?? 'Sem descrição.') ?></p>

                            <hr class="hr">

                            <h2 class="card-pecas">Peças Incluídas:</h2>
                            <ul class="pecas-lista">
                                <?php foreach ($itensKit as $item):
                                    $comp = read($pdo, 'componentes', 'id = ?', [$item['componente_id']]);
                                    if (!$comp) {
                                        continue;
                                    }

                                    $emEstoque = $comp['quantidade_disponivel'];
                                    if ($emEstoque < $item['quantidade']) {
                                        $kitDisponivel = false;
                                    }
                                ?>
                                    <li>
                                        <span class="item-nome"><?= htmlspecialchars($comp['nome']) ?></span>
                                        <span class="item-info">
                                            <strong class="qtd"><?= $item['quantidade'] ?>x</strong>
                                            <span class="estoque">(<?= $emEstoque ?> em est.)</span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($kitDisponivel && count($itensKit) > 0): ?>
                                <a href="requisitar_kit.php?id=<?= $kit['id'] ?>" class="botao">Requisitar kit completo</a>
                            <?php else: ?>
                                <button class="botao botao-indisponivel" disabled>Estoque insuficiente</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

        </main>
    </div>

</body>

</html>
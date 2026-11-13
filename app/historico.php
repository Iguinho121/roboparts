
<?php

/*
    HISTORICO - ROBOPARTS

    Pagina responsavel por consultar e apresentar
    as movimentacoes registradas no banco de dados.
*/

// Inclui a conexao com o banco.
require_once __DIR__ . "/banco/database.php";

// Recebe os filtros da pagina.
$busca = trim($_GET["busca"] ?? "");
$tipo = $_GET["tipo"] ?? "";
$dataInicio = $_GET["data_inicio"] ?? "";
$dataFim = $_GET["data_fim"] ?? "";

// Tipos de movimentacao existentes no banco.
$tiposPermitidos = ["Entrada", "Saida", "Emprestimo", "Devolucao"];

// Verifica se o tipo selecionado e valido.
if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = "";
}

// Verifica se a data esta correta.
function dataValida($data)
{
    if ($data == "") {
        return false;
    }

    $dataObjeto = DateTime::createFromFormat("Y-m-d", $data);

    return $dataObjeto && $dataObjeto->format("Y-m-d") == $data;
}

// Descarta datas invalidas.
if (!dataValida($dataInicio)) {
    $dataInicio = "";
}

if (!dataValida($dataFim)) {
    $dataFim = "";
}

// Prepara as condicoes dos filtros.
$condicoes = [];
$parametros = [];

// Pesquisa pelo nome ou codigo do componente.
if ($busca != "") {
    $condicoes[] = "(c.nome LIKE :busca_nome OR c.codigo LIKE :busca_codigo)";

    $parametros[":busca_nome"] = "%" . $busca . "%";
    $parametros[":busca_codigo"] = "%" . $busca . "%";
}

// Filtra pelo tipo de movimentacao.
if ($tipo != "") {
    $condicoes[] = "m.tipo_movimentacao = :tipo";
    $parametros[":tipo"] = $tipo;
}

// Filtra pela data inicial.
if ($dataInicio != "") {
    $condicoes[] = "m.data_movimentacao >= :data_inicio";
    $parametros[":data_inicio"] = $dataInicio . " 00:00:00";
}

// Filtra pela data final.
if ($dataFim != "") {
    $condicoes[] = "m.data_movimentacao <= :data_fim";
    $parametros[":data_fim"] = $dataFim . " 23:59:59";
}

// Junta os filtros na condicao WHERE.
$sqlWhere = "";

if (count($condicoes) > 0) {
    $sqlWhere = " WHERE " . implode(" AND ", $condicoes);
}

// Variaveis que guardam os resultados.
$movimentacoes = [];

$total = 0;
$entradas = 0;
$emprestimos = 0;
$devolucoes = 0;
$saidas = 0;

$erroConsulta = "";

// Executa as consultas no banco.
try {

    // Cria a conexao usando o database.php.
    $pdo = database();

    // Consulta os totais das movimentacoes.
    $sqlResumo = "
        SELECT
            COUNT(*) AS total,

            COALESCE(SUM(
                CASE WHEN m.tipo_movimentacao = 'Entrada'
                THEN 1 ELSE 0 END
            ), 0) AS entradas,

            COALESCE(SUM(
                CASE WHEN m.tipo_movimentacao = 'Emprestimo'
                THEN 1 ELSE 0 END
            ), 0) AS emprestimos,

            COALESCE(SUM(
                CASE WHEN m.tipo_movimentacao = 'Devolucao'
                THEN 1 ELSE 0 END
            ), 0) AS devolucoes,

            COALESCE(SUM(
                CASE WHEN m.tipo_movimentacao = 'Saida'
                THEN 1 ELSE 0 END
            ), 0) AS saidas

        FROM movimentacoes m

        INNER JOIN componentes c
        ON m.componente_id = c.id

        $sqlWhere
    ";

    $consultaResumo = $pdo->prepare($sqlResumo);
    $consultaResumo->execute($parametros);

    $resumo = $consultaResumo->fetch();

    // Guarda os valores encontrados.
    $total = $resumo["total"];
    $entradas = $resumo["entradas"];
    $emprestimos = $resumo["emprestimos"];
    $devolucoes = $resumo["devolucoes"];
    $saidas = $resumo["saidas"];


    // Consulta as movimentacoes registradas.
    $sqlHistorico = "
        SELECT
            m.id,
            m.tipo_movimentacao,
            m.quantidade,
            m.data_movimentacao,
            m.observacao,

            c.nome AS componente_nome,
            c.codigo AS componente_codigo,

            p.nome AS projeto_nome

        FROM movimentacoes m

        INNER JOIN componentes c
        ON m.componente_id = c.id

        LEFT JOIN projetos p
        ON m.projeto_id = p.id

        $sqlWhere

        ORDER BY m.data_movimentacao DESC, m.id DESC

        LIMIT 200
    ";

    $consultaHistorico = $pdo->prepare($sqlHistorico);
    $consultaHistorico->execute($parametros);

    $movimentacoes = $consultaHistorico->fetchAll();

} catch (PDOException $erro) {

    // Mostra o erro durante o desenvolvimento.
    $erroConsulta = $erro->getMessage();

}


// Traduz os tipos para aparecerem corretamente.
function nomeTipo($tipoMovimentacao)
{
    if ($tipoMovimentacao == "Saida") {
        return "Saída";
    }

    if ($tipoMovimentacao == "Emprestimo") {
        return "Empréstimo";
    }

    if ($tipoMovimentacao == "Devolucao") {
        return "Devolução";
    }

    return $tipoMovimentacao;
}


// Define a classe CSS de cada movimentacao.
function classeTipo($tipoMovimentacao)
{
    if ($tipoMovimentacao == "Entrada") {
        return "entrada";
    }

    if ($tipoMovimentacao == "Devolucao") {
        return "devolucao";
    }

    if ($tipoMovimentacao == "Emprestimo") {
        return "emprestimo";
    }

    return "saida";
}


// Protege os textos exibidos na pagina.
function escapar($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Histórico | RoboParts</title>

    <!-- CSS da pagina -->
    <link rel="stylesheet" href="css/historico.css">

    <!-- Icones do menu -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0">

</head>

<body>

    <!-- MENU LATERAL -->
    <nav class="sidebar">

        <div class="sidebar-logo">

            <img src="imagens/logoflux.png" alt="Logo Flux">

            <span>LUX</span>

        </div>


        <!-- Opcoes de navegacao -->
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
                <a href="cadastro.php">
                    <span class="material-symbols-outlined menu-icon">add_circle</span>
                    <span>Cadastro</span>
                </a>
            </li>

            <li>
                <a href="emprestimo.php">
                    <span class="material-symbols-outlined menu-icon">swap_horiz</span>
                    <span>Empréstimo</span>
                </a>
            </li>

            <li>
                <a href="projetos.php">
                    <span class="material-symbols-outlined menu-icon">folder</span>
                    <span>Projetos</span>
                </a>
            </li>

            <!-- Pagina atual -->
            <li class="active">
                <a href="historico.php">
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


        <!-- Cartao do robo -->
        <div class="sidebar-card">

            <img src="imagens/robozin.png" alt="Robô do menu">

            <h3>Flux</h3>

            <span class="robo-descricao">
                Inovação em<br>cada componente.
            </span>

        </div>


        <!-- Status do sistema -->
        <div class="sidebar-status">

            <span class="status-bolinha"></span>

            <p>
                Sistema Operacional <strong>v1.0</strong>
            </p>

        </div>

    </nav>


    <!-- CONTEUDO PRINCIPAL -->
    <main class="pagina">

        <!-- Cabecalho -->
        <header class="cabecalho">

            <div>

                <p class="identificacao">ROBOPARTS / ESTOQUE</p>

                <h1>Histórico de movimentações</h1>

                <p class="descricao">
                    Acompanhe todas as entradas, saídas, empréstimos e devoluções dos componentes.
                </p>

            </div>

        </header>


        <!-- Cartoes de resumo -->
        <section class="resumo">

            <article class="cartao">

                <span>Total de registros</span>

                <strong>
                    <?php echo escapar($total); ?>
                </strong>

            </article>


            <article class="cartao">

                <span>Entradas</span>

                <strong class="texto-entrada">
                    <?php echo escapar($entradas); ?>
                </strong>

            </article>


            <article class="cartao">

                <span>Empréstimos</span>

                <strong class="texto-emprestimo">
                    <?php echo escapar($emprestimos); ?>
                </strong>

            </article>


            <article class="cartao">

                <span>Devoluções</span>

                <strong class="texto-devolucao">
                    <?php echo escapar($devolucoes); ?>
                </strong>

            </article>


            <article class="cartao">

                <span>Saídas</span>

                <strong class="texto-saida">
                    <?php echo escapar($saidas); ?>
                </strong>

            </article>

        </section>


        <!-- Filtros -->
        <section class="painel">

            <div class="titulo-painel">

                <h2>Consultar movimentações</h2>

                <p>
                    Utilize os filtros para encontrar um registro específico.
                </p>

            </div>


            <form method="GET" action="historico.php" class="filtros">

                <div class="campo campo-pesquisa">

                    <label for="busca">Componente ou código</label>

                    <input
                        type="text"
                        id="busca"
                        name="busca"
                        placeholder="Ex.: Arduino ou ARD-001"
                        value="<?php echo escapar($busca); ?>"
                    >

                </div>


                <div class="campo">

                    <label for="tipo">Tipo de movimentação</label>

                    <select name="tipo" id="tipo">

                        <option value="">Todos</option>

                        <option value="Entrada" <?php echo $tipo == "Entrada" ? "selected" : ""; ?>>
                            Entrada
                        </option>

                        <option value="Saida" <?php echo $tipo == "Saida" ? "selected" : ""; ?>>
                            Saída
                        </option>

                        <option value="Emprestimo" <?php echo $tipo == "Emprestimo" ? "selected" : ""; ?>>
                            Empréstimo
                        </option>

                        <option value="Devolucao" <?php echo $tipo == "Devolucao" ? "selected" : ""; ?>>
                            Devolução
                        </option>

                    </select>

                </div>


                <div class="campo">

                    <label for="data_inicio">Data inicial</label>

                    <input
                        type="date"
                        id="data_inicio"
                        name="data_inicio"
                        value="<?php echo escapar($dataInicio); ?>"
                    >

                </div>


                <div class="campo">

                    <label for="data_fim">Data final</label>

                    <input
                        type="date"
                        id="data_fim"
                        name="data_fim"
                        value="<?php echo escapar($dataFim); ?>"
                    >

                </div>


                <div class="acoes-filtro">

                    <button type="submit" class="botao-filtrar">
                        Filtrar histórico
                    </button>

                    <a href="historico.php" class="botao-limpar">
                        Limpar
                    </a>

                </div>

            </form>

        </section>


        <!-- Tabela de movimentacoes -->
        <section class="painel painel-tabela">

            <div class="titulo-painel titulo-lista">

                <div>

                    <h2>Movimentações registradas</h2>

                    <p>Os registros mais recentes aparecem primeiro.</p>

                </div>

                <span class="contador">
                    <?php echo escapar(count($movimentacoes)); ?> registro(s)
                </span>

            </div>


            <?php if ($erroConsulta != ""): ?>

                <!-- Mensagem de erro -->
                <div class="mensagem-vazia">

                    <h3>Erro ao consultar o histórico</h3>

                    <p>
                        <?php echo escapar($erroConsulta); ?>
                    </p>

                </div>


            <?php elseif (count($movimentacoes) > 0): ?>

                <div class="tabela-responsiva">

                    <table>

                        <thead>

                            <tr>

                                <th>Data e hora</th>
                                <th>Componente</th>
                                <th>Tipo</th>
                                <th>Quantidade</th>
                                <th>Projeto</th>
                                <th>Observação</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($movimentacoes as $movimentacao): ?>

                                <tr>

                                    <td class="data">

                                        <?php
                                        echo date(
                                            "d/m/Y H:i",
                                            strtotime($movimentacao["data_movimentacao"])
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <strong>
                                            <?php echo escapar($movimentacao["componente_nome"]); ?>
                                        </strong>

                                        <small>
                                            <?php echo escapar($movimentacao["componente_codigo"] ?? "Sem código"); ?>
                                        </small>

                                    </td>


                                    <td>

                                        <span class="etiqueta <?php echo escapar(classeTipo($movimentacao["tipo_movimentacao"])); ?>">

                                            <?php echo escapar(nomeTipo($movimentacao["tipo_movimentacao"])); ?>

                                        </span>

                                    </td>


                                    <td>
                                        <?php echo escapar($movimentacao["quantidade"]); ?>
                                    </td>


                                    <td>
                                        <?php echo escapar($movimentacao["projeto_nome"] ?? "—"); ?>
                                    </td>


                                    <td class="observacao">

                                        <?php echo escapar($movimentacao["observacao"] ?: "Sem observação"); ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <?php if (count($movimentacoes) == 200): ?>

                    <p class="aviso-limite">
                        São exibidos no máximo 200 registros.
                        Utilize os filtros para refinar a pesquisa.
                    </p>

                <?php endif; ?>


            <?php else: ?>

                <!-- Quando nao existem registros -->
                <div class="mensagem-vazia">

                    <h3>Nenhuma movimentação encontrada</h3>

                    <p>
                        Não existem registros para os filtros selecionados.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <p class="rodape">
            O histórico apresenta as movimentações salvas no banco de dados.
        </p>

    </main>

</body>

</html>

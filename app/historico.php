<?php
/*
    PAGINA DE HISTORICO - ROBOPARTS

    Esta pagina apenas consulta as movimentacoes que ja foram registradas
    na tabela movimentacoes. Ela nao cadastra emprestimos nem altera estoque.
*/

// Inclui o arquivo que faz a conexao com o banco flux.
require_once __DIR__ . "/banco/conexao.php";

// Recebe os filtros enviados pelo formulario.
$busca = trim($_GET["busca"] ?? "");
$tipo = $_GET["tipo"] ?? "";
$dataInicio = $_GET["data_inicio"] ?? "";
$dataFim = $_GET["data_fim"] ?? "";

// Lista os tipos que realmente existem no ENUM do banco.
$tiposPermitidos = ["Entrada", "Saida", "Emprestimo", "Devolucao"];

// Se o tipo recebido nao for permitido, deixa o filtro vazio.
if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = "";
}

// Verifica se a data esta no formato AAAA-MM-DD.
function dataValida($data) {
    if ($data === "") {
        return false;
    }

    $dataObjeto = DateTime::createFromFormat("Y-m-d", $data);
    return $dataObjeto && $dataObjeto->format("Y-m-d") === $data;
}

// Descarta datas invalidas para evitar filtros incorretos.
if (!dataValida($dataInicio)) {
    $dataInicio = "";
}

if (!dataValida($dataFim)) {
    $dataFim = "";
}

// Monta as condicoes da pesquisa conforme os filtros preenchidos.
$condicoes = [];
$parametros = [];

// Pesquisa pelo nome ou pelo codigo do componente.
if ($busca !== "") {
    $condicoes[] = "(c.nome LIKE :busca_nome OR c.codigo LIKE :busca_codigo)";
    $parametros[":busca_nome"] = "%" . $busca . "%";
    $parametros[":busca_codigo"] = "%" . $busca . "%";
}

// Filtra pelo tipo de movimentacao.
if ($tipo !== "") {
    $condicoes[] = "m.tipo_movimentacao = :tipo";
    $parametros[":tipo"] = $tipo;
}

// Filtra pela data inicial.
if ($dataInicio !== "") {
    $condicoes[] = "m.data_movimentacao >= :data_inicio";
    $parametros[":data_inicio"] = $dataInicio . " 00:00:00";
}

// Filtra pela data final, incluindo o dia inteiro.
if ($dataFim !== "") {
    $condicoes[] = "m.data_movimentacao <= :data_fim";
    $parametros[":data_fim"] = $dataFim . " 23:59:59";
}

// Junta todas as condicoes em uma parte WHERE.
$sqlWhere = "";

if (count($condicoes) > 0) {
    $sqlWhere = " WHERE " . implode(" AND ", $condicoes);
}

// Prepara variaveis para guardar os resultados.
$movimentacoes = [];
$total = 0;
$entradas = 0;
$emprestimos = 0;
$devolucoes = 0;
$saidas = 0;
$erroConsulta = "";

try {
    // Consulta os totais de cada tipo usando os mesmos filtros da tabela.
    $sqlResumo = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'Entrada' THEN 1 ELSE 0 END), 0) AS entradas,
            COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'Emprestimo' THEN 1 ELSE 0 END), 0) AS emprestimos,
            COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'Devolucao' THEN 1 ELSE 0 END), 0) AS devolucoes,
            COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'Saida' THEN 1 ELSE 0 END), 0) AS saidas
        FROM movimentacoes m
        INNER JOIN componentes c ON m.componente_id = c.id
        $sqlWhere
    ";

    $consultaResumo = $pdo->prepare($sqlResumo);
    $consultaResumo->execute($parametros);
    $resumo = $consultaResumo->fetch();

    // Guarda os totais para mostrar nos cartoes da pagina.
    $total = $resumo["total"];
    $entradas = $resumo["entradas"];
    $emprestimos = $resumo["emprestimos"];
    $devolucoes = $resumo["devolucoes"];
    $saidas = $resumo["saidas"];

    // Busca as movimentacoes e os nomes dos componentes e projetos.
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
        INNER JOIN componentes c ON m.componente_id = c.id
        LEFT JOIN projetos p ON m.projeto_id = p.id
        $sqlWhere
        ORDER BY m.data_movimentacao DESC, m.id DESC
        LIMIT 200
    ";

    $consultaHistorico = $pdo->prepare($sqlHistorico);
    $consultaHistorico->execute($parametros);
    $movimentacoes = $consultaHistorico->fetchAll();

} catch (PDOException $erro) {
    // Guarda uma mensagem simples sem exibir detalhes internos do banco.
    $erroConsulta = "Ocorreu um erro ao buscar o historico. Verifique a conexao e as tabelas do banco.";
}

// Converte o nome do tipo para uma forma mais bonita na tela.
function nomeTipo($tipoMovimentacao) {
    if ($tipoMovimentacao === "Saida") {
        return "Saída";
    }

    if ($tipoMovimentacao === "Emprestimo") {
        return "Empréstimo";
    }

    if ($tipoMovimentacao === "Devolucao") {
        return "Devolução";
    }

    return $tipoMovimentacao;
}

// Retorna a classe CSS de acordo com o tipo de movimentacao.
function classeTipo($tipoMovimentacao) {
    if ($tipoMovimentacao === "Entrada") {
        return "entrada";
    }

    if ($tipoMovimentacao === "Devolucao") {
        return "devolucao";
    }

    if ($tipoMovimentacao === "Emprestimo") {
        return "emprestimo";
    }

    return "saida";
}

// Protege os textos que serao mostrados no HTML.
function escapar($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico | RoboParts</title>

    <!-- Arquivo responsavel pelo visual da pagina. -->
    <link rel="stylesheet" href="historico.css">
</head>
<body>
    <main class="pagina">

        <!-- Cabecalho da pagina. -->
        <header class="cabecalho">
            <div>
                <p class="identificacao">ROBOPARTS / ESTOQUE</p>
                <h1>Histórico de movimentações</h1>
                <p class="descricao">Acompanhe as entradas, saídas, empréstimos e devoluções dos componentes.</p>
            </div>

            <!-- Altere o destino do link conforme a pagina inicial do seu projeto. -->
            <a class="botao-voltar" href="index.php">Voltar</a>
        </header>

        <!-- Cartoes com o resumo das movimentacoes encontradas. -->
        <section class="resumo">
            <article class="cartao">
                <span>Total de registros</span>
                <strong><?php echo escapar($total); ?></strong>
            </article>

            <article class="cartao">
                <span>Entradas</span>
                <strong class="texto-entrada"><?php echo escapar($entradas); ?></strong>
            </article>

            <article class="cartao">
                <span>Empréstimos</span>
                <strong class="texto-emprestimo"><?php echo escapar($emprestimos); ?></strong>
            </article>

            <article class="cartao">
                <span>Devoluções</span>
                <strong class="texto-devolucao"><?php echo escapar($devolucoes); ?></strong>
            </article>

            <article class="cartao">
                <span>Saídas</span>
                <strong class="texto-saida"><?php echo escapar($saidas); ?></strong>
            </article>
        </section>

        <!-- Formulario de filtros. Os dados sao enviados pela URL usando GET. -->
        <section class="painel">
            <div class="titulo-painel">
                <h2>Consultar movimentações</h2>
                <p>Use os filtros para encontrar um registro específico.</p>
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
                        <option value="Entrada" <?php echo $tipo === "Entrada" ? "selected" : ""; ?>>Entrada</option>
                        <option value="Saida" <?php echo $tipo === "Saida" ? "selected" : ""; ?>>Saída</option>
                        <option value="Emprestimo" <?php echo $tipo === "Emprestimo" ? "selected" : ""; ?>>Empréstimo</option>
                        <option value="Devolucao" <?php echo $tipo === "Devolucao" ? "selected" : ""; ?>>Devolução</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="data_inicio">Data inicial</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo escapar($dataInicio); ?>">
                </div>

                <div class="campo">
                    <label for="data_fim">Data final</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo escapar($dataFim); ?>">
                </div>

                <div class="acoes-filtro">
                    <button type="submit" class="botao-filtrar">Filtrar histórico</button>
                    <a href="historico.php" class="botao-limpar">Limpar</a>
                </div>
            </form>
        </section>

        <!-- Tabela que mostra os registros encontrados no banco. -->
        <section class="painel painel-tabela">
            <div class="titulo-painel titulo-lista">
                <div>
                    <h2>Movimentações registradas</h2>
                    <p>Exibindo os registros mais recentes primeiro.</p>
                </div>
                <span class="contador"><?php echo escapar(count($movimentacoes)); ?> registro(s) nesta página</span>
            </div>

            <?php if ($erroConsulta !== ""): ?>
                <!-- Mensagem apresentada quando ocorre algum erro na consulta. -->
                <p class="mensagem-vazia"><?php echo escapar($erroConsulta); ?></p>

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
                                    <!-- Formata a data para o padrão brasileiro. -->
                                    <td class="data">
                                        <?php echo date("d/m/Y H:i", strtotime($movimentacao["data_movimentacao"])); ?>
                                    </td>

                                    <td>
                                        <strong><?php echo escapar($movimentacao["componente_nome"]); ?></strong>
                                        <small><?php echo escapar($movimentacao["componente_codigo"] ?? "Sem código"); ?></small>
                                    </td>

                                    <td>
                                        <span class="etiqueta <?php echo escapar(classeTipo($movimentacao["tipo_movimentacao"])); ?>">
                                            <?php echo escapar(nomeTipo($movimentacao["tipo_movimentacao"])); ?>
                                        </span>
                                    </td>

                                    <td><?php echo escapar($movimentacao["quantidade"]); ?></td>

                                    <td><?php echo escapar($movimentacao["projeto_nome"] ?? "—"); ?></td>

                                    <td class="observacao">
                                        <?php echo escapar($movimentacao["observacao"] ?: "Sem observação"); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Aviso sobre o limite aplicado na consulta. -->
                <?php if (count($movimentacoes) === 200): ?>
                    <p class="aviso-limite">São exibidos no máximo 200 registros por vez. Use os filtros para refinar a pesquisa.</p>
                <?php endif; ?>

            <?php else: ?>
                <!-- Aparece quando nenhum registro corresponde aos filtros. -->
                <div class="mensagem-vazia">
                    <h3>Nenhuma movimentação encontrada</h3>
                    <p>Não existem registros para os filtros selecionados.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Informacao para lembrar que o historico depende das outras paginas. -->
        <p class="rodape">O histórico apresenta as movimentações salvas no banco de dados.</p>
    </main>
</body>
</html>

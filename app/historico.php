<?php
// Historico de movimentacoes do RoboParts.

$erroConsulta = '';
$consultaOk = false;
$movimentacoes = [];
$resumo = ['total' => 0, 'entradas' => 0, 'emprestimos' => 0, 'devolucoes' => 0, 'saidas' => 0];

// Aceita somente valores simples enviados pelo formulario.
$lerFiltro = static fn(string $nome): string =>
  isset($_GET[$nome]) && is_string($_GET[$nome]) ? trim($_GET[$nome]) : '';

$busca = $lerFiltro('busca');
$tipo = $lerFiltro('tipo');
$dataInicio = $lerFiltro('data_inicio');
$dataFim = $lerFiltro('data_fim');

$tiposPermitidos = ['Entrada', 'Saida', 'Emprestimo', 'Devolucao'];
if (!in_array($tipo, $tiposPermitidos, true)) {
  $tipo = '';
}

// Valida datas no formato AAAA-MM-DD.
$validarData = static function (string $data): bool {
  if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $data)) {
    return false;
  }

  [$ano, $mes, $dia] = array_map('intval', explode('-', $data));
  return checkdate($mes, $dia, $ano);
};

if (!$validarData($dataInicio)) {
  $dataInicio = '';
}
if (!$validarData($dataFim)) {
  $dataFim = '';
}

// Monta os filtros usados tanto no resumo quanto na tabela.
$filtros = [];
$parametros = [];

if ($busca !== '') {
  $filtros[] = '(c.nome LIKE :nome OR c.codigo LIKE :codigo)';
  $parametros[':nome'] = "%{$busca}%";
  $parametros[':codigo'] = "%{$busca}%";
}
if ($tipo !== '') {
  $filtros[] = 'm.tipo_movimentacao = :tipo';
  $parametros[':tipo'] = $tipo;
}
if ($dataInicio !== '') {
  $filtros[] = 'm.data_movimentacao >= :data_inicio';
  $parametros[':data_inicio'] = $dataInicio . ' 00:00:00';
}
if ($dataFim !== '') {
  $filtros[] = 'm.data_movimentacao <= :data_fim';
  $parametros[':data_fim'] = $dataFim . ' 23:59:59';
}

$sqlWhere = $filtros ? ' WHERE ' . implode(' AND ', $filtros) : '';

try {
  // Segue o padrao das outras paginas do projeto: init cria a sessao e $pdo.
  require_once __DIR__ . '/init.php';

  // Os indicadores contam registros de movimentacao, nao unidades movimentadas.
  $sqlResumo = "
    SELECT COUNT(*) AS total,
      COALESCE(SUM(m.tipo_movimentacao = 'Entrada'), 0) AS entradas,
      COALESCE(SUM(m.tipo_movimentacao = 'Emprestimo'), 0) AS emprestimos,
      COALESCE(SUM(m.tipo_movimentacao = 'Devolucao'), 0) AS devolucoes,
      COALESCE(SUM(m.tipo_movimentacao = 'Saida'), 0) AS saidas
    FROM movimentacoes m
    INNER JOIN componentes c ON c.id = m.componente_id
    {$sqlWhere}
  ";
  $consultaResumo = $pdo->prepare($sqlResumo);
  $consultaResumo->execute($parametros);
  $resumo = $consultaResumo->fetch() ?: $resumo;

  // Busca as movimentacoes mais recentes, com os nomes relacionados.
  $sqlHistorico = "
    SELECT m.id, m.tipo_movimentacao, m.quantidade, m.data_movimentacao,
        m.observacao, c.nome AS componente_nome, c.codigo AS componente_codigo,
        p.nome AS projeto_nome
    FROM movimentacoes m
    INNER JOIN componentes c ON c.id = m.componente_id
    LEFT JOIN projetos p ON p.id = m.projeto_id
    {$sqlWhere}
    ORDER BY m.data_movimentacao DESC, m.id DESC
    LIMIT 200
  ";
  $consultaHistorico = $pdo->prepare($sqlHistorico);
  $consultaHistorico->execute($parametros);
  $movimentacoes = $consultaHistorico->fetchAll();
  $consultaOk = true;
} catch (Throwable $erro) {
  // O detalhe tecnico fica no log; a tela mostra uma mensagem amigavel.
  error_log('Erro no historico do RoboParts: ' . $erro->getMessage());
  $erroConsulta = 'Não foi possível carregar o histórico. Verifique a conexão e tente novamente.';
}

// Textos e cores dos tipos de movimentacao.
$tiposExibicao = [
  'Entrada' => ['nome' => 'Entrada', 'classe' => 'entrada'],
  'Saida' => ['nome' => 'Saída', 'classe' => 'saida'],
  'Emprestimo' => ['nome' => 'Empréstimo', 'classe' => 'emprestimo'],
  'Devolucao' => ['nome' => 'Devolução', 'classe' => 'devolucao'],
];

// Escapa os valores antes de inseri-los no HTML.
$h = static fn($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Histórico | RoboParts</title>
  <link rel="stylesheet" href="css/historico.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300..400,0..1,0">
</head>
<body>
  <nav class="sidebar" aria-label="Navegação principal">
    <a class="sidebar-logo" href="index.php" aria-label="Flux - início">
      <img src="imagens/logoflux.png" alt="Logo Flux">
      <span>LUX</span>
    </a>

    <ul class="sidebar-menu">
      <li><a href="index.php"><span class="material-symbols-outlined menu-icon">dashboard</span><span>Dashboard</span></a></li>
      <li><a href="componentes.php"><span class="material-symbols-outlined menu-icon">memory</span><span>Componentes</span></a></li>
      <li><a href="adicionar_componente.php"><span class="material-symbols-outlined menu-icon">add_circle</span><span>Cadastro</span></a></li>
      <li><a href="#" aria-disabled="true" title="Página ainda não disponível"><span class="material-symbols-outlined menu-icon">swap_horiz</span><span>Empréstimo</span></a></li>
      <li><a href="#" aria-disabled="true" title="Página ainda não disponível"><span class="material-symbols-outlined menu-icon">folder</span><span>Projetos</span></a></li>
      <li class="active"><a href="historico.php" aria-current="page"><span class="material-symbols-outlined menu-icon">history</span><span>Histórico</span></a></li>
      <li><a href="#" aria-disabled="true" title="Página ainda não disponível"><span class="material-symbols-outlined menu-icon">inventory_2</span><span>Kits e Catálogos</span></a></li>
    </ul>

    <div class="sidebar-card">
      <img src="imagens/robozin.png" alt="Robô Flux">
      <h3>Flux</h3>
      <span class="robo-descricao">Inovação em<br>cada componente.</span>
    </div>

    <div class="sidebar-status"><span class="status-bolinha"></span><p>Sistema Operacional <strong>v1.0</strong></p></div>
  </nav>

  <main class="pagina">
    <header class="cabecalho">
      <div>
        <p class="identificacao">ROBOPARTS / ESTOQUE</p>
        <h1>Histórico de movimentações</h1>
        <p class="descricao">Acompanhe as entradas, saídas, empréstimos e devoluções dos componentes.</p>
      </div>
    </header>

    <section class="resumo" aria-label="Resumo das movimentações">
      <?php
      $cartoes = [
        ['titulo' => 'Total de registros', 'valor' => 'total', 'classe' => ''],
        ['titulo' => 'Entradas', 'valor' => 'entradas', 'classe' => 'texto-entrada'],
        ['titulo' => 'Empréstimos', 'valor' => 'emprestimos', 'classe' => 'texto-emprestimo'],
        ['titulo' => 'Devoluções', 'valor' => 'devolucoes', 'classe' => 'texto-devolucao'],
        ['titulo' => 'Saídas', 'valor' => 'saidas', 'classe' => 'texto-saida'],
      ];
      foreach ($cartoes as $cartao): ?>
        <article class="cartao">
          <span><?= $h($cartao['titulo']) ?></span>
          <strong class="<?= $h($cartao['classe']) ?>"><?= $consultaOk ? $h($resumo[$cartao['valor']]) : '—' ?></strong>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="painel">
      <div class="titulo-painel">
        <h2>Consultar movimentações</h2>
        <p>Utilize os filtros para encontrar um registro específico.</p>
      </div>

      <form method="GET" action="historico.php" class="filtros">
        <div class="campo campo-pesquisa">
          <label for="busca">Componente ou código</label>
          <input type="search" id="busca" name="busca" placeholder="Ex.: Arduino ou ARD-001" value="<?= $h($busca) ?>">
        </div>
        <div class="campo">
          <label for="tipo">Tipo de movimentação</label>
          <select name="tipo" id="tipo">
            <option value="">Todos</option>
            <?php foreach ($tiposExibicao as $valor => $info): ?>
              <option value="<?= $h($valor) ?>" <?= $tipo === $valor ? 'selected' : '' ?>><?= $h($info['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="data_inicio">Data inicial</label>
          <input type="date" id="data_inicio" name="data_inicio" value="<?= $h($dataInicio) ?>">
        </div>
        <div class="campo">
          <label for="data_fim">Data final</label>
          <input type="date" id="data_fim" name="data_fim" value="<?= $h($dataFim) ?>">
        </div>
        <div class="acoes-filtro">
          <button type="submit" class="botao-filtrar">Filtrar histórico</button>
          <a href="historico.php" class="botao-limpar">Limpar</a>
        </div>
      </form>
    </section>

    <section class="painel painel-tabela">
      <div class="titulo-painel titulo-lista">
        <div>
          <h2>Movimentações registradas</h2>
          <p>Os registros mais recentes aparecem primeiro.</p>
        </div>
        <span class="contador"><?= $consultaOk ? count($movimentacoes) . ' registro(s)' : 'Indisponível' ?></span>
      </div>

      <?php if ($erroConsulta !== ''): ?>
        <div class="mensagem-vazia" role="alert">
          <h3>Histórico indisponível</h3>
          <p><?= $h($erroConsulta) ?></p>
        </div>
      <?php elseif (!$movimentacoes): ?>
        <div class="mensagem-vazia">
          <h3>Nenhuma movimentação encontrada</h3>
          <p>Não existem registros para os filtros selecionados.</p>
        </div>
      <?php else: ?>
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
              <?php foreach ($movimentacoes as $movimentacao):
                $tipoAtual = $tiposExibicao[$movimentacao['tipo_movimentacao']] ?? ['nome' => $movimentacao['tipo_movimentacao'], 'classe' => 'saida'];
              ?>
                <tr>
                  <td class="data"><?= $h(date('d/m/Y H:i', strtotime($movimentacao['data_movimentacao']))) ?></td>
                  <td><strong><?= $h($movimentacao['componente_nome']) ?></strong><small><?= $h($movimentacao['componente_codigo'] ?: 'Sem código') ?></small></td>
                  <td><span class="etiqueta <?= $h($tipoAtual['classe']) ?>"><?= $h($tipoAtual['nome']) ?></span></td>
                  <td><?= $h($movimentacao['quantidade']) ?></td>
                  <td><?= $h($movimentacao['projeto_nome'] ?: '—') ?></td>
                  <td class="observacao"><?= $h($movimentacao['observacao'] ?: 'Sem observação') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($movimentacoes) === 200): ?>
          <p class="aviso-limite">Exibindo os 200 registros mais recentes. Utilize os filtros para refinar a pesquisa.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <p class="rodape">O histórico apresenta as movimentações salvas no banco de dados.</p>
  </main>
</body>
</html>

<?php
// Historico de movimentacoes do RoboParts.
require_once __DIR__ . '/auth.php';
exigirLogin();

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

$tiposPermitidos = ['Entrada', 'Saida', 'Emprestimo', 'Devolucao', 'Kit Requisitado'];
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
  'Kit Requisitado' => ['nome' => 'Kit Requisitado', 'classe' => 'emprestimo'],
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

  <!-- ⚠️ DEPENDÊNCIA DO JAVASCRIPT (Flatpickr): remova esta linha se retirar o JS do calendário -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <link rel="stylesheet" href="css/historico.css">
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
      <li><a href="dashboard.php"><span class="material-symbols-outlined menu-icon">dashboard</span><span>Dashboard</span></a></li>
      <li><a href="componentes.php"><span class="material-symbols-outlined menu-icon">memory</span><span>Componentes</span></a></li>
      <li><a href="projetos.php"><span class="material-symbols-outlined menu-icon">folder</span><span>Projetos</span></a></li>
      <li class="active"><a href="historico.php" aria-current="page"><span class="material-symbols-outlined menu-icon">history</span><span>Histórico</span></a></li>
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
        <div class="lista-movimentacoes">
          <?php foreach ($movimentacoes as $movimentacao):
            $tipoAtual = $tiposExibicao[$movimentacao['tipo_movimentacao']] ?? ['nome' => $movimentacao['tipo_movimentacao'], 'classe' => 'saida'];
          ?>
            <article class="movimentacao-bolha">
              <div class="bolha-topo">
                <div class="bolha-componente">
                  <strong><?= $h($movimentacao['componente_nome']) ?></strong>
                  <span class="bolha-codigo"><?= $h($movimentacao['componente_codigo'] ?: 'Sem código') ?></span>
                </div>

                <div class="bolha-badges">
                  <span class="etiqueta <?= $h($tipoAtual['classe']) ?>"><?= $h($tipoAtual['nome']) ?></span>
                  <span class="bolha-qtd"><?= $h($movimentacao['quantidade']) ?> un.</span>
                </div>

                <div class="bolha-meta">
                  <span class="bolha-projeto" title="Projeto vinculado">
                    <span class="material-symbols-outlined">folder</span>
                    <?= $h($movimentacao['projeto_nome'] ?: 'Sem projeto') ?>
                  </span>
                  <time class="bolha-data">
                    <span class="material-symbols-outlined">schedule</span>
                    <?= $h(date('d/m/Y H:i', strtotime($movimentacao['data_movimentacao']))) ?>
                  </time>
                </div>
              </div>

              <p class="bolha-observacao">
                <?= $h($movimentacao['observacao'] ?: 'Sem observação registrada.') ?>
              </p>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if (count($movimentacoes) === 200): ?>
          <p class="aviso-limite">Exibindo os 200 registros mais recentes. Utilize os filtros para refinar a pesquisa.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <p class="rodape">O histórico apresenta as movimentações salvas no banco de dados.</p>
  </main>

  <!-- =========================================================
       ⚠️ INÍCIO JAVASCRIPT 1 - BRILHO INTERATIVO NA SIDEBAR
       (Opcional: pode apagar este bloco se não usar JS)
       ========================================================= -->
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
  </script>
  <!-- ⚠️ FIM JAVASCRIPT 1 -->

  <!-- =========================================================
       ⚠️ INÍCIO JAVASCRIPT 2 - CALENDÁRIO E SELECT LIQUID GLASS
       (Opcional: pode apagar este bloco se não usar JS;
        os filtros continuam funcionando com HTML/CSS nativos)
       ========================================================= -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
  <script>
    /* Calendário moderno (Flatpickr) em português */
    flatpickr('.campo input[type="date"]', {
      locale: 'pt',
      altInput: true,
      altFormat: 'd/m/Y',
      dateFormat: 'Y-m-d',
      allowInput: false
    });

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
  <!-- ⚠️ FIM JAVASCRIPT 2 -->
</body>
</html>
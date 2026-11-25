<?php
// Projetos do Flux — padronizado no mesmo estilo do Histórico.
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

$mensagem = '';
$tipoMensagem = '';

$lerFiltro = static fn(string $nome): string =>
  isset($_GET[$nome]) && is_string($_GET[$nome]) ? trim($_GET[$nome]) : '';

$validarData = static function (string $data): bool {
  if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $data)) {
    return false;
  }

  [$ano, $mes, $dia] = array_map('intval', explode('-', $data));
  return checkdate($mes, $dia, $ano);
};

$statusPermitidos = ['Planejamento', 'Em andamento', 'Concluido'];

// Ações de Admin: Criar, Atualizar Status ou Excluir Projeto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
  $acao = $_POST['acao'] ?? '';

  if ($acao === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $status = $_POST['status'] ?? 'Planejamento';
    $dataInicioRaw = trim($_POST['data_inicio'] ?? '');
    $dataInicio = $validarData($dataInicioRaw) ? $dataInicioRaw : date('Y-m-d');

    if ($nome === '') {
      $mensagem = 'Informe o nome do projeto.';
      $tipoMensagem = 'erro';
    } elseif (!in_array($status, $statusPermitidos, true)) {
      $mensagem = 'Status inválido.';
      $tipoMensagem = 'erro';
    } else {
      create($pdo, 'projetos', [
        'nome'        => $nome,
        'descricao'   => $descricao !== '' ? $descricao : null,
        'status'      => $status,
        'data_inicio' => $dataInicio,
      ]);
      header('Location: projetos.php?msg=criado');
      exit;
    }
  } elseif ($acao === 'alterar_status') {
    $idProjeto = (int) ($_POST['id'] ?? 0);
    $novoStatus = $_POST['status'] ?? '';

    if ($idProjeto > 0 && in_array($novoStatus, $statusPermitidos, true)) {
      update($pdo, 'projetos', ['status' => $novoStatus], 'id = ?', [$idProjeto]);
      header('Location: projetos.php?msg=atualizado');
      exit;
    }
  } elseif ($acao === 'excluir') {
    $idExcluir = (int) ($_POST['id'] ?? 0);
    if ($idExcluir > 0) {
      delete($pdo, 'projetos', 'id = ?', [$idExcluir]);
      header('Location: projetos.php?msg=excluido');
      exit;
    }
  }
}

if (isset($_GET['msg'])) {
  if ($_GET['msg'] === 'criado') {
    $mensagem = 'Projeto cadastrado com sucesso!';
    $tipoMensagem = 'sucesso';
  } elseif ($_GET['msg'] === 'atualizado') {
    $mensagem = 'Status do projeto atualizado.';
    $tipoMensagem = 'sucesso';
  } elseif ($_GET['msg'] === 'excluido') {
    $mensagem = 'Projeto excluído com sucesso.';
    $tipoMensagem = 'sucesso';
  }
}

// Filtros de busca e status
$busca = $lerFiltro('busca');
$statusFiltro = $lerFiltro('status');
if (!in_array($statusFiltro, $statusPermitidos, true)) {
  $statusFiltro = '';
}

$filtros = [];
$params = [];

if ($busca !== '') {
  $filtros[] = '(nome LIKE ? OR descricao LIKE ?)';
  $params[] = "%{$busca}%";
  $params[] = "%{$busca}%";
}
if ($statusFiltro !== '') {
  $filtros[] = 'status = ?';
  $params[] = $statusFiltro;
}

$where = count($filtros) > 0 ? implode(' AND ', $filtros) : null;
$projetos = readAll($pdo, 'projetos', $where, $params);

// Resumo geral dos projetos
$todosProjetos = readAll($pdo, 'projetos');
$resumo = [
  'total'        => count($todosProjetos),
  'planejamento' => 0,
  'andamento'    => 0,
  'concluidos'   => 0,
];
foreach ($todosProjetos as $itemResumo) {
  if (($itemResumo['status'] ?? '') === 'Planejamento') {
    $resumo['planejamento']++;
  } elseif (($itemResumo['status'] ?? '') === 'Em andamento') {
    $resumo['andamento']++;
  } elseif (($itemResumo['status'] ?? '') === 'Concluido') {
    $resumo['concluidos']++;
  }
}

$statusExibicao = [
  'Planejamento' => ['nome' => 'Planejamento', 'classe' => 'planejamento'],
  'Em andamento' => ['nome' => 'Em andamento', 'classe' => 'andamento'],
  'Concluido'    => ['nome' => 'Concluído',    'classe' => 'concluido'],
];

$h = static fn($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projetos | Flux</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="css/projetos.css">
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
      <?php if (isAdmin()): ?>
        <li><a href="adicionar_componente.php"><span class="material-symbols-outlined menu-icon">add_circle</span><span>Cadastro</span></a></li>
      <?php endif; ?>
      <li class="active"><a href="projetos.php" aria-current="page"><span class="material-symbols-outlined menu-icon">folder</span><span>Projetos</span></a></li>
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
        <p class="identificacao">Flux / LABORATÓRIO</p>
        <h1>Projetos do laboratório</h1>
        <p class="descricao">Acompanhe robôs, protótipos e status de desenvolvimento.</p>
      </div>

      <?php if (isAdmin()): ?>
        <div class="acoes-cabecalho">
          <button type="button" class="botao-novo" id="btn-toggle-projeto">
            <span class="material-symbols-outlined">add</span>
            <span>Novo projeto</span>
          </button>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($mensagem !== ''): ?>
      <div class="alerta-feedback <?= $tipoMensagem === 'sucesso' ? 'sucesso' : 'erro' ?>" role="alert">
        <span class="material-symbols-outlined"><?= $tipoMensagem === 'sucesso' ? 'check_circle' : 'error' ?></span>
        <span><?= $h($mensagem) ?></span>
      </div>
    <?php endif; ?>

    <section class="resumo" aria-label="Resumo dos projetos">
      <article class="cartao">
        <span>Total de projetos</span>
        <strong><?= $h($resumo['total']) ?></strong>
      </article>
      <article class="cartao">
        <span>Planejamento</span>
        <strong class="texto-planejamento"><?= $h($resumo['planejamento']) ?></strong>
      </article>
      <article class="cartao">
        <span>Em andamento</span>
        <strong class="texto-andamento"><?= $h($resumo['andamento']) ?></strong>
      </article>
      <article class="cartao">
        <span>Concluídos</span>
        <strong class="texto-concluido"><?= $h($resumo['concluidos']) ?></strong>
      </article>
    </section>

    <?php if (isAdmin()): ?>
      <section class="painel painel-cadastro <?= ($mensagem !== '' && $tipoMensagem === 'erro') ? 'aberto' : '' ?>" id="painel-novo-projeto">
        <div class="titulo-painel titulo-lista">
          <div>
            <h2>Cadastrar novo projeto</h2>
            <p>Defina nome, descrição, status inicial e data de início.</p>
          </div>
          <button type="button" class="botao-fechar-painel" id="btn-fechar-projeto" aria-label="Fechar formulário">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form method="POST" action="projetos.php" class="filtros-cadastro">
          <input type="hidden" name="acao" value="criar">

          <div class="campo">
            <label for="nome">Nome do projeto</label>
            <input type="text" id="nome" name="nome" placeholder="Ex.: Robô Seguidor de Linha" required>
          </div>

          <div class="campo">
            <label for="descricao">Descrição</label>
            <input type="text" id="descricao" name="descricao" placeholder="Objetivo ou equipe responsável">
          </div>

          <div class="campo">
            <label for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($statusExibicao as $valor => $info): ?>
                <option value="<?= $h($valor) ?>"><?= $h($info['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="campo">
            <label for="data_inicio">Data de início</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= $h(date('Y-m-d')) ?>">
          </div>

          <div class="acoes-filtro">
            <button type="submit">Salvar projeto</button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <section class="painel painel-filtro">
      <div class="titulo-painel">
        <h2>Consultar projetos</h2>
        <p>Filtre por nome, descrição ou status atual.</p>
      </div>

      <form method="GET" action="projetos.php" class="filtros">
        <div class="campo campo-pesquisa">
          <label for="busca">Nome ou descrição</label>
          <input type="search" id="busca" name="busca" placeholder="Ex.: Carrinho ou braço robótico" value="<?= $h($busca) ?>">
        </div>

        <div class="campo">
          <label for="status_filtro">Status</label>
          <select name="status" id="status_filtro">
            <option value="">Todos</option>
            <?php foreach ($statusExibicao as $valor => $info): ?>
              <option value="<?= $h($valor) ?>" <?= $statusFiltro === $valor ? 'selected' : '' ?>><?= $h($info['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="acoes-filtro">
          <button type="submit">Filtrar projetos</button>
          <a href="projetos.php">Limpar</a>
        </div>
      </form>
    </section>

    <section class="painel painel-tabela">
      <div class="titulo-painel titulo-lista">
        <div>
          <h2>Projetos cadastrados</h2>
          <p>Visualize o andamento e gerencie os projetos do laboratório.</p>
        </div>
        <span class="contador"><?= count($projetos) ?> projeto(s)</span>
      </div>

      <?php if (!$projetos): ?>
        <div class="mensagem-vazia">
          <h3>Nenhum projeto encontrado</h3>
          <p>Não existem projetos para os filtros selecionados.</p>
        </div>
      <?php else: ?>
        <div class="lista-movimentacoes">
          <?php foreach ($projetos as $p):
            $statusAtual = $statusExibicao[$p['status']] ?? ['nome' => $p['status'], 'classe' => 'planejamento'];
            $dataInicio = !empty($p['data_inicio']) ? date('d/m/Y', strtotime($p['data_inicio'])) : 'Sem data';
          ?>
            <article class="movimentacao-bolha">
              <div class="bolha-topo">
                <div class="bolha-componente">
                  <strong><?= $h($p['nome']) ?></strong>
                  <span class="bolha-codigo">#<?= (int) $p['id'] ?></span>
                </div>

                <div class="bolha-badges">
                  <?php if (isAdmin()): ?>
                    <form method="POST" action="projetos.php" class="form-status-inline">
                      <input type="hidden" name="acao" value="alterar_status">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <select name="status" class="status-select-native" aria-label="Alterar status de <?= $h($p['nome']) ?>" onchange="this.form.submit()">
                        <?php foreach ($statusExibicao as $valor => $info): ?>
                          <option value="<?= $h($valor) ?>" <?= $p['status'] === $valor ? 'selected' : '' ?>><?= $h($info['nome']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  <?php else: ?>
                    <span class="etiqueta <?= $h($statusAtual['classe']) ?>"><?= $h($statusAtual['nome']) ?></span>
                  <?php endif; ?>
                </div>

                <div class="bolha-meta">
                  <span class="bolha-data" title="Data de início">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <?= $h($dataInicio) ?>
                  </span>

                  <?php if (isAdmin()): ?>
                    <button type="button"
                      class="botao-excluir-bolha"
                      onclick="abrirModalExcluir(<?= (int) $p['id'] ?>, <?= json_encode($p['nome'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                      <span class="material-symbols-outlined">delete</span>
                      <span>Excluir</span>
                    </button>
                  <?php endif; ?>
                </div>
              </div>

              <p class="bolha-observacao">
                <?= $h($p['descricao'] ?: 'Sem descrição registrada.') ?>
              </p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <p class="rodape">Os projetos exibidos refletem os registros salvos no banco de dados.</p>
  </main>

  <?php if (isAdmin()): ?>
    <div id="modalExcluirProjeto" class="modal-overlay" onclick="if(event.target === this) fecharModalExcluir()">
      <div class="modal-box modal-confirm">
        <div class="confirm-icone-box vermelho">
          <span class="material-symbols-outlined">delete_forever</span>
        </div>
        <h3>Excluir projeto</h3>
        <p id="texto-confirm-excluir">Tem certeza que deseja excluir este projeto?</p>

        <form method="POST" action="projetos.php" id="form-excluir-projeto">
          <input type="hidden" name="acao" value="excluir">
          <input type="hidden" name="id" id="excluir_projeto_id">

          <div class="modal-rodape centralizado">
            <button type="button" class="btn-modal-cancelar" onclick="fecharModalExcluir()">Cancelar</button>
            <button type="submit" class="btn-modal-confirmar vermelho">Sim, excluir</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

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

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
  <script>
    flatpickr('.campo input[type="date"]', {
      locale: 'pt',
      altInput: true,
      altFormat: 'd/m/Y',
      dateFormat: 'Y-m-d',
      allowInput: false
    });

    function inicializarGlassSelect(select, opcoes) {
      select.classList.add('glass-select-native');

      const wrapper = document.createElement('div');
      wrapper.className = 'glass-select' + (opcoes.compacto ? ' glass-select-compacto' : '');

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
          trigger.querySelector('span').textContent = opt.text;
          menu.querySelectorAll('.glass-select-option').forEach((el) => el.classList.remove('selected'));
          item.classList.add('selected');
          wrapper.classList.remove('open');

          if (opcoes.submitOnChange && select.form) {
            select.form.submit();
          } else {
            select.dispatchEvent(new Event('change', { bubbles: true }));
          }
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
    }

    document.querySelectorAll('.campo select').forEach((select) => {
      inicializarGlassSelect(select, { compacto: false, submitOnChange: false });
    });

    document.querySelectorAll('select.status-select-native').forEach((select) => {
      inicializarGlassSelect(select, { compacto: true, submitOnChange: true });
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));
    });

    const btnToggle = document.getElementById('btn-toggle-projeto');
    const btnFechar = document.getElementById('btn-fechar-projeto');
    const painelProjeto = document.getElementById('painel-novo-projeto');
    const inputNomeProjeto = document.getElementById('nome');

    if (btnToggle && painelProjeto) {
      btnToggle.addEventListener('click', () => {
        painelProjeto.classList.toggle('aberto');
        if (painelProjeto.classList.contains('aberto') && inputNomeProjeto) {
          inputNomeProjeto.focus();
        }
      });
    }

    if (btnFechar && painelProjeto) {
      btnFechar.addEventListener('click', () => {
        painelProjeto.classList.remove('aberto');
      });
    }

    function abrirModalExcluir(id, nome) {
      const modal = document.getElementById('modalExcluirProjeto');
      const inputId = document.getElementById('excluir_projeto_id');
      const texto = document.getElementById('texto-confirm-excluir');
      if (!modal || !inputId || !texto) return;

      inputId.value = id;
      texto.innerHTML = `Tem certeza que deseja excluir o projeto <strong>${nome}</strong>? Essa ação não pode ser desfeita.`;
      modal.classList.add('ativo');
    }

    function fecharModalExcluir() {
      const modal = document.getElementById('modalExcluirProjeto');
      if (modal) {
        modal.classList.remove('ativo');
      }
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        fecharModalExcluir();
      }
    });
  </script>
</body>
</html>
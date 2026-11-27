<?php
// Projetos do Flux — padronizado no mesmo estilo do Histórico e com modal de cadastro.
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/auth.php';
exigirLogin();

$mensagem = '';
$tipoMensagem = '';
$abrirModalCadastro = false;

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

$formProjeto = [
  'nome'        => '',
  'descricao'   => '',
  'status'      => 'Planejamento',
  'data_inicio' => date('Y-m-d'),
];

// Ações de Admin: Criar, Atualizar Status ou Excluir Projeto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
  $acao = $_POST['acao'] ?? '';

  if ($acao === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $status = $_POST['status'] ?? 'Planejamento';
    $dataInicioRaw = trim($_POST['data_inicio'] ?? '');
    $dataInicio = $validarData($dataInicioRaw) ? $dataInicioRaw : date('Y-m-d');

    $formProjeto = [
      'nome'        => $nome,
      'descricao'   => $descricao,
      'status'      => in_array($status, $statusPermitidos, true) ? $status : 'Planejamento',
      'data_inicio' => $dataInicio,
    ];

    if ($nome === '') {
      $mensagem = 'Informe o nome do projeto.';
      $tipoMensagem = 'erro';
      $abrirModalCadastro = true;
    } elseif (!in_array($status, $statusPermitidos, true)) {
      $mensagem = 'Status inválido.';
      $tipoMensagem = 'erro';
      $abrirModalCadastro = true;
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
      try {
        $pdo->beginTransaction();
        $stmtDesvincular = $pdo->prepare('UPDATE movimentacoes SET projeto_id = NULL WHERE projeto_id = ?');
        $stmtDesvincular->execute([$idExcluir]);
        delete($pdo, 'projetos', 'id = ?', [$idExcluir]);
        $pdo->commit();
        header('Location: projetos.php?msg=excluido');
        exit;
      } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        error_log('Erro ao excluir projeto no Flux: ' . $erro->getMessage());
        $mensagem = 'Não foi possível excluir o projeto. Verifique se há registros vinculados.';
        $tipoMensagem = 'erro';
      }
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
$todosProjetos = ($where === null) ? $projetos : readAll($pdo, 'projetos');
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
          <button type="button" class="botao-novo" onclick="abrirModal('modalCadastrarProjeto')">
            <span class="material-symbols-outlined">add</span>
            <span>Novo projeto</span>
          </button>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($mensagem !== '' && !$abrirModalCadastro): ?>
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
                      data-id="<?= (int) $p['id'] ?>"
                      data-nome="<?= $h($p['nome']) ?>"
                      onclick="abrirModalExcluir(this)">
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
    <div id="modalCadastrarProjeto" class="modal-overlay <?= $abrirModalCadastro ? 'ativo' : '' ?>" onclick="if(event.target === this) fecharModal('modalCadastrarProjeto')">
      <div class="modal-box modal-grande">
        <div class="modal-header">
          <h3><span class="material-symbols-outlined">add_circle</span> Cadastrar Projeto</h3>
          <button type="button" class="modal-fechar" onclick="fecharModal('modalCadastrarProjeto')" aria-label="Fechar">×</button>
        </div>

        <?php if ($abrirModalCadastro && $mensagem !== ''): ?>
          <div class="modal-alerta-erro" role="alert">
            <span class="material-symbols-outlined">error</span>
            <span><?= $h($mensagem) ?></span>
          </div>
        <?php endif; ?>

        <form id="formCadastrarProjeto" method="POST" action="projetos.php"
              onsubmit="return pedirConfirmacao(event, 'Confirmar Cadastro', 'Deseja cadastrar este projeto no laboratório?', 'verde')">
          <input type="hidden" name="acao" value="criar">

          <div class="modal-campo">
            <label for="cad_nome">Nome do Projeto *</label>
            <input type="text" name="nome" id="cad_nome" required placeholder="Ex: Robô Seguidor de Linha" value="<?= $h($formProjeto['nome']) ?>">
          </div>

          <div class="modal-campo">
            <label for="cad_descricao">Descrição</label>
            <textarea name="descricao" id="cad_descricao" rows="3" placeholder="Objetivo, equipe responsável ou detalhes técnicos..."><?= $h($formProjeto['descricao']) ?></textarea>
          </div>

          <div class="modal-grid-2">
            <div class="modal-campo">
              <label for="cad_status">Status *</label>
              <select name="status" id="cad_status" required>
                <?php foreach ($statusExibicao as $valor => $info): ?>
                  <option value="<?= $h($valor) ?>" <?= $formProjeto['status'] === $valor ? 'selected' : '' ?>><?= $h($info['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="modal-campo">
              <label for="cad_data_inicio">Data de Início</label>
              <input type="date" name="data_inicio" id="cad_data_inicio" value="<?= $h($formProjeto['data_inicio']) ?>">
            </div>
          </div>

          <div class="modal-rodape">
            <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalCadastrarProjeto')">Cancelar</button>
            <button type="submit" class="btn-modal-confirmar verde">Salvar Projeto</button>
          </div>
        </form>
      </div>
    </div>

    <div id="modalExcluirProjeto" class="modal-overlay" onclick="if(event.target === this) fecharModal('modalExcluirProjeto')">
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
            <button type="button" class="btn-modal-cancelar" onclick="fecharModal('modalExcluirProjeto')">Cancelar</button>
            <button type="submit" class="btn-modal-confirmar vermelho">Sim, excluir</button>
          </div>
        </form>
      </div>
    </div>

    <div id="modalConfirmar" class="modal-overlay modal-overlay-topo" onclick="if(event.target === this) cancelarConfirmacao()">
      <div class="modal-box modal-confirm">
        <div id="confirmIconeBox" class="confirm-icone-box verde">
          <span id="confirmIcone" class="material-symbols-outlined">check_circle</span>
        </div>
        <h3 id="confirmTitulo">Confirmar ação</h3>
        <p id="confirmMensagem">Deseja continuar?</p>

        <div class="modal-rodape centralizado">
          <button type="button" class="btn-modal-cancelar" onclick="cancelarConfirmacao()">Cancelar</button>
          <button type="button" id="btnConfirmarAcao" class="btn-modal-confirmar verde" onclick="confirmarAcao()">Confirmar</button>
        </div>
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
    const flatpickrInstancias = flatpickr('.campo input[type="date"], .modal-campo input[type="date"]', {
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

    document.querySelectorAll('.campo select, .modal-campo select').forEach((select) => {
      inicializarGlassSelect(select, { compacto: false, submitOnChange: false });
    });

    document.querySelectorAll('select.status-select-native').forEach((select) => {
      inicializarGlassSelect(select, { compacto: true, submitOnChange: true });
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));
    });

    let formPendenteConfirmacao = null;

    function abrirModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;
      modal.classList.add('ativo');

      if (id === 'modalCadastrarProjeto') {
        const inputNome = document.getElementById('cad_nome');
        if (inputNome) {
          setTimeout(() => inputNome.focus(), 40);
        }
      }
    }

    function fecharModal(id) {
      const modal = document.getElementById(id);
      if (!modal) return;

      modal.classList.remove('ativo');
      modal.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));

      const instancias = Array.isArray(flatpickrInstancias) ? flatpickrInstancias : [flatpickrInstancias];
      instancias.forEach((fp) => {
        if (fp && typeof fp.close === 'function') fp.close();
      });
    }

    function pedirConfirmacao(event, titulo, mensagem, cor = 'azul') {
      event.preventDefault();
      formPendenteConfirmacao = event.target;

      const coresPermitidas = ['azul', 'verde', 'vermelho'];
      const corFinal = coresPermitidas.includes(cor) ? cor : 'azul';
      const icones = {
        azul: 'help',
        verde: 'check_circle',
        vermelho: 'delete_forever',
      };

      const iconeBox = document.getElementById('confirmIconeBox');
      const icone = document.getElementById('confirmIcone');
      const tituloEl = document.getElementById('confirmTitulo');
      const mensagemEl = document.getElementById('confirmMensagem');
      const btnConfirmar = document.getElementById('btnConfirmarAcao');

      if (!iconeBox || !icone || !tituloEl || !mensagemEl || !btnConfirmar) {
        return true;
      }

      iconeBox.className = `confirm-icone-box ${corFinal}`;
      icone.textContent = icones[corFinal];
      tituloEl.textContent = titulo;
      mensagemEl.textContent = mensagem;
      btnConfirmar.className = `btn-modal-confirmar ${corFinal}`;

      abrirModal('modalConfirmar');
      return false;
    }

    function confirmarAcao() {
      const form = formPendenteConfirmacao;
      formPendenteConfirmacao = null;
      fecharModal('modalConfirmar');
      if (form) {
        form.submit();
      }
    }

    function cancelarConfirmacao() {
      formPendenteConfirmacao = null;
      fecharModal('modalConfirmar');
    }

    function abrirModalExcluir(botao) {
      const inputId = document.getElementById('excluir_projeto_id');
      const texto = document.getElementById('texto-confirm-excluir');
      if (!inputId || !texto || !botao) return;

      inputId.value = botao.dataset.id || '';
      texto.textContent = 'Tem certeza que deseja excluir o projeto ';
      const nomeEl = document.createElement('strong');
      nomeEl.textContent = botao.dataset.nome || '';
      texto.appendChild(nomeEl);
      texto.appendChild(document.createTextNode('? Essa ação não pode ser desfeita.'));

      abrirModal('modalExcluirProjeto');
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modalConfirm = document.getElementById('modalConfirmar');
        if (modalConfirm && modalConfirm.classList.contains('ativo')) {
          cancelarConfirmacao();
          return;
        }
        document.querySelectorAll('.modal-overlay.ativo').forEach((m) => fecharModal(m.id));
        document.querySelectorAll('.glass-select.open').forEach((el) => el.classList.remove('open'));
      }
    });
  </script>
</body>
</html>
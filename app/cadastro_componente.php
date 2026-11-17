<?php
require 'conexao.php'; // precisa criar a variável $pdo

// Dados para preencher os <select>
$categorias   = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $pdo->query('SELECT id, nome FROM fornecedores ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$condicoes    = $pdo->query('SELECT id, nome FROM condicoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// Valores do formulário (ficam preenchidos se der erro)
$dados = [
    'nome'                  => '',
    'codigo'                => '',
    'descricao'             => '',
    'categoria_id'          => '',
    'fornecedor_id'         => '',
    'condicao_id'           => '',
    'quantidade_total'      => "",
    'quantidade_disponivel' => '',
    'estoque_minimo'        => '',
];
$erros = [];

/* ================= PROCESSA O CADASTRO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($dados as $campo => $valor) {
        $dados[$campo] = trim($_POST[$campo] ?? '');
    }

    // Se não informar o disponível, considera igual ao total
    if ($dados['quantidade_disponivel'] === '') {
        $dados['quantidade_disponivel'] = $dados['quantidade_total'];
    }

    if ($dados['nome'] === '') {
        $erros[] = 'Informe o nome do componente.';
    }
    if ($dados['categoria_id'] === '') {
        $erros[] = 'Selecione uma categoria.';
    }
    if ($dados['condicao_id'] === '') {
        $erros[] = 'Selecione a condição do componente.';
    }

    $numericos = [
        'quantidade_total'      => 'Quantidade total',
        'quantidade_disponivel' => 'Quantidade disponível',
        'estoque_minimo'        => 'Estoque mínimo',
    ];
    foreach ($numericos as $campo => $rotulo) {
        if (filter_var($dados[$campo], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $erros[] = "$rotulo deve ser um número inteiro maior ou igual a 0.";
        }
    }

    if (empty($erros) && (int) $dados['quantidade_disponivel'] > (int) $dados['quantidade_total']) {
        $erros[] = 'A quantidade disponível não pode ser maior que a quantidade total.';
    }

    // Código não pode repetir
    if ($dados['codigo'] !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM componentes WHERE codigo = ?');
        $stmt->execute([$dados['codigo']]);
        if ($stmt->fetchColumn() > 0) {
            $erros[] = 'Já existe um componente com o código ' . htmlspecialchars($dados['codigo']) . '.';
        }
    }

    // Tudo certo: grava e volta para a listagem
    if (empty($erros)) {
        $sql = "INSERT INTO componentes
                    (nome, codigo, descricao, categoria_id, fornecedor_id, condicao_id,
                     quantidade_total, quantidade_disponivel, estoque_minimo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $pdo->prepare($sql)->execute([
            $dados['nome'],
            $dados['codigo'] !== '' ? $dados['codigo'] : null,
            $dados['descricao'] !== '' ? $dados['descricao'] : null,
            (int) $dados['categoria_id'],
            $dados['fornecedor_id'] !== '' ? (int) $dados['fornecedor_id'] : null,
            (int) $dados['condicao_id'],
            (int) $dados['quantidade_total'],
            (int) $dados['quantidade_disponivel'],
            (int) $dados['estoque_minimo'],
        ]);

        // Abre a listagem já filtrando pelo componente novo
        $termo = $dados['codigo'] !== '' ? $dados['codigo'] : $dados['nome'];
        header('Location: componentes.php?busca=' . urlencode($termo));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Componente - Flux</title>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="cadastro.css">
    <link rel="stylesheet" href="componentes.css">
</head>

<body>

    <div class="container-principal container-componentes">

        <!-- COLUNA DA ESQUERDA: informações -->
        <div class="info-esquerda">
            <div class="tag-conta">
                <span class="material-symbols-outlined">add_circle</span> Cadastro Flux
            </div>

            <h1>Cadastrar componente</h1>
            <p class="subtitulo-info">Registre uma nova peça no estoque da oficina. Depois de salvar,
                você será levado para a lista de componentes.</p>

            <div class="lista-alertas">
                <div class="card-alerta-sistema ok">
                    <div class="alerta-cabecalho">
                        <span class="material-symbols-outlined icone-aviso">lightbulb</span>
                        Dicas de cadastro
                    </div>
                    <p class="alerta-corpo">
                        Use um <strong>código único</strong> (ex.: RES-10K) para facilitar a busca.
                        O <strong>estoque mínimo</strong> serve para gerar o alerta de "Estoque Baixo"
                        na listagem.
                    </p>
                </div>
            </div>
        </div>

        <!-- COLUNA DA DIREITA: formulário -->
        <form method="POST" action="adicionar_componente.php" class="form-componente">

            <div class="seletor-abas">
                <a href="adicionar_componente.php" class="aba ativa">Cadastrar</a>
                <a href="componentes.php" class="aba">Ver estoque</a>
            </div>

            <div class="cabecalho-form">
                <h2>Novo componente</h2>
                <p>Preencha os dados abaixo. Campos com * são obrigatórios.</p>
            </div>

            <?php if (!empty($erros)): ?>
                <div class="mensagem erro">
                    <span class="material-symbols-outlined">error</span>
                    <ul>
                        <?php foreach ($erros as $erro): ?>
                            <li><?= $erro ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="divisor-secao">Identificação</div>

            <label for="nome">Nome *</label>
            <div class="input-com-icone">
                <span class="material-symbols-outlined icone-input">memory</span>
                <input type="text" id="nome" name="nome" required
                    value="<?= htmlspecialchars($dados['nome']) ?>" placeholder="Ex.: Resistor 10kΩ">
            </div>

            <label for="codigo">Código / SKU</label>
            <div class="input-com-icone">
                <span class="material-symbols-outlined icone-input">qr_code</span>
                <input type="text" id="codigo" name="codigo"
                    value="<?= htmlspecialchars($dados['codigo']) ?>" placeholder="Ex.: RES-10K">
            </div>
            <p class="dica">Opcional, mas precisa ser único.</p>

            <label for="descricao">Descrição</label>
            <div class="input-com-icone area">
                <span class="material-symbols-outlined icone-input">description</span>
                <textarea id="descricao" name="descricao"
                    placeholder="Detalhes técnicos, modelo, observações..."><?= htmlspecialchars($dados['descricao']) ?></textarea>
            </div>

            <div class="divisor-secao">Classificação</div>

            <label for="categoria_id">Categoria *</label>
            <div class="input-com-icone">
                <span class="material-symbols-outlined icone-input">category</span>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $dados['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="material-symbols-outlined icone-seta">expand_more</span>
            </div>

            <div class="linha-campos">
                <div>
                    <label for="fornecedor_id">Fornecedor</label>
                    <div class="input-com-icone">
                        <span class="material-symbols-outlined icone-input">local_shipping</span>
                        <select id="fornecedor_id" name="fornecedor_id">
                            <option value="">Não informado</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $dados['fornecedor_id'] == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined icone-seta">expand_more</span>
                    </div>
                </div>

                <div>
                    <label for="condicao_id">Condição *</label>
                    <div class="input-com-icone">
                        <span class="material-symbols-outlined icone-input">verified</span>
                        <select id="condicao_id" name="condicao_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($condicoes as $co): ?>
                                <option value="<?= $co['id'] ?>" <?= $dados['condicao_id'] == $co['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($co['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined icone-seta">expand_more</span>
                    </div>
                </div>
            </div>

            <div class="divisor-secao">Estoque</div>

            <div class="linha-campos tres">
                <div>
                    <label for="quantidade_total">Total *</label>
                    <input type="number" id="quantidade_total" name="quantidade_total" min="0" required
                        value="<?= htmlspecialchars($dados['quantidade_total']) ?>" placeholder="0">
                </div>
                <div>
                    <label for="quantidade_disponivel">Disponível</label>
                    <input type="number" id="quantidade_disponivel" name="quantidade_disponivel" min="0"
                        value="<?= htmlspecialchars($dados['quantidade_disponivel']) ?>" placeholder="= total">
                </div>
                <div>
                    <label for="estoque_minimo">Mínimo *</label>
                    <input type="number" id="estoque_minimo" name="estoque_minimo" min="0" required
                        value="<?= htmlspecialchars($dados['estoque_minimo']) ?>" placeholder="0">
                </div>
            </div>
            <p class="dica">Se "Disponível" ficar vazio, será igual ao total.</p>

            <button type="submit">Cadastrar componente</button>
            <a href="componentes.php" class="aba" style="text-align:center;">Cancelar e voltar ao estoque</a>
        </form>
    </div>

    <?php include 'rodape.php'; ?>
</body>

</html>

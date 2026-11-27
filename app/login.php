<?php
session_start();

// Se caso ele ja estiver logado ele redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/init.php';

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $senhaValida = false;

        if ($usuario) {
            if (password_verify($senha, $usuario['senha'])) {
                // Senha já está com hash seguro
                $senhaValida = true;
            } elseif (empty(password_get_info($usuario['senha'])['algo']) && hash_equals($usuario['senha'], $senha)) {
                // Senha antiga em texto puro: valida e já atualiza para hash no banco
                $senhaValida = true;
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);

                $upd = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
                $upd->execute([
                    'senha' => $novoHash,
                    'id'    => $usuario['id']
                ]);
            }
        }

        if ($senhaValida) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'] ?? 'Comum';

            header('Location: dashboard.php');
            exit;
        } else {
            $erro = 'E-mail ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Conta Flux</title>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="css/index.css">
</head>

<body>

    <div class="container-principal">

        <div class="info-esquerda">
            <div class="tag-conta">
                <span class="material-symbols-outlined">account_circle</span> Conta Flux
            </div>
            
            <h1>Sua oficina de robótica organizada</h1>
            <p class="subtitulo-info">Gerencie componentes, controle empréstimos de peças e monitore o uso em projetos.</p>
            
            <div class="lista-recursos">
                <!-- Recurso 1 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Estoque e Histórico</h4>
                        <p>Controle quantidades disponíveis, registre a condição das peças e acesse o histórico de movimentações.</p>
                    </div>
                </div>
                
                <!-- Recurso 2 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">engineering</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Projetos e Empréstimos</h4>
                        <p>Acompanhe devoluções de peças e saiba exatamente quais componentes estão alocados em cada robô.</p>
                    </div>
                </div>

                <!-- Recurso 3 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">build_circle</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Kits e Catálogos</h4>
                        <p>Crie kits prontos por tipo de projeto e monte listas de componentes com compatibilidade técnica.</p>
                    </div>
                </div>
            </div>
        </div>

        <form action="" method="POST">

            <div class="seletor-abas">
                <a href="login.php" class="aba ativa">Entrar</a>
                <a href="index.php" class="aba">Criar conta</a>
            </div>

            <div class="cabecalho-form">
                <h2>Bem-vindo de volta</h2>
                <p>Entre com seu e-mail e senha.</p>
                
                <!-- Mensagem de erro -->
                <?php if (!empty($erro)): ?>
                    <p style="color: #fecdd3; background: linear-gradient(180deg, rgba(251, 113, 133, 0.26), rgba(251, 113, 133, 0.10)); padding: 10px 14px; border-radius: 14px; margin-top: 15px; font-size: 14px; border: 1px solid rgba(251, 113, 133, 0.55);">
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">error</span>
                        <?= htmlspecialchars($erro) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="campo-grupo">
                <label for="email">E-mail</label>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">mail</span>
                    <input type="email" id="email" name="email" placeholder="nome@dominio.com" value="<?= htmlspecialchars($email) ?>" required>
                </div>
            </div>

            <div class="campo-grupo">
                <div class="senha-cabecalho">
                    <label for="senha">Senha</label>
                    <a href="#" class="link-esqueci">Esqueci minha senha</a>
                </div>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">lock</span>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                    <span class="material-symbols-outlined icone-senha-olho" id="btn-olhinho">visibility</span>
                </div>
            </div>

            <button type="submit">Entrar</button>

        </form>
    </div>
    
    <footer class="rodape-avancado">
        <div class="rodape-conteudo">

            <div class="rodape-marca">
                <div class="rodape-logo">
                    <span class="material-symbols-outlined">precision_manufacturing</span>
                    <span>Flux</span>
                </div>
                <p>Organização inteligente para oficinas de robótica. Feito para equipes e makers que querem controle
                    total de suas peças e projetos, sem surpresas.</p>
                <a href="#" class="rodape-comunidade">
                    <span class="material-symbols-outlined">forum</span> Nossa Comunidade - G.Britto
                </a>
            </div>

            <div class="rodape-menus">

                <div class="rodape-coluna">
                    <h4>Produto</h4>
                    <a href="#">Recursos</a>
                    <a href="#">Catálogos</a>
                    <a href="#">Preços</a>
                </div>

                <div class="rodape-coluna">
                    <h4>Conta</h4>
                    <a href="login.php">Entrar</a>
                    <a href="index.php">Criar conta</a>
                </div>

                <div class="rodape-coluna">
                    <h4>Legal</h4>
                    <a href="#">Privacidade</a>
                    <a href="#">Termos de uso</a>
                </div>

                <div class="rodape-coluna">
                    <h4>Suporte</h4>
                    <a href="#">Falar com a gente</a>
                    <a href="#">Central de Ajuda</a>
                </div>

            </div>
        </div>
    </footer>

    <!-- Olho -->
    <script>
        const campoSenha = document.getElementById('senha');
        const btnOlhinho = document.getElementById('btn-olhinho');

        btnOlhinho.addEventListener('click', function() {
            if (campoSenha.type === 'password') {
                campoSenha.type = 'text';
                btnOlhinho.textContent = 'visibility_off';
            } else {
                campoSenha.type = 'password';
                btnOlhinho.textContent = 'visibility';
            }
        });
    </script>
</body>

</html>
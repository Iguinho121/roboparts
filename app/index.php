<?php
session_start();

// Se o utilizador já estiver logado redireciona para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mensagem = '';$tipoMensagem = '';

try {
    require_once __DIR__ . '/init.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha =$_POST['senha'] ?? '';

        if (empty($nome) || empty($email) || empty($senha)) {
            $mensagem = 'Por favor, preencha todos os campos.';$tipoMensagem = 'erro';
            
        } else {
            $stmt =$pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
            $stmt->execute(['email' =>$email]);
            
            if ($stmt->fetch()) {$mensagem = 'Este e-mail já está cadastrado. Vá para a aba "Entrar".';
                $tipoMensagem = 'erro';
                
            } else {
                $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);$sql = 'INSERT INTO usuarios (nome, email, senha, tipo_usuario) VALUES (:nome, :email, :senha, "Comum")';
                $inserir = $pdo->prepare($sql);
                
                $sucesso =$inserir->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $senhaCriptografada
                ]);
                
                if ($sucesso) {$mensagem = 'Conta criada com sucesso! Vá para a aba "Entrar" para aceder ao sistema.';
                    $tipoMensagem = 'sucesso';
                } else {
                    $mensagem = 'Erro ao criar a conta. Tente novamente.';
                    $tipoMensagem = 'erro';
                }
            }
        }
    }
} catch (Throwable $exception) {$mensagem = 'Erro no BD: ' . $exception->getMessage();$tipoMensagem = 'erro';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Flux</title>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="css/index.css">
</head>

<body>

    <div class="container-principal">

        <!-- COLUNA DA ESQUERDA -->
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

        <!-- FORMULÁRIO -->
        <form action="" method="POST">

            <div class="seletor-abas">
                <a href="login.php" class="aba">Entrar</a>
                <a href="index.php" class="aba ativa">Criar conta</a>
            </div>

            <div class="cabecalho-form">
                <h2>Crie sua conta Flux</h2>
                <p>Leva menos de um minuto.</p>
            </div>

            <?php if (!empty($mensagem)): ?>
                <div class="alerta-moderno <?= $tipoMensagem === 'sucesso' ? 'alerta-sucesso' : 'alerta-erro' ?>" style="margin-bottom: 15px; padding: 10px; border-radius: 6px; <?= $tipoMensagem === 'sucesso' ? 'background-color: #112a18; color: #4aff71; border: 1px solid #1a5a2a;' : 'background-color: #2a1111; color: #ff4a4a; border: 1px solid #5a1a1a;' ?>">
                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">
                        <?= $tipoMensagem === 'sucesso' ? 'check_circle' : 'error' ?>
                    </span>
                    <span><?= htmlspecialchars($mensagem) ?></span>
                </div>
            <?php endif; ?>

            <div class="campo-grupo">
                <label for="nome">Nome</label>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">person</span>
                    <input type="text" id="nome" name="nome" placeholder="Como podemos te chamar" required>
                </div>
            </div>

            <div class="campo-grupo">
                <label for="email">E-mail</label>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">mail</span>
                    <input type="email" id="email" name="email" placeholder="nome@dominio.com" required>
                </div>
            </div>

            <div class="campo-grupo">
                <div class="senha-cabecalho">
                    <label for="senha">Senha</label>
                </div>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">lock</span>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                    
                    <span class="material-symbols-outlined icone-senha-olho" id="btn-olhinho">visibility</span>
                </div>
                <div class="requisitos-senha">
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> 8+ caracteres</span>
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> Uma letra</span>
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> Um número</span>
                </div>
            </div>

            <button type="submit">Criar conta</button>

            <div class="termos-privacidade">
                Ao criar a conta você concorda com os <a href="#">Termos de Uso</a> e a <a href="#">Política de Privacidade</a>.
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="rodape-avancado">
        <div class="rodape-conteudo">

            <div class="rodape-marca">
                <div class="rodape-logo">
                    <span class="material-symbols-outlined">precision_manufacturing</span>
                    <span>Flux</span>
                </div>
                <p>Organização inteligente para oficinas de robótica. Feito para equipes e makers que querem controle total de suas peças e projetos, sem surpresas.</p>
                <a href="#" class="rodape-comunidade">
                    <span class="material-symbols-outlined">forum</span> Nossa comunidade
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
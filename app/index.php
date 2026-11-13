<?php

declare(strict_types=1);

$databaseStatus = 'Conectado';

try {
    require_once __DIR__ . '/init.php';
    $pdo->query('SELECT 1');
} catch (Throwable $exception) {
    $databaseStatus = 'Indisponível';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta</title>
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
            <p class="subtitulo-info">Gerencie componentes, controle empréstimos de peças e monitore o uso em projetos.
            </p>

            <div class="lista-recursos">
                <!-- Recurso 1 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">inventory_2</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Estoque e Histórico</h4>
                        <p>Controle quantidades disponíveis, registre a condição das peças e acesse o histórico de
                            movimentações.</p>
                    </div>
                </div>

                <!-- Recurso 2 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">engineering</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Projetos e Empréstimos</h4>
                        <p>Acompanhe devoluções de peças e saiba exatamente quais componentes estão alocados em cada
                            robô.</p>
                    </div>
                </div>

                <!-- Recurso 3 -->
                <div class="item-recurso">
                    <div class="icone-recurso">
                        <span class="material-symbols-outlined">build_circle</span>
                    </div>
                    <div class="texto-recurso">
                        <h4>Kits e Catálogos</h4>
                        <p>Crie kits prontos por tipo de projeto e monte listas de componentes com compatibilidade
                            técnica.</p>
                    </div>
                </div>
            </div>



        </div>

        <form action="" method="POST">

            <div class="seletor-abas">
                <a href="login.html" class="aba">Entrar</a>
                <a href="cadastro.html" class="aba ativa">Criar conta</a>
            </div>

            <div class="cabecalho-form">
                <h2>Crie sua conta Flux</h2>
                <p>Leva menos de um minuto.</p>
            </div>

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
                    <!-- <a href="#" class="link-esqueci">Esqueci minha senha</a> Apenas para o login e n para o Criar conta -->
                </div>
                <div class="input-com-icone">
                    <span class="material-symbols-outlined icone-input">lock</span>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                    <span class="material-symbols-outlined icone-senha-olho">visibility</span>
                </div>
                <div class="requisitos-senha">
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> 8+
                        caracteres</span>
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> Uma
                        letra</span>
                    <span class="requisito"><span class="material-symbols-outlined icone-check">check</span> Um
                        número</span>
                </div>
            </div>

            <button type="submit">Criar conta</button>

            <div class="termos-privacidade">
                Ao criar a conta você concorda com os <a href="#">Termos de Uso</a> e a <a href="#">Política de
                    Privacidade</a>.
            </div>
        </form>
    </div>
    <!-- Footer -->
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
                    <a href="login.html">Entrar</a>
                    <a href="cadastro.html">Criar conta</a>
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
</body>

</html>

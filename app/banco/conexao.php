<?php
/*
    CONEXAO COM O BANCO DE DADOS
    Altere os dados abaixo conforme a configuracao do seu MySQL.
*/

$servidor = "localhost";
$banco = "flux";
$usuario = "root";
$senha = "";

try {
    // Cria a conexao com o MySQL usando PDO.
    $pdo = new PDO(
        "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    // Faz o PDO mostrar erros como excecoes.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Faz as consultas retornarem os resultados como arrays associativos.
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $erro) {
    // Interrompe a pagina caso nao consiga conectar ao banco.
    die("Nao foi possivel conectar ao banco de dados.");
}
?>

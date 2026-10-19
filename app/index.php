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
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projeto Integrador</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 4rem auto; padding: 0 1rem; line-height: 1.5; }
        .status { padding: 1rem; border-radius: .5rem; background: #eef6ee; }
        code { background: #eee; padding: .15rem .3rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <h1>Projeto Integrador: RoboParts</h1>
    <p>O ambiente PHP está funcionando.</p>
    <div class="status">
        Banco de dados: <strong><?= htmlspecialchars($databaseStatus, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <p>Comece a aplicação editando <code>app/index.php</code>.</p>
</body>
</html>

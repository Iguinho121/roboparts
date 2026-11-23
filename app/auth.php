<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Bloqueia quem não estiver logado
function exigirLogin(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Retorna true se o usuário logado for Admin
function isAdmin(): bool
{
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'Admin';
}

// Bloqueia páginas exclusivas de Administrador
function exigirAdmin(): void
{
    exigirLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}
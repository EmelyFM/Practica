<?php defined('APP') || exit; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <meta name="csrf-token" content="<?= e($_SESSION['csrf'] ?? '') ?>">
    <title><?= e(($titulo ?? 'Acceso') . ' · ' . APP_NOMBRE_CORTO) ?></title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(base_url()) ?>/css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e(base_url()) ?>/css/lib/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(base_url()) ?>/css/lib/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= e(base_url()) ?>/css/estilo.css">
</head>

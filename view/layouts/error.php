<?php
defined('APP') || exit;
$tituloError = $titulo;
require __DIR__ . '/head.php';
?>
<body class="pagina-acceso">
    <div class="tarjeta-acceso text-center">
        <i class="bi bi-exclamation-triangle fs-1 text-warning" aria-hidden="true"></i>
        <h1 class="h4 mt-2"><?= e($tituloError) ?></h1>
        <p class="text-secondary"><?= e($mensaje) ?></p>
        <a class="btn btn-primary" href="<?= e(base_url()) ?>/index.php">Volver al inicio</a>
    </div>
</body>
</html>

<?php
defined('APP') || exit;
require __DIR__ . '/head.php';
?>
<body data-rol="<?= e($usuario['nombre_rol']) ?>"
      data-pagina="<?= e($clave) ?>"
      data-id-empleado="<?= e($usuario['estado_empleado'] === 'Activo' ? $usuario['id_empleado'] : '') ?>"
      data-nombre-empleado="<?= e($usuario['nombre_empleado']) ?>"
      data-id-usuario="<?= (int) $usuario['id_usuario'] ?>">
<div class="app-shell">

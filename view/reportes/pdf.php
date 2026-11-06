<?php defined('APP') || exit; /** @var array $reporte */ ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= e($reporte['titulo']) ?></title>
<style>
    @page { margin: 28px 30px 38px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #1d2733; }
    .sistema { font-size: 8px; color: #5b6b7d; text-transform: uppercase; letter-spacing: .5px; }
    h1 { font-size: 15px; margin: 2px 0 8px; color: #14233a; }
    .datos { width: 100%; border-top: 2px solid #14233a; border-bottom: 1px solid #d6dce4; padding: 5px 0; margin-bottom: 10px; }
    .datos td { padding: 1px 12px 1px 0; vertical-align: top; }
    .datos .k { color: #5b6b7d; }
    table.reg { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.reg th { background: #14233a; color: #fff; text-align: left; font-weight: bold; padding: 4px; }
    table.reg td { border-bottom: 1px solid #e1e6ec; padding: 4px; vertical-align: top; word-wrap: break-word; }
    table.reg tr { page-break-inside: avoid; }
    .vacio { text-align: center; padding: 22px; color: #5b6b7d; border: 1px dashed #c6ced8; margin-bottom: 10px; }
    h2 { font-size: 10px; margin: 14px 0 5px; color: #14233a; }
    table.res { border-collapse: collapse; }
    table.res td { border: 1px solid #d6dce4; padding: 4px 8px; }
    table.res .n { font-weight: bold; }
</style>
</head>
<body>
    <div class="sistema"><?= e($reporte['sistema']) ?></div>
    <h1><?= e($reporte['titulo']) ?></h1>
    <table class="datos">
        <tr>
            <td><span class="k">Fecha de generación:</span> <?= e($reporte['generado']) ?></td>
            <td><span class="k">Generado por:</span> <?= e($reporte['usuario']) ?></td>
            <?php if ($reporte['periodo']): ?>
                <td><span class="k">Periodo consultado:</span> <?= e($reporte['periodo']) ?></td>
            <?php endif; ?>
        </tr>
        <?php if (!empty($reporte['encabezado'])): ?>
            <tr>
                <td colspan="3">
                    <?php foreach ($reporte['encabezado'] as $clave => $valor): ?>
                        <span class="k"><?= e($clave) ?>:</span> <?= e($valor) ?> &nbsp;&nbsp;
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if (!$reporte['filas']): ?>
        <div class="vacio">No hay registros que coincidan con los filtros seleccionados.</div>
    <?php else: ?>
        <table class="reg">
            <thead>
                <tr>
                    <?php foreach ($reporte['columnas'] as $i => $columna): ?>
                        <th style="width: <?= (int) $reporte['anchos'][$i] ?>%"><?= e($columna) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte['filas'] as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $celda): ?>
                            <td><?= nl2br(e($celda)) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Totales y resumen</h2>
    <table class="res">
        <tr>
            <?php foreach ($reporte['resumen'] as $clave => $valor): ?>
                <td><?= e($clave) ?>: <span class="n"><?= e($valor) ?></span></td>
            <?php endforeach; ?>
        </tr>
    </table>
</body>
</html>

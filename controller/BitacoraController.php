<?php
defined('APP') || exit;

class BitacoraController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requerirAdmin();
    }

    public function listar(): void
    {
        $v = new Validador($_GET);
        $desde = $v->fecha('desde', 'La fecha inicial', false);
        $hasta = $v->fecha('hasta', 'La fecha final', false);
        $modulo = $v->texto('modulo', 'El módulo', 80, false);
        if ($desde !== null && $hasta !== null && $hasta < $desde) {
            $v->error('hasta', 'La fecha final no puede ser anterior a la inicial.');
        }
        $v->verificar();
        $modelo = new BitacoraModel();
        $this->responder('Bitácora obtenida.', [
            'registros' => $modelo->listar($desde, $hasta, $modulo),
            'modulos' => $modelo->modulos(),
        ]);
    }
}

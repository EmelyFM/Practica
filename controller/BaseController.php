<?php
defined('APP') || exit;

/**
 * Base de los controladores. El constructor exige sesión y token CSRF, de modo
 * que ninguna acción nueva queda desprotegida por olvido.
 */
abstract class BaseController
{
    /** Los controladores públicos (solo autenticación) lo cambian a false. */
    protected bool $requiereSesion = true;
    protected ?array $usuario = null;
    private ?array $cuerpo = null;

    public function __construct()
    {
        $this->usuario = usuario_actual();
        if ($this->requiereSesion && !$this->usuario) {
            throw new AppException('Tu sesión no está activa. Inicia sesión para continuar.', 401);
        }
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            $this->verificarCsrf();
        }
    }

    private function verificarCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || $token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
            throw new AppException('La solicitud no pudo verificarse. Recarga la página e inténtalo de nuevo.', 403);
        }
    }

    protected function esAdmin(): bool
    {
        return ($this->usuario['nombre_rol'] ?? '') === ROL_ADMIN;
    }

    protected function requerirAdmin(): void
    {
        if (!$this->esAdmin()) {
            throw new AppException('No tienes permiso para realizar esta acción.', 403);
        }
    }

    protected function idUsuario(): int
    {
        return (int) $this->usuario['id_usuario'];
    }

    /** Cuerpo JSON de la petición. */
    protected function entrada(): array
    {
        if ($this->cuerpo === null) {
            $crudo = file_get_contents('php://input');
            $json = $crudo === '' || $crudo === false ? [] : json_decode($crudo, true);
            if (!is_array($json)) {
                throw new AppException('El cuerpo de la solicitud no es JSON válido.', 400);
            }
            $this->cuerpo = $json;
        }
        return $this->cuerpo;
    }

    protected function responder(string $mensaje, $datos = null, int $http = 200): void
    {
        if (ob_get_level()) {
            ob_clean();
        }
        http_response_code($http);
        $cuerpo = ['success' => true, 'message' => $mensaje];
        if ($datos !== null) {
            $cuerpo['data'] = $datos;
        }
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }

    protected function noEncontrado(string $mensaje): AppException
    {
        return new AppException($mensaje, 404);
    }
}

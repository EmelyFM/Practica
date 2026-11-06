<?php
defined('APP') || exit;

/**
 * Validación de entrada en el servidor. Cada método devuelve el valor limpio y
 * acumula los errores por campo; verificar() responde 422 si hubo alguno.
 * Los límites de longitud coinciden con las columnas reales de la base de datos.
 */
class Validador
{
    private array $datos;
    private array $errores = [];

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function error(string $campo, string $mensaje): void
    {
        $this->errores[$campo] ??= $mensaje;
    }

    public function tieneError(string $campo): bool
    {
        return isset($this->errores[$campo]);
    }

    public function verificar(): void
    {
        if ($this->errores) {
            throw new AppException('Revisa los campos marcados.', 422, $this->errores);
        }
    }

    /** Valor recortado; las cadenas vacías y los valores no escalares no cuentan como dato. */
    private function bruto(string $campo): ?string
    {
        $valor = $this->datos[$campo] ?? null;
        if ($valor === null || is_bool($valor)) {
            return null;
        }
        if (is_array($valor)) {
            $this->error($campo, 'El valor enviado no es válido.');
            return null;
        }
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }

    private function obligatorio(string $campo, string $etiqueta, bool $requerido, ?string $valor): bool
    {
        if ($valor === null) {
            if ($requerido) {
                $this->error($campo, "$etiqueta es obligatorio.");
            }
            return false;
        }
        return true;
    }

    public function texto(string $campo, string $etiqueta, int $max, bool $requerido = true): ?string
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        if (!mb_check_encoding($v, 'UTF-8') || preg_match('/[\x{10000}-\x{10FFFF}]/u', $v)) {
            // Las tablas usan el juego utf8 de MySQL 5.5, que no admite emojis.
            $this->error($campo, "$etiqueta contiene caracteres no permitidos.");
            return null;
        }
        if (mb_strlen($v) > $max) {
            $this->error($campo, "$etiqueta no puede exceder $max caracteres.");
            return null;
        }
        return $v;
    }

    public function nombrePersona(string $campo, string $etiqueta, int $max, bool $requerido = true): ?string
    {
        $v = $this->texto($campo, $etiqueta, $max, $requerido);
        if ($v !== null && !preg_match("/^\p{L}[\p{L}\s'.-]*$/u", $v)) {
            $this->error($campo, "$etiqueta solo puede contener letras.");
            return null;
        }
        return $v;
    }

    public function nombreUsuario(string $campo = 'nombre_usuario'): ?string
    {
        $v = $this->texto($campo, 'El nombre de usuario', 50);
        if ($v !== null && !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $v)) {
            $this->error($campo, 'Usa de 3 a 50 letras, números, punto, guion o guion bajo, sin espacios.');
            return null;
        }
        return $v;
    }

    public function correo(string $campo = 'correo'): ?string
    {
        $v = $this->texto($campo, 'El correo electrónico', 100);
        if ($v !== null && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->error($campo, 'Escribe un correo electrónico válido.');
            return null;
        }
        return $v;
    }

    public function telefono(string $campo = 'telefono', bool $requerido = false): ?string
    {
        $v = $this->texto($campo, 'El teléfono', 15, $requerido);
        if ($v !== null && (!preg_match('/^\+?[0-9 ()-]+$/', $v) || strlen(preg_replace('/\D/', '', $v)) < 7)) {
            $this->error($campo, 'Escribe un teléfono válido: al menos 7 dígitos, con +, espacios, guiones o paréntesis.');
            return null;
        }
        return $v;
    }

    /** Contraseña segura: 8 a 72 bytes (límite de bcrypt), mayúscula, minúscula, número y símbolo. */
    public function contrasena(string $campo = 'contrasena'): ?string
    {
        $v = $this->datos[$campo] ?? null;
        if (!is_string($v) || $v === '') {
            $this->error($campo, 'La contraseña es obligatoria.');
            return null;
        }
        if (strlen($v) < 8 || strlen($v) > 72 || !preg_match('/[A-Z]/', $v) || !preg_match('/[a-z]/', $v)
            || !preg_match('/\d/', $v) || !preg_match('/[^A-Za-z0-9]/', $v)) {
            $this->error($campo, 'Debe tener de 8 a 72 caracteres, con mayúscula, minúscula, número y símbolo.');
            return null;
        }
        return $v;
    }

    public function entero(string $campo, string $etiqueta, int $min, int $max, bool $requerido = true): ?int
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        if (!preg_match('/^-?\d{1,10}$/', $v) || (int) $v < $min || (int) $v > $max) {
            $this->error($campo, $min === 0 && (int) $v < 0
                ? "$etiqueta no puede ser negativo."
                : "$etiqueta debe ser un número entero entre $min y $max.");
            return null;
        }
        return (int) $v;
    }

    /** Identificador de otro registro. */
    public function id(string $campo, string $etiqueta, bool $requerido = true): ?int
    {
        return $this->entero($campo, $etiqueta, 1, 2147483647, $requerido);
    }

    /** DECIMAL(10,2) no negativo; se devuelve como cadena para conservar la precisión. */
    public function decimal(string $campo, string $etiqueta, bool $requerido = false): ?string
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        if (preg_match('/^-\d/', $v)) {
            $this->error($campo, "$etiqueta no puede ser negativo.");
            return null;
        }
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) {
            $this->error($campo, "$etiqueta debe ser un número válido, con hasta 2 decimales.");
            return null;
        }
        return $v;
    }

    public function opcion(string $campo, string $etiqueta, array $opciones, bool $requerido = true): ?string
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        if (!in_array($v, $opciones, true)) {
            $this->error($campo, "$etiqueta no es válido.");
            return null;
        }
        return $v;
    }

    /** Fecha y hora; acepta el formato de datetime-local. Devuelve 'Y-m-d H:i:s'. */
    public function fechaHora(string $campo, string $etiqueta, bool $requerido = true): ?string
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $formato) {
            $f = DateTime::createFromFormat('!' . $formato, $v);
            if ($f && $f->format($formato) === $v && (int) $f->format('Y') >= 2000 && (int) $f->format('Y') <= 2100) {
                return $f->format('Y-m-d H:i:s');
            }
        }
        $this->error($campo, "$etiqueta no es una fecha y hora válida.");
        return null;
    }

    public function fecha(string $campo, string $etiqueta, bool $requerido = true): ?string
    {
        $v = $this->bruto($campo);
        if (!$this->obligatorio($campo, $etiqueta, $requerido, $v)) {
            return null;
        }
        $f = DateTime::createFromFormat('!Y-m-d', $v);
        if (!$f || $f->format('Y-m-d') !== $v || (int) $f->format('Y') < 1950 || (int) $f->format('Y') > 2100) {
            $this->error($campo, "$etiqueta no es una fecha válida.");
            return null;
        }
        return $v;
    }
}

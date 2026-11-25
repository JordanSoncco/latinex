<?php

declare(strict_types=1);

namespace Latinex\Infraestructura;

use PDO;
use PDOException;

/**
 * Servicio de autenticación basado en sesiones seguras PHP, Argon2id/Bcrypt y tokens CSRF.
 */
class ServicioAutenticacion
{
    private PDO $pdo;
    private RepositorioDocumentos $repositorioDocs;

    public function __construct(PDO $pdo, ?RepositorioDocumentos $repositorioDocs = null)
    {
        $this->pdo             = $pdo;
        $this->repositorioDocs = $repositorioDocs ?? new RepositorioDocumentos($pdo);
    }

    public function iniciarContextoSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 86400 * 7,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public function obtenerCsrfToken(): string
    {
        $this->iniciarContextoSesion();
        return (string) ($_SESSION['csrf_token'] ?? '');
    }

    public function verificarCsrf(?string $token): bool
    {
        $this->iniciarContextoSesion();
        $esperado = (string) ($_SESSION['csrf_token'] ?? '');
        if ($esperado === '' || $token === null || $token === '') {
            return false;
        }
        return hash_equals($esperado, $token);
    }

    public function usuarioActual(): ?array
    {
        $this->iniciarContextoSesion();
        $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, correo, creado_en FROM usuarios WHERE id = :id'
        );
        $stmt->execute([':id' => $usuarioId]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            unset($_SESSION['usuario_id']);
            return null;
        }

        $usuario['id'] = (int) $usuario['id'];
        return $usuario;
    }

    public function registrar(string $nombre, string $correo, string $password): array
    {
        $this->iniciarContextoSesion();

        $nombreLimpio = trim($nombre);
        $correoLimpio = mb_strtolower(trim($correo));

        if (mb_strlen($nombreLimpio) < 2 || mb_strlen($nombreLimpio) > 120) {
            throw new ExcepcionDominio(
                'El nombre debe tener entre 2 y 120 caracteres.',
                422,
                'VALIDACION_NOMBRE'
            );
        }

        if ($correoLimpio === '' || strlen($correoLimpio) > 180 || !filter_var($correoLimpio, FILTER_VALIDATE_EMAIL)) {
            throw new ExcepcionDominio(
                'Ingresa un correo electrónico válido.',
                422,
                'VALIDACION_CORREO'
            );
        }

        if (strlen($password) < 8 || strlen($password) > 128) {
            throw new ExcepcionDominio(
                'La contraseña debe tener al menos 8 caracteres.',
                422,
                'VALIDACION_PASSWORD'
            );
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);

        $this->pdo->beginTransaction();
        try {
            $dup = $this->pdo->prepare('SELECT id FROM usuarios WHERE correo = :correo');
            $dup->execute([':correo' => $correoLimpio]);
            if ($dup->fetchColumn()) {
                throw new ExcepcionDominio(
                    'Este correo ya está registrado. Inicia sesión para acceder a tus documentos.',
                    409,
                    'CORREO_DUPLICADO'
                );
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password_hash)
                 VALUES (:nombre, :correo, :hash)
                 RETURNING id, nombre, correo, creado_en'
            );
            $insert->execute([
                ':nombre' => $nombreLimpio,
                ':correo' => $correoLimpio,
                ':hash'   => $hash,
            ]);

            $usuario       = $insert->fetch();
            $usuario['id'] = (int) $usuario['id'];

            $docInicial = $this->repositorioDocs->crearDocumento(
                $usuario['id'],
                'Mi Primer Documento',
                'bienvenida.ltx',
                RepositorioDocumentos::contenidoInicialBienvenida($usuario['nombre']),
                true
            );

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string) $e->getCode() === '23505') {
                throw new ExcepcionDominio(
                    'Este correo ya está registrado. Inicia sesión para acceder a tus documentos.',
                    409,
                    'CORREO_DUPLICADO',
                    $e
                );
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return [
            'usuario'          => $usuario,
            'documentoInicial' => $docInicial,
        ];
    }

    public function iniciarSesion(string $correo, string $password): array
    {
        $this->iniciarContextoSesion();

        $correoLimpio = mb_strtolower(trim($correo));
        if ($correoLimpio === '' || $password === '') {
            throw new ExcepcionDominio(
                'Debes ingresar correo y contraseña.',
                422,
                'VALIDACION_CREDENCIALES'
            );
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, correo, password_hash, creado_en FROM usuarios WHERE correo = :correo'
        );
        $stmt->execute([':correo' => $correoLimpio]);
        $fila = $stmt->fetch();

        if (!$fila || !password_verify($password, (string) $fila['password_hash'])) {
            throw new ExcepcionDominio(
                'Correo o contraseña incorrectos.',
                401,
                'CREDENCIALES_INVALIDAS'
            );
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        if (password_needs_rehash((string) $fila['password_hash'], $algo)) {
            $nuevoHash = password_hash($password, $algo);
            $upd       = $this->pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
            $upd->execute([':hash' => $nuevoHash, ':id' => (int) $fila['id']]);
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['usuario_id'] = (int) $fila['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return [
            'id'        => (int) $fila['id'],
            'nombre'    => $fila['nombre'],
            'correo'    => $fila['correo'],
            'creado_en' => $fila['creado_en'],
        ];
    }

    public function cerrarSesion(): void
    {
        $this->iniciarContextoSesion();
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?: '/',
                    $params['domain'] ?: '',
                    (bool) $params['secure'],
                    (bool) $params['httponly']
                );
            }
            session_destroy();
        }
    }
}

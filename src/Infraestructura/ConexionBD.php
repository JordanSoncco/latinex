<?php

declare(strict_types=1);

namespace Latinex\Infraestructura;

use PDO;

/**
 * Proveedor centralizado de conexión PDO hacia PostgreSQL 17.
 */
class ConexionBD
{
    private static ?PDO $instancia = null;

    private static function leerEnv(string $clave, string $porDefecto = ''): string
    {
        $valor = $_ENV[$clave] ?? $_SERVER[$clave] ?? getenv($clave);
        if ($valor === false || $valor === null) {
            return $porDefecto;
        }
        return trim((string) $valor);
    }

    public static function estaConfigurada(): bool
    {
        return self::leerEnv('DB_HOST') !== ''
            && self::leerEnv('DB_NAME') !== ''
            && self::leerEnv('DB_USER') !== '';
    }

    public static function obtener(): PDO
    {
        if (self::$instancia instanceof PDO) {
            return self::$instancia;
        }

        $host = self::leerEnv('DB_HOST', 'db');
        $port = self::leerEnv('DB_PORT', '5432');
        $name = self::leerEnv('DB_NAME', 'latinex');
        $user = self::leerEnv('DB_USER', 'latinex');
        $pass = self::leerEnv('DB_PASS', '');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        self::$instancia = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instancia;
    }

    public static function reiniciar(): void
    {
        self::$instancia = null;
    }
}

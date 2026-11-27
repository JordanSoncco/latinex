<?php

declare(strict_types=1);

namespace Latinex\Infraestructura;

/**
 * Utilidades HTTP compartidas para endpoints REST JSON (CORS controlado, parsing, errores y CSRF).
 */
class ControladorHttp
{
    public static function esOrigenPermitido(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && ($origin === "http://{$host}" || $origin === "https://{$host}")) {
            return true;
        }

        $listaEnv = trim((string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? $_SERVER['CORS_ALLOWED_ORIGINS'] ?? getenv('CORS_ALLOWED_ORIGINS') ?: ''));
        if ($listaEnv !== '') {
            $permitidos = array_map('trim', explode(',', $listaEnv));
            if (in_array($origin, $permitidos, true)) {
                return true;
            }
        }

        return false;
    }

    public static function aplicarCorsControlado(string $metodosPermitidos = 'GET, POST, PUT, DELETE, OPTIONS'): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '' && self::esOrigenPermitido($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        header("Access-Control-Allow-Methods: {$metodosPermitidos}");
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    }

    public static function configurarCabecerasJson(string $metodosPermitidos = 'GET, POST, PUT, DELETE, OPTIONS'): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        self::aplicarCorsControlado($metodosPermitidos);

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    public static function leerPayload(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $raw         = file_get_contents('php://input');

        if ($raw !== false && trim($raw) !== '' && stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new ExcepcionDominio('El cuerpo JSON enviado no es válido.', 400, 'JSON_INVALIDO');
            }
            return $decoded;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            parse_str($raw, $parsed);
            if (is_array($parsed) && !empty($parsed)) {
                return $parsed;
            }
        }

        return [];
    }

    public static function obtenerTokenCsrfEntrante(array $payload): ?string
    {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }
        $bodyToken = $payload['csrfToken'] ?? null;
        return is_string($bodyToken) && trim($bodyToken) !== '' ? trim($bodyToken) : null;
    }

    public static function exigirCsrf(ServicioAutenticacion $auth, array $payload): void
    {
        $token = self::obtenerTokenCsrfEntrante($payload);
        if (!$auth->verificarCsrf($token)) {
            throw new ExcepcionDominio(
                'Token de seguridad CSRF ausente o expirado. Recarga la sesión e inténtalo de nuevo.',
                403,
                'CSRF_INVALIDO'
            );
        }
    }

    public static function responderJson(int $statusCode, array $data): never
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }

    public static function responderError(\Throwable $e): never
    {
        if ($e instanceof ExcepcionDominio) {
            self::responderJson($e->getHttpStatus(), [
                'ok'      => false,
                'code'    => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
        }

        error_log('[LATINEX HTTP ERROR] ' . $e->getMessage());
        self::responderJson(500, [
            'ok'      => false,
            'code'    => 'ERROR_INTERNO',
            'message' => 'Ocurrió un error interno en el servidor.',
        ]);
    }
}

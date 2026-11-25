<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Infraestructura/ExcepcionDominio.php';
require_once dirname(__DIR__) . '/src/Infraestructura/ConexionBD.php';
require_once dirname(__DIR__) . '/src/Infraestructura/RepositorioDocumentos.php';
require_once dirname(__DIR__) . '/src/Infraestructura/ServicioAutenticacion.php';
require_once dirname(__DIR__) . '/src/Infraestructura/ControladorHttp.php';

use Latinex\Infraestructura\ConexionBD;
use Latinex\Infraestructura\ControladorHttp;
use Latinex\Infraestructura\ExcepcionDominio;
use Latinex\Infraestructura\RepositorioDocumentos;
use Latinex\Infraestructura\ServicioAutenticacion;

ControladorHttp::configurarCabecerasJson('GET, POST, OPTIONS');

try {
    $pdo      = ConexionBD::obtener();
    $repoDocs = new RepositorioDocumentos($pdo);
    $auth     = new ServicioAutenticacion($pdo, $repoDocs);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $payload = $method === 'POST' ? ControladorHttp::leerPayload() : [];
    $action = (string) ($_GET['action'] ?? ($payload['action'] ?? ($method === 'GET' ? 'me' : '')));

    $construirLimites = static function (?array $usuario) use ($repoDocs): array {
        $usados = $usuario !== null ? $repoDocs->contarPorUsuario((int) $usuario['id']) : 0;
        return [
            'maxDocumentos'           => RepositorioDocumentos::MAX_DOCUMENTOS_POR_USUARIO,
            'documentosUsados'        => $usados,
            'maxBytesDocumento'       => RepositorioDocumentos::MAX_BYTES_DOCUMENTO,
            'maxImagenesPorDocumento' => RepositorioDocumentos::MAX_IMAGENES_POR_DOCUMENTO,
            'maxBytesImagen'          => RepositorioDocumentos::MAX_BYTES_IMAGEN,
        ];
    };

    if ($method === 'GET' && $action === 'me') {
        $usuario = $auth->usuarioActual();
        ControladorHttp::responderJson(200, [
            'ok'          => true,
            'autenticado' => $usuario !== null,
            'usuario'     => $usuario,
            'csrfToken'   => $auth->obtenerCsrfToken(),
            'limites'     => $construirLimites($usuario),
        ]);
    }

    if ($method === 'POST' && $action === 'register') {
        $resultado = $auth->registrar(
            (string) ($payload['nombre'] ?? ''),
            (string) ($payload['correo'] ?? ''),
            (string) ($payload['password'] ?? '')
        );

        $usuario = $resultado['usuario'];
        ControladorHttp::responderJson(201, [
            'ok'               => true,
            'autenticado'      => true,
            'message'          => 'Registro completado. Se creó tu documento bienvenida.ltx.',
            'usuario'          => $usuario,
            'documentoInicial' => $resultado['documentoInicial'],
            'csrfToken'        => $auth->obtenerCsrfToken(),
            'limites'          => $construirLimites($usuario),
        ]);
    }

    if ($method === 'POST' && $action === 'login') {
        $usuario = $auth->iniciarSesion(
            (string) ($payload['correo'] ?? ''),
            (string) ($payload['password'] ?? '')
        );

        ControladorHttp::responderJson(200, [
            'ok'          => true,
            'autenticado' => true,
            'message'     => 'Sesión iniciada correctamente.',
            'usuario'     => $usuario,
            'csrfToken'   => $auth->obtenerCsrfToken(),
            'limites'     => $construirLimites($usuario),
        ]);
    }

    if ($method === 'POST' && $action === 'logout') {
        if ($auth->usuarioActual() !== null) {
            ControladorHttp::exigirCsrf($auth, $payload);
        }
        $auth->cerrarSesion();
        ControladorHttp::responderJson(200, [
            'ok'          => true,
            'autenticado' => false,
            'message'     => 'Sesión cerrada.',
            'usuario'     => null,
            'csrfToken'   => $auth->obtenerCsrfToken(),
            'limites'     => $construirLimites(null),
        ]);
    }

    throw new ExcepcionDominio('Acción de autenticación no válida o método no permitido.', 400, 'ACCION_INVALIDA');
} catch (\Throwable $e) {
    ControladorHttp::responderError($e);
}

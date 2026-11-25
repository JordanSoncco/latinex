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

ControladorHttp::configurarCabecerasJson('GET, POST, PUT, DELETE, OPTIONS');

try {
    $pdo      = ConexionBD::obtener();
    $repoDocs = new RepositorioDocumentos($pdo);
    $auth     = new ServicioAutenticacion($pdo, $repoDocs);

    $usuario = $auth->usuarioActual();
    if ($usuario === null) {
        throw new ExcepcionDominio(
            'Debes iniciar sesión para acceder o modificar tus documentos guardados.',
            401,
            'AUTH_REQUERIDA'
        );
    }

    $uid    = (int) $usuario['id'];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');

    $leerId = static function (array $payload = []): int {
        $raw = $_GET['id'] ?? ($payload['id'] ?? '');
        return ctype_digit((string) $raw) ? (int) $raw : 0;
    };

    $cuotaUsuario = static function () use ($repoDocs, $uid): array {
        return [
            'usados' => $repoDocs->contarPorUsuario($uid),
            'maximo' => RepositorioDocumentos::MAX_DOCUMENTOS_POR_USUARIO,
        ];
    };

    if ($method === 'GET') {
        $id = $leerId();
        if ($id > 0) {
            $doc = $repoDocs->obtenerDocumento($uid, $id);
            if ($doc === null) {
                throw new ExcepcionDominio('Documento no encontrado.', 404, 'DOCUMENTO_NO_ENCONTRADO');
            }
            ControladorHttp::responderJson(200, [
                'ok'        => true,
                'documento' => $doc,
            ]);
        }

        $docs = $repoDocs->listarPorUsuario($uid);
        ControladorHttp::responderJson(200, [
            'ok'         => true,
            'documentos' => $docs,
            'cuota'      => [
                'usados' => count($docs),
                'maximo' => RepositorioDocumentos::MAX_DOCUMENTOS_POR_USUARIO,
            ],
        ]);
    }

    $payload = ControladorHttp::leerPayload();
    ControladorHttp::exigirCsrf($auth, $payload);

    $action = $action !== '' ? $action : (string) ($payload['action'] ?? '');
    $id     = $leerId($payload);

    if ($method === 'POST' && $action === 'upload_image') {
        if ($id <= 0) {
            throw new ExcepcionDominio('Falta el identificador del documento.', 400, 'ID_REQUERIDO');
        }
        $archivo = $_FILES['imagen'] ?? $_FILES['image'] ?? null;
        if (!is_array($archivo)) {
            throw new ExcepcionDominio('Debes adjuntar un archivo JPEG en el campo imagen.', 400, 'ARCHIVO_REQUERIDO');
        }

        $imagen = $repoDocs->subirImagen($uid, $id, $archivo);
        ControladorHttp::responderJson(201, [
            'ok'     => true,
            'imagen' => $imagen,
        ]);
    }

    if (($method === 'DELETE' || $method === 'POST') && $action === 'delete_image') {
        $rawImg  = $_GET['imageId'] ?? ($payload['imageId'] ?? '');
        $imageId = ctype_digit((string) $rawImg) ? (int) $rawImg : 0;
        if ($id <= 0 || $imageId <= 0) {
            throw new ExcepcionDominio('Faltan id del documento e imageId.', 400, 'ID_REQUERIDO');
        }
        if (!$repoDocs->eliminarImagen($uid, $id, $imageId)) {
            throw new ExcepcionDominio('Imagen no encontrada.', 404, 'IMAGEN_NO_ENCONTRADA');
        }
        ControladorHttp::responderJson(200, [
            'ok'        => true,
            'eliminada' => true,
        ]);
    }

    if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
        if ($id <= 0) {
            throw new ExcepcionDominio('Falta el identificador del documento a eliminar.', 400, 'ID_REQUERIDO');
        }
        if (!$repoDocs->eliminarDocumento($uid, $id)) {
            throw new ExcepcionDominio('Documento no encontrado.', 404, 'DOCUMENTO_NO_ENCONTRADO');
        }
        ControladorHttp::responderJson(200, [
            'ok'        => true,
            'eliminado' => true,
            'cuota'     => $cuotaUsuario(),
        ]);
    }

    if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
        if ($id <= 0) {
            throw new ExcepcionDominio('Falta el identificador del documento a actualizar.', 400, 'ID_REQUERIDO');
        }
        $doc = $repoDocs->actualizarDocumento($uid, $id, $payload);
        if ($doc === null) {
            throw new ExcepcionDominio('Documento no encontrado.', 404, 'DOCUMENTO_NO_ENCONTRADO');
        }
        ControladorHttp::responderJson(200, [
            'ok'        => true,
            'documento' => $doc,
        ]);
    }

    if ($method === 'POST' && ($action === '' || $action === 'create')) {
        $titulo        = trim((string) ($payload['titulo'] ?? 'Nuevo Documento'));
        $nombreArchivo = trim((string) ($payload['nombre_archivo'] ?? $titulo));
        $contenido     = array_key_exists('contenido_ltx', $payload)
            ? (string) $payload['contenido_ltx']
            : "#titulo { {$titulo} }\n#seccion { Introducción }\n#texto { Escribe aquí tu código LATINEX. }\n";

        $creado = $repoDocs->crearDocumento($uid, $titulo, $nombreArchivo, $contenido);
        ControladorHttp::responderJson(201, [
            'ok'        => true,
            'documento' => $creado,
            'cuota'     => $cuotaUsuario(),
        ]);
    }

    throw new ExcepcionDominio('Operación no soportada en /projects.php.', 405, 'METODO_NO_PERMITIDO');
} catch (\Throwable $e) {
    ControladorHttp::responderError($e);
}

<?php

require_once __DIR__ . '/src/TablaLexica.php';
require_once __DIR__ . '/src/Tokenizador.php';
require_once __DIR__ . '/src/AnalizadorSintactico.php';
require_once __DIR__ . '/src/TablaSimbolos.php';
require_once __DIR__ . '/src/AnalizadorSemantico.php';
require_once __DIR__ . '/src/Ejecutor.php';
require_once __DIR__ . '/src/PdfGenerator.php';
require_once __DIR__ . '/src/Infraestructura/ExcepcionDominio.php';
require_once __DIR__ . '/src/Infraestructura/ConexionBD.php';
require_once __DIR__ . '/src/Infraestructura/RepositorioDocumentos.php';
require_once __DIR__ . '/src/Infraestructura/ServicioAutenticacion.php';
require_once __DIR__ . '/src/Infraestructura/ControladorHttp.php';

use Latinex\AnalizadorSemantico;
use Latinex\AnalizadorSintactico;
use Latinex\Ejecutor;
use Latinex\Infraestructura\ConexionBD;
use Latinex\Infraestructura\ControladorHttp;
use Latinex\Infraestructura\RepositorioDocumentos;
use Latinex\Infraestructura\ServicioAutenticacion;
use Latinex\PdfGenerator;
use Latinex\TablaLexica;
use Latinex\Tokenizador;

ControladorHttp::aplicarCorsControlado('POST, GET, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

// ── Preflight CORS ────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── GET /api.php?action=tabla  →  exporta la tabla léxica ────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'tabla') {
        $tablaLexica = new TablaLexica();
        echo json_encode([
            'status' => 'success',
            'tabla'  => $tablaLexica->exportar(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    echo json_encode(['error' => 'Acción no reconocida. Usa ?action=tabla']);
    exit();
}

// ── POST /api.php  →  pipeline completo de compilación ────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $codigo    = $_POST['codigo']    ?? '';
    $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['sessionId'] ?? 'default_session'));
    if ($sessionId === '') {
        $sessionId = 'default_session';
    }

    // 1. Gestión de imágenes adjuntas (documento persistido del usuario o subida efímera de invitado)
    $uploadDir        = __DIR__ . '/temp_uploads/';
    $sessionUploadDir = $uploadDir . $sessionId . '/';
    $assetsDir        = $sessionUploadDir;
    $documentoId      = isset($_POST['documentoId']) && ctype_digit((string) $_POST['documentoId'])
        ? (int) $_POST['documentoId']
        : 0;

    if ($documentoId > 0 && ConexionBD::estaConfigurada()) {
        $pdo      = ConexionBD::obtener();
        $repoDocs = new RepositorioDocumentos($pdo);
        $auth     = new ServicioAutenticacion($pdo, $repoDocs);
        $usuario  = $auth->usuarioActual();

        if ($usuario === null) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'estado'  => 'error_auth',
                'message' => 'Tu sesión expiró. Inicia sesión nuevamente para compilar este documento.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        }

        $doc = $repoDocs->obtenerDocumento((int) $usuario['id'], $documentoId);
        if ($doc === null) {
            http_response_code(404);
            echo json_encode([
                'status'  => 'error',
                'estado'  => 'error_documento',
                'message' => 'Documento no encontrado o sin permisos de acceso.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit();
        }

        $assetsDir = $repoDocs->rutaAssetsDocumento((int) $usuario['id'], $documentoId);
    }

    // Subidas efímeras permitidas únicamente en Modo Invitado (con validación JPEG, tamaño y limpieza TTL)
    if ($documentoId === 0 && !empty($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
        if (is_dir($uploadDir)) {
            $limiteAntiguedad = time() - 3600;
            foreach (scandir($uploadDir) ?: [] as $entrada) {
                if ($entrada === '.' || $entrada === '..' || $entrada === '.gitkeep' || $entrada === $sessionId) {
                    continue;
                }
                $rutaSesion = $uploadDir . $entrada;
                if (is_dir($rutaSesion) && (@filemtime($rutaSesion) ?: 0) < $limiteAntiguedad) {
                    foreach (scandir($rutaSesion) ?: [] as $f) {
                        if ($f !== '.' && $f !== '..') {
                            @unlink($rutaSesion . '/' . $f);
                        }
                    }
                    @rmdir($rutaSesion);
                }
            }
        }

        if (!is_dir($sessionUploadDir)) {
            @mkdir($sessionUploadDir, 0775, true);
        }

        $files = $_FILES['images'];
        $count = min(count($files['name']), RepositorioDocumentos::MAX_IMAGENES_POR_DOCUMENTO);
        for ($i = 0; $i < $count; $i++) {
            $tmpName = (string) ($files['tmp_name'][$i] ?? '');
            $errCode = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $size    = (int) ($files['size'][$i] ?? 0);
            $name    = preg_replace('/[^a-zA-Z0-9._-]/', '', basename((string) ($files['name'][$i] ?? ''))) ?? '';
            $name    = ltrim($name, '._-');

            if (
                $errCode !== UPLOAD_ERR_OK
                || $name === ''
                || !preg_match('/\.(jpe?g)$/i', $name)
                || $size <= 0
                || $size > RepositorioDocumentos::MAX_BYTES_IMAGEN
                || !is_file($tmpName)
            ) {
                continue;
            }

            $infoImg = @getimagesize($tmpName);
            if (!is_array($infoImg) || ($infoImg[2] ?? 0) !== IMAGETYPE_JPEG) {
                continue;
            }

            if (is_uploaded_file($tmpName)) {
                move_uploaded_file($tmpName, $sessionUploadDir . $name);
            }
        }
        @touch($sessionUploadDir);
    }

    $tablaLexica = new TablaLexica();

    // ── Fase 1: Análisis Léxico ───────────────────────────────
    $tokenizadorLexico = new Tokenizador($codigo, $tablaLexica);
    $tokensArray       = [];
    $erroresLexicos    = [];
    $totalTokens       = 0;

    while (true) {
        $token         = $tokenizadorLexico->getToken();
        $tokensArray[] = $token->toArray();

        if ($token->token === \Latinex\T_ERROR) {
            $erroresLexicos[] = "Error léxico en Ln {$token->linea}, Col {$token->columna}: lexema '{$token->lexema}'";
        }
        if ($token->token !== \Latinex\T_COMENTARIO && $token->token !== \Latinex\T_FIN) {
            $totalTokens++;
        }
        if ($token->token === \Latinex\T_FIN) {
            break;
        }
    }

    $lexicoValido = count($erroresLexicos) === 0;

    // Estructuras por defecto para fases posteriores
    $sintacticoData = [
        'valido'       => false,
        'estado'       => $lexicoValido ? 'PENDIENTE' : 'OMITIDO_POR_ERROR_LEXICO',
        'estadoFinal'  => null,
        'transiciones' => [],
        'error'        => null,
    ];

    $semanticoData = [
        'valido'        => false,
        'estado'        => 'OMITIDO',
        'tablaSimbolos' => [],
        'advertencias'  => [],
        'error'         => null,
    ];

    $ejecucionData = [
        'valido'            => false,
        'implementado'      => false,
        'estado'            => 'OMITIDO',
        'figurasOElementos' => [],
        'resumen'           => [],
        'pdfBase64'         => null,
        'error'             => null,
    ];

    $estadoGeneral = 'error_lexico';
    $mensajeFinal  = implode("\n", $erroresLexicos);

    // ── Fase 2: Análisis Sintáctico ───────────────────────────
    if ($lexicoValido) {
        $parser           = new AnalizadorSintactico(new Tokenizador($codigo, $tablaLexica), true);
        $sintacticoValido = $parser->Analizar();

        $sintacticoData = [
            'valido'       => $sintacticoValido,
            'estado'       => $sintacticoValido ? 'ACEPTADO' : 'RECHAZADO',
            'estadoFinal'  => $parser->getEstadoActual(),
            'transiciones' => $parser->getSeguimiento(),
            'error'        => $parser->getError(),
        ];

        if (!$sintacticoValido) {
            $estadoGeneral            = 'error_sintactico';
            $mensajeFinal             = $parser->getError()['mensaje'] ?? 'Error sintáctico.';
            $semanticoData['estado']  = 'OMITIDO_POR_ERROR_SINTACTICO';
            $ejecucionData['estado']  = 'OMITIDO_POR_ERROR_SINTACTICO';
        } else {
            // ── Fase 3: Análisis Semántico ────────────────────
            $semantico       = new AnalizadorSemantico(new Tokenizador($codigo, $tablaLexica));
            $semanticoValido = $semantico->Analizar();

            $semanticoData = [
                'valido'        => $semanticoValido,
                'estado'        => $semanticoValido ? 'ACEPTADO' : 'RECHAZADO',
                'tablaSimbolos' => $semantico->getTablaSimbolos()->ObtenerTodos(),
                'advertencias'  => $semantico->getAdvertencias(),
                'error'         => $semantico->getError(),
            ];

            if (!$semanticoValido) {
                $estadoGeneral           = 'error_semantico';
                $mensajeFinal            = $semantico->getError()['mensaje'] ?? 'Error semántico.';
                $ejecucionData['estado'] = 'OMITIDO_POR_ERROR_SEMANTICO';
            } else {
                // ── Fase 4: Ejecución Two-Pass y Síntesis PDF ─
                $ejecutor       = new Ejecutor(new Tokenizador($codigo, $tablaLexica), clone $semantico->getTablaSimbolos(), $assetsDir);
                $ejecucionOk    = $ejecutor->Ejecutar();
                $resumen        = $ejecutor->getResumen();
                if ($ejecucionOk) {
                    $semanticoData['tablaSimbolos'] = $ejecutor->getTablaSimbolos()->ObtenerTodos();
                }
                $pdfGen         = new PdfGenerator(
                    $ejecutor->getElementos(),
                    $ejecutor->getMetadatos(),
                    $assetsDir
                );
                $pdfImplementado = $pdfGen->isImplemented();
                $pdfBase64       = null;

                if ($ejecucionOk && $pdfImplementado) {
                    $binario = $pdfGen->generate();
                    if ($binario !== '') {
                        $pdfBase64 = base64_encode($binario);
                    }
                }

                $elementosVista = array_map(static function (array $el): array {
                    if (($el['tipo'] ?? '') === 'image' && !empty($el['ruta']) && is_file((string) $el['ruta'])) {
                        $rawImg = @file_get_contents((string) $el['ruta']);
                        if (is_string($rawImg) && $rawImg !== '' && strlen($rawImg) <= 2097152) {
                            $el['dataUrl'] = 'data:image/jpeg;base64,' . base64_encode($rawImg);
                        }
                    }
                    return $el;
                }, $ejecutor->getElementos());

                $ejecucionData = [
                    'valido'            => $ejecucionOk,
                    'implementado'      => $pdfImplementado && ($resumen['implementado'] ?? false),
                    'estado'            => !$ejecucionOk
                        ? 'RECHAZADO'
                        : ($pdfImplementado ? 'ACEPTADO' : 'PENDIENTE_FRENTE_2'),
                    'figurasOElementos' => $elementosVista,
                    'resumen'           => $resumen,
                    'pdfStats'          => $pdfGen->getStats(),
                    'pdfBase64'         => $pdfBase64,
                    'error'             => $ejecutor->getError(),
                ];

                if (!$ejecucionOk) {
                    $estadoGeneral = 'error_ejecucion';
                    $mensajeFinal  = $ejecutor->getError()['mensaje'] ?? 'Error durante la ejecución.';
                } else {
                    $estadoGeneral = 'exito';
                    if ($pdfImplementado && $pdfBase64 !== null) {
                        $pdfStats     = $pdfGen->getStats();
                        $mensajeFinal = sprintf(
                            '4 Fases ACEPTADAS (%d tokens, %d transiciones, %d símbolos, PDF %d pág. / %d B).',
                            $totalTokens,
                            count($sintacticoData['transiciones']),
                            count($semanticoData['tablaSimbolos']),
                            (int) ($pdfStats['paginas'] ?? 0),
                            (int) ($pdfStats['bytes'] ?? 0)
                        );
                    } else {
                        $mensajeFinal = sprintf(
                            'Análisis Léxico, Sintáctico y Semántico ACEPTADOS (%d tokens, %d transiciones, %d símbolos).',
                            $totalTokens,
                            count($sintacticoData['transiciones']),
                            count($semanticoData['tablaSimbolos'])
                        );
                    }
                }
            }
        }
    }

    echo json_encode([
        // Claves legacy compatibles con el visor actual
        'status'      => $estadoGeneral === 'exito' ? 'success' : 'warning',
        'sessionId'   => $sessionId,
        'documentoId' => $documentoId > 0 ? $documentoId : null,
        'message'     => $mensajeFinal,
        'totalTokens' => $totalTokens,
        'errores'     => $erroresLexicos,
        'tokens'      => $tokensArray,

        // Contrato completo del IDE de 4 pestañas (Frente 3)
        'estado'      => $estadoGeneral,
        'lexico'      => [
            'valido'      => $lexicoValido,
            'totalTokens' => $totalTokens,
            'errores'     => $erroresLexicos,
            'tokens'      => $tokensArray,
        ],
        'sintactico'  => $sintacticoData,
        'semantico'   => $semanticoData,
        'ejecucion'   => $ejecucionData,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    exit();
}

// ── Método no permitido ───────────────────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Método no permitido. Usa GET o POST.']);

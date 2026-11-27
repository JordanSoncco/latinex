<?php

declare(strict_types=1);

/* ============================================================
 * LATINEX Cloud — Suite HTTP E2E del Pipeline Compilador (/api.php)
 * ============================================================
 * Valida de extremo a extremo sobre HTTP real (Caddy -> PHP-FPM):
 *   1. Exportación de la Tabla Léxica oficial (78 entradas).
 *   2. Compilación de casos representativos (01..30, Two-Pass, /Symbol,
 *      fracciones anidadas y ajuste de palabras largas en #celda).
 *   3. Contratos de diagnóstico de error (léxico, sintáctico, semántico
 *      1500, 1601 recursión, 1501, 1700) y advertencias semánticas.
 *   4. Subida multipart JPEG en Modo Invitado y bloqueo CORS externo.
 *
 * Ejecutar dentro del contenedor app:
 *   docker compose exec -T app php test_e2e.php
 * ============================================================
 */

$baseUrl = getenv('LATINEX_TEST_URL') ?: 'http://web';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = substr($arg, strlen('--base-url='));
    }
}
$baseUrl = rtrim($baseUrl, '/');

function httpJson(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $hdrLines = [];
    foreach ($headers as $k => $v) {
        $hdrLines[] = "{$k}: {$v}";
    }
    $ctx = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $hdrLines),
            'content'       => $body ?? '',
            'ignore_errors' => true,
            'timeout'       => 15,
        ],
    ]);
    $raw  = @file_get_contents($url, false, $ctx);
    $hdrs = $http_response_header ?? [];
    $code = 0;
    if (isset($hdrs[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $hdrs[0], $m)) {
        $code = (int) $m[1];
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'status'  => $code,
        'headers' => $hdrs,
        'json'    => is_array($json) ? $json : null,
        'raw'     => (string) $raw,
    ];
}

function postCodigo(string $baseUrl, string $codigo, string $sessionId = 'e2e_session'): array
{
    return httpJson(
        'POST',
        $baseUrl . '/api.php',
        ['Content-Type' => 'application/x-www-form-urlencoded'],
        http_build_query(['codigo' => $codigo, 'sessionId' => $sessionId])
    );
}

function checkE2E(bool $cond, string $label): void
{
    if ($cond) {
        echo "  [PASS] {$label}\n";
        return;
    }
    echo "  [FAIL] {$label}\n";
    exit(1);
}

echo "=========================================================================\n";
echo " LATINEX Cloud — Suite HTTP E2E del Compilador y Contratos JSON (/api.php)\n";
echo " Endpoint objetivo: {$baseUrl}\n";
echo "=========================================================================\n\n";

// 1. Tabla léxica oficial
$tablaRes = httpJson('GET', $baseUrl . '/api.php?action=tabla');
checkE2E(
    $tablaRes['status'] === 200
    && ($tablaRes['json']['status'] ?? '') === 'success'
    && count($tablaRes['json']['tabla'] ?? []) === 78,
    'GET /api.php?action=tabla exporta las 78 definiciones léxicas oficiales'
);

// 2. Casos positivos E2E + invariantes del binario %PDF-1.4 devuelto en pdfBase64
$casosPositivos = [
    'E2E Caso 01/02: Texto con negrita y cursiva' => [
        'codigo' => '#texto { Hola #fuerte{Jordan} y #cursiva{Dominic}. }',
        'check'  => static fn(array $j, string $pdf): bool => str_contains($pdf, '/F2 12.00 Tf') && str_contains($pdf, "P\xE1gina 1 de 1"),
    ],
    'E2E Caso 10/15: Macros + justificación con Tw' => [
        'codigo' => "#definir(uni) { UNAP Puno }\n#texto(justificado) { Proyecto académico desarrollado en la #imprimir(uni) con justificación exacta de márgenes en hoja A4 estándar. }",
        'check'  => static fn(array $j, string $pdf): bool => str_contains($pdf, 'UNAP Puno') && str_contains($pdf, ' Tw '),
    ],
    'E2E Caso 23b/24: Two-Pass forward reference + #indicegeneral' => [
        'codigo' => "#indicegeneral\n#texto { Ir al cierre en #referencia(cierre). }\n#nuevapagina\n#seccion(id=cierre) { Conclusión }",
        'check'  => static fn(array $j, string $pdf): bool => ($j['ejecucion']['pdfStats']['paginas'] ?? 0) === 2
            && str_contains((string) ($j['semantico']['tablaSimbolos'][0]['valor'] ?? ''), '(pág. 2)')
            && str_contains($pdf, "p\xE1gina 2.) Tj"),
    ],
    'E2E Caso 27/Frac: Ecuación con fracción doble anidada' => [
        'codigo' => "#ecuacion { #fraccion { {#fraccion { {a+1} {b} }} {c+2} } = 1 }",
        'check'  => static fn(array $j, string $pdf): bool => str_contains($pdf, '(a+1) Tj') && str_contains($pdf, '(c+2) Tj'),
    ],
    'E2E Caso 22/Celda: Corte limpio de URL larga dentro de #celda' => [
        'codigo' => "#tabla(bordes=verdadero) { #fila { #celda { https://github.com/JordanSoncco/latinex/compilador/documentacion/extensa/2026 } #celda { OK } } }",
        'check'  => static fn(array $j, string $pdf): bool => str_contains($pdf, '(OK) Tj') && ($j['ejecucion']['resumen']['totalElementos'] ?? 0) >= 4,
    ],
    'E2E Caso 30: Griego y Operadores científicos nativos (/Symbol /F7)' => [
        'codigo' => "#titulo { Física }\n#texto { Para ∀ ε > 0, ∑ α ≤ π y límite → ∞. }",
        'check'  => static fn(array $j, string $pdf): bool => str_contains($pdf, '/BaseFont /Symbol') && str_contains($pdf, '/F7 ') && !str_contains($pdf, '?'),
    ],
];

foreach ($casosPositivos as $etiqueta => $spec) {
    $res    = postCodigo($baseUrl, $spec['codigo']);
    $json   = $res['json'] ?? [];
    $b64    = (string) ($json['ejecucion']['pdfBase64'] ?? '');
    $pdfBin = base64_decode($b64, true);

    $estructuraOk = $res['status'] === 200
        && ($json['estado'] ?? '') === 'exito'
        && ($json['lexico']['valido'] ?? false) === true
        && ($json['sintactico']['valido'] ?? false) === true
        && ($json['semantico']['valido'] ?? false) === true
        && ($json['ejecucion']['valido'] ?? false) === true
        && ($json['ejecucion']['implementado'] ?? false) === true
        && is_string($pdfBin)
        && str_starts_with($pdfBin, "%PDF-1.4\n")
        && str_ends_with($pdfBin, "%%EOF\n")
        && ($spec['check'])($json, $pdfBin);

    checkE2E($estructuraOk, $etiqueta);
}

// 3. Contratos de error por fase y advertencias semánticas
$errLex = postCodigo($baseUrl, '#texto(color=@) { Error }');
checkE2E(($errLex['json']['estado'] ?? '') === 'error_lexico', 'Diagnóstico HTTP de error léxico (estado=error_lexico)');

$errSint = postCodigo($baseUrl, '#texto { Sin cerrar');
checkE2E(
    ($errSint['json']['estado'] ?? '') === 'error_sintactico'
    && ($errSint['json']['sintactico']['estado'] ?? '') === 'RECHAZADO',
    'Diagnóstico HTTP de error sintáctico (estado=error_sintactico)'
);

$err1500 = postCodigo($baseUrl, '#texto { #imprimir(faltante) }');
checkE2E(
    ($err1500['json']['estado'] ?? '') === 'error_semantico'
    && ($err1500['json']['semantico']['error']['codigo'] ?? 0) === 1500,
    'Diagnóstico HTTP de error semántico 1500 (macro inexistente)'
);

$err1601 = postCodigo($baseUrl, "#definir(x){1}\n#definir(x){#imprimir(x)}\n#texto{#imprimir(x)}");
checkE2E(
    ($err1601['json']['estado'] ?? '') === 'error_semantico'
    && ($err1601['json']['semantico']['error']['codigo'] ?? 0) === 1601,
    'Diagnóstico HTTP de error semántico 1601 (recursión cíclica bloqueada)'
);

$err1501 = postCodigo($baseUrl, '#texto { #referencia(no_existe) }');
checkE2E(
    ($err1501['json']['estado'] ?? '') === 'error_semantico'
    && ($err1501['json']['semantico']['error']['codigo'] ?? 0) === 1501,
    'Diagnóstico HTTP de error semántico 1501 (referencia inexistente)'
);

$warnRes = postCodigo($baseUrl, '#texto(alineasion=centro, alerta) { Con advertencias }');
checkE2E(
    ($warnRes['json']['estado'] ?? '') === 'exito'
    && count($warnRes['json']['semantico']['advertencias'] ?? []) >= 2,
    'Advertencias semánticas por atributo desconocido y bandera fuera de contexto'
);

// 4. Subida multipart JPEG en Modo Invitado + CORS seguro
$boundary = '----LatinexE2E' . bin2hex(random_bytes(6));
$tinyJpeg = (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', true);
$mpBody   = "--{$boundary}\r\nContent-Disposition: form-data; name=\"sessionId\"\r\n\r\ne2e_guest_img\r\n"
          . "--{$boundary}\r\nContent-Disposition: form-data; name=\"codigo\"\r\n\r\n#imagen(ruta=guest.jpg, ancho=40%, alto=auto, alineacion=centro)\r\n"
          . "--{$boundary}\r\nContent-Disposition: form-data; name=\"images[0]\"; filename=\"guest.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n{$tinyJpeg}\r\n"
          . "--{$boundary}--\r\n";

$guestImgRes = httpJson('POST', $baseUrl . '/api.php', ['Content-Type' => "multipart/form-data; boundary={$boundary}"], $mpBody);
$guestPdf    = base64_decode((string) ($guestImgRes['json']['ejecucion']['pdfBase64'] ?? ''), true);
checkE2E(
    $guestImgRes['status'] === 200
    && is_string($guestPdf)
    && str_contains($guestPdf, '/Filter /DCTDecode'),
    'Modo Invitado incrusta imagen JPEG multipart en %PDF-1.4 (/DCTDecode)'
);

$corsEvil = httpJson('POST', $baseUrl . '/api.php', [
    'Origin'       => 'https://sitio-externo-no-autorizado.example',
    'Content-Type' => 'application/x-www-form-urlencoded',
], 'codigo=%23texto%7BHola%7D');
$hdrTxt = implode("\n", $corsEvil['headers']);
checkE2E(
    stripos($hdrTxt, 'Access-Control-Allow-Origin') === false,
    'Bloqueo CORS ante orígenes externos no autorizados'
);

echo "\n✔ ESTADO SUITE HTTP E2E (/api.php): 100% APROBADO\n";

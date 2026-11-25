<?php

declare(strict_types=1);

/* ============================================================
 * LATINEX Cloud — Suite HTTP E2E (Auth, Tope 10 .ltx y Compilación)
 * ============================================================
 * Ejecutar dentro del contenedor app:
 *   docker compose exec -T app php test_auth_proyectos.php
 * O desde host Windows:
 *   php test_auth_proyectos.php --base-url=http://localhost:8080
 */

require_once __DIR__ . '/src/Infraestructura/ConexionBD.php';
require_once __DIR__ . '/src/Infraestructura/RepositorioDocumentos.php';

use Latinex\Infraestructura\ConexionBD;

$baseUrl = getenv('LATINEX_TEST_URL') ?: 'http://web';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = substr($arg, strlen('--base-url='));
    }
}
$baseUrl = rtrim($baseUrl, '/');

class HttpClientTest
{
    private string $baseUrl;
    private array $cookies = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function request(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $url        = $this->baseUrl . $path;
        $headerList = [];
        foreach ($headers as $k => $v) {
            $headerList[] = "{$k}: {$v}";
        }
        if (!empty($this->cookies)) {
            $pairs = [];
            foreach ($this->cookies as $ck => $cv) {
                $pairs[] = "{$ck}={$cv}";
            }
            $headerList[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headerList),
                'content'       => $body ?? '',
                'ignore_errors' => true,
                'timeout'       => 15,
            ],
        ];

        $context  = stream_context_create($opts);
        $rawBody  = @file_get_contents($url, false, $context);
        $respHdrs = $http_response_header ?? [];

        if ($rawBody === false && empty($respHdrs)) {
            throw new RuntimeException("No se pudo conectar a {$url}");
        }

        $status = 0;
        if (isset($respHdrs[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $respHdrs[0], $m)) {
            $status = (int) $m[1];
        }

        foreach ($respHdrs as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $val = trim(substr($h, strlen('Set-Cookie:')));
                $firstPart = explode(';', $val, 2)[0];
                if (strpos($firstPart, '=') !== false) {
                    [$name, $cookieVal] = explode('=', $firstPart, 2);
                    $this->cookies[trim($name)] = trim($cookieVal);
                }
            }
        }

        $json = json_decode((string) $rawBody, true);
        return [
            'status' => $status,
            'raw'    => (string) $rawBody,
            'json'   => is_array($json) ? $json : null,
        ];
    }

    public function requestMultipart(string $path, array $fields, array $files = [], array $headers = []): array
    {
        $boundary = '----LatinexTest' . bin2hex(random_bytes(8));
        $eol      = "\r\n";
        $body     = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}{$eol}";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"{$eol}{$eol}";
            $body .= "{$value}{$eol}";
        }

        foreach ($files as $field => $fileInfo) {
            $filename = $fileInfo['filename'];
            $mime     = $fileInfo['mime'];
            $content  = $fileInfo['content'];
            $body    .= "--{$boundary}{$eol}";
            $body    .= "Content-Disposition: form-data; name=\"{$field}\"; filename=\"{$filename}\"{$eol}";
            $body    .= "Content-Type: {$mime}{$eol}{$eol}";
            $body    .= "{$content}{$eol}";
        }
        $body .= "--{$boundary}--{$eol}";

        $headers['Content-Type'] = "multipart/form-data; boundary={$boundary}";
        return $this->request('POST', $path, $headers, $body);
    }

    public function resetCookies(): void
    {
        $this->cookies = [];
    }
}

function assertCheck(bool $cond, string $label): void
{
    if ($cond) {
        echo "  [PASS] {$label}\n";
        return;
    }
    echo "  [FAIL] {$label}\n";
    exit(1);
}

echo "=================================================================\n";
echo " LATINEX Cloud — Pruebas HTTP Auth, Límite 10 .ltx y Compilación\n";
echo " Endpoint objetivo: {$baseUrl}\n";
echo "=================================================================\n";

$sufijo    = bin2hex(random_bytes(4));
$correo1   = "prueba1_{$sufijo}@latinex.edu.pe";
$correo2   = "prueba2_{$sufijo}@latinex.edu.pe";
$password  = 'ClaveSegura2026!';
$client1   = new HttpClientTest($baseUrl);
$client2   = new HttpClientTest($baseUrl);

try {
    // 1. Portada Overleaf HTML
    $home = $client1->request('GET', '/');
    assertCheck($home['status'] === 200 && str_contains($home['raw'], 'LATINEX Cloud'), 'GET / devuelve UI Overleaf (200 OK)');

    // 2. Acceso anónimo bloqueado en /projects.php
    $unauth = $client1->request('GET', '/projects.php');
    assertCheck($unauth['status'] === 401 && ($unauth['json']['code'] ?? '') === 'AUTH_REQUERIDA', 'GET /projects.php sin sesión bloqueado (401)');

    // 3. Registro Usuario 1 (crea bienvenida.ltx -> 1/10)
    $reg1 = $client1->request('POST', '/auth.php?action=register', ['Content-Type' => 'application/json'], json_encode([
        'nombre'   => 'Jordan Prueba',
        'correo'   => $correo1,
        'password' => $password,
    ]));
    assertCheck($reg1['status'] === 201 && ($reg1['json']['autenticado'] ?? false) === true, 'Registro Usuario 1 exitoso (201 Created)');
    assertCheck(($reg1['json']['limites']['documentosUsados'] ?? 0) === 1, 'Documento inicial bienvenida.ltx creado automáticamente (1/10)');
    $csrf1 = (string) ($reg1['json']['csrfToken'] ?? '');
    $doc1Id = (int) ($reg1['json']['documentoInicial']['id'] ?? 0);
    assertCheck($doc1Id > 0 && $csrf1 !== '', 'Token CSRF y documento inicial recibidos');

    // 4. Logout + Login erróneo (401) + Login correcto (200)
    $logout = $client1->request('POST', '/auth.php?action=logout', ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrf1], '{}');
    assertCheck($logout['status'] === 200 && ($logout['json']['autenticado'] ?? true) === false, 'Cerrar sesión exitoso (200)');

    $badLogin = $client1->request('POST', '/auth.php?action=login', ['Content-Type' => 'application/json'], json_encode([
        'correo'   => $correo1,
        'password' => 'ClaveEquivocada99',
    ]));
    assertCheck($badLogin['status'] === 401 && ($badLogin['json']['code'] ?? '') === 'CREDENCIALES_INVALIDAS', 'Login con clave incorrecta rechazado (401)');

    $okLogin = $client1->request('POST', '/auth.php?action=login', ['Content-Type' => 'application/json'], json_encode([
        'correo'   => $correo1,
        'password' => $password,
    ]));
    assertCheck($okLogin['status'] === 200 && ($okLogin['json']['autenticado'] ?? false) === true, 'Login con credenciales válidas exitoso (200)');
    $csrf1 = (string) ($okLogin['json']['csrfToken'] ?? '');

    // 5. Crear 9 documentos más hasta completar 10/10
    $creadosIds = [$doc1Id];
    for ($n = 2; $n <= 10; $n++) {
        $createRes = $client1->request('POST', '/projects.php', [
            'Content-Type' => 'application/json',
            'X-CSRF-Token' => $csrf1,
        ], json_encode([
            'titulo'        => "Proyecto {$n}",
            'nombre_archivo' => "proyecto_{$n}.ltx",
            'contenido_ltx' => "#titulo { Proyecto {$n} }\n#texto { Contenido #{$n}. }",
        ]));
        assertCheck($createRes['status'] === 201 && ($createRes['json']['cuota']['usados'] ?? 0) === $n, "Documento {$n}/10 creado (201)");
        $creadosIds[] = (int) $createRes['json']['documento']['id'];
    }

    // 6. Bloquear documento #11 con 409 Conflict
    $doc11 = $client1->request('POST', '/projects.php', [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $csrf1,
    ], json_encode([
        'titulo'        => 'Proyecto 11 Excedido',
        'nombre_archivo' => 'excedido.ltx',
        'contenido_ltx' => '#texto { No debe crearse }',
    ]));
    assertCheck($doc11['status'] === 409 && ($doc11['json']['code'] ?? '') === 'LIMITE_ARCHIVOS_ALCANZADO', 'Bloqueo estricto del documento #11 (409 LIMITE_ARCHIVOS_ALCANZADO)');

    // 7. Eliminar 1 documento baja a 9/10 y permite crear otro
    $delRes = $client1->request('DELETE', "/projects.php?id={$creadosIds[9]}", [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $csrf1,
    ], '{}');
    assertCheck($delRes['status'] === 200 && ($delRes['json']['cuota']['usados'] ?? -1) === 9, 'Eliminar documento reduce cuota a 9/10');

    // 8. Aislamiento entre Usuario 2 y Usuario 1 + subida de imagen JPEG + compilación
    $reg2 = $client2->request('POST', '/auth.php?action=register', ['Content-Type' => 'application/json'], json_encode([
        'nombre'   => 'William Aislamiento',
        'correo'   => $correo2,
        'password' => $password,
    ]));
    assertCheck($reg2['status'] === 201, 'Registro Usuario 2 exitoso (201)');
    $csrf2  = (string) ($reg2['json']['csrfToken'] ?? '');
    $doc2Id = (int) ($reg2['json']['documentoInicial']['id'] ?? 0);

    $ajenoGet = $client2->request('GET', "/projects.php?id={$doc1Id}");
    assertCheck($ajenoGet['status'] === 404, 'Usuario 2 no puede leer documento de Usuario 1 (404)');

    $ajenoPut = $client2->request('PUT', "/projects.php?id={$doc1Id}", [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $csrf2,
    ], json_encode(['contenido_ltx' => '#texto { Hack }']));
    assertCheck($ajenoPut['status'] === 404, 'Usuario 2 no puede modificar documento de Usuario 1 (404)');

    // 1x1 JPEG válido mínimo para validar getimagesize() y almacenamiento en disco
    $tinyJpegB64 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
    $tinyJpeg    = base64_decode($tinyJpegB64, true);
    assertCheck(is_string($tinyJpeg) && strlen($tinyJpeg) > 20, 'JPEG binario de prueba decodificado');

    $upImg = $client2->requestMultipart(
        "/projects.php?action=upload_image&id={$doc2Id}",
        ['csrfToken' => $csrf2],
        ['imagen' => ['filename' => 'prueba.jpg', 'mime' => 'image/jpeg', 'content' => $tinyJpeg]],
        ['X-CSRF-Token' => $csrf2]
    );
    assertCheck($upImg['status'] === 201 && ($upImg['json']['imagen']['nombre_archivo'] ?? '') === 'prueba.jpg', 'Subida de imagen JPEG al documento (201)');

    $compilar = $client2->requestMultipart('/api.php', [
        'documentoId' => (string) $doc2Id,
        'codigo'      => "#titulo { Compilación Cloud }\n#imagen(ruta=prueba.jpg, ancho=50%, alto=auto, alineacion=centro)\n#texto { Imagen validada correctamente. }",
    ]);
    assertCheck(
        $compilar['status'] === 200
        && ($compilar['json']['estado'] ?? '') === 'exito'
        && ($compilar['json']['semantico']['valido'] ?? false) === true
        && (int) ($compilar['json']['documentoId'] ?? 0) === $doc2Id,
        'Compilación POST /api.php ligada a documentoId en BD (200 ACEPTADO)'
    );

    echo "\n✔ ESTADO AUTH Y PROYECTOS: 100% APROBADO\n";
} finally {
    if (ConexionBD::estaConfigurada()) {
        try {
            $pdo  = ConexionBD::obtener();
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE correo IN (:c1, :c2)');
            $stmt->execute([':c1' => $correo1, ':c2' => $correo2]);
            echo "  (Usuarios sintéticos de prueba limpiados de PostgreSQL)\n";
        } catch (Throwable $e) {
            echo "  (Aviso limpieza: {$e->getMessage()})\n";
        }
    }
}

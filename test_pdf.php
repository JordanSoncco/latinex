<?php

declare(strict_types=1);

/* ============================================================
 * LATINEX — Suite Integral de Verificación Two-Pass y %PDF-1.4
 * ============================================================
 *
 * Verifica los 29 casos oficiales del Documento de Alcance (+ variantes):
 *   1. Pipeline completo de 4 fases (Léxico, Sintáctico, Semántico, Ejecución).
 *   2. Estructura binaria estricta %PDF-1.4 (/Catalog, /Pages, /Page, /Font, /Info).
 *   3. Exactitud matemática en bytes de la tabla xref y startxref.
 *   4. Invariantes específicos de cada caso (StyleStack, Tw, AFM, macros,
 *      JPEG /DCTDecode, superíndices, fracciones, tablas, referencias
 *      hacia adelante multipágina, #indicegeneral e íconos vectoriales #caja).
 *
 * Ejecutar:
 *   docker compose exec -T app php test_pdf.php
 * ============================================================
 */

require_once __DIR__ . '/src/TablaLexica.php';
require_once __DIR__ . '/src/Tokenizador.php';
require_once __DIR__ . '/src/AnalizadorSintactico.php';
require_once __DIR__ . '/src/TablaSimbolos.php';
require_once __DIR__ . '/src/AnalizadorSemantico.php';
require_once __DIR__ . '/src/Ejecutor.php';
require_once __DIR__ . '/src/PdfGenerator.php';

use Latinex\AnalizadorSemantico;
use Latinex\AnalizadorSintactico;
use Latinex\Ejecutor;
use Latinex\PdfGenerator;
use Latinex\TablaLexica;
use Latinex\Tokenizador;
use const Latinex\T_ERROR;
use const Latinex\T_FIN;

/**
 * Valida estructuralmente que el binario sea un %PDF-1.4 bien formado con xref exacto.
 */
function ValidarEstructuraPdf14(string $pdf): ?string
{
    if (!str_starts_with($pdf, "%PDF-1.4\n")) {
        return 'No inicia con cabecera %PDF-1.4';
    }
    if (!str_ends_with($pdf, "%%EOF\n")) {
        return 'No termina con %%EOF';
    }
    foreach (['/Type /Catalog', '/Type /Pages', '/Type /Page', '/MediaBox [0 0 595 842]', 'xref', 'trailer', 'startxref'] as $req) {
        if (!str_contains($pdf, $req)) {
            return "Falta marcador obligatorio: {$req}";
        }
    }

    if (!preg_match('/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m)) {
        return 'Formato inválido de startxref';
    }
    $xrefPos = (int) $m[1];
    if (substr($pdf, $xrefPos, 5) !== "xref\n") {
        return "startxref ({$xrefPos}) no apunta exactamente a 'xref'";
    }

    // Verificar cada entrada en uso de la tabla xref contra su offset real en bytes
    $colaXref = substr($pdf, $xrefPos);
    if (!preg_match('/^xref\n0 (\d+)\n((?:\d{10} \d{5} [fn] \n)+)/', $colaXref, $mx)) {
        return 'Tabla xref mal estructurada o líneas fuera de 20 bytes';
    }
    $lineasXref = explode("\n", trim($mx[2], "\n"));
    foreach ($lineasXref as $id => $linea) {
        if (strlen($linea) !== 19) { // 19 + "\n" = 20 bytes exactos del estándar PDF 1.4
            return "Entrada xref #{$id} no mide 20 bytes exactos";
        }
        if ($id === 0) {
            continue;
        }
        $offset = (int) substr($linea, 0, 10);
        $cabObj = "{$id} 0 obj\n";
        if (substr($pdf, $offset, strlen($cabObj)) !== $cabObj) {
            return "Offset xref del objeto {$id} ({$offset}) no coincide con '{$cabObj}'";
        }
    }

    return null;
}

function CompilarCasoCompleto(string $codigo, TablaLexica $tablaLexica, string $assetsDir = ''): array
{
    $tok = new Tokenizador($codigo, $tablaLexica);
    while (true) {
        $t = $tok->getToken();
        if ($t->token === T_ERROR) {
            return ['ok' => false, 'error' => "Error léxico: {$t->lexema}"];
        }
        if ($t->token === T_FIN) {
            break;
        }
    }

    $parser = new AnalizadorSintactico(new Tokenizador($codigo, $tablaLexica), false);
    if (!$parser->Analizar()) {
        return ['ok' => false, 'error' => $parser->getError()['mensaje'] ?? 'Error sintáctico'];
    }

    $sem = new AnalizadorSemantico(new Tokenizador($codigo, $tablaLexica));
    if (!$sem->Analizar()) {
        return ['ok' => false, 'error' => $sem->getError()['mensaje'] ?? 'Error semántico'];
    }

    $ejecutor = new Ejecutor(new Tokenizador($codigo, $tablaLexica), clone $sem->getTablaSimbolos(), $assetsDir);
    if (!$ejecutor->Ejecutar()) {
        return ['ok' => false, 'error' => $ejecutor->getError()['mensaje'] ?? 'Error ejecución'];
    }

    $pdfGen = new PdfGenerator($ejecutor->getElementos(), $ejecutor->getMetadatos(), $assetsDir);
    $pdf    = $pdfGen->generate();
    $errPdf = ValidarEstructuraPdf14($pdf);
    if ($errPdf !== null) {
        return ['ok' => false, 'error' => $errPdf];
    }

    return [
        'ok'        => true,
        'pdf'       => $pdf,
        'stats'     => $pdfGen->getStats(),
        'resumen'   => $ejecutor->getResumen(),
        'elementos' => $ejecutor->getElementos(),
    ];
}

// Directorio temporal aislado con un JPEG RGB baseline real (32x16) para el Caso 16
$tempAssetsDir = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . 'latinex_test_pdf_' . getmypid();
@mkdir($tempAssetsDir, 0777, true);
$jpegFixtureB64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAAQACADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDzqiiiv7FP5vCiiigD/9k=';
file_put_contents($tempAssetsDir . DIRECTORY_SEPARATOR . 'logo.jpg', (string) base64_decode($jpegFixtureB64, true));
register_shutdown_function(static function () use ($tempAssetsDir): void {
    @unlink($tempAssetsDir . DIRECTORY_SEPARATOR . 'logo.jpg');
    @rmdir($tempAssetsDir);
});

$parrafoExtenso = "Párrafo extenso que ocupa múltiples líneas dentro del ancho útil de 495 puntos de la hoja A4 de 595 por 842 pt usando métricas AFM reales de la fuente Helvetica de 12 puntos en el compilador nativo LATINEX de la EPIS UNAP Puno. Cada oración suma desplazamiento en la coordenada vertical lógica Y_logica hasta rebasar el límite inferior permitido de 792 puntos y provocar un salto de página automático.";

$casos = [
    [
        'nombre' => 'Caso 01: Estructura de texto (#texto)',
        'codigo' => "#texto {\n    Hola Mundo\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(Hola Mundo) Tj') && $r['stats']['paginas'] === 1,
    ],
    [
        'nombre' => 'Caso 02: Formato Negrita y Cursiva (StyleStack + espacios)',
        'codigo' => "#texto {\n    Los Compañeritos #fuerte{Jordan} y #cursiva{Dominic}.\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '/F2 12.00 Tf')
            && str_contains($r['pdf'], '/F3 12.00 Tf')
            && str_contains($r['pdf'], '( y ) Tj'),
    ],
    [
        'nombre' => 'Caso 03: Metadatos (#titulo, #autor -> /Info)',
        'codigo' => "#titulo { Tesis }\n#autor { Grupo 3 }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '/Title (Tesis)') && str_contains($r['pdf'], '/Author (Grupo 3)'),
    ],
    [
        'nombre' => 'Caso 04: Jerarquía estructural (#seccion..#subseccion4)',
        'codigo' => "#seccion { Marco Teórico }\n#subseccion { Antecedentes }\n#subseccion2 { Investigaciones Previas }\n#subseccion3 { Alcance }\n#subseccion4 { Detalle }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '1.1.1.1.1. Detalle'),
    ],
    [
        'nombre' => 'Caso 05: Salto de línea explícito (#salto)',
        'codigo' => '#texto { Línea 1 } #salto #texto { Línea 2 }',
        'check'  => fn(array $r): bool => $r['stats']['paginas'] === 1 && count($r['elementos']) >= 2,
    ],
    [
        'nombre' => 'Caso 06: Salto de página explícito (#nuevapagina)',
        'codigo' => '#texto { Página 1 } #nuevapagina #texto { Página 2 }',
        'check'  => fn(array $r): bool => $r['stats']['paginas'] === 2 && str_contains($r['pdf'], '/Count 2'),
    ],
    [
        'nombre' => 'Caso 07: Comentarios ignorados (//)',
        'codigo' => "// Este texto no debe aparecer en el PDF.\n#texto { Solo esto se compila. }",
        'check'  => fn(array $r): bool => !str_contains($r['pdf'], 'no debe aparecer') && str_contains($r['pdf'], '(Solo esto se compila.) Tj'),
    ],
    [
        'nombre' => 'Caso 08: Escape de caracteres reservados (\# \{ \})',
        'codigo' => "#texto {\n    Usamos el numeral \\# y llaves \\{ \\}\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '# y llaves { }'),
    ],
    [
        'nombre' => 'Caso 09: Alineación derecha con métricas AFM',
        'codigo' => "#texto(derecha) {\n    Azángaro, 2026\n}",
        'check'  => fn(array $r): bool => ($r['elementos'][0]['x'] ?? 0) > 400.0,
    ],
    [
        'nombre' => 'Caso 10: Alineación justificada con operador Tw',
        'codigo' => "#texto(justificado) {\n    Párrafo con texto largo que debe tocar obligatoriamente ambos márgenes del documento para mostrar la justificación de texto PDF.\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], ' Tw ') && str_contains($r['pdf'], ' 0 Tw ET'),
    ],
    [
        'nombre' => 'Caso 11: Lista no ordenada con bloques anónimos (bullet \\x95)',
        'codigo' => '#lista(punto) { {Elemento A} {Elemento B} }',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], "(\x95) Tj") && str_contains($r['pdf'], '(Elemento A) Tj'),
    ],
    [
        'nombre' => 'Caso 12: Lista ordenada numerada',
        'codigo' => '#lista(numero) { {Inicio} {Fin} }',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(1.) Tj') && str_contains($r['pdf'], '(2.) Tj'),
    ],
    [
        'nombre' => 'Caso 13: Color #HEX y #resaltado con rectángulo de fondo',
        'codigo' => "#texto(color=#FF0000) { Alerta } #salto\n#resaltado(color=#FFFF00) { Dato } #salto\n#texto(color=#1A73E8) { Texto azul institucional }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '1.000 0.000 0.000 rg') && str_contains($r['pdf'], '1.000 1.000 0.000 rg') && str_contains($r['pdf'], 're f'),
    ],
    [
        'nombre' => 'Caso 14: Declaración de Macro (#definir sin salida visual)',
        'codigo' => '#definir(curso) { Compiladores }',
        'check'  => fn(array $r): bool => $r['stats']['paginas'] === 1 && count($r['elementos']) === 0,
    ],
    [
        'nombre' => 'Caso 15: Invocación de Macro (#imprimir)',
        'codigo' => "#definir(curso) { Compiladores }\n#texto { Proyecto del curso de #imprimir(curso). }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(Proyecto del curso de Compiladores.) Tj'),
    ],
    [
        'nombre' => 'Caso 16: Inserción de Imagen JPEG (/DCTDecode + matriz cm)',
        'codigo' => '#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '/Filter /DCTDecode') && str_contains($r['pdf'], '/Im1 Do Q'),
    ],
    [
        'nombre' => 'Caso 17: Matemáticas en línea (#superindice y #subindice)',
        'codigo' => '#texto { El área es m#superindice{2} y el agua es H#subindice{2}O }',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '8.40 Tf') && str_contains($r['pdf'], '( y el agua es H) Tj') && str_contains($r['pdf'], '(O) Tj'),
    ],
    [
        'nombre' => 'Caso 18: Fracción con bloques anónimos { {num} {den} }',
        'codigo' => '#fraccion { {x+1} {y-2} }',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(x+1) Tj') && str_contains($r['pdf'], '(y-2) Tj') && str_contains($r['pdf'], ' l S Q'),
    ],
    [
        'nombre' => 'Caso 18b: Fracción con atributos (numerador/denominador)',
        'codigo' => '#fraccion(numerador=x+1, denominador=y-2)',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(x+1) Tj') && str_contains($r['pdf'], '(y-2) Tj'),
    ],
    [
        'nombre' => 'Caso 19: Atributos combinados (fuente=Courier, azul, centrado)',
        'codigo' => "#texto(fuente=Courier, color=#0000FF, centrado) {\n    Código final\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '/F5 12.00 Tf') && str_contains($r['pdf'], '0.000 0.000 1.000 rg'),
    ],
    [
        'nombre' => 'Caso 20: Salto de página automático (Y_logica > 792 pt)',
        'codigo' => "#titulo { Paginación Automática }\n" . str_repeat("#texto(justificado) { {$parrafoExtenso} }\n", 8),
        'check'  => fn(array $r): bool => $r['stats']['paginas'] >= 2,
    ],
    [
        'nombre' => 'Caso 21: Estructura base de Tablas (#tabla/#fila/#celda)',
        'codigo' => "#tabla {\n    #fila {\n        #celda{ A }\n        #celda{ B }\n    }\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(A) Tj') && str_contains($r['pdf'], '(B) Tj'),
    ],
    [
        'nombre' => 'Caso 22: Tablas completas (bordes=verdadero, fondo=gris)',
        'codigo' => "#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{ID} #celda{Nombre} }\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '0.878 0.878 0.878 rg') && str_contains($r['pdf'], 're S Q'),
    ],
    [
        'nombre' => 'Caso 23: Referencia cruzada simple (#referencia)',
        'codigo' => "#seccion(id=sec1) { Inicio }\n#texto { Ver la #referencia(sec1). }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '1.) Tj'),
    ],
    [
        'nombre' => 'Caso 23b: Forward reference hacia página 2 (Two-Pass real)',
        'codigo' => "#texto { Ver cierre en la página #referencia(sec_final). }\n#nuevapagina\n#seccion(id=sec_final) { Conclusiones }",
        'check'  => fn(array $r): bool => $r['stats']['paginas'] === 2 && str_contains($r['pdf'], "p\xE1gina 2.) Tj"),
    ],
    [
        'nombre' => 'Caso 24: Índice automático (#indicegeneral con puntos)',
        'codigo' => "#indicegeneral\n#seccion { Inicio }\n#nuevapagina\n#seccion { Marco Teórico }",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(2. Marco Te') && str_contains($r['pdf'], '(2) Tj') && str_contains($r['pdf'], ' . . .'),
    ],
    [
        'nombre' => 'Caso 25: Anidamiento profundo 1 (#lista dentro de #celda)',
        'codigo' => "#tabla(bordes=verdadero) {\n    #fila {\n        #celda { #lista(punto) { {Item 1} {Item 2} } }\n    }\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(Item 1) Tj') && str_contains($r['pdf'], '(Item 2) Tj') && str_contains($r['pdf'], 're S Q'),
    ],
    [
        'nombre' => 'Caso 26: Anidamiento profundo 2 (StyleStack colores y negrita)',
        'codigo' => "#texto(color=#0000FF) {\n    Todo azul pero esto es #fuerte(color=#FF0000){rojo y negrita} y vuelve a ser azul.\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(rojo y negrita) Tj') && str_contains($r['pdf'], '( y vuelve a ser azul.) Tj'),
    ],
    [
        'nombre' => 'Caso 27: Modo Ecuación en bloque centrada y numerada (1)',
        'codigo' => "#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {y - 2} } = 0\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '(\(1\)) Tj') && str_contains($r['pdf'], '= 0) Tj'),
    ],
    [
        'nombre' => 'Caso 28: Caja de advertencia con ícono vectorial PDF (#caja)',
        'codigo' => '#caja(tipo=alerta) { Error crítico del sistema. }',
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '1.000 0.953 0.804 rg') && str_contains($r['pdf'], ' h B Q'),
    ],
    [
        'nombre' => 'Caso 29: Prueba de Estrés (Documento Académico Completo)',
        'codigo' => "// Documento integral\n#definir(version) { 1.0 }\n#titulo(color=#0000FF) { Manual del Compilador }\n#autor { Equipo de Compiladores }\n#indicegeneral\n#seccion(id=intro) { Uso en versión #imprimir(version) }\n#texto(justificado) {\n    Pruebas integrales del #fuerte(color=#FF0000){lenguaje de marcado} descrito en la página #referencia(intro).\n}",
        'check'  => fn(array $r): bool => str_contains($r['pdf'], '/Title (Manual del Compilador)') && str_contains($r['pdf'], '(lenguaje de marcado) Tj') && str_contains($r['pdf'], '1.0'),
    ],
];

echo "=========================================================================\n";
echo " LATINEX — SUITE INTEGRAL TWO-PASS Y GENERADOR NATIVO %PDF-1.4\n";
echo "=========================================================================\n\n";

$tablaLexica = new TablaLexica();
$aprobados   = 0;

foreach ($casos as $caso) {
    $res = CompilarCasoCompleto($caso['codigo'], $tablaLexica, $tempAssetsDir);
    if (!$res['ok']) {
        printf("  [FAIL] %-62s | %s\n", $caso['nombre'], $res['error']);
        continue;
    }

    $invarianteOk = ($caso['check'])($res);
    if ($invarianteOk) {
        $aprobados++;
        printf(
            "  [PASS] %-62s | %d pág | %5d B | %2d objs\n",
            $caso['nombre'],
            $res['stats']['paginas'],
            $res['stats']['bytes'],
            $res['stats']['objetosPdf']
        );
    } else {
        printf("  [FAIL] %-62s | Falló invariante semántico/gráfico en el PDF emitido\n", $caso['nombre']);
    }
}

$total = count($casos);
echo "\n=========================================================================\n";
printf(" Casos %%PDF-1.4 aprobados: %d / %d\n", $aprobados, $total);
echo "=========================================================================\n";

if ($aprobados === $total) {
    echo "✔ ESTADO MOTOR TWO-PASS Y %PDF-1.4 NATIVO: 100% APROBADO\n\n";
    exit(0);
}

echo "✘ EXISTEN CASOS PDF FALLIDOS\n\n";
exit(1);

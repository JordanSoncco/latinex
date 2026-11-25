<?php

/* ============================================================
 * LATINEX — Suite Completa de Pruebas del Frente 1
 * ============================================================
 *
 * Verifica en conjunto:
 *   1. Analizador Léxico (Tokenizador + TablaLexica)
 *   2. Analizador Sintáctico (Autómata de 22 estados + pila)
 *   3. Analizador Semántico (TablaSimbolos + códigos 1500–1707)
 *
 * Bloque A: los 29 casos oficiales de la especificación +
 *           variantes (forward references y #fraccion atributos).
 * Bloque B: pruebas negativas obligatorias (léxico, sintáctico y
 *           todos los códigos de error semántico 1500–1707).
 *
 * Ejecutar: php test_frente1.php
 * ============================================================
 */

require_once __DIR__ . '/src/TablaLexica.php';
require_once __DIR__ . '/src/Tokenizador.php';
require_once __DIR__ . '/src/AnalizadorSintactico.php';
require_once __DIR__ . '/src/TablaSimbolos.php';
require_once __DIR__ . '/src/AnalizadorSemantico.php';

use Latinex\AnalizadorSemantico;
use Latinex\AnalizadorSintactico;
use Latinex\TablaLexica;
use Latinex\Tokenizador;
use const Latinex\T_ERROR;
use const Latinex\T_FIN;

$tablaLexica = new TablaLexica();

function EjecutarPipelineFrente1(string $codigo, TablaLexica $tablaLexica): array
{
    // 1. Léxico
    $tok = new Tokenizador($codigo, $tablaLexica);
    while (true) {
        $t = $tok->getToken();
        if ($t->token === T_ERROR) {
            return ['fase' => 'lexico', 'ok' => false, 'codigo' => T_ERROR, 'mensaje' => "Error léxico en Ln {$t->linea}, Col {$t->columna}: '{$t->lexema}'"];
        }
        if ($t->token === T_FIN) {
            break;
        }
    }

    // 2. Sintáctico
    $parser = new AnalizadorSintactico(new Tokenizador($codigo, $tablaLexica), false);
    if (!$parser->Analizar()) {
        $err = $parser->getError();
        return ['fase' => 'sintactico', 'ok' => false, 'codigo' => $err['estado'] ?? 0, 'mensaje' => $err['mensaje'] ?? 'Error sintáctico'];
    }

    // 3. Semántico
    $semantico = new AnalizadorSemantico(new Tokenizador($codigo, $tablaLexica));
    if (!$semantico->Analizar()) {
        $err = $semantico->getError();
        return ['fase' => 'semantico', 'ok' => false, 'codigo' => $err['codigo'] ?? 0, 'mensaje' => $err['mensaje'] ?? 'Error semántico'];
    }

    return [
        'fase'    => 'completo',
        'ok'      => true,
        'simbolos' => $semantico->getTablaSimbolos()->ObtenerTodos(),
    ];
}

// ── BLOQUE A: 29 CASOS OFICIALES DEL PDF + VARIANTES ─────────
$casosValidos = [
    'Caso 01: Estructura de texto'                  => "#texto {\n    Hola Mundo\n}",
    'Caso 02: Negrita y Cursiva'                    => "#texto {\n    Los Compañeritos #fuerte{Jordan} y #cursiva{Dominic}.\n}",
    'Caso 03: Metadatos (#titulo, #autor)'          => "#titulo { Tesis }\n#autor { Grupo 3 }",
    'Caso 04: Jerarquía (#seccion..#subseccion2)'   => "#seccion { Marco Teórico }\n#subseccion { Antecedentes }\n#subseccion2 { Investigaciones Previas }",
    'Caso 05: Salto de línea explícito'             => '#texto { Línea 1 } #salto #texto { Línea 2 }',
    'Caso 06: Salto de página explícito'            => '#texto { Página 1 } #nuevapagina #texto { Página 2 }',
    'Caso 07: Comentarios (//)'                     => "// Este texto no debe aparecer en el PDF.\n#texto { Solo esto se compila. }",
    'Caso 08: Escape de caracteres reservados'      => "#texto {\n    Usamos el símbolo del numeral así: \\#\n}",
    'Caso 09: Alineación derecha'                   => "// Bandera posicional\n#texto(derecha) {\n    Azángaro, 2026\n}",
    'Caso 10: Alineación justificada'               => "#texto(justificado) {\n    Párrafo con texto largo que debe tocar obligatoriamente ambos márgenes del documento para mostrar la justificación de texto PDF.\n}",
    'Caso 11: Lista no ordenada (punto)'            => '#lista(punto) { {Elemento A} {Elemento B} }',
    'Caso 12: Lista ordenada (numero)'              => '#lista(numero) { {Inicio} {Fin} }',
    'Caso 13: Uso de color y resaltado'             => "#texto(color=#FF0000) { Alerta } #salto\n#resaltado(color=#FFFF00) { Dato } #salto\n#texto(color=#1A73E8) { Texto azul institucional }",
    'Caso 14: Declaración de Macro'                 => '#definir(curso) { Compiladores }',
    'Caso 15: Invocación de Macro'                  => "#definir(curso) { Compiladores }\n#texto { Proyecto del curso de #imprimir(curso). }",
    'Caso 16: Inserción de Imágenes (JPEG)'         => '#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)',
    'Caso 17: Matemáticas en línea'                 => '#texto { El área es m#superindice{2} y el agua es H#subindice{2}O }',
    'Caso 18: Fracción con bloques anónimos'        => '#fraccion { {x+1} {y-2} }',
    'Caso 18b: Fracción con atributos'              => '#fraccion(numerador=x+1, denominador=y-2)',
    'Caso 19: Atributos combinados complejos'       => "#texto(fuente=Courier, color=#0000FF, centrado) {\n    Código final\n}",
    'Caso 20: Salto de página automático'           => "#texto { Párrafo 1 con contenido extenso. }\n#texto { Párrafo 2 adicional. }\n#texto { Párrafo 3 automático. }",
    'Caso 21: Estructura base de Tablas'            => "#tabla {\n    #fila {\n        #celda{ A }\n        #celda{ B }\n    }\n}",
    'Caso 22: Tablas completas (bordes y fondo)'    => "#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{ID} #celda{Nombre} }\n}",
    'Caso 23: Referencias cruzadas'                 => "#seccion(id=sec1) { Inicio }\n#texto { Ver la #referencia(sec1). }",
    'Caso 23b: Referencia cruzada hacia adelante'   => "#texto { Ver sección final en #referencia(sec_fin). }\n#seccion(id=sec_fin) { Cierre }",
    'Caso 24: Índice automático'                    => '#indicegeneral',
    'Caso 25: Anidamiento #lista dentro de #celda'  => "#tabla(bordes=verdadero) {\n    #fila {\n        #celda { #lista(punto) { {Item 1} {Item 2} } }\n    }\n}",
    'Caso 26: Anidamiento de estilos (StyleStack)'  => "#texto(color=#0000FF) {\n    Todo azul pero esto es #fuerte(color=#FF0000){rojo y negrita} y vuelve a ser azul.\n}",
    'Caso 27: Modo Ecuación en Bloque'              => "#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {y - 2} } = 0\n}",
    'Caso 28: Cajas de advertencia'                 => '#caja(tipo=alerta) { Error crítico del sistema. }',
    'Caso 29: Prueba de Estrés (Doc completo)'      => "// Documento integral\n#definir(version) { 1.0 }\n#titulo(color=#0000FF) { Manual del Compilador }\n#autor { Equipo de Compiladores }\n#indicegeneral\n#seccion(id=intro) { Uso en versión #imprimir(version) }\n#texto(justificado) {\n    Pruebas integrales del #fuerte(color=#FF0000){lenguaje de marcado} descrito en la página #referencia(intro).\n}",
];

// ── BLOQUE B: CASOS NEGATIVOS OBLIGATORIOS ───────────────────
$casosNegativos = [
    [
        'nombre'       => 'Error léxico: carácter inválido en atributos (@)',
        'codigo'       => '#texto(color=@) { Hola }',
        'faseEsperada' => 'lexico',
        'codigoEsp'    => T_ERROR,
    ],
    [
        'nombre'       => 'Error sintáctico: falta llave de apertura {',
        'codigo'       => '#texto',
        'faseEsperada' => 'sintactico',
        'codigoEsp'    => null,
    ],
    [
        'nombre'       => 'Error sintáctico: bloque sin cerrar }',
        'codigo'       => '#texto { Hola mundo',
        'faseEsperada' => 'sintactico',
        'codigoEsp'    => null,
    ],
    [
        'nombre'       => 'Error sintáctico: comando desconocido',
        'codigo'       => '#inventado { Hola }',
        'faseEsperada' => 'sintactico',
        'codigoEsp'    => null,
    ],
    [
        'nombre'       => 'Error sintáctico: #celda fuera de #fila',
        'codigo'       => '#tabla { #celda { A } }',
        'faseEsperada' => 'sintactico',
        'codigoEsp'    => null,
    ],
    [
        'nombre'       => 'Error semántico 1500: macro #imprimir no registrada',
        'codigo'       => '#texto { Curso de #imprimir(noExiste). }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1500,
    ],
    [
        'nombre'       => 'Error semántico 1600: macro definida vacía (no_asignado)',
        'codigo'       => "#definir(vacia) { }\n#texto { Valor: #imprimir(vacia) }",
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1600,
    ],
    [
        'nombre'       => 'Error semántico 1501: #referencia a etiqueta inexistente',
        'codigo'       => '#texto { Ver la #referencia(sec_inexistente). }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1501,
    ],
    [
        'nombre'       => 'Error semántico 1502: etiqueta de sección duplicada',
        'codigo'       => "#seccion(id=dup) { A }\n#seccion(id=dup) { B }",
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1502,
    ],
    [
        'nombre'       => 'Error semántico 1700a: #imagen sin atributo ruta',
        'codigo'       => '#imagen(ancho=50%)',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1700,
    ],
    [
        'nombre'       => 'Error semántico 1700b: #imagen con extensión no JPEG (.png)',
        'codigo'       => '#imagen(ruta=foto.png, ancho=50%)',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1700,
    ],
    [
        'nombre'       => 'Error semántico 1701a: #lista con tipo inválido',
        'codigo'       => '#lista(alerta) { {Elemento} }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1701,
    ],
    [
        'nombre'       => 'Error semántico 1701b: #lista vacía sin bloques anónimos',
        'codigo'       => '#lista(punto) { }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1701,
    ],
    [
        'nombre'       => 'Error semántico 1702a: #fraccion con un solo bloque',
        'codigo'       => '#fraccion { {x+1} }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1702,
    ],
    [
        'nombre'       => 'Error semántico 1702b: #fraccion(numerador=x) sin denominador',
        'codigo'       => '#fraccion(numerador=x)',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1702,
    ],
    [
        'nombre'       => 'Error semántico 1703: #caja con tipo inválido',
        'codigo'       => '#caja(tipo=peligro) { Advertencia }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1703,
    ],
    [
        'nombre'       => 'Error semántico 1704: #tabla con bordes inválido',
        'codigo'       => '#tabla(bordes=quizas) { #fila { #celda { A } } }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1704,
    ],
    [
        'nombre'       => 'Error semántico 1705: atributo color inválido',
        'codigo'       => '#texto(color=morado) { Texto }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1705,
    ],
    [
        'nombre'       => 'Error semántico 1706: atributo alineacion inválido',
        'codigo'       => '#texto(alineacion=oblicuo) { Texto }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1706,
    ],
    [
        'nombre'       => 'Error semántico 1707: atributo tamaño <= 0',
        'codigo'       => '#texto(tamaño=0) { Texto }',
        'faseEsperada' => 'semantico',
        'codigoEsp'    => 1707,
    ],
];

echo "=========================================================================\n";
echo " LATINEX — SUITE DE PRUEBAS INTEGRAL FRENTE 1 (LÉXICO + SINTÁCTICO + SEMÁNTICO)\n";
echo "=========================================================================\n\n";

$aprobadosValidos = 0;
foreach ($casosValidos as $nombre => $codigo) {
    $res = EjecutarPipelineFrente1($codigo, $tablaLexica);
    if ($res['ok']) {
        $aprobadosValidos++;
        printf("  [PASS] %-46s | Lex + Sint + Sem OK\n", $nombre);
    } else {
        printf("  [FAIL] %-46s | Falló en %s: %s\n", $nombre, $res['fase'], $res['mensaje']);
    }
}

echo "\n-------------------------------------------------------------------------\n";
echo " PRUEBAS NEGATIVAS (DETECCIÓN DE ERRORES LÉXICOS, SINTÁCTICOS Y SEMÁNTICOS)\n";
echo "-------------------------------------------------------------------------\n\n";

$aprobadosNeg = 0;
foreach ($casosNegativos as $caso) {
    $res = EjecutarPipelineFrente1($caso['codigo'], $tablaLexica);
    $faseOk = !$res['ok'] && $res['fase'] === $caso['faseEsperada'];
    $codOk  = $caso['codigoEsp'] === null || (($res['codigo'] ?? null) === $caso['codigoEsp']);

    if ($faseOk && $codOk) {
        $aprobadosNeg++;
        printf("  [PASS] %-56s | %s (%s)\n", $caso['nombre'], $res['fase'], (string) ($res['codigo'] ?? 'ok'));
    } else {
        printf(
            "  [FAIL] %-56s | esperado=%s(%s) obtenido=%s(%s) -> %s\n",
            $caso['nombre'],
            $caso['faseEsperada'],
            (string) ($caso['codigoEsp'] ?? '*'),
            $res['ok'] ? 'OK' : $res['fase'],
            (string) ($res['codigo'] ?? '-'),
            $res['mensaje'] ?? 'ACEPTADO INDEBIDAMENTE'
        );
    }
}

$totalValidos = count($casosValidos);
$totalNeg     = count($casosNegativos);
echo "\n=========================================================================\n";
echo sprintf(" Casos válidos aprobados   : %d / %d\n", $aprobadosValidos, $totalValidos);
echo sprintf(" Casos negativos aprobados : %d / %d\n", $aprobadosNeg, $totalNeg);
echo "=========================================================================\n";

if ($aprobadosValidos === $totalValidos && $aprobadosNeg === $totalNeg) {
    echo "✔ ESTADO FRENTE 1: 100% APROBADO\n\n";
    exit(0);
}

echo "✘ ESTADO FRENTE 1: EXISTEN PRUEBAS FALLIDAS\n\n";
exit(1);

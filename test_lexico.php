<?php

/* ============================================================
 * LATINEX — Suite de Pruebas Unitarias Léxicas
 * ============================================================
 *
 * Ejecuta los casos de prueba del documento de alcance y sintaxis
 * verificando que el Tokenizador y la Tabla Léxica funcionen al 100%
 * sin generar errores inesperados.
 */

require_once __DIR__ . '/src/TablaLexica.php';
require_once __DIR__ . '/src/Tokenizador.php';

use Latinex\TablaLexica;
use Latinex\Tokenizador;

$tablaLexica = new TablaLexica();
$entradas = $tablaLexica->exportar();

echo "=================================================================\n";
echo " LATINEX — SUITE DE PRUEBAS DEL ANALIZADOR LÉXICO\n";
echo " Total de tokens registrados en Tabla Léxica: " . count($entradas) . "\n";
echo "=================================================================\n\n";

$casos = [
    'Caso 01: Estructura de texto'           => '#texto { Hola Mundo }',
    'Caso 02: Negrita y Cursiva'             => '#texto { Los Compañeritos #fuerte{Jordan} y #cursiva{Dominic}. }',
    'Caso 03: Metadatos (#titulo, #autor)'   => "#titulo { Tesis }\n#autor { Grupo 3 }",
    'Caso 04: Jerarquía de secciones'        => "#seccion { Marco }\n#subseccion { Antecedentes }\n#subseccion2 { Previas }",
    'Caso 05: Salto de línea'                => '#texto { Línea 1 } #salto #texto { Línea 2 }',
    'Caso 06: Salto de página'               => '#texto { Pag 1 } #nuevapagina #texto { Pag 2 }',
    'Caso 07: Comentarios'                   => "// Ignorado\n#texto { Compilado }",
    'Caso 08: Escape de caracteres'          => '#texto { Numeral: \# y llaves: \{ \} }',
    'Caso 09: Alineación derecha'            => '#texto(derecha) { Azángaro, 2026 }',
    'Caso 10: Alineación justificada'        => '#texto(justificado) { Texto largo justificado. }',
    'Caso 11: Lista no ordenada (punto)'     => '#lista(punto) { {Item A} {Item B} }',
    'Caso 12: Lista ordenada (numero)'       => '#lista(numero) { {Primero} {Segundo} }',
    'Caso 13: Color y resaltado (#HEX)'      => "#texto(color=#FF0000) { Alerta } #salto\n#resaltado(color=#FFFF00) { Dato }",
    'Caso 14: Declaración de Macro'          => '#definir(curso) { Compiladores }',
    'Caso 15: Invocación de Macro'           => '#texto { Proyecto de #imprimir(curso). }',
    'Caso 16: Inserción de Imágenes'         => '#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)',
    'Caso 17: Superíndice y Subíndice'       => '#texto { Área m#superindice{2} y H#subindice{2}O }',
    'Caso 18: Fracciones matemáticas'        => '#fraccion(numerador=x+1, denominador=y-2)',
    'Caso 19: Atributos combinados'          => "#texto(fuente=Courier, color=#0000FF, centrado) { Codigo }",
    'Caso 21: Tablas base'                   => "#tabla { #fila { #celda{A} #celda{B} } }",
    'Caso 22: Tablas con bordes y fondo'     => "#tabla(bordes=verdadero) { #fila(fondo=gris) { #celda{ID} #celda{Nombre} } }",
    'Caso 23: Referencias cruzadas'          => "#seccion(id=sec1) { Inicio }\n#texto { Ver #referencia(sec1). }",
    'Caso 24: Índice automático'             => '#indicegeneral',
    'Caso 28: Cajas de advertencia'          => '#caja(tipo=alerta) { Error crítico detectado. }',
    'Caso 29: Documento completo'            => "#titulo(color=#0000FF) { Manual }\n#autor { Equipo }\n#seccion { Uso }\n#texto { Pruebas del #fuerte{lenguaje}. }",
];

$totalErrores = 0;
$totalTokens = 0;
$casosCorrectos = 0;

foreach ($casos as $titulo => $codigo) {
    $tok = new Tokenizador($codigo, $tablaLexica);
    $tokens = $tok->tokenizarTodo();

    $errores = array_filter($tokens, fn($t) => $t->token === Latinex\T_ERROR);
    $numTokens = count(array_filter($tokens, fn($t) => $t->token !== Latinex\T_FIN && $t->token !== Latinex\T_COMENTARIO));

    $totalErrores += count($errores);
    $totalTokens += $numTokens;

    if (count($errores) === 0) {
        $casosCorrectos++;
        printf("  [PASS] %-38s -> %2d tokens | 0 errores\n", $titulo, $numTokens);
    } else {
        printf("  [FAIL] %-38s -> %2d tokens | %d errores\n", $titulo, $numTokens, count($errores));
        foreach ($errores as $err) {
            echo "         ! " . $err->lexema . " (Ln " . $err->linea . ", Col " . $err->columna . ")\n";
        }
    }
}

echo "\n-----------------------------------------------------------------\n";
echo " Resumen de la suite:\n";
echo " Casos aprobados: {$casosCorrectos} / " . count($casos) . "\n";
echo " Tokens totales generados: {$totalTokens}\n";
echo " Errores léxicos totales: {$totalErrores}\n";
echo "-----------------------------------------------------------------\n";

if ($totalErrores === 0) {
    echo "✔ ESTADO: TODOS LOS CASOS PASARON CORRECTAMENTE (100% OK)\n\n";
    exit(0);
} else {
    echo "✘ ESTADO: SE ENCONTRARON {$totalErrores} ERRORES LÉXICOS\n\n";
    exit(1);
}

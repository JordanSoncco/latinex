<?php

/* ============================================================
 * DEMOSTRACIÓN EJECUTABLE DEL COMPILADOR LATINEX (FRENTE 1)
 * ============================================================
 *
 * Equivalente a EjecutarDemostracion() de graficador.php:
 *   1. Flujo de tokens (Tokenizador + TablaLexica)
 *   2. Análisis sintáctico con seguimiento de estados (AnalizadorSintactico)
 *   3. Análisis semántico y Tabla de Símbolos (AnalizadorSemantico)
 *   4. Invocación contractual del Ejecutor (Pasada 1 - Frente 2)
 *
 * Ejecutar por CLI:  php demo_compilador.php
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
use Latinex\TablaLexica;
use Latinex\Tokenizador;
use const Latinex\T_FIN;

function EjecutarDemostracion(): void
{
    $codigo = <<<'LTX'
// Demostración completa del compilador LATINEX
#definir(version) { 1.0 }
#definir(curso) { Compiladores }
#titulo(color=#0000FF) { Manual del Compilador LATINEX }
#autor { Grupo 3 — EPIS UNAP }
#fecha { Noviembre 2026 }
#indicegeneral
#seccion(id=intro) { Marco Teórico — Versión #imprimir(version) }
#subseccion { Antecedentes }
#subseccion2 { Investigaciones Previas }
#subseccion3 { Alcance del Compilador }
#subseccion4 { Validación de #imprimir(curso) }
#texto(justificado) {
    Bienvenidos al compilador #fuerte(color=#FF0000){LATINEX}, revisado en la página #referencia(intro) y ampliado en #referencia(cierre).
}
#lista(punto) { {Item A} {Item B} }
#tabla(bordes=verdadero) {
    #fila(fondo=gris) { #celda{Fase} #celda{Estado} }
    #fila { #celda{Semántico} #celda{Operativo} }
}
#ecuacion { #fraccion { {x#superindice{2} + 1} {y - 2} } = 0 }
#caja(tipo=exito) { Validación léxica, sintáctica y semántica completada. }
#seccion(id=cierre) { Conclusiones Finales }
LTX;

    $esCli = PHP_SAPI === 'cli';
    if (!$esCli) {
        echo '<pre>';
    }

    $tablaLexica = new TablaLexica();

    echo "=================================================================\n";
    echo " 1. FLUJO DE TOKENS (ANALIZADOR LÉXICO)\n";
    echo "=================================================================\n\n";

    $tokenizadorLexico = new Tokenizador($codigo, $tablaLexica);
    while (true) {
        $token = $tokenizadorLexico->getToken();
        printf(
            "Token(%3d) %-16s Lexema(%s) Ln(%d) Col(%d)\n",
            $token->token,
            \Latinex\NombreToken($token->token),
            $token->lexema ?? 'null',
            $token->linea,
            $token->columna
        );
        if ($token->token === T_FIN) {
            break;
        }
    }

    echo "\n=================================================================\n";
    echo " 2. ANÁLISIS SINTÁCTICO (AUTÓMATA DE 22 ESTADOS + PILA)\n";
    echo "=================================================================\n\n";

    $parser = new AnalizadorSintactico(new Tokenizador($codigo, $tablaLexica), true);
    $validoSintactico = $parser->Analizar();

    printf(
        "%-5s %-15s %-18s %-28s %-16s\n",
        'Paso',
        'Estado actual',
        'Token',
        'Lexema',
        'Estado siguiente'
    );

    foreach ($parser->getSeguimiento() as $paso) {
        $lex = (string) ($paso['lexema'] ?? 'null');
        if (mb_strlen($lex, 'UTF-8') > 25) {
            $lex = mb_substr($lex, 0, 22, 'UTF-8') . '...';
        }
        printf(
            "%-5d %-15d %-18s %-28s %-16s\n",
            $paso['paso'],
            $paso['estadoActual'],
            $paso['nombreToken'],
            $lex,
            (string) ($paso['estadoSiguiente'] ?? 'ERROR')
        );
    }

    echo "\nResultado sintáctico: ", $validoSintactico ? 'ACEPTADO' : 'RECHAZADO', "\n";

    if (!$validoSintactico) {
        echo $parser->getError()['mensaje'] . "\n";
        if (!$esCli) {
            echo '</pre>';
        }
        return;
    }

    echo "\n=================================================================\n";
    echo " 3. ANÁLISIS SEMÁNTICO (TABLA DE SÍMBOLOS Y REGLAS)\n";
    echo "=================================================================\n\n";

    $semantico       = new AnalizadorSemantico(new Tokenizador($codigo, $tablaLexica));
    $validoSemantico = $semantico->Analizar();

    printf(
        "%-16s %-8s %-12s %-34s %-12s\n",
        'Lexema',
        'Token',
        'Tipo',
        'Valor',
        'Estado'
    );

    foreach ($semantico->getTablaSimbolos()->ObtenerTodos() as $simbolo) {
        $val = (string) ($simbolo['valor'] ?? 'null');
        if (mb_strlen($val, 'UTF-8') > 31) {
            $val = mb_substr($val, 0, 28, 'UTF-8') . '...';
        }
        printf(
            "%-16s %-8d %-12s %-34s %-12s\n",
            $simbolo['lexema'],
            $simbolo['token'],
            $simbolo['tipo'],
            $val,
            $simbolo['estado']
        );
    }

    echo "\nResultado semantico: ", $validoSemantico ? 'ACEPTADO' : 'RECHAZADO', "\n";

    if (!$validoSemantico) {
        echo $semantico->getError()['mensaje'] . "\n";
        if (!$esCli) {
            echo '</pre>';
        }
        return;
    }

    echo "\n=================================================================\n";
    echo " 4. EJECUCIÓN TWO-PASS (CONTRATO FRENTE 2)\n";
    echo "=================================================================\n\n";

    $ejecutor = new Ejecutor(new Tokenizador($codigo, $tablaLexica));
    $ejecutor->Ejecutar();
    $resumen = $ejecutor->getResumen();
    echo 'Estado Ejecutor: ' . $resumen['estado'] . "\n";
    echo 'Elementos IR:    ' . $resumen['totalElementos'] . "\n";

    if (!$esCli) {
        echo '</pre>';
    }
}

if (
    PHP_SAPI === 'cli' ||
    (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)
) {
    EjecutarDemostracion();
}

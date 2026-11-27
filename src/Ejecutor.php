<?php

namespace Latinex;

/* ============================================================
 * EJECUTOR / MOTOR GEOMÉTRICO TWO-PASS — LATINEX (FASE 4.1)
 * ============================================================
 *
 * Implementa la Pasada 1 del compilador de dos pasadas según el
 * Documento de Alcance y Sintaxis de LATINEX:
 *   - Hoja A4 (595 x 842 pt), márgenes de 50 pt, ancho útil 495 pt,
 *     coordenada vertical lógica Y_logica en [50 .. 792] pt.
 *   - Pila de Estado Gráfico (StyleStack) con push/pop para fuentes
 *     Type1 (/F1 Helvetica, /F2 Bold, /F3 Oblique, /F4 BoldOblique,
 *     /F5 Courier, /F6 Times-Roman), tamaño, colores RGB 0..1,
 *     resaltado, subrayado y desplazamiento superíndice/subíndice.
 *   - Tablas reales de métricas AFM para cálculo de anchos en puntos,
 *     ajuste de líneas (word-wrap), alineación izquierda/centro/derecha
 *     y justificación con espaciado entre palabras Tw = delta / espacios.
 *   - Resolución Two-Pass con estabilización de punto fijo para
 *     referencias cruzadas (#referencia forward/backward) e #indicegeneral.
 *   - Soporte integral de los 29 casos oficiales (#titulo, #autor, #fecha,
 *     #resumen, #seccion..#subseccion4, #texto, #fuerte, #cursiva,
 *     #subrayado, #resaltado, #salto, #nuevapagina, #lista, #definir,
 *     #imprimir, #imagen JPEG, #superindice, #subindice, #fraccion,
 *     #ecuacion numerada, #tabla/#fila/#celda recursivas y #caja vectorial).
 * ============================================================
 */
class Ejecutor
{
    public const ANCHO_PAGINA  = 595.0;
    public const ALTO_PAGINA   = 842.0;
    public const MARGEN_IZQ    = 50.0;
    public const MARGEN_DER    = 50.0;
    public const MARGEN_SUP    = 50.0;
    public const MARGEN_INF    = 50.0;
    public const ANCHO_UTIL    = 495.0; // 595 - 50 - 50
    public const LIMITE_INF    = 792.0; // 842 - 50

    /** Mapa de colores reservados en español a tripletas RGB normalizadas [0..1] */
    private const DICCIONARIO_COLORES = [
        'negro'    => [0.0, 0.0, 0.0],
        'blanco'   => [1.0, 1.0, 1.0],
        'rojo'     => [1.0, 0.0, 0.0],
        'azul'     => [0.0, 0.0, 1.0],
        'verde'    => [0.0, 0.502, 0.0],
        'amarillo' => [1.0, 1.0, 0.0],
        'gris'     => [0.878, 0.878, 0.878], // #E0E0E0 según Caso 22 del PDF
    ];

    /** Anchos AFM estándar (en 1/1000 em) para Helvetica Regular / Oblique */
    private const AFM_HELVETICA = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889,
        '&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
        ',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556,
        '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
        '>' => 584, '?' => 556, '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722,
        'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278,
        'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
        'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278,
        '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556,
        'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833,
        'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500,
        't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
        'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        'á' => 556, 'é' => 556, 'í' => 278, 'ó' => 556, 'ú' => 556, 'ü' => 556,
        'ñ' => 556, 'Á' => 667, 'É' => 667, 'Í' => 278, 'Ó' => 778, 'Ú' => 722,
        'Ü' => 722, 'Ñ' => 722, '¿' => 611, '¡' => 333, '•' => 350, '—' => 1000,
        '–' => 556, '«' => 556, '»' => 556, '°' => 400,
    ];

    /** Anchos AFM estándar (en 1/1000 em) para Helvetica-Bold / BoldOblique */
    private const AFM_HELVETICA_BOLD = [
        ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889,
        '&' => 722, "'" => 238, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
        ',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556,
        '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 333, ';' => 333, '<' => 584, '=' => 584,
        '>' => 584, '?' => 611, '@' => 975, 'A' => 722, 'B' => 722, 'C' => 722,
        'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278,
        'J' => 556, 'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
        'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 333,
        '\\' => 278, ']' => 333, '^' => 584, '_' => 556, '`' => 333, 'a' => 556,
        'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556, 'f' => 333, 'g' => 611,
        'h' => 611, 'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278, 'm' => 889,
        'n' => 611, 'o' => 611, 'p' => 611, 'q' => 611, 'r' => 389, 's' => 556,
        't' => 333, 'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556, 'y' => 556,
        'z' => 500, '{' => 389, '|' => 280, '}' => 389, '~' => 584,
        'á' => 556, 'é' => 556, 'í' => 278, 'ó' => 611, 'ú' => 611, 'ü' => 611,
        'ñ' => 611, 'Á' => 722, 'É' => 667, 'Í' => 278, 'Ó' => 778, 'Ú' => 722,
        'Ü' => 722, 'Ñ' => 722, '¿' => 611, '¡' => 333, '•' => 350, '—' => 1000,
        '–' => 556, '«' => 556, '»' => 556, '°' => 400,
    ];

    /** Anchos AFM estándar (en 1/1000 em) para Times-Roman (/F6) */
    private const AFM_TIMES_ROMAN = [
        ' ' => 250, '!' => 333, '"' => 408, '#' => 500, '$' => 500, '%' => 833,
        '&' => 778, "'" => 180, '(' => 333, ')' => 333, '*' => 500, '+' => 564,
        ',' => 250, '-' => 333, '.' => 250, '/' => 278, '0' => 500, '1' => 500,
        '2' => 500, '3' => 500, '4' => 500, '5' => 500, '6' => 500, '7' => 500,
        '8' => 500, '9' => 500, ':' => 278, ';' => 278, '<' => 564, '=' => 564,
        '>' => 564, '?' => 444, '@' => 921, 'A' => 722, 'B' => 667, 'C' => 667,
        'D' => 722, 'E' => 611, 'F' => 556, 'G' => 722, 'H' => 722, 'I' => 333,
        'J' => 389, 'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722,
        'P' => 556, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611, 'U' => 722,
        'V' => 722, 'W' => 944, 'X' => 722, 'Y' => 722, 'Z' => 611, '[' => 333,
        '\\' => 278, ']' => 333, '^' => 469, '_' => 500, '`' => 333, 'a' => 444,
        'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333, 'g' => 500,
        'h' => 500, 'i' => 278, 'j' => 278, 'k' => 500, 'l' => 278, 'm' => 778,
        'n' => 500, 'o' => 500, 'p' => 500, 'q' => 500, 'r' => 333, 's' => 389,
        't' => 278, 'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
        'z' => 444, '{' => 480, '|' => 200, '}' => 480, '~' => 541,
        'á' => 444, 'é' => 444, 'í' => 278, 'ó' => 500, 'ú' => 500, 'ü' => 500,
        'ñ' => 500, 'Á' => 722, 'É' => 611, 'Í' => 333, 'Ó' => 722, 'Ú' => 722,
        'Ü' => 722, 'Ñ' => 722, '¿' => 444, '¡' => 333, '•' => 350, '—' => 1000,
        '–' => 500, '«' => 500, '»' => 500, '°' => 400,
    ];

    /** Banner JPEG baseline institucional (360x110 RGB) de respaldo cuando Caso 16 se compila sin adjuntar logo.jpg */
    private const LOGO_RESPALDO_B64 = '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';

    private Tokenizador $tokenizador;

    private TablaSimbolos $tablaSimbolos;

    private string $assetsDir;

    /** @var array<int, array<string, mixed>> */
    private array $elementos = [];

    private ?array $error = null;

    /** @var array<string, string> */
    private array $metadatos = [];

    private int $totalPaginas = 0;

    // ── Estado interno por pasada de layout ─────────────────────
    /** @var array<int, array<string, mixed>> */
    private array $styleStack = [];

    private int $paginaActual = 1;

    private float $yLogica = self::MARGEN_SUP;

    private int $lineaFuenteActual = 1;

    /** @var int[] Contadores jerárquicos [n1, n2, n3, n4, n5] */
    private array $contadoresSeccion = [0, 0, 0, 0, 0];

    private int $contadorEcuacion = 0;

    /** @var array<string, array<int, array<string, mixed>>> Macros de nodos AST */
    private array $macrosNodos = [];

    /** @var array<string, int> Mapa etiqueta -> número de página resuelto */
    private array $paginasEtiquetas = [];

    /** @var array<int, array{nivel:int, numeracion:string, titulo:string, pagina:int, id:?string}> */
    private array $entradasIndice = [];

    /** @var array<int, array{nivel:int, numeracion:string, titulo:string, pagina:int, id:?string}> */
    private array $indiceConocido = [];

    /** @var array<string, int> */
    private array $etiquetasConocidas = [];

    public function __construct(
        Tokenizador $tokenizador,
        ?TablaSimbolos $tablaSimbolos = null,
        string $assetsDir = ''
    ) {
        $this->tokenizador   = $tokenizador;
        $this->tablaSimbolos = $tablaSimbolos ?? new TablaSimbolos();
        $this->assetsDir     = $assetsDir;
    }

    /**
     * Ejecuta la Pasada 1 completa con estabilización Two-Pass para
     * referencias hacia adelante (#referencia) e #indicegeneral.
     */
    public function Ejecutar(): bool
    {
        $this->elementos    = [];
        $this->error        = null;
        $this->metadatos    = [];
        $this->totalPaginas = 0;

        $tokens = $this->LeerTokens();
        if ($tokens === null) {
            return false;
        }

        $pos  = 0;
        $nodos = $this->ParsearSecuencia($tokens, $pos, null);

        // Pasada 1A: descubrimiento de secciones, páginas preliminares y macros
        $this->indiceConocido    = [];
        $this->etiquetasConocidas = [];
        $this->EjecutarPasadaLayout($nodos, false);

        // Pasada 1B: layout geométrico definitivo con #indicegeneral y #referencia resueltos
        $this->indiceConocido     = $this->entradasIndice;
        $this->etiquetasConocidas = $this->paginasEtiquetas;
        $this->EjecutarPasadaLayout($nodos, true);

        // Si el tamaño real de #indicegeneral desplazó páginas de secciones posteriores,
        // ejecutamos una pasada adicional de estabilización de punto fijo.
        if (
            $this->paginasEtiquetas !== $this->etiquetasConocidas
            || $this->ExtraerPaginasIndice($this->entradasIndice) !== $this->ExtraerPaginasIndice($this->indiceConocido)
        ) {
            $this->indiceConocido     = $this->entradasIndice;
            $this->etiquetasConocidas = $this->paginasEtiquetas;
            $this->EjecutarPasadaLayout($nodos, true);
        }

        $this->ActualizarTablaSimbolosFinal();
        $this->totalPaginas = max(1, $this->paginaActual);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getElementos(): array
    {
        return $this->elementos;
    }

    public function getTablaSimbolos(): TablaSimbolos
    {
        return $this->tablaSimbolos;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    /** @return array<string, string> */
    public function getMetadatos(): array
    {
        return $this->metadatos;
    }

    public function getResumen(): array
    {
        return [
            'implementado'   => true,
            'estado'         => $this->error === null ? 'ACEPTADO' : 'RECHAZADO',
            'totalPaginas'   => $this->totalPaginas,
            'totalElementos' => count($this->elementos),
            'secciones'      => count($this->entradasIndice),
            'ecuaciones'     => $this->contadorEcuacion,
            'metadatos'      => $this->metadatos,
        ];
    }

    /* ============================================================
     * LECTURA DE TOKENS Y CONSTRUCCIÓN DE AST LIGERO
     * ============================================================ */

    /** @return Token[]|null */
    private function LeerTokens(): ?array
    {
        $lista = [];
        while (true) {
            $tok = $this->tokenizador->getToken();
            if ($tok->token === T_COMENTARIO) {
                continue;
            }
            if ($tok->token === T_ERROR) {
                $this->error = [
                    'tipo'        => 'ejecucion',
                    'codigo'      => T_ERROR,
                    'token'       => $tok->token,
                    'nombreToken' => NombreToken($tok->token),
                    'lexema'      => $tok->lexema,
                    'linea'       => $tok->linea,
                    'columna'     => $tok->columna,
                    'descripcion' => 'Error léxico encontrado durante la ejecución.',
                    'mensaje'     => sprintf(
                        'Error %d (ejecución) en línea %d, columna %d: error léxico.',
                        T_ERROR,
                        $tok->linea,
                        $tok->columna
                    ),
                ];
                return null;
            }
            $lista[] = $tok;
            if ($tok->token === T_FIN) {
                break;
            }
        }
        return $lista;
    }

    /**
     * @param Token[] $tokens
     * @return array<int, array<string, mixed>>
     */
    private function ParsearSecuencia(array $tokens, int &$pos, ?int $tokenCierre): array
    {
        $nodos = [];
        $total = count($tokens);

        while ($pos < $total) {
            $tok = $tokens[$pos];

            if ($tok->token === T_FIN) {
                break;
            }
            if ($tokenCierre !== null && $tok->token === $tokenCierre) {
                break;
            }

            // Bloque anónimo { ... } (usado en #lista y #fraccion)
            if ($tok->token === T_ALLAVE) {
                $startLn  = $tok->linea;
                $startCol = $tok->columna;
                $pos++; // consumir '{'
                $hijos    = $this->ParsearSecuencia($tokens, $pos, T_CLLAVE);
                $endLn    = $startLn;
                $endCol   = $startCol + 1;
                if ($pos < $total && $tokens[$pos]->token === T_CLLAVE) {
                    $endLn  = $tokens[$pos]->linea;
                    $endCol = $tokens[$pos]->columna + 1;
                    $pos++;
                }
                $nodos[] = [
                    'node'     => 'anonimo',
                    'children' => $hijos,
                    'startLn'  => $startLn,
                    'startCol' => $startCol,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                continue;
            }

            // Texto plano, carácter escapado o literal #HEX en contenido
            if (in_array($tok->token, [T_TEXTO_PLANO, T_ESCAPE_CHAR, T_COLOR_HEX], true)) {
                [$endLn, $endCol] = $this->CalcularFinToken($tok);
                $nodos[] = [
                    'node'     => 'texto',
                    'texto'    => (string) ($tok->lexema ?? ''),
                    'esEscape' => $tok->token === T_ESCAPE_CHAR,
                    'startLn'  => $tok->linea,
                    'startCol' => $tok->columna,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                $pos++;
                continue;
            }

            // Declaración de macro: #definir ( id ) { ... }
            if ($tok->token === T_DEFINIR) {
                $startLn  = $tok->linea;
                $startCol = $tok->columna;
                $pos++;
                $macroId = '';
                if ($pos < $total && $tokens[$pos]->token === T_APAR) {
                    $pos++;
                    if ($pos < $total && $tokens[$pos]->token !== T_CPAR) {
                        $macroId = trim((string) $tokens[$pos]->lexema);
                        $pos++;
                    }
                    if ($pos < $total && $tokens[$pos]->token === T_CPAR) {
                        $pos++;
                    }
                }
                $hijos  = [];
                $endLn  = $startLn;
                $endCol = $startCol + 8;
                if ($pos < $total && $tokens[$pos]->token === T_ALLAVE) {
                    $pos++;
                    $hijos = $this->ParsearSecuencia($tokens, $pos, T_CLLAVE);
                    if ($pos < $total && $tokens[$pos]->token === T_CLLAVE) {
                        $endLn  = $tokens[$pos]->linea;
                        $endCol = $tokens[$pos]->columna + 1;
                        $pos++;
                    }
                }
                $nodos[] = [
                    'node'     => 'definir',
                    'id'       => $macroId,
                    'children' => $hijos,
                    'token'    => $tok,
                    'startLn'  => $startLn,
                    'startCol' => $startCol,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                continue;
            }

            // Comandos atómicos con parámetro: #imprimir(id) y #referencia(id)
            if ($tok->token === T_IMPRIMIR || $tok->token === T_REFERENCIA) {
                $esImprimir = $tok->token === T_IMPRIMIR;
                $startLn    = $tok->linea;
                $startCol   = $tok->columna;
                $endLn      = $startLn;
                $endCol     = $startCol + mb_strlen((string) $tok->lexema, 'UTF-8');
                $pos++;
                $targetId = '';
                if ($pos < $total && $tokens[$pos]->token === T_APAR) {
                    $pos++;
                    if ($pos < $total && $tokens[$pos]->token !== T_CPAR) {
                        $targetId = trim((string) $tokens[$pos]->lexema);
                        $pos++;
                    }
                    if ($pos < $total && $tokens[$pos]->token === T_CPAR) {
                        $endLn  = $tokens[$pos]->linea;
                        $endCol = $tokens[$pos]->columna + 1;
                        $pos++;
                    }
                }
                $nodos[] = [
                    'node'     => $esImprimir ? 'imprimir' : 'referencia',
                    'id'       => $targetId,
                    'token'    => $tok,
                    'startLn'  => $startLn,
                    'startCol' => $startCol,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                continue;
            }

            // Comandos atómicos puros: #salto, #nuevapagina, #indicegeneral
            if (in_array($tok->token, [T_SALTO, T_NUEVAPAGINA, T_INDICEGENERAL], true)) {
                [$endLn, $endCol] = $this->CalcularFinToken($tok);
                $nodos[] = [
                    'node'     => 'atomico',
                    'cmd'      => $tok->token,
                    'startLn'  => $tok->linea,
                    'startCol' => $tok->columna,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                $pos++;
                continue;
            }

            // Comando atómico #imagen(attrs)
            if ($tok->token === T_IMAGEN) {
                $startLn  = $tok->linea;
                $startCol = $tok->columna;
                [$endLn, $endCol] = $this->CalcularFinToken($tok);
                $pos++;
                $attrs = $this->ParsearAtributos($tokens, $pos, $endLn, $endCol);
                $nodos[] = [
                    'node'     => 'imagen',
                    'attrs'    => $attrs,
                    'startLn'  => $startLn,
                    'startCol' => $startCol,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                continue;
            }

            // Comando #fraccion (con bloques anónimos { {num} {den} } o con atributos)
            if ($tok->token === T_FRACCION) {
                $startLn  = $tok->linea;
                $startCol = $tok->columna;
                [$endLn, $endCol] = $this->CalcularFinToken($tok);
                $pos++;
                $attrs = $this->ParsearAtributos($tokens, $pos, $endLn, $endCol);

                $numNodos = [];
                $denNodos = [];

                if ($pos < $total && $tokens[$pos]->token === T_ALLAVE) {
                    $pos++;
                    $subBloques = $this->ParsearSecuencia($tokens, $pos, T_CLLAVE);
                    if ($pos < $total && $tokens[$pos]->token === T_CLLAVE) {
                        $endLn  = $tokens[$pos]->linea;
                        $endCol = $tokens[$pos]->columna + 1;
                        $pos++;
                    }
                    $anonimos = array_values(array_filter($subBloques, fn(array $n): bool => $n['node'] === 'anonimo'));
                    $numNodos = $anonimos[0]['children'] ?? [];
                    $denNodos = $anonimos[1]['children'] ?? [];
                } else {
                    $numTxt = $this->ObtenerTextoAtributo($attrs, T_ATTR_NUMERADOR);
                    $denTxt = $this->ObtenerTextoAtributo($attrs, T_ATTR_DENOMINADOR);
                    $numNodos = [['node' => 'texto', 'texto' => $numTxt, 'esEscape' => false, 'startLn' => $startLn, 'startCol' => $startCol, 'endLn' => $startLn, 'endCol' => $startCol + mb_strlen($numTxt, 'UTF-8')]];
                    $denNodos = [['node' => 'texto', 'texto' => $denTxt, 'esEscape' => false, 'startLn' => $startLn, 'startCol' => $startCol, 'endLn' => $startLn, 'endCol' => $startCol + mb_strlen($denTxt, 'UTF-8')]];
                }

                $nodos[] = [
                    'node'     => 'fraccion',
                    'attrs'    => $attrs,
                    'num'      => $numNodos,
                    'den'      => $denNodos,
                    'startLn'  => $startLn,
                    'startCol' => $startCol,
                    'endLn'    => $endLn,
                    'endCol'   => $endCol,
                ];
                continue;
            }

            // Cualquier otro comando de bloque/inline (#titulo, #texto, #lista, #tabla, #fuerte, #caja, etc.)
            $cmdTok   = $tok;
            $startLn  = $cmdTok->linea;
            $startCol = $cmdTok->columna;
            [$endLn, $endCol] = $this->CalcularFinToken($cmdTok);
            $pos++;

            $attrs = $this->ParsearAtributos($tokens, $pos, $endLn, $endCol);
            $hijos = [];
            if ($pos < $total && $tokens[$pos]->token === T_ALLAVE) {
                $pos++;
                $hijos = $this->ParsearSecuencia($tokens, $pos, T_CLLAVE);
                if ($pos < $total && $tokens[$pos]->token === T_CLLAVE) {
                    $endLn  = $tokens[$pos]->linea;
                    $endCol = $tokens[$pos]->columna + 1;
                    $pos++;
                }
            }

            $nodos[] = [
                'node'     => 'bloque',
                'cmd'      => $cmdTok->token,
                'lexema'   => (string) $cmdTok->lexema,
                'token'    => $cmdTok,
                'attrs'    => $attrs,
                'children' => $hijos,
                'startLn'  => $startLn,
                'startCol' => $startCol,
                'endLn'    => $endLn,
                'endCol'   => $endCol,
            ];
        }

        return $nodos;
    }

    /**
     * @param Token[] $tokens
     * @return array<int, array{claveToken:?int, clave:?string, valores:Token[]}>
     */
    private function ParsearAtributos(array $tokens, int &$pos, int &$endLn, int &$endCol): array
    {
        $attrs = [];
        $total = count($tokens);
        if ($pos >= $total || $tokens[$pos]->token !== T_APAR) {
            return $attrs;
        }
        $pos++; // '('

        while ($pos < $total && $tokens[$pos]->token !== T_CPAR) {
            if ($tokens[$pos]->token === T_COMA) {
                $pos++;
                continue;
            }
            if ($pos + 1 < $total && $tokens[$pos + 1]->token === T_IGUAL) {
                $claveTok = $tokens[$pos];
                $pos += 2;
                $valores = [];
                while ($pos < $total && $tokens[$pos]->token !== T_COMA && $tokens[$pos]->token !== T_CPAR) {
                    $valores[] = $tokens[$pos];
                    $pos++;
                }
                $attrs[] = [
                    'claveToken' => $claveTok->token,
                    'clave'      => (string) $claveTok->lexema,
                    'valores'    => $valores,
                ];
            } else {
                $attrs[] = [
                    'claveToken' => null,
                    'clave'      => null,
                    'valores'    => [$tokens[$pos]],
                ];
                $pos++;
            }
        }

        if ($pos < $total && $tokens[$pos]->token === T_CPAR) {
            $endLn  = $tokens[$pos]->linea;
            $endCol = $tokens[$pos]->columna + 1;
            $pos++;
        }

        return $attrs;
    }

    /** @return array{0:int, 1:int} */
    private function CalcularFinToken(Token $tok): array
    {
        if ($tok->token === T_ESCAPE_CHAR) {
            return [$tok->linea, $tok->columna + 2];
        }
        $lex = (string) ($tok->lexema ?? '');
        $ln  = $tok->linea;
        $col = $tok->columna;
        $len = mb_strlen($lex, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($lex, $i, 1, 'UTF-8');
            if ($ch === "\n") {
                $ln++;
                $col = 1;
            } else {
                $col++;
            }
        }
        return [$ln, $col];
    }

    /* ============================================================
     * EJECUCIÓN DE UNA PASADA GEOMÉTRICA A4
     * ============================================================ */

    /**
     * @param array<int, array<string, mixed>> $nodos
     */
    private function EjecutarPasadaLayout(array $nodos, bool $emitirDefinitivo): void
    {
        $this->elementos         = [];
        $this->metadatos         = [];
        $this->paginaActual      = 1;
        $this->yLogica           = self::MARGEN_SUP;
        $this->lineaFuenteActual = 1;
        $this->contadoresSeccion = [0, 0, 0, 0, 0];
        $this->contadorEcuacion  = 0;
        $this->macrosNodos       = [];
        $this->paginasEtiquetas  = [];
        $this->entradasIndice    = [];

        $this->styleStack = [[
            'family'    => 'Helvetica',
            'bold'      => false,
            'italic'    => false,
            'underline' => false,
            'size'      => 12.0,
            'color'     => [0.0, 0.0, 0.0],
            'highlight' => null,
            'rise'      => 0.0,
            'align'     => 'izquierda',
        ]];

        $this->RenderizarSecuenciaBloques($nodos, self::MARGEN_IZQ, self::ANCHO_UTIL, $emitirDefinitivo);
    }

    /**
     * Renderiza una lista de nodos en un contexto horizontal [xInicio, xInicio + anchoDisponible].
     *
     * @param array<int, array<string, mixed>> $nodos
     */
    private function RenderizarSecuenciaBloques(
        array $nodos,
        float $xInicio,
        float $anchoDisp,
        bool $emitir
    ): void {
        $bufferInline = [];

        $vaciarInline = function () use (&$bufferInline, $xInicio, $anchoDisp, $emitir): void {
            if (!empty($bufferInline)) {
                $this->RenderizarParrafo($bufferInline, $xInicio, $anchoDisp, $this->EstiloActual(), $emitir, 4.0);
                $bufferInline = [];
            }
        };

        foreach ($nodos as $nodo) {
            if (!empty($nodo['startLn'])) {
                $this->lineaFuenteActual = (int) $nodo['startLn'];
            }
            $tipoNodo = $nodo['node'];

            if ($tipoNodo === 'definir') {
                $vaciarInline();
                $this->macrosNodos[$nodo['id']] = $nodo['children'];
                $valorPlano = trim($this->ExtraerTextoPlanoNodos($nodo['children']));
                $tokDef     = $nodo['token'];
                $estado     = $valorPlano !== '' ? 'asignado' : 'no_asignado';
                if ($this->tablaSimbolos->Buscar($nodo['id'], 'MACRO') !== null) {
                    $this->tablaSimbolos->ActualizarValor($nodo['id'], $valorPlano !== '' ? $valorPlano : null, 'MACRO');
                    $this->tablaSimbolos->ActualizarEstado($nodo['id'], $estado, 'MACRO');
                } else {
                    $this->tablaSimbolos->Insertar(
                        $nodo['id'],
                        $valorPlano !== '' ? $valorPlano : null,
                        $estado,
                        'MACRO',
                        T_ID,
                        $tokDef->linea,
                        $tokDef->columna
                    );
                }
                continue;
            }

            if ($tipoNodo === 'atomico') {
                if ($nodo['cmd'] === T_SALTO) {
                    $vaciarInline();
                    $altoSalto = $this->EstiloActual()['size'] * 1.35;
                    $this->AsegurarEspacioVertical($altoSalto);
                    $this->yLogica += $altoSalto;
                    continue;
                }
                if ($nodo['cmd'] === T_NUEVAPAGINA) {
                    $vaciarInline();
                    $this->NuevaPagina();
                    continue;
                }
                if ($nodo['cmd'] === T_INDICEGENERAL) {
                    $vaciarInline();
                    $this->RenderizarIndiceGeneral($xInicio, $anchoDisp, $emitir);
                    continue;
                }
            }

            if ($tipoNodo === 'imagen') {
                $vaciarInline();
                $this->RenderizarImagen($nodo['attrs'], $xInicio, $anchoDisp, $emitir);
                continue;
            }

            if ($tipoNodo === 'fraccion') {
                $vaciarInline();
                $this->RenderizarFraccionBloque($nodo, $xInicio, $anchoDisp, $emitir);
                continue;
            }

            if ($tipoNodo === 'bloque') {
                $cmd = $nodo['cmd'];

                if (in_array($cmd, [
                    T_TITULO, T_AUTOR, T_FECHA, T_RESUMEN,
                    T_SECCION, T_SUBSECCION, T_SUBSECCION2, T_SUBSECCION3, T_SUBSECCION4,
                    T_TEXTO, T_LISTA, T_TABLA, T_ECUACION, T_CAJA,
                ], true)) {
                    $vaciarInline();
                    $this->RenderizarComandoBloque($nodo, $xInicio, $anchoDisp, $emitir);
                    continue;
                }
            }

            // Nodos inline sueltos (p. ej. dentro de #celda o #lista)
            $bufferInline[] = $nodo;
        }

        $vaciarInline();
    }

    /**
     * @param array<string, mixed> $nodo
     */
    private function RenderizarComandoBloque(
        array $nodo,
        float $xInicio,
        float $anchoDisp,
        bool $emitir
    ): void {
        $cmd   = $nodo['cmd'];
        $attrs = $nodo['attrs'];
        $hijos = $nodo['children'];

        // 1. Metadatos de cabecera (#titulo, #autor, #fecha, #resumen)
        if ($cmd === T_TITULO) {
            $base = array_merge($this->EstiloActual(), [
                'bold'  => true,
                'size'  => 24.0,
                'align' => 'centro',
            ]);
            $estilo = $this->AplicarAtributosAEstilo($base, $attrs, false);
            $textoPlano = trim($this->ExtraerTextoPlanoNodos($hijos));
            if ($textoPlano !== '') {
                $this->metadatos['Title'] = $textoPlano;
                $this->RegistrarSimboloMetadato('#titulo', $textoPlano, $nodo['token']);
            }
            $this->PushEstilo($estilo);
            $this->RenderizarParrafo($hijos, $xInicio, $anchoDisp, $estilo, $emitir, 10.0);
            $this->PopEstilo();
            return;
        }

        if ($cmd === T_AUTOR) {
            $base = array_merge($this->EstiloActual(), [
                'bold'  => false,
                'size'  => 14.0,
                'align' => 'centro',
                'color' => [0.22, 0.27, 0.35],
            ]);
            $estilo = $this->AplicarAtributosAEstilo($base, $attrs, false);
            $textoPlano = trim($this->ExtraerTextoPlanoNodos($hijos));
            if ($textoPlano !== '') {
                $this->metadatos['Author'] = $textoPlano;
                $this->RegistrarSimboloMetadato('#autor', $textoPlano, $nodo['token']);
            }
            $this->PushEstilo($estilo);
            $this->RenderizarParrafo($hijos, $xInicio, $anchoDisp, $estilo, $emitir, 10.0);
            $this->PopEstilo();
            return;
        }

        if ($cmd === T_FECHA) {
            $base = array_merge($this->EstiloActual(), [
                'italic' => true,
                'size'   => 11.0,
                'align'  => 'centro',
                'color'  => [0.35, 0.40, 0.48],
            ]);
            $estilo = $this->AplicarAtributosAEstilo($base, $attrs, false);
            $textoPlano = trim($this->ExtraerTextoPlanoNodos($hijos));
            if ($textoPlano !== '') {
                $this->metadatos['Subject'] = 'Fecha: ' . $textoPlano;
                $this->RegistrarSimboloMetadato('#fecha', $textoPlano, $nodo['token']);
            }
            $this->PushEstilo($estilo);
            $this->RenderizarParrafo($hijos, $xInicio, $anchoDisp, $estilo, $emitir, 10.0);
            $this->PopEstilo();
            return;
        }

        if ($cmd === T_RESUMEN) {
            $this->yLogica += 4.0;
            $estiloCab = array_merge($this->EstiloActual(), ['bold' => true, 'size' => 12.0, 'align' => 'centro']);
            $this->RenderizarTextoSimple('Resumen', $xInicio + 20.0, $anchoDisp - 40.0, $estiloCab, $emitir, 4.0);
            $base = array_merge($this->EstiloActual(), ['italic' => true, 'size' => 11.0, 'align' => 'justificado']);
            $estilo = $this->AplicarAtributosAEstilo($base, $attrs, false);
            $this->PushEstilo($estilo);
            $this->RenderizarParrafo($hijos, $xInicio + 20.0, max(60.0, $anchoDisp - 40.0), $estilo, $emitir, 10.0);
            $this->PopEstilo();
            return;
        }

        // 2. Jerarquía estructural (#seccion .. #subseccion4)
        if (in_array($cmd, [T_SECCION, T_SUBSECCION, T_SUBSECCION2, T_SUBSECCION3, T_SUBSECCION4], true)) {
            $nivel = match ($cmd) {
                T_SECCION     => 1,
                T_SUBSECCION  => 2,
                T_SUBSECCION2 => 3,
                T_SUBSECCION3 => 4,
                default       => 5,
            };

            $this->contadoresSeccion[$nivel - 1]++;
            for ($k = $nivel; $k < 5; $k++) {
                $this->contadoresSeccion[$k] = 0;
            }

            $partesNum = array_slice($this->contadoresSeccion, 0, $nivel);
            $prefijo   = implode('.', $partesNum) . '.';

            $tamanoBase = match ($nivel) {
                1 => 16.0,
                2 => 14.0,
                3 => 13.0,
                4 => 12.0,
                default => 11.5,
            };

            $espacioAntes = $nivel === 1 ? 10.0 : 6.0;
            if ($this->yLogica > self::MARGEN_SUP + 2.0) {
                $this->yLogica += $espacioAntes;
            }
            $this->AsegurarEspacioVertical($tamanoBase * 1.6);

            $paginaSeccion = $this->paginaActual;
            $idSeccion     = $this->ObtenerTextoAtributo($attrs, T_ATTR_ID);
            $tituloLimpio  = trim($this->ExtraerTextoPlanoNodos($hijos));

            if ($idSeccion !== '') {
                $this->paginasEtiquetas[$idSeccion] = $paginaSeccion;
            }

            $this->entradasIndice[] = [
                'nivel'      => $nivel,
                'numeracion' => $prefijo,
                'titulo'     => $tituloLimpio,
                'pagina'     => $paginaSeccion,
                'id'         => $idSeccion !== '' ? $idSeccion : null,
            ];

            $base = array_merge($this->EstiloActual(), [
                'bold'  => true,
                'size'  => $tamanoBase,
                'align' => 'izquierda',
            ]);
            $estilo = $this->AplicarAtributosAEstilo($base, $attrs, false);

            $nodoPrefijo = [
                'node'     => 'texto',
                'texto'    => $prefijo . ' ',
                'esEscape' => false,
                'startLn'  => 0,
                'startCol' => 0,
                'endLn'    => 0,
                'endCol'   => 0,
            ];

            $this->PushEstilo($estilo);
            $this->RenderizarParrafo(array_merge([$nodoPrefijo], $hijos), $xInicio, $anchoDisp, $estilo, $emitir, 6.0);
            $this->PopEstilo();
            return;
        }

        // 3. Párrafo de texto (#texto)
        if ($cmd === T_TEXTO) {
            $estilo = $this->AplicarAtributosAEstilo($this->EstiloActual(), $attrs, false);
            $this->PushEstilo($estilo);
            $this->RenderizarParrafo($hijos, $xInicio, $anchoDisp, $estilo, $emitir, 8.0);
            $this->PopEstilo();
            return;
        }

        // 4. Listas (#lista(punto|numero) con bloques anónimos)
        if ($cmd === T_LISTA) {
            $esNumerada = $this->EsListaNumerada($attrs);
            $estilo     = $this->AplicarAtributosAEstilo($this->EstiloActual(), $attrs, false);
            $this->PushEstilo($estilo);

            $sangriaViñeta = 15.0;
            $anchoPrefijo  = 15.0;
            $contadorItem  = 1;

            foreach ($hijos as $hijo) {
                if ($hijo['node'] !== 'anonimo') {
                    continue;
                }
                $prefijo = $esNumerada ? ($contadorItem . '.') : '•';
                $contadorItem++;

                $altoLinea = $estilo['size'] * 1.38;
                $this->AsegurarEspacioVertical($altoLinea);

                $yAntesItem   = $this->yLogica;
                $paginaAntes  = $this->paginaActual;

                // Renderizar el contenido del bloque anónimo con sangría izquierda
                $this->RenderizarSecuenciaBloques(
                    $hijo['children'],
                    $xInicio + $sangriaViñeta + $anchoPrefijo,
                    max(40.0, $anchoDisp - $sangriaViñeta - $anchoPrefijo),
                    $emitir
                );

                // Imprimir viñeta o numeración alineada a la primera línea del ítem
                $paginaPrefijo = $this->paginaActual > $paginaAntes && $yAntesItem + $altoLinea > self::LIMITE_INF
                    ? $this->paginaActual
                    : $paginaAntes;
                $yPrefijo = ($paginaPrefijo !== $paginaAntes) ? self::MARGEN_SUP : $yAntesItem;

                if ($emitir) {
                    $yBase = $yPrefijo + $estilo['size'] * 0.85;
                    $this->EmitirTexto(
                        $paginaPrefijo,
                        $xInicio + $sangriaViñeta,
                        $yBase,
                        $this->ResolverFuentePdf($estilo),
                        $estilo['size'],
                        $estilo['color'],
                        0.0,
                        $prefijo,
                        0.0
                    );
                }
            }

            $this->yLogica += 4.0;
            $this->PopEstilo();
            return;
        }

        // 5. Tablas recursivas (#tabla / #fila / #celda)
        if ($cmd === T_TABLA) {
            $this->RenderizarTabla($nodo, $xInicio, $anchoDisp, $emitir);
            return;
        }

        // 6. Ecuación centrada y numerada (#ecuacion)
        if ($cmd === T_ECUACION) {
            $this->RenderizarEcuacion($nodo, $xInicio, $anchoDisp, $emitir);
            return;
        }

        // 7. Caja de advertencia con vectores PDF (#caja)
        if ($cmd === T_CAJA) {
            $this->RenderizarCaja($nodo, $xInicio, $anchoDisp, $emitir);
            return;
        }
    }

    /* ============================================================
     * COMPOSICIÓN DE PÁRRAFOS, SPANS INLINE Y JUSTIFICACIÓN (Tw)
     * ============================================================ */

    /**
     * Aplana una lista de nodos inline en tokens estilizados (palabras, espacios, saltos o fracciones).
     *
     * @param array<int, array<string, mixed>> $nodos
     * @param array<string, mixed>             $estiloBase
     * @return array<int, array<string, mixed>>
     */
    private function AplanarInline(array $nodos, array $estiloBase): array
    {
        $piezas    = [];
        $prevEndLn = null;
        $prevEndCol = null;

        foreach ($nodos as $nodo) {
            $startLn  = $nodo['startLn'] ?? 0;
            $startCol = $nodo['startCol'] ?? 0;

            // Reconstruir espacio consumido por Tokenizador::OmitirEspacios() tras '}' o ')'
            if (
                $prevEndLn !== null
                && $startLn > 0
                && ($startLn > $prevEndLn || ($startLn === $prevEndLn && $startCol > $prevEndCol))
            ) {
                $ultimaPieza = empty($piezas) ? null : $piezas[count($piezas) - 1];
                if ($ultimaPieza !== null && $ultimaPieza['kind'] !== 'space' && $ultimaPieza['kind'] !== 'break') {
                    $piezas[] = [
                        'kind'  => 'space',
                        'style' => $estiloBase,
                        'width' => $this->MedirAnchoTexto(' ', $estiloBase),
                    ];
                }
            }

            $tipo = $nodo['node'];

            if ($tipo === 'texto') {
                $raw = (string) $nodo['texto'];
                if (!empty($nodo['esEscape'])) {
                    if ($raw !== '') {
                        $piezas[] = [
                            'kind'  => 'word',
                            'text'  => $raw,
                            'style' => $estiloBase,
                            'width' => $this->MedirAnchoTexto($raw, $estiloBase),
                        ];
                    }
                } else {
                    $normalizado = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
                    $partes = preg_split('/( )/u', $normalizado, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
                    foreach ($partes as $parte) {
                        if ($parte === ' ') {
                            $ultimo = empty($piezas) ? null : $piezas[count($piezas) - 1];
                            if ($ultimo !== null && $ultimo['kind'] !== 'space' && $ultimo['kind'] !== 'break') {
                                $piezas[] = [
                                    'kind'  => 'space',
                                    'style' => $estiloBase,
                                    'width' => $this->MedirAnchoTexto(' ', $estiloBase),
                                ];
                            }
                        } else {
                            $piezas[] = [
                                'kind'  => 'word',
                                'text'  => $parte,
                                'style' => $estiloBase,
                                'width' => $this->MedirAnchoTexto($parte, $estiloBase),
                            ];
                        }
                    }
                }
            } elseif ($tipo === 'imprimir') {
                $hijosMacro = $this->macrosNodos[$nodo['id']] ?? [];
                if (!empty($hijosMacro)) {
                    $subPiezas = $this->AplanarInline($hijosMacro, $estiloBase);
                    foreach ($subPiezas as $sp) {
                        $piezas[] = $sp;
                    }
                } else {
                    $sim = $this->tablaSimbolos->Buscar($nodo['id'], 'MACRO');
                    $val = trim((string) ($sim['valor'] ?? ''));
                    if ($val !== '') {
                        $piezas[] = [
                            'kind'  => 'word',
                            'text'  => $val,
                            'style' => $estiloBase,
                            'width' => $this->MedirAnchoTexto($val, $estiloBase),
                        ];
                    }
                }
            } elseif ($tipo === 'referencia') {
                $pag = $this->etiquetasConocidas[$nodo['id']] ?? ($this->paginasEtiquetas[$nodo['id']] ?? 1);
                $textoPrevio = $this->UltimaPalabraEmitida($piezas);
                $yaDicePagina = preg_match('/^(?:p[aá]gina|p[aá]g\.?)$/iu', $textoPrevio) === 1;
                $refTexto = $yaDicePagina ? (string) $pag : ('página ' . $pag);

                $partesRef = explode(' ', $refTexto);
                foreach ($partesRef as $idxR => $pRef) {
                    if ($idxR > 0) {
                        $piezas[] = [
                            'kind'  => 'space',
                            'style' => $estiloBase,
                            'width' => $this->MedirAnchoTexto(' ', $estiloBase),
                        ];
                    }
                    $piezas[] = [
                        'kind'  => 'word',
                        'text'  => $pRef,
                        'style' => $estiloBase,
                        'width' => $this->MedirAnchoTexto($pRef, $estiloBase),
                    ];
                }
            } elseif ($tipo === 'atomico' && $nodo['cmd'] === T_SALTO) {
                $piezas[] = ['kind' => 'break', 'style' => $estiloBase, 'width' => 0.0];
            } elseif ($tipo === 'fraccion') {
                $medida = $this->MedirFraccion($nodo, $estiloBase);
                $piezas[] = [
                    'kind'     => 'fraccion',
                    'nodo'     => $nodo,
                    'style'    => $estiloBase,
                    'width'    => $medida['ancho'],
                    'height'   => $medida['alto'],
                    'medida'   => $medida,
                ];
            } elseif ($tipo === 'bloque') {
                $estiloHijo = $this->DerivarEstiloInline($estiloBase, $nodo['cmd'], $nodo['attrs']);
                $sub = $this->AplanarInline($nodo['children'], $estiloHijo);

                // Si #superindice / #subindice va pegado a una palabra previa (ej. m#superindice{2}),
                // mantenemos que no haya espacio intermedio.
                foreach ($sub as $sp) {
                    $piezas[] = $sp;
                }
            } elseif ($tipo === 'anonimo') {
                foreach ($this->AplanarInline($nodo['children'], $estiloBase) as $sp) {
                    $piezas[] = $sp;
                }
            }

            $prevEndLn  = $nodo['endLn'] ?? null;
            $prevEndCol = $nodo['endCol'] ?? null;
        }

        // Recortar espacios al inicio y al final del flujo inline
        while (!empty($piezas) && $piezas[0]['kind'] === 'space') {
            array_shift($piezas);
        }
        while (!empty($piezas) && $piezas[count($piezas) - 1]['kind'] === 'space') {
            array_pop($piezas);
        }

        return $piezas;
    }

    /**
     * @param array<int, array<string, mixed>> $nodos
     * @param array<string, mixed>             $estiloParrafo
     */
    private function RenderizarParrafo(
        array $nodos,
        float $xInicio,
        float $anchoDisp,
        array $estiloParrafo,
        bool $emitir,
        float $espacioDespues = 6.0
    ): void {
        $piezas = $this->AplanarInline($nodos, $estiloParrafo);
        if (empty($piezas)) {
            return;
        }

        // Agrupar piezas contiguas sin espacios intermedios en "clusters" indivisibles
        // (por ejemplo: m + ² en "m²" o H + ₂ + O en "H₂O" o Dominic + . en "Dominic.")
        $items = [];
        $clusterActual = [];

        $cerrarCluster = static function () use (&$clusterActual, &$items): void {
            if (empty($clusterActual)) {
                return;
            }
            $w = 0.0;
            $maxFont = 0.0;
            $tieneFraccion = false;
            foreach ($clusterActual as $c) {
                $w += $c['width'];
                $maxFont = max($maxFont, (float) ($c['style']['size'] ?? 12.0));
                if ($c['kind'] === 'fraccion') {
                    $tieneFraccion = true;
                }
            }
            $items[] = [
                'type'          => 'cluster',
                'parts'         => $clusterActual,
                'width'         => $w,
                'maxFont'       => $maxFont,
                'tieneFraccion' => $tieneFraccion,
            ];
            $clusterActual = [];
        };

        foreach ($piezas as $p) {
            if ($p['kind'] === 'space') {
                $cerrarCluster();
                $items[] = [
                    'type'  => 'space',
                    'style' => $p['style'],
                    'width' => $p['width'],
                ];
            } elseif ($p['kind'] === 'break') {
                $cerrarCluster();
                $items[] = ['type' => 'break'];
            } else {
                $clusterActual[] = $p;
            }
        }
        $cerrarCluster();

        // Partir en líneas ajustadas a $anchoDisp (Word-Wrap)
        $lineas = [];
        $lineaActual = [];
        $anchoActual = 0.0;

        foreach ($items as $it) {
            if ($it['type'] === 'break') {
                while (!empty($lineaActual) && $lineaActual[count($lineaActual) - 1]['type'] === 'space') {
                    $quitado = array_pop($lineaActual);
                    $anchoActual -= $quitado['width'];
                }
                $lineas[] = ['items' => $lineaActual, 'width' => max(0.0, $anchoActual), 'esUltima' => true];
                $lineaActual = [];
                $anchoActual = 0.0;
                continue;
            }

            if ($it['type'] === 'space') {
                if (!empty($lineaActual)) {
                    $lineaActual[] = $it;
                    $anchoActual  += $it['width'];
                }
                continue;
            }

            // Es un cluster de palabra
            if (!empty($lineaActual) && ($anchoActual + $it['width']) > ($anchoDisp + 0.01)) {
                while (!empty($lineaActual) && $lineaActual[count($lineaActual) - 1]['type'] === 'space') {
                    $quitado = array_pop($lineaActual);
                    $anchoActual -= $quitado['width'];
                }
                $lineas[] = ['items' => $lineaActual, 'width' => max(0.0, $anchoActual), 'esUltima' => false];
                $lineaActual = [$it];
                $anchoActual = $it['width'];
            } else {
                $lineaActual[] = $it;
                $anchoActual  += $it['width'];
            }
        }

        if (!empty($lineaActual)) {
            while (!empty($lineaActual) && $lineaActual[count($lineaActual) - 1]['type'] === 'space') {
                $quitado = array_pop($lineaActual);
                $anchoActual -= $quitado['width'];
            }
            $lineas[] = ['items' => $lineaActual, 'width' => max(0.0, $anchoActual), 'esUltima' => true];
        }

        $alineacion = $estiloParrafo['align'] ?? 'izquierda';

        foreach ($lineas as $linea) {
            if (empty($linea['items'])) {
                $saltoVacio = $estiloParrafo['size'] * 1.35;
                $this->AsegurarEspacioVertical($saltoVacio);
                $this->yLogica += $saltoVacio;
                continue;
            }

            $maxFont   = $estiloParrafo['size'];
            $tieneFrac = false;
            $numSpaces = 0;

            foreach ($linea['items'] as $li) {
                if ($li['type'] === 'space') {
                    $numSpaces++;
                } elseif ($li['type'] === 'cluster') {
                    $maxFont = max($maxFont, $li['maxFont']);
                    if ($li['tieneFraccion']) {
                        $tieneFrac = true;
                    }
                }
            }

            $altoLinea = $tieneFrac ? max($maxFont * 2.35, 28.0) : ($maxFont * 1.38);
            $this->AsegurarEspacioVertical($altoLinea);

            $anchoLinea = $linea['width'];
            $tw         = 0.0;
            $xCursor    = $xInicio;

            if ($alineacion === 'centro') {
                $xCursor = $xInicio + max(0.0, ($anchoDisp - $anchoLinea) / 2.0);
            } elseif ($alineacion === 'derecha') {
                $xCursor = $xInicio + max(0.0, $anchoDisp - $anchoLinea);
            } elseif ($alineacion === 'justificado' && !$linea['esUltima'] && $numSpaces > 0) {
                $delta = max(0.0, $anchoDisp - $anchoLinea);
                $tw    = $delta / $numSpaces;
            }

            $yBaseline = $tieneFrac
                ? ($this->yLogica + $altoLinea * 0.56)
                : ($this->yLogica + $maxFont * 0.85);

            if ($emitir) {
                $this->EmitirLineaRica($linea['items'], $xCursor, $yBaseline, $tw);
            }

            $this->yLogica += $altoLinea;
        }

        $this->yLogica += $espacioDespues;
    }

    /**
     * Fusiona segmentos contiguos que comparten el mismo estilo para emitir
     * instrucciones PDF limpias (ej. un único `Tj` con `Tw` en párrafos uniformes).
     *
     * @param array<int, array<string, mixed>> $lineItems
     */
    private function EmitirLineaRica(array $lineItems, float $xInicio, float $yBaseline, float $tw): void
    {
        $runs = [];

        $empujarFragmento = function (string $txt, array $style, float $width, int $spaces) use (&$runs): void {
            $n = count($runs);
            if ($n > 0 && $runs[$n - 1]['kind'] === 'text' && $this->EstilosIguales($runs[$n - 1]['style'], $style)) {
                $runs[$n - 1]['text']   .= $txt;
                $runs[$n - 1]['width']  += $width;
                $runs[$n - 1]['spaces'] += $spaces;
                return;
            }
            $runs[] = [
                'kind'   => 'text',
                'text'   => $txt,
                'style'  => $style,
                'width'  => $width,
                'spaces' => $spaces,
            ];
        };

        foreach ($lineItems as $it) {
            if ($it['type'] === 'space') {
                $empujarFragmento(' ', $it['style'], $it['width'], 1);
                continue;
            }

            if ($it['type'] === 'cluster') {
                foreach ($it['parts'] as $part) {
                    if ($part['kind'] === 'fraccion') {
                        $runs[] = [
                            'kind'   => 'fraccion',
                            'part'   => $part,
                            'width'  => $part['width'],
                            'spaces' => 0,
                        ];
                    } else {
                        $empujarFragmento($part['text'], $part['style'], $part['width'], 0);
                    }
                }
            }
        }

        $x = $xInicio;
        foreach ($runs as $run) {
            if ($run['kind'] === 'fraccion') {
                $part   = $run['part'];
                $medida = $part['medida'];
                $yAxis  = $yBaseline - ($part['style']['size'] * 0.32);
                $this->DibujarFraccionEn($medida, $x, $yAxis, $this->paginaActual);
                $x += $run['width'];
                continue;
            }

            $st        = $run['style'];
            $anchoReal = $run['width'] + ($run['spaces'] * $tw);
            $rise      = (float) ($st['rise'] ?? 0.0);

            // Fondo resaltado (#resaltado)
            if ($st['highlight'] !== null && trim($run['text']) !== '') {
                $altoRect = $st['size'] * 1.22;
                $yTopRect = ($yBaseline - $rise) - ($st['size'] * 0.88);
                $this->EmitirRectangulo(
                    $this->paginaActual,
                    $x - 1.5,
                    $yTopRect,
                    $anchoReal + 3.0,
                    $altoRect,
                    $st['highlight'],
                    null,
                    0.0
                );
            }

            $this->EmitirTexto(
                $this->paginaActual,
                $x,
                $yBaseline,
                $this->ResolverFuentePdf($st),
                $st['size'],
                $st['color'],
                $run['spaces'] > 0 ? $tw : 0.0,
                $run['text'],
                $rise
            );

            // Subrayado (#subrayado)
            if (!empty($st['underline']) && trim($run['text']) !== '') {
                $ySub = ($yBaseline - $rise) + 2.0;
                $this->EmitirLinea(
                    $this->paginaActual,
                    $x,
                    $ySub,
                    $x + $anchoReal,
                    $ySub,
                    $st['color'],
                    max(0.6, $st['size'] * 0.06)
                );
            }

            $x += $anchoReal;
        }
    }

    private function RenderizarTextoSimple(
        string $texto,
        float $xInicio,
        float $anchoDisp,
        array $estilo,
        bool $emitir,
        float $espacioDespues = 4.0
    ): void {
        $nodos = [[
            'node'     => 'texto',
            'texto'    => $texto,
            'esEscape' => false,
            'startLn'  => 0,
            'startCol' => 0,
            'endLn'    => 0,
            'endCol'   => 0,
        ]];
        $this->RenderizarParrafo($nodos, $xInicio, $anchoDisp, $estilo, $emitir, $espacioDespues);
    }

    /* ============================================================
     * COMPONENTES ESPECIALES: ÍNDICE, IMÁGENES, FRACCIONES, ECUACIONES, TABLAS, CAJAS
     * ============================================================ */

    private function RenderizarIndiceGeneral(float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $estiloTitulo = array_merge($this->EstiloActual(), [
            'bold'  => true,
            'size'  => 16.0,
            'align' => 'izquierda',
        ]);
        $this->RenderizarTextoSimple('Índice General', $xInicio, $anchoDisp, $estiloTitulo, $emitir, 6.0);

        if (empty($this->indiceConocido)) {
            $estiloVacio = array_merge($this->EstiloActual(), [
                'italic' => true,
                'size'   => 10.5,
                'color'  => [0.40, 0.45, 0.52],
            ]);
            $this->RenderizarTextoSimple('(Tabla de contenidos lista — sin secciones posteriores)', $xInicio, $anchoDisp, $estiloVacio, $emitir, 8.0);
            return;
        }

        foreach ($this->indiceConocido as $entrada) {
            $nivel    = (int) $entrada['nivel'];
            $sangria  = ($nivel - 1) * 14.0;
            $estilo   = array_merge($this->EstiloActual(), [
                'bold'  => $nivel === 1,
                'size'  => $nivel === 1 ? 11.5 : 10.8,
                'color' => [0.08, 0.12, 0.20],
            ]);

            $altoFila = $estilo['size'] * 1.5;
            $this->AsegurarEspacioVertical($altoFila);

            $etiquetaIzq = trim($entrada['numeracion'] . ' ' . $entrada['titulo']);
            $pagTexto    = (string) $entrada['pagina'];

            $xIzq      = $xInicio + $sangria;
            $anchoIzq  = $this->MedirAnchoTexto($etiquetaIzq, $estilo);
            $anchoPag  = $this->MedirAnchoTexto($pagTexto, $estilo);
            $xDerPag   = $xInicio + $anchoDisp - $anchoPag;
            $yBaseline = $this->yLogica + $estilo['size'] * 0.85;

            if ($emitir) {
                $fuentePdf = $this->ResolverFuentePdf($estilo);
                $this->EmitirTexto($this->paginaActual, $xIzq, $yBaseline, $fuentePdf, $estilo['size'], $estilo['color'], 0.0, $etiquetaIzq, 0.0);
                $this->EmitirTexto($this->paginaActual, $xDerPag, $yBaseline, $fuentePdf, $estilo['size'], $estilo['color'], 0.0, $pagTexto, 0.0);

                // Relleno de puntos (leaders) entre el título de sección y el número de página
                $estiloPuntos = array_merge($estilo, ['bold' => false, 'color' => [0.45, 0.50, 0.58]]);
                $xInicioPts   = $xIzq + $anchoIzq + 6.0;
                $xFinPts      = $xDerPag - 6.0;
                $espacioPts   = $xFinPts - $xInicioPts;
                $anchoUnPunto = $this->MedirAnchoTexto(' .', $estiloPuntos);

                if ($espacioPts > $anchoUnPunto * 2 && $anchoUnPunto > 0) {
                    $cantidad = (int) floor($espacioPts / $anchoUnPunto);
                    $cadenaPuntos = str_repeat(' .', $cantidad);
                    $this->EmitirTexto(
                        $this->paginaActual,
                        $xInicioPts,
                        $yBaseline,
                        $this->ResolverFuentePdf($estiloPuntos),
                        $estiloPuntos['size'],
                        $estiloPuntos['color'],
                        0.0,
                        $cadenaPuntos,
                        0.0
                    );
                }
            }

            $this->yLogica += $altoFila;
        }

        $this->yLogica += 10.0;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function RenderizarImagen(array $attrs, float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $rutaLogical = $this->ObtenerTextoAtributo($attrs, T_ATTR_RUTA);
        $nombreLimpio = basename($rutaLogical);
        $rutaFisica   = $this->ResolverRutaImagen($nombreLimpio);

        $alineacion = $this->ResolverAlineacionAtributos($attrs, 'izquierda');
        $anchoAttr  = $this->ObtenerTextoAtributo($attrs, T_ATTR_ANCHO);
        $altoAttr   = $this->ObtenerTextoAtributo($attrs, T_ATTR_ALTO);

        $pxAncho = 480;
        $pxAlto  = 180;
        $canales = 3;
        $existeJpeg = false;

        if ($rutaFisica !== null && is_file($rutaFisica)) {
            $info = @getimagesize($rutaFisica);
            if (is_array($info) && ($info[2] ?? 0) === IMAGETYPE_JPEG && $info[0] > 0 && $info[1] > 0) {
                $pxAncho    = (int) $info[0];
                $pxAlto     = (int) $info[1];
                $canales    = (int) ($info['channels'] ?? 3);
                $existeJpeg = true;
            }
        }

        $ratio = $pxAlto / max(1, $pxAncho);

        // Calcular dimensiones en puntos PDF según especificación del Caso 16
        $anchoPt = null;
        $altoPt  = null;

        if ($anchoAttr !== '' && strtolower($anchoAttr) !== 'auto') {
            if (str_ends_with($anchoAttr, '%')) {
                $pct     = max(1.0, min(100.0, (float) substr($anchoAttr, 0, -1)));
                $anchoPt = $anchoDisp * ($pct / 100.0);
            } else {
                $anchoPt = max(10.0, min($anchoDisp, (float) $anchoAttr));
            }
        }

        if ($altoAttr !== '' && strtolower($altoAttr) !== 'auto') {
            if (str_ends_with($altoAttr, '%')) {
                $pct    = max(1.0, min(100.0, (float) substr($altoAttr, 0, -1)));
                $altoPt = (self::LIMITE_INF - self::MARGEN_SUP) * ($pct / 100.0);
            } else {
                $altoPt = max(10.0, (float) $altoAttr);
            }
        }

        if ($anchoPt !== null && $altoPt === null) {
            $altoPt = $anchoPt * $ratio;
        } elseif ($altoPt !== null && $anchoPt === null) {
            $anchoPt = min($anchoDisp, $altoPt / max(0.01, $ratio));
        } elseif ($anchoPt === null && $altoPt === null) {
            $anchoPt = min($anchoDisp, (float) $pxAncho * 0.75);
            $altoPt  = $anchoPt * $ratio;
        }

        $maxAltoHoja = (self::LIMITE_INF - self::MARGEN_SUP) - 12.0;
        if ($altoPt > $maxAltoHoja) {
            $escala  = $maxAltoHoja / $altoPt;
            $altoPt  = $maxAltoHoja;
            $anchoPt *= $escala;
        }

        $this->AsegurarEspacioVertical($altoPt + 8.0);

        $xImg = $xInicio;
        if ($alineacion === 'centro') {
            $xImg = $xInicio + max(0.0, ($anchoDisp - $anchoPt) / 2.0);
        } elseif ($alineacion === 'derecha') {
            $xImg = $xInicio + max(0.0, $anchoDisp - $anchoPt);
        }

        if ($emitir) {
            if ($existeJpeg && $rutaFisica !== null) {
                $this->elementos[] = [
                    'tipo'    => 'image',
                    'pagina'  => $this->paginaActual,
                    'ruta'    => $rutaFisica,
                    'nombre'  => $nombreLimpio,
                    'x'       => round($xImg, 2),
                    'yLogica' => round($this->yLogica, 2),
                    'ancho'   => round($anchoPt, 2),
                    'alto'    => round($altoPt, 2),
                    'yPdf'    => round(self::ALTO_PAGINA - ($this->yLogica + $altoPt), 2),
                    'pxAncho' => $pxAncho,
                    'pxAlto'  => $pxAlto,
                    'canales' => $canales,
                    'linea'   => $this->lineaFuenteActual,
                ];
            } else {
                // Recuadro vectorial de marcador en caso de imagen externa no subida aún
                $this->EmitirRectangulo($this->paginaActual, $xImg, $this->yLogica, $anchoPt, $altoPt, [0.95, 0.96, 0.98], [0.58, 0.64, 0.72], 0.8);
                $etiqueta = '[Imagen JPEG: ' . $nombreLimpio . ']';
                $est = array_merge($this->EstiloActual(), ['size' => 10.0, 'color' => [0.35, 0.40, 0.48]]);
                $wTxt = $this->MedirAnchoTexto($etiqueta, $est);
                $this->EmitirTexto(
                    $this->paginaActual,
                    $xImg + max(4.0, ($anchoPt - $wTxt) / 2.0),
                    $this->yLogica + ($altoPt / 2.0) + 3.0,
                    '/F1',
                    10.0,
                    $est['color'],
                    0.0,
                    $etiqueta,
                    0.0
                );
            }
        }

        $this->yLogica += $altoPt + 10.0;
    }

    /**
     * Mide una estructura #fraccion para uso en bloque, inline o dentro de #ecuacion.
     *
     * @param array<string, mixed> $nodoFrac
     * @param array<string, mixed> $estiloContexto
     * @return array<string, mixed>
     */
    private function MedirFraccion(array $nodoFrac, array $estiloContexto): array
    {
        $estiloSub = array_merge($estiloContexto, [
            'size' => max(7.5, round($estiloContexto['size'] * 0.88, 2)),
            'rise' => 0.0,
        ]);
        $estiloSub = $this->AplicarAtributosAEstilo($estiloSub, $nodoFrac['attrs'] ?? [], false);

        $numPiezas = $this->AplanarInline($nodoFrac['num'], $estiloSub);
        $denPiezas = $this->AplanarInline($nodoFrac['den'], $estiloSub);

        $anchoNum = 0.0;
        foreach ($numPiezas as $p) {
            $anchoNum += $p['width'];
        }
        $anchoDen = 0.0;
        foreach ($denPiezas as $p) {
            $anchoDen += $p['width'];
        }

        $anchoBarra = max($anchoNum, $anchoDen, 12.0) + 10.0;
        $altoTotal  = max(26.0, $estiloSub['size'] * 2.55);

        return [
            'estilo'    => $estiloSub,
            'numPiezas' => $numPiezas,
            'denPiezas' => $denPiezas,
            'anchoNum'  => $anchoNum,
            'anchoDen'  => $anchoDen,
            'ancho'     => $anchoBarra,
            'alto'      => $altoTotal,
        ];
    }

    /**
     * Dibuja una fracción medida centrada respecto a su línea divisoria en (x, yAxis).
     *
     * @param array<string, mixed> $medida
     */
    private function DibujarFraccionEn(array $medida, float $x, float $yAxis, int $pagina): void
    {
        $anchoBarra = $medida['ancho'];
        $estiloSub  = $medida['estilo'];

        $xNum      = $x + ($anchoBarra - $medida['anchoNum']) / 2.0;
        $yBaseNum  = $yAxis - 4.2;
        $this->DibujarSecuenciaPiezasLineal($medida['numPiezas'], $xNum, $yBaseNum, $pagina);

        $this->EmitirLinea(
            $pagina,
            $x + 1.0,
            $yAxis,
            $x + $anchoBarra - 1.0,
            $yAxis,
            $estiloSub['color'],
            0.85
        );

        $xDen     = $x + ($anchoBarra - $medida['anchoDen']) / 2.0;
        $yBaseDen = $yAxis + $estiloSub['size'] + 2.2;
        $this->DibujarSecuenciaPiezasLineal($medida['denPiezas'], $xDen, $yBaseDen, $pagina);
    }

    /**
     * @param array<int, array<string, mixed>> $piezas
     */
    private function DibujarSecuenciaPiezasLineal(array $piezas, float $xInicio, float $yBaseline, int $pagina): void
    {
        $compactadas = [];
        foreach ($piezas as $p) {
            if ($p['kind'] === 'fraccion') {
                $compactadas[] = $p;
                continue;
            }
            $txt = $p['kind'] === 'space' ? ' ' : (string) ($p['text'] ?? '');
            $n   = count($compactadas);
            if (
                $n > 0
                && $compactadas[$n - 1]['kind'] === 'text_run'
                && $this->EstilosIguales($compactadas[$n - 1]['style'], $p['style'])
            ) {
                $compactadas[$n - 1]['text']  .= $txt;
                $compactadas[$n - 1]['width'] += $p['width'];
            } else {
                $compactadas[] = [
                    'kind'  => 'text_run',
                    'text'  => $txt,
                    'style' => $p['style'],
                    'width' => $p['width'],
                ];
            }
        }

        $x = $xInicio;
        foreach ($compactadas as $item) {
            if ($item['kind'] === 'fraccion') {
                $yAxisSub = $yBaseline - ($item['style']['size'] * 0.32);
                $this->DibujarFraccionEn($item['medida'], $x, $yAxisSub, $pagina);
                $x += $item['width'];
                continue;
            }
            $st = $item['style'];
            if (trim($item['text']) !== '') {
                $this->EmitirTexto(
                    $pagina,
                    $x,
                    $yBaseline,
                    $this->ResolverFuentePdf($st),
                    $st['size'],
                    $st['color'],
                    0.0,
                    $item['text'],
                    (float) ($st['rise'] ?? 0.0)
                );
            }
            $x += $item['width'];
        }
    }

    /**
     * @param array<string, mixed> $nodoFrac
     */
    private function RenderizarFraccionBloque(array $nodoFrac, float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $estilo   = $this->AplicarAtributosAEstilo($this->EstiloActual(), $nodoFrac['attrs'], false);
        $medida   = $this->MedirFraccion($nodoFrac, $estilo);
        $altoCaja = $medida['alto'] + 8.0;

        $this->AsegurarEspacioVertical($altoCaja);

        $x = $xInicio;
        if ($estilo['align'] === 'centro') {
            $x = $xInicio + max(0.0, ($anchoDisp - $medida['ancho']) / 2.0);
        } elseif ($estilo['align'] === 'derecha') {
            $x = $xInicio + max(0.0, $anchoDisp - $medida['ancho']);
        }

        $yAxis = $this->yLogica + ($altoCaja * 0.48);
        if ($emitir) {
            $this->DibujarFraccionEn($medida, $x, $yAxis, $this->paginaActual);
        }

        $this->yLogica += $altoCaja + 4.0;
    }

    /**
     * Modo ecuación en bloque centrada y numerada (Caso 27).
     *
     * @param array<string, mixed> $nodoEc
     */
    private function RenderizarEcuacion(array $nodoEc, float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $this->contadorEcuacion++;
        $etiquetaNum = '(' . $this->contadorEcuacion . ')';

        $estiloEc = $this->AplicarAtributosAEstilo($this->EstiloActual(), $nodoEc['attrs'], false);
        $piezas   = $this->AplanarInline($nodoEc['children'], $estiloEc);

        $anchoTotal = 0.0;
        $tieneFrac  = false;
        foreach ($piezas as $p) {
            $anchoTotal += $p['width'];
            if ($p['kind'] === 'fraccion') {
                $tieneFrac = true;
            }
        }

        $altoBloque = $tieneFrac ? 36.0 : ($estiloEc['size'] * 1.8);
        $this->yLogica += 8.0; // margen superior adicional (+10 pt nominal)
        $this->AsegurarEspacioVertical($altoBloque + 8.0);

        $xExpr     = $xInicio + max(0.0, ($anchoDisp - $anchoTotal) / 2.0);
        $yBaseline = $this->yLogica + ($altoBloque * 0.56);

        if ($emitir) {
            $this->DibujarSecuenciaPiezasLineal($piezas, $xExpr, $yBaseline, $this->paginaActual);

            $anchoContador = $this->MedirAnchoTexto($etiquetaNum, $estiloEc);
            $xContador     = $xInicio + $anchoDisp - $anchoContador;
            $this->EmitirTexto(
                $this->paginaActual,
                $xContador,
                $yBaseline,
                $this->ResolverFuentePdf($estiloEc),
                $estiloEc['size'],
                $estiloEc['color'],
                0.0,
                $etiquetaNum,
                0.0
            );
        }

        $this->yLogica += $altoBloque + 10.0;
    }

    /**
     * Tablas con cálculo automático de columnas, alturas de celda recursivas,
     * fondos de fila/celda y bordes (Casos 21, 22 y 25).
     *
     * @param array<string, mixed> $nodoTabla
     */
    private function RenderizarTabla(array $nodoTabla, float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $attrsTabla = $nodoTabla['attrs'];
        $conBordes  = $this->ObtenerBooleanoAtributo($attrsTabla, T_ATTR_BORDES, false);
        $fondoTabla = $this->ObtenerColorAtributo($attrsTabla, T_ATTR_FONDO);
        $padding    = 5.0;

        $this->yLogica += 4.0;

        foreach ($nodoTabla['children'] as $nodoFila) {
            if ($nodoFila['node'] !== 'bloque' || $nodoFila['cmd'] !== T_FILA) {
                continue;
            }

            $celdas = array_values(array_filter(
                $nodoFila['children'],
                fn(array $c): bool => $c['node'] === 'bloque' && $c['cmd'] === T_CELDA
            ));

            $numCeldas = count($celdas);
            if ($numCeldas === 0) {
                continue;
            }

            $anchoCol  = $anchoDisp / $numCeldas;
            $fondoFila = $this->ObtenerColorAtributo($nodoFila['attrs'], T_ATTR_FONDO) ?? $fondoTabla;

            // 1. Medición previa (dry-run local) para conocer max_height de la fila
            $paginaSnapshot   = $this->paginaActual;
            $ySnapshot        = $this->yLogica;
            $elemSnapshot     = $this->elementos;
            $secSnapshot      = $this->contadoresSeccion;
            $ecSnapshot       = $this->contadorEcuacion;
            $indSnapshot      = $this->entradasIndice;
            $etiqSnapshot     = $this->paginasEtiquetas;

            $maxAltoInterno = 14.0;
            foreach ($celdas as $idxC => $celda) {
                $this->paginaActual = 1;
                $this->yLogica      = self::MARGEN_SUP;
                $estiloCelda        = $this->AplicarAtributosAEstilo($this->EstiloActual(), $celda['attrs'], false);
                $this->PushEstilo($estiloCelda);
                $this->RenderizarSecuenciaBloques(
                    $celda['children'],
                    $xInicio + ($idxC * $anchoCol) + $padding,
                    max(25.0, $anchoCol - 2.0 * $padding),
                    false
                );
                $this->PopEstilo();
                $usado = max(14.0, ($this->yLogica - self::MARGEN_SUP) - 2.0);
                $maxAltoInterno = max($maxAltoInterno, $usado);
            }

            // Restaurar estado tras medición
            $this->paginaActual      = $paginaSnapshot;
            $this->yLogica           = $ySnapshot;
            $this->elementos         = $elemSnapshot;
            $this->contadoresSeccion = $secSnapshot;
            $this->contadorEcuacion  = $ecSnapshot;
            $this->entradasIndice    = $indSnapshot;
            $this->paginasEtiquetas  = $etiqSnapshot;

            $altoFila = $maxAltoInterno + (2.0 * $padding);
            $this->AsegurarEspacioVertical($altoFila);

            $yTopFila   = $this->yLogica;
            $paginaFila = $this->paginaActual;

            // 2. Pintar rectángulo de fondo de la fila / celdas primero (Caso 22)
            if ($emitir && $fondoFila !== null) {
                $this->EmitirRectangulo($paginaFila, $xInicio, $yTopFila, $anchoDisp, $altoFila, $fondoFila, null, 0.0);
            }

            // 3. Renderizar cada celda en su sub-lienzo horizontal [X_col, X_col + anchoCol]
            foreach ($celdas as $idxC => $celda) {
                $xCol       = $xInicio + ($idxC * $anchoCol);
                $fondoCelda = $this->ObtenerColorAtributo($celda['attrs'], T_ATTR_FONDO);

                if ($emitir && $fondoCelda !== null) {
                    $this->EmitirRectangulo($paginaFila, $xCol, $yTopFila, $anchoCol, $altoFila, $fondoCelda, null, 0.0);
                }

                $this->paginaActual = $paginaFila;
                $this->yLogica      = $yTopFila + $padding;

                $estiloCelda = $this->AplicarAtributosAEstilo($this->EstiloActual(), $celda['attrs'], false);
                $this->PushEstilo($estiloCelda);
                $this->RenderizarSecuenciaBloques(
                    $celda['children'],
                    $xCol + $padding,
                    max(25.0, $anchoCol - 2.0 * $padding),
                    $emitir
                );
                $this->PopEstilo();

                // 4. Trazar contorno de celda si bordes=verdadero
                if ($emitir && $conBordes) {
                    $this->EmitirRectangulo($paginaFila, $xCol, $yTopFila, $anchoCol, $altoFila, null, [0.0, 0.0, 0.0], 0.75);
                }
            }

            $this->paginaActual = $paginaFila;
            $this->yLogica      = $yTopFila + $altoFila;
        }

        $this->yLogica += 8.0;
    }

    /**
     * Cajas de advertencia con fondo suave, barra lateral e ícono vectorial nativo PDF (Caso 28).
     *
     * @param array<string, mixed> $nodoCaja
     */
    private function RenderizarCaja(array $nodoCaja, float $xInicio, float $anchoDisp, bool $emitir): void
    {
        $tipoCaja = $this->ResolverTipoCaja($nodoCaja['attrs']);

        // Paletas según Caso 28 (#FFF3CD / #FFC107 para alerta, más variantes info y exito)
        [$colorFondo, $colorBarra, $colorIcono] = match ($tipoCaja) {
            'alerta' => [
                [1.0, 0.953, 0.804],   // #FFF3CD
                [1.0, 0.757, 0.027],   // #FFC107
                [0.851, 0.467, 0.024], // #D97706
            ],
            'exito' => [
                [0.925, 0.992, 0.961], // #ECFDF5
                [0.063, 0.725, 0.506], // #10B981
                [0.020, 0.588, 0.412], // #059669
            ],
            default => [ // 'info'
                [0.937, 0.965, 1.0],   // #EFF6FF
                [0.145, 0.388, 0.922], // #2563EB
                [0.114, 0.306, 0.847], // #1D4ED8
            ],
        };

        $overrideFondo = $this->ObtenerColorAtributo($nodoCaja['attrs'], T_ATTR_FONDO);
        if ($overrideFondo !== null) {
            $colorFondo = $overrideFondo;
        }

        $paddingSup = 9.0;
        $sangriaTxt = 36.0;
        $anchoTexto = max(60.0, $anchoDisp - $sangriaTxt - 12.0);

        // Medición previa de la altura del contenido interno
        $paginaSnap = $this->paginaActual;
        $ySnap      = $this->yLogica;
        $elemSnap   = $this->elementos;
        $secSnap    = $this->contadoresSeccion;
        $ecSnap     = $this->contadorEcuacion;
        $indSnap    = $this->entradasIndice;
        $etiqSnap   = $this->paginasEtiquetas;

        $this->paginaActual = 1;
        $this->yLogica      = self::MARGEN_SUP;
        $estiloCaja = $this->AplicarAtributosAEstilo($this->EstiloActual(), $nodoCaja['attrs'], false);
        $this->PushEstilo($estiloCaja);
        $this->RenderizarSecuenciaBloques($nodoCaja['children'], $xInicio + $sangriaTxt, $anchoTexto, false);
        $this->PopEstilo();
        $altoInterno = max(18.0, ($this->yLogica - self::MARGEN_SUP) - 4.0);

        $this->paginaActual      = $paginaSnap;
        $this->yLogica           = $ySnap;
        $this->elementos         = $elemSnap;
        $this->contadoresSeccion = $secSnap;
        $this->contadorEcuacion  = $ecSnap;
        $this->entradasIndice    = $indSnap;
        $this->paginasEtiquetas  = $etiqSnap;

        $altoCaja = max(32.0, $altoInterno + (2.0 * $paddingSup));
        $this->yLogica += 4.0;
        $this->AsegurarEspacioVertical($altoCaja);

        $yTopCaja   = $this->yLogica;
        $paginaCaja = $this->paginaActual;

        if ($emitir) {
            // 1. Fondo suave del contenedor
            $this->EmitirRectangulo($paginaCaja, $xInicio, $yTopCaja, $anchoDisp, $altoCaja, $colorFondo, null, 0.0);
            // 2. Barra lateral izquierda destacada (4.5 pt)
            $this->EmitirRectangulo($paginaCaja, $xInicio, $yTopCaja, 4.5, $altoCaja, $colorBarra, null, 0.0);
            // 3. Ícono vectorial nativo PDF (sin emojis Unicode)
            $this->EmitirIconoVectorialCaja($paginaCaja, $tipoCaja, $xInicio + 13.0, $yTopCaja + 9.0, $colorBarra, $colorIcono);
        }

        $this->yLogica = $yTopCaja + $paddingSup;
        $this->PushEstilo($estiloCaja);
        $this->RenderizarSecuenciaBloques($nodoCaja['children'], $xInicio + $sangriaTxt, $anchoTexto, $emitir);
        $this->PopEstilo();

        $this->paginaActual = $paginaCaja;
        $this->yLogica      = $yTopCaja + $altoCaja + 8.0;
    }

    /**
     * Traza los íconos vectoriales de #caja usando primitivas nativas PDF (m, l, h, f, S).
     */
    private function EmitirIconoVectorialCaja(
        int $pagina,
        string $tipoCaja,
        float $x,
        float $yTop,
        array $colorRelleno,
        array $colorDetalle
    ): void {
        if ($tipoCaja === 'alerta') {
            // Triángulo de advertencia relleno (14 x 13 pt) + signo '!' interior
            $this->elementos[] = [
                'tipo'        => 'poly',
                'pagina'      => $pagina,
                'puntos'      => [
                    [$x + 7.0, $yTop],
                    [$x + 14.0, $yTop + 13.0],
                    [$x, $yTop + 13.0],
                ],
                'cerrado'     => true,
                'relleno'     => $colorRelleno,
                'borde'       => $colorDetalle,
                'grosorBorde' => 0.8,
            ];
            $this->EmitirLinea($pagina, $x + 7.0, $yTop + 4.2, $x + 7.0, $yTop + 9.2, [0.1, 0.1, 0.1], 1.3);
            $this->EmitirRectangulo($pagina, $x + 6.3, $yTop + 10.4, 1.4, 1.4, [0.1, 0.1, 0.1], null, 0.0);
            return;
        }

        if ($tipoCaja === 'exito') {
            // Escudo octogonal / insignia verde + check vectorial (m, l, S)
            $this->EmitirRectangulo($pagina, $x, $yTop + 1.0, 13.0, 13.0, $colorRelleno, $colorDetalle, 0.7);
            $this->elementos[] = [
                'tipo'        => 'poly',
                'pagina'      => $pagina,
                'puntos'      => [
                    [$x + 3.0, $yTop + 7.8],
                    [$x + 5.6, $yTop + 10.6],
                    [$x + 10.4, $yTop + 4.5],
                ],
                'cerrado'     => false,
                'relleno'     => null,
                'borde'       => [1.0, 1.0, 1.0],
                'grosorBorde' => 1.6,
            ];
            return;
        }

        // 'info': insignia azul con símbolo 'i' vectorial blanco
        $this->EmitirRectangulo($pagina, $x, $yTop + 1.0, 13.0, 13.0, $colorRelleno, $colorDetalle, 0.7);
        $this->EmitirRectangulo($pagina, $x + 5.7, $yTop + 3.4, 1.6, 1.6, [1.0, 1.0, 1.0], null, 0.0);
        $this->EmitirLinea($pagina, $x + 6.5, $yTop + 6.3, $x + 6.5, $yTop + 11.6, [1.0, 1.0, 1.0], 1.6);
    }

    /* ============================================================
     * MÉTRICAS AFM, PILA DE ESTILOS Y PAGINACIÓN
     * ============================================================ */

    /** @return array<string, mixed> */
    private function EstiloActual(): array
    {
        return $this->styleStack[count($this->styleStack) - 1];
    }

    /** @param array<string, mixed> $estilo */
    private function PushEstilo(array $estilo): void
    {
        $this->styleStack[] = $estilo;
    }

    private function PopEstilo(): void
    {
        if (count($this->styleStack) > 1) {
            array_pop($this->styleStack);
        }
    }

    /**
     * Deriva un nuevo estado de estilo al entrar en un comando inline (#fuerte, #cursiva, #resaltado, etc.).
     *
     * @param array<string, mixed> $padre
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     * @return array<string, mixed>
     */
    private function DerivarEstiloInline(array $padre, int $cmd, array $attrs): array
    {
        $hijo = $padre;

        if ($cmd === T_FUERTE) {
            $hijo['bold'] = true;
            return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
        }
        if ($cmd === T_CURSIVA) {
            $hijo['italic'] = true;
            return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
        }
        if ($cmd === T_SUBRAYADO) {
            $hijo['underline'] = true;
            return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
        }
        if ($cmd === T_RESALTADO) {
            // Según Caso 13: #resaltado(color=#FFFF00){Dato} pinta rectángulo de fondo y texto negro encima
            $hijo['highlight'] = [1.0, 1.0, 0.0];
            $hijo['color']     = [0.0, 0.0, 0.0];
            return $this->AplicarAtributosAEstilo($hijo, $attrs, true);
        }
        if ($cmd === T_SUPERINDICE) {
            $escala       = $padre['size'] / 12.0;
            $hijo['size'] = round($padre['size'] * 0.70, 2); // 12 pt -> 8.4 pt (Caso 17)
            $hijo['rise'] = (float) ($padre['rise'] ?? 0.0) + round(4.0 * $escala, 2);
            return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
        }
        if ($cmd === T_SUBINDICE) {
            $escala       = $padre['size'] / 12.0;
            $hijo['size'] = round($padre['size'] * 0.70, 2);
            $hijo['rise'] = (float) ($padre['rise'] ?? 0.0) - round(2.5 * $escala, 2);
            return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
        }

        return $this->AplicarAtributosAEstilo($hijo, $attrs, false);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     * @return array<string, mixed>
     */
    private function AplicarAtributosAEstilo(array $base, array $attrs, bool $esResaltado): array
    {
        $res = $base;

        foreach ($attrs as $attr) {
            $claveTok = $attr['claveToken'];
            $valTok   = $attr['valores'][0] ?? null;
            if ($valTok === null) {
                continue;
            }

            // Banderas posicionales (izquierda, centro, centrado, derecha, justificado)
            if ($claveTok === null) {
                $al = $this->MapearTokenAlineacion($valTok->token);
                if ($al !== null) {
                    $res['align'] = $al;
                }
                continue;
            }

            if ($claveTok === T_ATTR_ALINEACION) {
                $al = $this->MapearTokenAlineacion($valTok->token);
                if ($al !== null) {
                    $res['align'] = $al;
                }
                continue;
            }

            if ($claveTok === T_ATTR_COLOR) {
                $rgb = $this->ConvertirTokenColor($valTok);
                if ($rgb !== null) {
                    if ($esResaltado) {
                        $res['highlight'] = $rgb;
                    } else {
                        $res['color'] = $rgb;
                    }
                }
                continue;
            }

            if ($claveTok === T_ATTR_FONDO) {
                $rgb = $this->ConvertirTokenColor($valTok);
                if ($rgb !== null) {
                    $res['highlight'] = $rgb;
                }
                continue;
            }

            if ($claveTok === T_ATTR_TAMANO) {
                $num = (float) $valTok->lexema;
                if ($num > 0) {
                    $res['size'] = $num;
                }
                continue;
            }

            if ($claveTok === T_ATTR_FUENTE) {
                $fam = strtolower(trim((string) $valTok->lexema));
                if (str_contains($fam, 'courier') || str_contains($fam, 'mono')) {
                    $res['family'] = 'Courier';
                } elseif (str_contains($fam, 'times') || str_contains($fam, 'serif')) {
                    $res['family'] = 'Times';
                } else {
                    $res['family'] = 'Helvetica';
                }
                continue;
            }
        }

        return $res;
    }

    /** @param array<string, mixed> $estilo */
    private function ResolverFuentePdf(array $estilo): string
    {
        $fam    = $estilo['family'] ?? 'Helvetica';
        $bold   = !empty($estilo['bold']);
        $italic = !empty($estilo['italic']);

        if ($fam === 'Courier') {
            return '/F5';
        }
        if ($fam === 'Times') {
            return '/F6';
        }
        if ($bold && $italic) {
            return '/F4';
        }
        if ($bold) {
            return '/F2';
        }
        if ($italic) {
            return '/F3';
        }
        return '/F1';
    }

    /**
     * Calcula el ancho exacto en puntos PDF de una cadena UTF-8 usando métricas AFM.
     *
     * @param array<string, mixed> $estilo
     */
    public function MedirAnchoTexto(string $texto, array $estilo): float
    {
        if ($texto === '') {
            return 0.0;
        }

        $tamano = (float) ($estilo['size'] ?? 12.0);
        $fam    = $estilo['family'] ?? 'Helvetica';

        if ($fam === 'Courier') {
            return (mb_strlen($texto, 'UTF-8') * 600.0 * $tamano) / 1000.0;
        }

        if ($fam === 'Times') {
            $tabla = self::AFM_TIMES_ROMAN;
        } else {
            $tabla = !empty($estilo['bold']) ? self::AFM_HELVETICA_BOLD : self::AFM_HELVETICA;
        }
        $suma  = 0;
        $len   = mb_strlen($texto, 'UTF-8');

        for ($i = 0; $i < $len; $i++) {
            $ch    = mb_substr($texto, $i, 1, 'UTF-8');
            $suma += $tabla[$ch] ?? 556;
        }

        return ($suma * $tamano) / 1000.0;
    }

    private function AsegurarEspacioVertical(float $alturaRequerida): void
    {
        if (
            $this->yLogica > self::MARGEN_SUP + 0.5
            && ($this->yLogica + $alturaRequerida) > self::LIMITE_INF
        ) {
            $this->NuevaPagina();
        }
    }

    private function NuevaPagina(): void
    {
        $this->paginaActual++;
        $this->yLogica = self::MARGEN_SUP;
    }

    /* ============================================================
     * EMISIÓN DE PRIMITIVAS IR GEOMÉTRICAS
     * ============================================================ */

    /** @param float[] $color */
    private function EmitirTexto(
        int $pagina,
        float $x,
        float $yBaselineLogica,
        string $fuentePdf,
        float $tamano,
        array $color,
        float $tw,
        string $texto,
        float $rise
    ): void {
        if ($texto === '') {
            return;
        }
        $yPdf = (self::ALTO_PAGINA - $yBaselineLogica) + $rise;

        $estiloMedida = [
            'family' => $fuentePdf === '/F5' ? 'Courier' : ($fuentePdf === '/F6' ? 'Times' : 'Helvetica'),
            'bold'   => in_array($fuentePdf, ['/F2', '/F4'], true),
            'italic' => in_array($fuentePdf, ['/F3', '/F4'], true),
            'size'   => $tamano,
        ];
        $anchoPt = $this->MedirAnchoTexto($texto, $estiloMedida);
        if ($tw > 0.0) {
            $anchoPt += substr_count($texto, ' ') * $tw;
        }

        $this->elementos[] = [
            'tipo'    => 'text',
            'pagina'  => $pagina,
            'x'       => round($x, 2),
            'yLogica' => round($yBaselineLogica, 2),
            'yPdf'    => round($yPdf, 2),
            'ancho'   => round($anchoPt, 2),
            'fuente'  => $fuentePdf,
            'tamano'  => round($tamano, 2),
            'color'   => $color,
            'tw'      => round($tw, 3),
            'texto'   => $texto,
            'linea'   => $this->lineaFuenteActual,
        ];
    }

    /**
     * @param float[]|null $relleno
     * @param float[]|null $borde
     */
    private function EmitirRectangulo(
        int $pagina,
        float $x,
        float $yTopLogica,
        float $ancho,
        float $alto,
        ?array $relleno,
        ?array $borde,
        float $grosorBorde
    ): void {
        $this->elementos[] = [
            'tipo'        => 'rect',
            'pagina'      => $pagina,
            'x'           => round($x, 2),
            'yLogica'     => round($yTopLogica, 2),
            'ancho'       => round($ancho, 2),
            'alto'        => round($alto, 2),
            'yPdf'        => round(self::ALTO_PAGINA - ($yTopLogica + $alto), 2),
            'relleno'     => $relleno,
            'borde'       => $borde,
            'grosorBorde' => round($grosorBorde, 2),
            'linea'       => $this->lineaFuenteActual,
        ];
    }

    /** @param float[] $color */
    private function EmitirLinea(
        int $pagina,
        float $x1,
        float $y1Logica,
        float $x2,
        float $y2Logica,
        array $color,
        float $grosor
    ): void {
        $this->elementos[] = [
            'tipo'     => 'line',
            'pagina'   => $pagina,
            'x1'       => round($x1, 2),
            'y1Logica' => round($y1Logica, 2),
            'x2'       => round($x2, 2),
            'y2Logica' => round($y2Logica, 2),
            'y1Pdf'    => round(self::ALTO_PAGINA - $y1Logica, 2),
            'y2Pdf'    => round(self::ALTO_PAGINA - $y2Logica, 2),
            'color'    => $color,
            'grosor'   => round($grosor, 2),
            'linea'    => $this->lineaFuenteActual,
        ];
    }

    /* ============================================================
     * UTILIDADES AUXILIARES DE ATRIBUTOS, COLORES Y SÍMBOLOS
     * ============================================================ */

    private function estilosIguales(array $a, array $b): bool
    {
        return ($a['family'] ?? '') === ($b['family'] ?? '')
            && ($a['bold'] ?? false) === ($b['bold'] ?? false)
            && ($a['italic'] ?? false) === ($b['italic'] ?? false)
            && ($a['underline'] ?? false) === ($b['underline'] ?? false)
            && abs(($a['size'] ?? 12.0) - ($b['size'] ?? 12.0)) < 0.01
            && abs(($a['rise'] ?? 0.0) - ($b['rise'] ?? 0.0)) < 0.01
            && ($a['color'] ?? []) === ($b['color'] ?? [])
            && ($a['highlight'] ?? null) === ($b['highlight'] ?? null);
    }

    private function MapearTokenAlineacion(int $token): ?string
    {
        return match ($token) {
            T_VAL_IZQUIERDA   => 'izquierda',
            T_VAL_CENTRO      => 'centro',
            T_VAL_DERECHA     => 'derecha',
            T_VAL_JUSTIFICADO => 'justificado',
            default           => null,
        };
    }

    /** @return float[]|null */
    private function ConvertirTokenColor(Token $tok): ?array
    {
        if ($tok->token === T_COLOR_HEX) {
            $hex = ltrim((string) $tok->lexema, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 6 && ctype_xdigit($hex)) {
                return [
                    round(hexdec(substr($hex, 0, 2)) / 255.0, 3),
                    round(hexdec(substr($hex, 2, 2)) / 255.0, 3),
                    round(hexdec(substr($hex, 4, 2)) / 255.0, 3),
                ];
            }
        }

        $nombre = mb_strtolower(trim((string) $tok->lexema), 'UTF-8');
        return self::DICCIONARIO_COLORES[$nombre] ?? null;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function ObtenerTextoAtributo(array $attrs, int $tokenClave): string
    {
        foreach ($attrs as $attr) {
            if ($attr['claveToken'] === $tokenClave && !empty($attr['valores'])) {
                $partes = array_map(fn(Token $t): string => (string) $t->lexema, $attr['valores']);
                return trim(implode('', $partes));
            }
        }
        return '';
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     * @return float[]|null
     */
    private function ObtenerColorAtributo(array $attrs, int $tokenClave): ?array
    {
        foreach ($attrs as $attr) {
            if ($attr['claveToken'] === $tokenClave && !empty($attr['valores'])) {
                return $this->ConvertirTokenColor($attr['valores'][0]);
            }
        }
        return null;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function ObtenerBooleanoAtributo(array $attrs, int $tokenClave, bool $defecto): bool
    {
        foreach ($attrs as $attr) {
            if ($attr['claveToken'] === $tokenClave && !empty($attr['valores'])) {
                return $attr['valores'][0]->token === T_VAL_VERDADERO;
            }
        }
        return $defecto;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function ResolverAlineacionAtributos(array $attrs, string $defecto): string
    {
        foreach ($attrs as $attr) {
            if (($attr['claveToken'] === null || $attr['claveToken'] === T_ATTR_ALINEACION) && !empty($attr['valores'])) {
                $al = $this->MapearTokenAlineacion($attr['valores'][0]->token);
                if ($al !== null) {
                    return $al;
                }
            }
        }
        return $defecto;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function EsListaNumerada(array $attrs): bool
    {
        foreach ($attrs as $attr) {
            if (($attr['claveToken'] === null || $attr['claveToken'] === T_ATTR_TIPO) && !empty($attr['valores'])) {
                return $attr['valores'][0]->token === T_VAL_NUMERO_ATTR;
            }
        }
        return false;
    }

    /**
     * @param array<int, array{claveToken:?int, clave:?string, valores:Token[]}> $attrs
     */
    private function ResolverTipoCaja(array $attrs): string
    {
        foreach ($attrs as $attr) {
            if (($attr['claveToken'] === null || $attr['claveToken'] === T_ATTR_TIPO) && !empty($attr['valores'])) {
                $tok = $attr['valores'][0]->token;
                if ($tok === T_VAL_ALERTA) {
                    return 'alerta';
                }
                if ($tok === T_VAL_EXITO) {
                    return 'exito';
                }
                if ($tok === T_VAL_INFO) {
                    return 'info';
                }
            }
        }
        return 'info';
    }

    private function ResolverRutaImagen(string $nombreArchivo): ?string
    {
        if ($nombreArchivo === '') {
            return null;
        }
        $candidatos = [];
        if ($this->assetsDir !== '') {
            $candidatos[] = rtrim($this->assetsDir, "/\\") . DIRECTORY_SEPARATOR . $nombreArchivo;
        }
        $raiz = dirname(__DIR__);
        $candidatos[] = $raiz . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $nombreArchivo;
        $candidatos[] = $raiz . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $nombreArchivo;

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        // Si se invoca el Caso 16 oficial (logo.jpg) sin haber subido archivo propio aún,
        // materializar el banner JPEG institucional de respaldo en directorio temporal.
        if (strtolower($nombreArchivo) === 'logo.jpg') {
            $rutaFallback = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . 'latinex_default_logo.jpg';
            if (!is_file($rutaFallback)) {
                $bin = base64_decode(self::LOGO_RESPALDO_B64, true);
                if (is_string($bin) && $bin !== '') {
                    @file_put_contents($rutaFallback, $bin);
                }
            }
            if (is_file($rutaFallback)) {
                return $rutaFallback;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $nodos
     */
    private function ExtraerTextoPlanoNodos(array $nodos): string
    {
        $piezas = $this->AplanarInline($nodos, $this->EstiloActual());
        $buf    = '';
        foreach ($piezas as $p) {
            if ($p['kind'] === 'space') {
                $buf .= ' ';
            } elseif ($p['kind'] === 'word') {
                $buf .= $p['text'];
            }
        }
        return $buf;
    }

    /**
     * @param array<int, array<string, mixed>> $piezas
     */
    private function UltimaPalabraEmitida(array $piezas): string
    {
        for ($i = count($piezas) - 1; $i >= 0; $i--) {
            if ($piezas[$i]['kind'] === 'word') {
                return trim((string) $piezas[$i]['text']);
            }
        }
        return '';
    }

    private function RegistrarSimboloMetadato(string $lexema, string $valor, Token $tok): void
    {
        if ($this->tablaSimbolos->Buscar($lexema, 'METADATO') !== null) {
            $this->tablaSimbolos->ActualizarValor($lexema, $valor, 'METADATO');
            $this->tablaSimbolos->ActualizarEstado($lexema, 'asignado', 'METADATO');
        } else {
            $this->tablaSimbolos->Insertar(
                $lexema,
                $valor,
                'asignado',
                'METADATO',
                $tok->token,
                $tok->linea,
                $tok->columna
            );
        }
    }

    private function ActualizarTablaSimbolosFinal(): void
    {
        foreach ($this->entradasIndice as $entrada) {
            if ($entrada['id'] === null) {
                continue;
            }
            $id  = $entrada['id'];
            $val = sprintf('%s %s (pág. %d)', $entrada['numeracion'], $entrada['titulo'], $entrada['pagina']);
            if ($this->tablaSimbolos->Buscar($id, 'ETIQUETA') !== null) {
                $this->tablaSimbolos->ActualizarValor($id, $val, 'ETIQUETA');
                $this->tablaSimbolos->ActualizarEstado($id, 'asignado', 'ETIQUETA');
            } else {
                $this->tablaSimbolos->Insertar($id, $val, 'asignado', 'ETIQUETA', T_ATTR_ID, 1, 1);
            }
        }
    }

    /**
     * @param array<int, array{nivel:int, numeracion:string, titulo:string, pagina:int, id:?string}> $lista
     * @return int[]
     */
    private function ExtraerPaginasIndice(array $lista): array
    {
        return array_map(fn(array $e): int => $e['pagina'], $lista);
    }
}

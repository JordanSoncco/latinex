<?php

namespace Latinex;

/* ============================================================
 * CLASE TOKENIZADOR — Analizador Léxico del Lenguaje LATINEX
 * ============================================================
 *
 * Recorre el código fuente carácter a carácter y genera un
 * flujo de objetos Token según las reglas léxicas de LATINEX.
 *
 * Modos de operación internos:
 *   - NORMAL      : fuera de bloques, busca #comandos
 *   - ATRIBUTOS   : dentro de ( ), reconoce atributo=valor
 *   - CONTENIDO   : dentro de { }, captura texto y comandos inline
 *
 * Características:
 *   - Comentarios de línea:      //  texto ignorado
 *   - Escape de caracteres:      \# \{ \} → tomados literales
 *   - Colores hexadecimales:     #FF0000 (3 o 6 dígitos)
 *   - Números y porcentajes:     50%, 24, 3.14
 *   - Cadenas entre comillas:    "texto" 'texto'
 *   - Rutas de archivo en attr.: logo.jpg
 *   - Soporte completo UTF-8:    ñ, á, é, í, ó, ú, etc.
 *   - Anidamiento:               #fuerte{ ... #cursiva{ ... } }
 * ============================================================
 */
class Tokenizador
{
    private string     $codigo;
    private int        $indice  = 0;
    private int        $linea   = 1;
    private int        $columna = 1;
    private TablaLexica $tablaLexica;

    /** Nivel de anidamiento de paréntesis — activa modo ATRIBUTOS */
    private int $nivelParentesis = 0;
    /** Nivel de anidamiento de llaves — activa modo CONTENIDO */
    private int $nivelLlaves = 0;

    public function __construct(string $codigo, TablaLexica $tablaLexica)
    {
        $this->codigo      = $codigo;
        $this->tablaLexica = $tablaLexica;
    }

    /* ============================================================
     * MÉTODO PRINCIPAL
     * ============================================================ */

    /**
     * Retorna el siguiente Token del flujo.
     * Llama repetidamente a este método para tokenizar.
     */
    public function getToken(): Token
    {
        $this->OmitirEspacios();

        if ($this->FinCodigo()) {
            return new Token(T_FIN, null, $this->linea, $this->columna, 'fin');
        }

        $lineaIni   = $this->linea;
        $columnaIni = $this->columna;
        $car        = $this->charActual();

        // ── 1. COMENTARIOS //  ───────────────────────────────
        if ($car === '/' && $this->mirarAdelante() === '/') {
            $lexema = $this->LeerComentario();
            return new Token(T_COMENTARIO, $lexema, $lineaIni, $columnaIni, 'comentario');
        }

        // ── 2. ESCAPE \# \{ \}  ──────────────────────────────
        if ($car === '\\') {
            $this->Avanzar();
            if (!$this->FinCodigo()) {
                $escapado = $this->charActual();
                $this->Avanzar();
                return new Token(T_ESCAPE_CHAR, $escapado, $lineaIni, $columnaIni, 'escape');
            }
            return new Token(T_ERROR, '\\', $lineaIni, $columnaIni, 'error');
        }

        // ── 3. NUMERAL # (comando o color hex)  ──────────────
        if ($car === '#') {
            if ($this->esColorHex()) {
                return new Token(T_COLOR_HEX, $this->LeerColorHex(), $lineaIni, $columnaIni, 'literal_color');
            }
            $this->Avanzar(); // consumir #
            if (!$this->FinCodigo() && $this->esLetraOGuionBajo($this->charActual())) {
                $nombre   = $this->LeerPalabra();
                $elemento = $this->tablaLexica->BuscarPorLexema($nombre);
                if ($elemento !== null) {
                    return new Token($elemento['token'], '#' . $nombre, $lineaIni, $columnaIni, $elemento['tipo']);
                }
                return new Token(T_ID, '#' . $nombre, $lineaIni, $columnaIni, 'comando_desconocido');
            }
            return new Token(T_NUMERAL, '#', $lineaIni, $columnaIni, 'simbolo');
        }

        // ── 4. PARÉNTESIS  ───────────────────────────────────
        if ($car === '(') {
            $this->nivelParentesis++;
            $this->Avanzar();
            return new Token(T_APAR, '(', $lineaIni, $columnaIni, 'simbolo');
        }
        if ($car === ')') {
            $this->nivelParentesis = max(0, $this->nivelParentesis - 1);
            $this->Avanzar();
            return new Token(T_CPAR, ')', $lineaIni, $columnaIni, 'simbolo');
        }

        // ── 5. LLAVES  ───────────────────────────────────────
        if ($car === '{') {
            $this->nivelLlaves++;
            $this->Avanzar();
            return new Token(T_ALLAVE, '{', $lineaIni, $columnaIni, 'simbolo');
        }
        if ($car === '}') {
            $this->nivelLlaves = max(0, $this->nivelLlaves - 1);
            $this->Avanzar();
            return new Token(T_CLLAVE, '}', $lineaIni, $columnaIni, 'simbolo');
        }

        // ── 6. MODO ATRIBUTOS (dentro de paréntesis)  ────────
        if ($this->nivelParentesis > 0) {
            return $this->LeerTokenAtributo($lineaIni, $columnaIni);
        }

        // ── 7. MODO CONTENIDO (dentro de llaves)  ────────────
        if ($this->nivelLlaves > 0) {
            return $this->LeerTokenContenido($lineaIni, $columnaIni);
        }

        // ── 8. TEXTO FUERA DE BLOQUE  ────────────────────────
        if ($this->esLetra($car)) {
            $palabra = $this->LeerPalabra();
            return new Token(T_TEXTO_PLANO, $palabra, $lineaIni, $columnaIni, 'texto');
        }

        // Carácter no reconocido
        $this->Avanzar();
        return new Token(T_ERROR, $car, $lineaIni, $columnaIni, 'error');
    }

    /* ============================================================
     * LECTORES POR MODO
     * ============================================================ */

    /**
     * Modo ATRIBUTOS: dentro de ( ).
     * Reconoce: identificadores, atributos, valores, =, , , #color, números, %, cadenas.
     */
    private function LeerTokenAtributo(int $lineaIni, int $columnaIni): Token
    {
        $car = $this->charActual();

        if ($car === '=') {
            $this->Avanzar();
            return new Token(T_IGUAL, '=', $lineaIni, $columnaIni, 'operador');
        }
        if ($car === ',') {
            $this->Avanzar();
            return new Token(T_COMA, ',', $lineaIni, $columnaIni, 'separador');
        }
        if ($car === '+') {
            $this->Avanzar();
            return new Token(T_MAS, '+', $lineaIni, $columnaIni, 'operador');
        }
        if ($car === '-') {
            $this->Avanzar();
            return new Token(T_MENOS, '-', $lineaIni, $columnaIni, 'operador');
        }
        if ($car === '*') {
            $this->Avanzar();
            return new Token(T_POR, '*', $lineaIni, $columnaIni, 'operador');
        }
        if ($car === '/' && $this->mirarAdelante() !== '/') {
            $this->Avanzar();
            return new Token(T_DIV, '/', $lineaIni, $columnaIni, 'operador');
        }

        // Color hexadecimal en atributos: color=#FF0000
        if ($car === '#' && $this->esColorHex()) {
            return new Token(T_COLOR_HEX, $this->LeerColorHex(), $lineaIni, $columnaIni, 'literal_color');
        }

        // Número (entero o decimal) con posible %
        if (ctype_digit($car)) {
            $lexema = $this->LeerNumero();
            if (!$this->FinCodigo() && $this->charActual() === '%') {
                $lexema .= '%';
                $this->Avanzar();
            }
            return new Token(T_NUMERO, $lexema, $lineaIni, $columnaIni, 'literal_numero');
        }

        // Cadena entre comillas
        if ($car === '"' || $car === "'") {
            $lexema = $this->LeerCadena($car);
            if ($lexema !== null) {
                return new Token(T_CADENA, $lexema, $lineaIni, $columnaIni, 'literal_cadena');
            }
            return new Token(T_ERROR, $car, $lineaIni, $columnaIni, 'error');
        }

        // Identificador, atributo o valor
        if ($this->esLetra($car)) {
            $palabra = $this->LeerPalabra();

            // Soporte para rutas simples: logo.jpg
            if (!$this->FinCodigo() && $this->charActual() === '.') {
                $palabra .= '.';
                $this->Avanzar();
                if (!$this->FinCodigo() && $this->esLetra($this->charActual())) {
                    $palabra .= $this->LeerPalabra();
                }
                return new Token(T_CADENA, $palabra, $lineaIni, $columnaIni, 'literal_archivo');
            }

            $elemento = $this->tablaLexica->BuscarPorLexema($palabra);
            if ($elemento !== null) {
                return new Token($elemento['token'], $palabra, $lineaIni, $columnaIni, $elemento['tipo']);
            }
            return new Token(T_ID, $palabra, $lineaIni, $columnaIni, 'identificador');
        }

        $this->Avanzar();
        return new Token(T_ERROR, $car, $lineaIni, $columnaIni, 'error');
    }

    /**
     * Modo CONTENIDO: dentro de { }.
     * Reconoce: texto plano, comandos inline #fuerte{...}, escapes.
     */
    private function LeerTokenContenido(int $lineaIni, int $columnaIni): Token
    {
        $car = $this->charActual();

        // Escape dentro del contenido
        if ($car === '\\') {
            $this->Avanzar();
            if (!$this->FinCodigo()) {
                $escapado = $this->charActual();
                $this->Avanzar();
                return new Token(T_ESCAPE_CHAR, $escapado, $lineaIni, $columnaIni, 'escape');
            }
            return new Token(T_ERROR, '\\', $lineaIni, $columnaIni, 'error');
        }

        // Comentario dentro del contenido
        if ($car === '/' && $this->mirarAdelante() === '/') {
            return new Token(T_COMENTARIO, $this->LeerComentario(), $lineaIni, $columnaIni, 'comentario');
        }

        // Comando inline o color hex
        if ($car === '#') {
            if ($this->esColorHex()) {
                return new Token(T_COLOR_HEX, $this->LeerColorHex(), $lineaIni, $columnaIni, 'literal_color');
            }
            $this->Avanzar();
            if (!$this->FinCodigo() && $this->esLetraOGuionBajo($this->charActual())) {
                $nombre   = $this->LeerPalabra();
                $elemento = $this->tablaLexica->BuscarPorLexema($nombre);
                if ($elemento !== null) {
                    return new Token($elemento['token'], '#' . $nombre, $lineaIni, $columnaIni, $elemento['tipo']);
                }
                return new Token(T_ID, '#' . $nombre, $lineaIni, $columnaIni, 'comando_desconocido');
            }
            return new Token(T_NUMERAL, '#', $lineaIni, $columnaIni, 'simbolo');
        }

        // Texto plano
        $lexema = $this->LeerTextoPlano();
        if ($lexema !== '') {
            return new Token(T_TEXTO_PLANO, $lexema, $lineaIni, $columnaIni, 'texto');
        }

        $this->Avanzar();
        return new Token(T_ERROR, $car, $lineaIni, $columnaIni, 'error');
    }

    /* ============================================================
     * LECTORES AUXILIARES
     * ============================================================ */

    /** Lee un comentario de línea //... hasta \n */
    private function LeerComentario(): string
    {
        $this->Avanzar(); // /
        $this->Avanzar(); // /
        $lexema = '//';
        while (!$this->FinCodigo()) {
            $car = $this->charActual();
            if ($car === "\n") { $this->Avanzar(); break; }
            $lexema .= $car;
            $this->Avanzar();
        }
        return $lexema;
    }

    /** Lee una secuencia de letras, dígitos y _ (incluye UTF-8) */
    private function LeerPalabra(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $car = $this->charActual();
            if ($this->esLetraOGuionBajo($car) || ctype_digit($car)) {
                $lexema .= $car;
                $this->Avanzar();
            } else {
                break;
            }
        }
        return $lexema;
    }

    /** Lee un número entero o decimal */
    private function LeerNumero(): string
    {
        $lexema = '';
        $puntos = 0;
        while (!$this->FinCodigo()) {
            $car = $this->charActual();
            if (ctype_digit($car)) {
                $lexema .= $car; $this->Avanzar();
            } elseif ($car === '.' && $puntos === 0) {
                $puntos++; $lexema .= $car; $this->Avanzar();
            } else {
                break;
            }
        }
        return $lexema;
    }

    /** Lee una cadena delimitada por comillas simples o dobles */
    private function LeerCadena(string $delimitador): ?string
    {
        $this->Avanzar(); // comilla de apertura
        $lexema = '';
        while (!$this->FinCodigo()) {
            $car = $this->charActual();
            if ($car === '\\') {
                $this->Avanzar();
                if (!$this->FinCodigo()) { $lexema .= $this->charActual(); $this->Avanzar(); }
                continue;
            }
            if ($car === $delimitador) { $this->Avanzar(); return $lexema; }
            $lexema .= $car;
            $this->Avanzar();
        }
        return null; // cadena sin cerrar
    }

    /** Lee texto plano hasta encontrar #, {, }, // o \ */
    private function LeerTextoPlano(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $car = $this->charActual();
            if ($car === '#' || $car === '{' || $car === '}' || $car === '\\') break;
            if ($car === '/' && $this->mirarAdelante() === '/') break;
            $lexema .= $car;
            $this->Avanzar();
        }
        return $lexema;
    }

    /** Verifica si la posición actual es el inicio de un color hex #RRGGBB o #RGB */
    private function esColorHex(): bool
    {
        if ($this->charActual() !== '#') return false;
        $pos   = $this->indice + 1;
        $total = mb_strlen($this->codigo, 'UTF-8');
        $count = 0;
        while ($pos < $total && $count < 6) {
            $c = mb_substr($this->codigo, $pos, 1, 'UTF-8');
            if (ctype_xdigit($c)) { $count++; $pos++; } else break;
        }
        if ($count !== 3 && $count !== 6) return false;
        // No debe seguir una letra no-hex (sería nombre de comando)
        if ($pos < $total) {
            $sig = mb_substr($this->codigo, $pos, 1, 'UTF-8');
            if ($this->esLetra($sig) && !ctype_xdigit($sig)) return false;
        }
        return true;
    }

    /** Lee un color hex #RRGGBB o #RGB */
    private function LeerColorHex(): string
    {
        $lexema = '#';
        $this->Avanzar();
        while (!$this->FinCodigo() && ctype_xdigit($this->charActual())) {
            $lexema .= $this->charActual();
            $this->Avanzar();
        }
        return $lexema;
    }

    /* ============================================================
     * UTILIDADES DE NAVEGACIÓN
     * ============================================================ */

    private function OmitirEspacios(): void
    {
        while (!$this->FinCodigo() && ctype_space($this->charActual())) {
            $this->Avanzar();
        }
    }

    private function charActual(): string
    {
        return mb_substr($this->codigo, $this->indice, 1, 'UTF-8');
    }

    private function mirarAdelante(): string
    {
        $pos = $this->indice + 1;
        if ($pos >= mb_strlen($this->codigo, 'UTF-8')) return '';
        return mb_substr($this->codigo, $pos, 1, 'UTF-8');
    }

    private function Avanzar(): void
    {
        if ($this->FinCodigo()) return;
        if ($this->charActual() === "\n") {
            $this->linea++;
            $this->columna = 1;
        } else {
            $this->columna++;
        }
        $this->indice++;
    }

    private function FinCodigo(): bool
    {
        return $this->indice >= mb_strlen($this->codigo, 'UTF-8');
    }

    private function esLetra(string $c): bool
    {
        return preg_match('/\p{L}/u', $c) === 1;
    }

    private function esLetraOGuionBajo(string $c): bool
    {
        return $c === '_' || $this->esLetra($c);
    }

    /**
     * Tokeniza todo el código y retorna un array de Token.
     * Útil para debug y tests.
     */
    public function tokenizarTodo(): array
    {
        $tokens = [];
        while (true) {
            $t = $this->getToken();
            $tokens[] = $t;
            if ($t->token === T_FIN) break;
        }
        return $tokens;
    }
}

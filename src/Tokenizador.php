<?php

namespace Latinex;

require_once __DIR__ . '/TablaLexica.php';

class Tokenizador {
    private $codigo;
    private $posicion;
    private $longitud;
    private $tokens;
    private $nivelLlaves;

    public function __construct($codigo) {
        $this->codigo = $codigo;
        $this->posicion = 0;
        $this->longitud = mb_strlen($codigo, 'UTF-8');
        $this->tokens = [];
        $this->nivelLlaves = 0;
    }

    public function tokenizar() {
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();

            // Ignorar comentarios que inician con // hasta el salto de línea
            if ($char === '/' && $this->mirarAdelante() === '/') {
                $this->saltarComentario();
                continue;
            }

            if ($this->nivelLlaves > 0) {
                // MODO BLOQUE: Capturar todo como texto a menos que sea comando o llave
                if ($char === '}') {
                    $this->agregarToken(TablaLexica::T_LLAVE_CIERRA, '}');
                    $this->nivelLlaves--;
                    $this->avanzar();
                } else if ($char === '#') {
                    $this->agregarToken(TablaLexica::T_NUMERAL, '#');
                    $this->avanzar();
                    $this->leerEtiqueta();
                } else if ($char === '{') {
                    $this->agregarToken(TablaLexica::T_LLAVE_ABRE, '{');
                    $this->nivelLlaves++;
                    $this->avanzar();
                } else {
                    $this->leerBloqueTexto();
                }
            } else {
                // MODO NORMAL: Fuera de bloques de texto (se ignoran espacios libres)
                if (preg_match('/\s/', $char)) {
                    $this->avanzar();
                    continue;
                }

                switch ($char) {
                    case '#':
                        $this->agregarToken(TablaLexica::T_NUMERAL, '#');
                        $this->avanzar();
                        $this->leerEtiqueta();
                        break;
                    case '(':
                        $this->agregarToken(TablaLexica::T_PARENTESIS_ABRE, '(');
                        $this->avanzar();
                        $this->leerAtributos();
                        break;
                    case '{':
                        $this->agregarToken(TablaLexica::T_LLAVE_ABRE, '{');
                        $this->nivelLlaves++;
                        $this->avanzar();
                        break;
                    case '}':
                        $this->agregarToken(TablaLexica::T_LLAVE_CIERRA, '}');
                        if ($this->nivelLlaves > 0) $this->nivelLlaves--;
                        $this->avanzar();
                        break;
                    default:
                        $this->avanzar();
                        break;
                }
            }
        }
        
        $this->agregarToken(TablaLexica::T_EOF, '');
        return $this->tokens;
    }

    private function saltarComentario() {
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();
            $this->avanzar();
            if ($char === "\n" || $char === "\r") {
                break;
            }
        }
    }

    private function mirarAdelante() {
        if ($this->posicion + 1 < $this->longitud) {
            return mb_substr($this->codigo, $this->posicion + 1, 1, 'UTF-8');
        }
        return null;
    }

    private function leerEtiqueta() {
        $etiqueta = '';
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();
            if (preg_match('/[a-zA-Z0-9_-]/', $char)) {
                $etiqueta .= $char;
                $this->avanzar();
            } else {
                break;
            }
        }
        
        if (!empty($etiqueta)) {
            $this->agregarToken(TablaLexica::T_ETIQUETA, $etiqueta);
        }
    }

    private function leerAtributos() {
        $buffer = '';
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();
            if ($char === ')') {
                if (!empty(trim($buffer))) {
                    $this->procesarBufferAtributos($buffer);
                }
                $this->agregarToken(TablaLexica::T_PARENTESIS_CIERRA, ')');
                $this->avanzar();
                break;
            }
            $buffer .= $char;
            $this->avanzar();
        }
    }
    
    private function procesarBufferAtributos($buffer) {
        $pares = explode(',', $buffer);
        foreach ($pares as $par) {
            $partes = explode('=', $par);
            if (count($partes) === 2) {
                $this->agregarToken(TablaLexica::T_ATRIBUTO, trim($partes[0]));
                $this->agregarToken(TablaLexica::T_IGUAL, '=');
                $this->agregarToken(TablaLexica::T_VALOR_ATRIBUTO, trim($partes[1]));
            } else {
                $this->agregarToken(TablaLexica::T_ATRIBUTO, trim($partes[0]));
            }
        }
    }

    /**
     * Captura el texto interno hasta encontrar una llave de cierre, apertura o un nuevo comando.
     * Soporta escape de caracteres con \ (backslash).
     */
    private function leerBloqueTexto() {
        $texto = '';
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();
            
            if ($char === '#' || $char === '{' || $char === '}') {
                break;
            } else if ($char === '\\') {
                $this->avanzar();
                if ($this->posicion < $this->longitud) {
                    $texto .= $this->obtenerCharActual();
                    $this->avanzar();
                }
            } else {
                $texto .= $char;
                $this->avanzar();
            }
        }
        
        if ($texto !== '') {
            $this->agregarToken(TablaLexica::T_TEXTO_PLANO, $texto);
        }
    }

    private function obtenerCharActual() {
        return mb_substr($this->codigo, $this->posicion, 1, 'UTF-8');
    }

    private function avanzar() {
        $this->posicion++;
    }

    private function agregarToken($tipo, $valor) {
        $this->tokens[] = [
            'tipo' => $tipo,
            'valor' => $valor
        ];
    }
}

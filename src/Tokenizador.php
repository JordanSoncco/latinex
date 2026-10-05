<?php

namespace Latinex;

require_once __DIR__ . '/TablaLexica.php';

class Tokenizador {
    private $codigo;
    private $posicion;
    private $longitud;
    private $tokens;

    public function __construct($codigo) {
        $this->codigo = $codigo;
        $this->posicion = 0;
        $this->longitud = mb_strlen($codigo, 'UTF-8');
        $this->tokens = [];
    }

    public function tokenizar() {
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();

            // Ignorar espacios en blanco fuera de los bloques de texto
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
                    $this->avanzar();
                    $this->leerBloqueTexto();
                    break;
                case '}':
                    $this->agregarToken(TablaLexica::T_LLAVE_CIERRA, '}');
                    $this->avanzar();
                    break;
                default:
                    // Caracteres sueltos que no inician instrucciones se ignoran por ahora
                    // a menos que pertenezcan a un bloque que fue parseado en leerBloqueTexto
                    $this->avanzar();
                    break;
            }
        }
        
        $this->agregarToken(TablaLexica::T_EOF, '');
        return $this->tokens;
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
        // Lógica para leer contenido dentro de los paréntesis: atributo=valor, atributo2=valor2
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
     * Captura TODO el texto dentro de las llaves, conservando espacios y formato.
     */
    private function leerBloqueTexto() {
        $texto = '';
        $llavesAnidadas = 0; // Para soportar llaves literales dentro del texto si fuera necesario
        
        while ($this->posicion < $this->longitud) {
            $char = $this->obtenerCharActual();
            
            if ($char === '{') {
                $llavesAnidadas++;
                $texto .= $char;
                $this->avanzar();
            } else if ($char === '}') {
                if ($llavesAnidadas === 0) {
                    // Fin del bloque. El switch principal capturará el '}'
                    break;
                } else {
                    $llavesAnidadas--;
                    $texto .= $char;
                    $this->avanzar();
                }
            } else {
                $texto .= $char;
                $this->avanzar();
            }
        }
        
        $this->agregarToken(TablaLexica::T_TEXTO_BLOQUE, $texto);
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

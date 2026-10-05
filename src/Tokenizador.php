<?php
namespace Latinex;

class Tokenizador
{
    private string $codigo;
    private int $indice = 0;
    private int $linea = 1;
    private int $columna = 1;
    private TablaLexica $tablaLexica;
    
    // Estado para saber si estamos dentro de corchetes de atributos [...]
    private bool $enAtributos = false;

    public function __construct(string $codigo, TablaLexica $tablaLexica)
    {
        $this->codigo = $codigo;
        $this->tablaLexica = $tablaLexica;
    }

    public function getToken(): Token
    {
        // Si estamos en atributos, omitimos espacios en blanco
        if ($this->enAtributos) {
            $this->OmitirEspacios();
        }

        if ($this->FinCodigo()) {
            return new Token(T_FIN, null, $this->linea, $this->columna);
        }

        $lineaInicial = $this->linea;
        $columnaInicial = $this->columna;

        $caracter = $this->obtenerCharActual();

        // 1. COMENTARIOS (//)
        if ($caracter === '/' && $this->mirarAdelante() === '/') {
            return new Token(T_COMENTARIO, $this->LeerComentario(), $lineaInicial, $columnaInicial);
        }

        // 2. COMANDOS EMPEZANDO CON # O @
        if ($caracter === '#' || $caracter === '@') {
            $lexema = $this->LeerComando();
            $elemento = $this->tablaLexica->BuscarPorLexema($lexema);
            
            if ($elemento !== null) {
                return new Token($elemento['token'], $lexema, $lineaInicial, $columnaInicial);
            } else {
                // Si no existe en la tabla, se considera texto plano o error. Lo trataremos como texto.
                return new Token(T_TEXTO_PLANO, $lexema, $lineaInicial, $columnaInicial);
            }
        }

        // 3. SÍMBOLOS FIJOS ({, }, [, ], =, ,)
        $elementoFijo = $this->LeerLexemaFijo();
        if ($elementoFijo !== null) {
            // Actualizar estado de atributos
            if ($elementoFijo['token'] === T_ACOR) {
                $this->enAtributos = true;
            } elseif ($elementoFijo['token'] === T_CCOR) {
                $this->enAtributos = false;
            }

            return new Token($elementoFijo['token'], $elementoFijo['lexema'], $lineaInicial, $columnaInicial);
        }

        // 4. MODO ATRIBUTOS: Leer Identificadores o Cadenas
        if ($this->enAtributos) {
            if (ctype_alpha($caracter)) {
                $lexema = $this->LeerIdentificador();
                return new Token(T_ID, $lexema, $lineaInicial, $columnaInicial);
            }
            if ($caracter === '"' || $caracter === "'") {
                $lexema = $this->LeerCadena($caracter);
                if ($lexema === null) {
                    return new Token(T_ERROR, null, $lineaInicial, $columnaInicial);
                }
                return new Token(T_CADENA, $lexema, $lineaInicial, $columnaInicial);
            }
            
            // Si es un número en los atributos (opcional)
            if (ctype_digit($caracter)) {
                $lexema = $this->LeerNumero();
                if ($lexema === null) {
                    return new Token(T_ERROR, null, $lineaInicial, $columnaInicial);
                }
                return new Token(T_TEXTO_PLANO, $lexema, $lineaInicial, $columnaInicial); // Números como texto plano
            }
        }

        // 5. MODO TEXTO PLANO
        // Si no estamos en atributos, o no encaja con lo anterior, es texto plano.
        $lexema = $this->LeerTextoPlano();
        if ($lexema !== '') {
            return new Token(T_TEXTO_PLANO, $lexema, $lineaInicial, $columnaInicial);
        }

        // Si por alguna razón cae aquí, devolvemos error
        $lexema = $caracter;
        $this->Avanzar();
        return new Token(T_ERROR, $lexema, $lineaInicial, $columnaInicial);
    }

    private function OmitirEspacios(): void
    {
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            if (!ctype_space($caracter)) {
                break;
            }
            $this->Avanzar();
        }
    }

    private function LeerComentario(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            if ($caracter === "\n") {
                $this->Avanzar(); // Consumir el salto de línea
                break;
            }
            $lexema .= $caracter;
            $this->Avanzar();
        }
        return $lexema;
    }

    private function LeerComando(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            // Comandos permitidos: empiezan con # o @, seguidos de letras o guion bajo
            if ($caracter === '#' || $caracter === '@' || ctype_alpha($caracter) || $caracter === '_') {
                $lexema .= $caracter;
                $this->Avanzar();
            } else {
                break;
            }
        }
        return $lexema;
    }

    private function LeerIdentificador(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            if (!ctype_alpha($caracter) && !ctype_digit($caracter) && $caracter !== '_') {
                break;
            }
            $lexema .= $caracter;
            $this->Avanzar();
        }
        return $lexema;
    }

    private function LeerNumero(): ?string
    {
        $lexema = '';
        $cantidadPuntos = 0;
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            if (ctype_digit($caracter)) {
                $lexema .= $caracter;
                $this->Avanzar();
                continue;
            }
            if ($caracter === '.') {
                $cantidadPuntos++;
                if ($cantidadPuntos > 1) {
                    $this->Avanzar();
                    return null;
                }
                $lexema .= $caracter;
                $this->Avanzar();
                continue;
            }
            break;
        }
        return $lexema;
    }

    private function LeerCadena(string $delimitador): ?string
    {
        $this->Avanzar(); // Consumir comilla inicial
        $lexema = '';
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();
            if ($caracter === '\\') {
                $this->Avanzar();
                if (!$this->FinCodigo()) {
                    $lexema .= $this->obtenerCharActual();
                    $this->Avanzar();
                }
                continue;
            }
            if ($caracter === $delimitador) {
                $this->Avanzar();
                return $lexema;
            }
            $lexema .= $caracter;
            $this->Avanzar();
        }
        return null; // Falta comilla de cierre
    }

    private function LeerTextoPlano(): string
    {
        $lexema = '';
        while (!$this->FinCodigo()) {
            $caracter = $this->obtenerCharActual();

            // Si escapamos el carácter, lo tomamos literal
            if ($caracter === '\\') {
                $this->Avanzar();
                if (!$this->FinCodigo()) {
                    $lexema .= $this->obtenerCharActual();
                    $this->Avanzar();
                }
                continue;
            }

            // Si encontramos inicio de un comando, bloque, o comentario, paramos.
            if ($caracter === '#' || $caracter === '@' || $caracter === '{' || $caracter === '}' || $caracter === '[' || $caracter === ']') {
                break;
            }
            
            // También paramos si vemos un '//' (comentario)
            if ($caracter === '/' && $this->mirarAdelante() === '/') {
                break;
            }

            $lexema .= $caracter;
            $this->Avanzar();
        }
        return $lexema;
    }

    private function LeerLexemaFijo(): ?array
    {
        $elemento = $this->tablaLexica->BuscarLexemaMasLargo($this->codigo, $this->indice);
        if ($elemento === null) {
            return null;
        }
        
        for ($i = 0; $i < $elemento['longitud']; $i++) {
            $this->Avanzar();
        }
        
        return $elemento;
    }

    private function obtenerCharActual(): string
    {
        return mb_substr($this->codigo, $this->indice, 1, 'UTF-8');
    }

    private function mirarAdelante(): string
    {
        if ($this->indice + 1 >= mb_strlen($this->codigo, 'UTF-8')) {
            return '';
        }
        return mb_substr($this->codigo, $this->indice + 1, 1, 'UTF-8');
    }

    private function Avanzar(): void
    {
        if ($this->FinCodigo()) {
            return;
        }

        $caracter = $this->obtenerCharActual();
        if ($caracter === "\n") {
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
}

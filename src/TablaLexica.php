<?php
namespace Latinex;

/* ============================================================
 * TOKENS DEL LENGUAJE
 * ============================================================
 */
const T_FIN = 666;
const T_ERROR = 999;

const T_ALLAVE = 3;
const T_CLLAVE = 4;
const T_ACOR = 5;
const T_CCOR = 6;
const T_IGUAL = 7;
const T_COMA = 8;

const T_TEXTO_PLANO = 9;
const T_ID = 10;
const T_CADENA = 11;
const T_COMENTARIO = 12;

// Comandos
const T_CONFIG = 20;
const T_METADATOS = 21;
const T_TITULO = 22;
const T_AUTOR = 23;
const T_FECHA = 24;
const T_RESUMEN = 25;
const T_SECCION = 26;
const T_SUBSECCION = 27;
const T_PARRAFO = 28;
const T_TEXTO = 29;
const T_FUERTE = 30;
const T_ENFASIS = 31;
const T_SUBRAYADO = 32;
const T_INLINE_CODE = 33;
const T_LISTA = 34;
const T_ITEM = 35;
const T_LISTA_NUMERADA = 36;
const T_IMAGEN = 37;
const T_TABLA = 38;
const T_FILA = 39;
const T_COLUMNA = 40;
const T_ENCABEZADO = 41;
const T_BLOQUE_CODIGO = 42;
const T_CITA = 43;
const T_ECUACION = 44;
const T_MATEMATICA = 45;

/* ============================================================
 * CLASE TOKEN
 * ============================================================
 */
class Token
{
    public int $token;
    public ?string $lexema;
    public int $linea;
    public int $columna;

    public function __construct(
        int $token,
        ?string $lexema,
        int $linea,
        int $columna
    ) {
        $this->token = $token;
        $this->lexema = $lexema;
        $this->linea = $linea;
        $this->columna = $columna;
    }

    public function Mostrar(): void
    {
        echo "Token(" . $this->token . ") \t";
        echo "Lexema(" . ($this->lexema ?? 'null') . ") \t";
        echo "Linea(" . $this->linea . ") \t";
        echo "Columna(" . $this->columna . ")";
        echo PHP_EOL;
    }
}

/* ============================================================
 * CLASE TABLA LÉXICA
 * ============================================================
 */
class TablaLexica
{
    private array $porToken = [];
    private array $porLexema = [];

    public function __construct()
    {
        // Símbolos
        $this->Agregar(T_ALLAVE,     '{',           'simbolo');
        $this->Agregar(T_CLLAVE,     '}',           'simbolo');
        $this->Agregar(T_ACOR,       '[',           'simbolo');
        $this->Agregar(T_CCOR,       ']',           'simbolo');
        $this->Agregar(T_IGUAL,      '=',           'operador');
        $this->Agregar(T_COMA,       ',',           'separador');

        // Comandos de Bloque / Globales
        $this->Agregar(T_CONFIG,        '@config',        'comando');
        $this->Agregar(T_METADATOS,     '@metadatos',     'comando');
        $this->Agregar(T_TITULO,        '#titulo',        'comando');
        $this->Agregar(T_AUTOR,         '#autor',         'comando');
        $this->Agregar(T_FECHA,         '#fecha',         'comando');
        $this->Agregar(T_RESUMEN,       '#resumen',       'comando');
        $this->Agregar(T_SECCION,       '#seccion',       'comando');
        $this->Agregar(T_SUBSECCION,    '#subseccion',    'comando');
        $this->Agregar(T_PARRAFO,       '#parrafo',       'comando');
        $this->Agregar(T_TEXTO,         '#texto',         'comando');
        
        // Comandos de Texto (Inline)
        $this->Agregar(T_FUERTE,        '#fuerte',        'comando');
        $this->Agregar(T_ENFASIS,       '#enfasis',       'comando');
        $this->Agregar(T_SUBRAYADO,     '#subrayado',     'comando');
        $this->Agregar(T_INLINE_CODE,   '#inline_code',   'comando');
        
        // Estructuras de Datos
        $this->Agregar(T_LISTA,         '#lista',         'comando');
        $this->Agregar(T_ITEM,          '#item',          'comando');
        $this->Agregar(T_LISTA_NUMERADA,'#lista_numerada','comando');
        $this->Agregar(T_IMAGEN,        '#imagen',        'comando');
        $this->Agregar(T_TABLA,         '#tabla',         'comando');
        $this->Agregar(T_FILA,          '#fila',          'comando');
        $this->Agregar(T_COLUMNA,       '#columna',       'comando');
        $this->Agregar(T_ENCABEZADO,    '#encabezado',    'comando');
        $this->Agregar(T_BLOQUE_CODIGO, '#bloque_codigo', 'comando');
        $this->Agregar(T_CITA,          '#cita',          'comando');
        
        // Matemáticas
        $this->Agregar(T_ECUACION,      '#ecuacion',      'comando');
        $this->Agregar(T_MATEMATICA,    '#matematica',    'comando');
    }

    private function Agregar(int $token, string $lexema, string $tipo): void
    {
        $dato = [
            'token'  => $token,
            'lexema' => $lexema,
            'tipo'   => $tipo
        ];
        $this->porToken[$token] = $dato;
        $this->porLexema[$lexema] = $dato;
    }

    public function BuscarPorToken(int $token): ?array
    {
        return $this->porToken[$token] ?? null;
    }

    public function BuscarPorLexema(string $lexema): ?array
    {
        return $this->porLexema[$lexema] ?? null;
    }

    public function BuscarLexemaMasLargo(string $codigo, int $posicion): ?array
    {
        $encontrado = null;

        foreach ($this->porLexema as $lexema => $datos) {
            $longitud = strlen($lexema);
            $fragmento = substr($codigo, $posicion, $longitud);

            if ($fragmento === $lexema) {
                if ($encontrado === null || $longitud > $encontrado['longitud']) {
                    $encontrado = [
                        'token'    => $datos['token'],
                        'lexema'   => $lexema,
                        'tipo'     => $datos['tipo'],
                        'longitud' => $longitud
                    ];
                }
            }
        }
        return $encontrado;
    }
}

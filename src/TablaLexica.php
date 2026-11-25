<?php

namespace Latinex;

/* ============================================================
 * TOKENS DEL LENGUAJE LATINEX
 * Regla de sintaxis: #etiqueta(atributo=valor) { contenido }
 * ============================================================
 *
 * Clasificación por rangos:
 *   0-9    : Símbolos y delimitadores
 *   10-16  : Literales y especiales
 *   20-28  : Comandos de estructura / metadatos
 *   30-33  : Comandos de formato inline
 *   40-41  : Listas y multimedia
 *   50-52  : Tablas
 *   60-63  : Matemáticas
 *   70-74  : Control de flujo y macros
 *   80     : Entornos especiales
 *   90-102 : Nombres de atributos reservados
 *   110-120: Valores de atributos reservados
 *   666    : Fin de archivo
 *   999    : Error léxico
 */

// ── Símbolos y delimitadores ──────────────────────────────────
const T_NUMERAL    = 0;   // #
const T_APAR       = 1;   // (
const T_CPAR       = 2;   // )
const T_ALLAVE     = 3;   // {
const T_CLLAVE     = 4;   // }
const T_IGUAL      = 5;   // =
const T_COMA       = 6;   // ,
const T_PUNTO      = 7;   // .
const T_BARRA_INV  = 8;   // \ (escape)
const T_PORCENTAJE = 9;   // %

// ── Literales y especiales ────────────────────────────────────
const T_ID           = 10;  // Identificador genérico
const T_NUMERO       = 11;  // Literal numérico (entero o decimal)
const T_CADENA       = 12;  // Cadena entre comillas
const T_TEXTO_PLANO  = 13;  // Contenido libre dentro de { }
const T_COMENTARIO   = 14;  // Comentario // ...
const T_COLOR_HEX    = 15;  // Color hexadecimal: #FF0000
const T_ESCAPE_CHAR  = 16;  // Carácter escapado: \# \{ \}

// ── Comandos de estructura / metadatos del documento ──────────
const T_TITULO        = 20;  // #titulo
const T_AUTOR         = 21;  // #autor
const T_FECHA         = 22;  // #fecha
const T_SECCION       = 23;  // #seccion
const T_SUBSECCION    = 24;  // #subseccion / #subseccion1
const T_SUBSECCION2   = 25;  // #subseccion2
const T_TEXTO         = 26;  // #texto
const T_RESUMEN       = 27;  // #resumen
const T_INDICEGENERAL = 28;  // #indicegeneral
const T_SUBSECCION3   = 29;  // #subseccion3
const T_SUBSECCION4   = 34;  // #subseccion4

// ── Comandos de formato de texto (inline) ─────────────────────
const T_FUERTE    = 30;  // #fuerte    → negrita
const T_CURSIVA   = 31;  // #cursiva   → itálica
const T_SUBRAYADO = 32;  // #subrayado → subrayado
const T_RESALTADO = 33;  // #resaltado → highlight/fondo

// ── Comandos de listas y multimedia ──────────────────────────
const T_LISTA  = 40;  // #lista
const T_IMAGEN = 41;  // #imagen

// ── Comandos de tablas ────────────────────────────────────────
const T_TABLA = 50;  // #tabla
const T_FILA  = 51;  // #fila
const T_CELDA = 52;  // #celda

// ── Comandos matemáticos y científicos ────────────────────────
const T_SUPERINDICE = 60;  // #superindice
const T_SUBINDICE   = 61;  // #subindice
const T_FRACCION    = 62;  // #fraccion
const T_ECUACION    = 63;  // #ecuacion
const T_MAS         = 64;  // +
const T_MENOS       = 65;  // -
const T_POR         = 66;  // *
const T_DIV         = 67;  // /

// ── Control de flujo y macros ─────────────────────────────────
const T_SALTO       = 70;  // #salto        → salto de línea
const T_NUEVAPAGINA = 71;  // #nuevapagina  → salto de página
const T_DEFINIR     = 72;  // #definir      → declaración de macro
const T_IMPRIMIR    = 73;  // #imprimir     → invocación de macro
const T_REFERENCIA  = 74;  // #referencia   → referencia cruzada

// ── Entornos especiales ───────────────────────────────────────
const T_CAJA = 80;  // #caja → caja de advertencia

// ── Nombres de atributos ──────────────────────────────────────
const T_ATTR_COLOR       = 90;
const T_ATTR_TAMAÑO      = 91;
const T_ATTR_TAMANO      = T_ATTR_TAMAÑO; // alias ASCII sin tilde
const T_ATTR_FUENTE      = 92;  // fuente tipográfica
const T_ATTR_FUENTE_ATTR = 92;  // alias de compatibilidad
const T_ATTR_ALINEACION  = 93;
const T_ATTR_RUTA        = 94;
const T_ATTR_ANCHO       = 95;
const T_ATTR_ALTO        = 96;
const T_ATTR_BORDES      = 97;
const T_ATTR_FONDO       = 98;
const T_ATTR_TIPO        = 99;
const T_ATTR_NUMERADOR   = 100;
const T_ATTR_DENOMINADOR = 101;
const T_ATTR_ID          = 102;

// ── Valores reservados de atributos ───────────────────────────
const T_VAL_PUNTO       = 110;
const T_VAL_NUMERO_ATTR = 111;  // 'numero' como valor (lista numerada)
const T_VAL_DERECHA     = 112;
const T_VAL_CENTRO      = 113;
const T_VAL_JUSTIFICADO = 114;
const T_VAL_IZQUIERDA   = 115;
const T_VAL_VERDADERO   = 116;
const T_VAL_FALSO       = 117;
const T_VAL_AUTO        = 118;
const T_VAL_GRIS        = 119;
const T_VAL_ALERTA      = 120;
const T_VAL_ROJO        = 121;
const T_VAL_AZUL        = 122;
const T_VAL_VERDE       = 123;
const T_VAL_AMARILLO    = 124;
const T_VAL_NEGRO       = 125;
const T_VAL_BLANCO      = 126;
const T_VAL_INFO        = 127;
const T_VAL_EXITO       = 128;

// ── Fin y Error ───────────────────────────────────────────────
const T_FIN   = 666;
const T_ERROR = 999;


/* ============================================================
 * TABLA DE NOMBRES LEGIBLES DE TOKEN
 * ============================================================
 */
function NombreToken(int $token): string
{
    $nombres = [
        T_NUMERAL    => 'NUMERAL',      T_APAR       => 'APAR',
        T_CPAR       => 'CPAR',         T_ALLAVE     => 'ALLAVE',
        T_CLLAVE     => 'CLLAVE',       T_IGUAL      => 'IGUAL',
        T_COMA       => 'COMA',         T_PUNTO      => 'PUNTO',
        T_BARRA_INV  => 'BARRA_INV',    T_PORCENTAJE => 'PORCENTAJE',

        T_ID           => 'ID',           T_NUMERO       => 'NUMERO',
        T_CADENA       => 'CADENA',       T_TEXTO_PLANO  => 'TEXTO_PLANO',
        T_COMENTARIO   => 'COMENTARIO',   T_COLOR_HEX    => 'COLOR_HEX',
        T_ESCAPE_CHAR  => 'ESCAPE_CHAR',

        T_TITULO        => 'TITULO',       T_AUTOR        => 'AUTOR',
        T_FECHA         => 'FECHA',        T_SECCION      => 'SECCION',
        T_SUBSECCION    => 'SUBSECCION',   T_SUBSECCION2  => 'SUBSECCION2',
        T_SUBSECCION3   => 'SUBSECCION3',  T_SUBSECCION4  => 'SUBSECCION4',
        T_TEXTO         => 'TEXTO',        T_RESUMEN      => 'RESUMEN',
        T_INDICEGENERAL => 'INDICEGENERAL',

        T_FUERTE     => 'FUERTE',       T_CURSIVA    => 'CURSIVA',
        T_SUBRAYADO  => 'SUBRAYADO',    T_RESALTADO  => 'RESALTADO',

        T_LISTA  => 'LISTA',   T_IMAGEN => 'IMAGEN',

        T_TABLA => 'TABLA',   T_FILA => 'FILA',   T_CELDA => 'CELDA',

        T_SUPERINDICE => 'SUPERINDICE',  T_SUBINDICE => 'SUBINDICE',
        T_FRACCION    => 'FRACCION',     T_ECUACION  => 'ECUACION',
        T_MAS         => 'MAS',          T_MENOS     => 'MENOS',
        T_POR         => 'POR',          T_DIV       => 'DIV',

        T_SALTO        => 'SALTO',        T_NUEVAPAGINA => 'NUEVAPAGINA',
        T_DEFINIR      => 'DEFINIR',      T_IMPRIMIR    => 'IMPRIMIR',
        T_REFERENCIA   => 'REFERENCIA',

        T_CAJA => 'CAJA',

        T_ATTR_COLOR       => 'ATTR_COLOR',
        T_ATTR_TAMAÑO      => 'ATTR_TAMAÑO',
        T_ATTR_FUENTE_ATTR => 'ATTR_FUENTE',
        T_ATTR_ALINEACION  => 'ATTR_ALINEACION',
        T_ATTR_RUTA        => 'ATTR_RUTA',
        T_ATTR_ANCHO       => 'ATTR_ANCHO',
        T_ATTR_ALTO        => 'ATTR_ALTO',
        T_ATTR_BORDES      => 'ATTR_BORDES',
        T_ATTR_FONDO       => 'ATTR_FONDO',
        T_ATTR_TIPO        => 'ATTR_TIPO',
        T_ATTR_NUMERADOR   => 'ATTR_NUMERADOR',
        T_ATTR_DENOMINADOR => 'ATTR_DENOMINADOR',
        T_ATTR_ID          => 'ATTR_ID',

        T_VAL_PUNTO       => 'VAL_PUNTO',
        T_VAL_NUMERO_ATTR => 'VAL_NUMERO',
        T_VAL_DERECHA     => 'VAL_DERECHA',
        T_VAL_CENTRO      => 'VAL_CENTRO',
        T_VAL_JUSTIFICADO => 'VAL_JUSTIFICADO',
        T_VAL_IZQUIERDA   => 'VAL_IZQUIERDA',
        T_VAL_VERDADERO   => 'VAL_VERDADERO',
        T_VAL_FALSO       => 'VAL_FALSO',
        T_VAL_AUTO        => 'VAL_AUTO',
        T_VAL_GRIS        => 'VAL_GRIS',
        T_VAL_ROJO        => 'VAL_ROJO',
        T_VAL_AZUL        => 'VAL_AZUL',
        T_VAL_VERDE       => 'VAL_VERDE',
        T_VAL_AMARILLO    => 'VAL_AMARILLO',
        T_VAL_NEGRO       => 'VAL_NEGRO',
        T_VAL_BLANCO      => 'VAL_BLANCO',
        T_VAL_ALERTA      => 'VAL_ALERTA',
        T_VAL_INFO        => 'VAL_INFO',
        T_VAL_EXITO       => 'VAL_EXITO',

        T_FIN   => 'FIN',
        T_ERROR => 'ERROR',
    ];
    return $nombres[$token] ?? "DESCONOCIDO($token)";
}

function DescripcionToken(int $token): string
{
    static $descripciones = [
        T_NUMERAL    => 'Símbolo numeral (#), prefijo de comandos',
        T_APAR       => 'Paréntesis de apertura (, inicio de atributos',
        T_CPAR       => 'Paréntesis de cierre ), fin de atributos',
        T_ALLAVE     => 'Llave de apertura {, inicio de contenido',
        T_CLLAVE     => 'Llave de cierre }, fin de contenido',
        T_IGUAL      => 'Operador de asignación = en atributos',
        T_COMA       => 'Separador de coma en atributos',
        T_PUNTO      => 'Punto delimitador',
        T_BARRA_INV  => 'Barra invertida de escape',
        T_PORCENTAJE => 'Símbolo de porcentaje (%)',

        T_ID          => 'Identificador genérico de nombre',
        T_NUMERO      => 'Literal numérico (entero, decimal o %)',
        T_CADENA      => 'Literal de cadena de texto entre comillas',
        T_TEXTO_PLANO => 'Contenido de texto libre',
        T_COMENTARIO  => 'Comentario de una línea (//)',
        T_COLOR_HEX   => 'Literal de color hexadecimal (#RRGGBB)',
        T_ESCAPE_CHAR => 'Carácter especial escapado',

        T_TITULO        => 'Comando de metadato: título del documento',
        T_AUTOR         => 'Comando de metadato: autor del documento',
        T_FECHA         => 'Comando de metadato: fecha del documento',
        T_SECCION       => 'Comando estructural: sección nivel 1',
        T_SUBSECCION    => 'Comando estructural: subsección nivel 2',
        T_SUBSECCION2   => 'Comando estructural: subsección nivel 3',
        T_SUBSECCION3   => 'Comando estructural: subsección nivel 4',
        T_SUBSECCION4   => 'Comando estructural: subsección nivel 5',
        T_TEXTO         => 'Comando de bloque de párrafo de texto',
        T_RESUMEN       => 'Comando estructural: resumen o abstract',
        T_INDICEGENERAL => 'Comando estructural: tabla de contenidos',

        T_FUERTE     => 'Formato inline: negrita (#fuerte)',
        T_CURSIVA    => 'Formato inline: cursiva (#cursiva)',
        T_SUBRAYADO  => 'Formato inline: subrayado (#subrayado)',
        T_RESALTADO  => 'Formato inline: resaltado (#resaltado)',

        T_LISTA  => 'Comando de lista ordenada o de viñetas',
        T_IMAGEN => 'Comando de inserción de imagen gráfica',

        T_TABLA => 'Comando de tabla de datos',
        T_FILA  => 'Comando de fila de tabla',
        T_CELDA => 'Comando de celda de tabla',

        T_SUPERINDICE => 'Comando matemático: superíndice (#superindice)',
        T_SUBINDICE   => 'Comando matemático: subíndice (#subindice)',
        T_FRACCION    => 'Comando matemático: fracción (#fraccion)',
        T_ECUACION    => 'Comando matemático: entorno de ecuación',
        T_MAS         => 'Operador aritmético suma (+)',
        T_MENOS       => 'Operador aritmético resta (-)',
        T_POR         => 'Operador aritmético multiplicación (*)',
        T_DIV         => 'Operador aritmético división (/)',

        T_SALTO        => 'Control de flujo: salto de línea explícito',
        T_NUEVAPAGINA  => 'Control de flujo: salto de página explícito',
        T_DEFINIR      => 'Macro: definición de variable/macro (#definir)',
        T_IMPRIMIR     => 'Macro: expansión e impresión (#imprimir)',
        T_REFERENCIA   => 'Referencia cruzada a sección o elemento',

        T_CAJA => 'Entorno especial: caja contenedora o alerta',

        T_ATTR_COLOR       => 'Atributo: color del elemento',
        T_ATTR_TAMAÑO      => 'Atributo: tamaño de fuente o elemento',
        T_ATTR_FUENTE_ATTR => 'Atributo: familia tipográfica',
        T_ATTR_ALINEACION  => 'Atributo: alineación del texto',
        T_ATTR_RUTA        => 'Atributo: ruta del archivo',
        T_ATTR_ANCHO       => 'Atributo: ancho del elemento',
        T_ATTR_ALTO        => 'Atributo: alto del elemento',
        T_ATTR_BORDES      => 'Atributo: activación de bordes',
        T_ATTR_FONDO       => 'Atributo: color o sombreado de fondo',
        T_ATTR_TIPO        => 'Atributo: tipo de entidad o caja',
        T_ATTR_NUMERADOR   => 'Atributo: numerador de fracción',
        T_ATTR_DENOMINADOR => 'Atributo: denominador de fracción',
        T_ATTR_ID          => 'Atributo: identificador único',

        T_VAL_PUNTO       => 'Valor de atributo: lista con viñeta (punto)',
        T_VAL_NUMERO_ATTR => 'Valor de atributo: lista numerada (numero)',
        T_VAL_DERECHA     => 'Valor de atributo: alineación a la derecha',
        T_VAL_CENTRO      => 'Valor de atributo: alineación centrada',
        T_VAL_JUSTIFICADO => 'Valor de atributo: alineación justificada',
        T_VAL_IZQUIERDA   => 'Valor de atributo: alineación a la izquierda',
        T_VAL_VERDADERO   => 'Valor de atributo booleano: verdadero',
        T_VAL_FALSO       => 'Valor de atributo booleano: falso',
        T_VAL_AUTO        => 'Valor de atributo de dimensión: auto',
        T_VAL_GRIS        => 'Valor de atributo de color: gris',
        T_VAL_ROJO        => 'Valor de atributo de color: rojo',
        T_VAL_AZUL        => 'Valor de atributo de color: azul',
        T_VAL_VERDE       => 'Valor de atributo de color: verde',
        T_VAL_AMARILLO    => 'Valor de atributo de color: amarillo',
        T_VAL_NEGRO       => 'Valor de atributo de color: negro',
        T_VAL_BLANCO      => 'Valor de atributo de color: blanco',
        T_VAL_ALERTA      => 'Valor de atributo de entorno: alerta',
        T_VAL_INFO        => 'Valor de atributo de entorno: info',
        T_VAL_EXITO       => 'Valor de atributo de entorno: exito',

        T_FIN   => 'Marcador de fin de archivo de entrada',
        T_ERROR => 'Error léxico: símbolo o secuencia no reconocida',
    ];
    return $descripciones[$token] ?? 'Token del lenguaje Latinex';
}


/* ============================================================
 * CLASE TOKEN
 * ============================================================
 */
class Token
{
    public int    $token;
    public ?string $lexema;
    public int    $linea;
    public int    $columna;
    public string $tipo;

    public function __construct(
        int    $token,
        ?string $lexema,
        int    $linea,
        int    $columna,
        string $tipo = ''
    ) {
        $this->token   = $token;
        $this->lexema  = $lexema;
        $this->linea   = $linea;
        $this->columna = $columna;
        $this->tipo    = $tipo;
    }

    /** Serializa el token a array asociativo para JSON. */
    public function toArray(): array
    {
        return [
            'token'   => $this->token,
            'nombre'  => NombreToken($this->token),
            'lexema'  => $this->lexema,
            'tipo'    => $this->tipo,
            'linea'   => $this->linea,
            'columna' => $this->columna,
        ];
    }
}


/* ============================================================
 * CLASE TABLA LÉXICA
 * ============================================================
 */
class TablaLexica
{
    private array $porToken  = [];
    private array $porLexema = [];

    public function __construct()
    {
        /* ── Símbolos ──────────────────────────────────────── */
        $this->Agregar(T_NUMERAL,    '#',  'simbolo');
        $this->Agregar(T_APAR,       '(',  'simbolo');
        $this->Agregar(T_CPAR,       ')',  'simbolo');
        $this->Agregar(T_ALLAVE,     '{',  'simbolo');
        $this->Agregar(T_CLLAVE,     '}',  'simbolo');
        $this->Agregar(T_IGUAL,      '=',  'operador');
        $this->Agregar(T_COMA,       ',',  'separador');

        /* ── Comandos de estructura / metadatos ────────────── */
        $this->Agregar(T_TITULO,        'titulo',        'comando_estructura');
        $this->Agregar(T_AUTOR,         'autor',         'comando_estructura');
        $this->Agregar(T_FECHA,         'fecha',         'comando_estructura');
        $this->Agregar(T_SECCION,       'seccion',       'comando_estructura');
        $this->Agregar(T_SUBSECCION,    'subseccion1',   'comando_estructura');
        $this->Agregar(T_SUBSECCION,    'subseccion',    'comando_estructura');
        $this->Agregar(T_SUBSECCION2,   'subseccion2',   'comando_estructura');
        $this->Agregar(T_SUBSECCION3,   'subseccion3',   'comando_estructura');
        $this->Agregar(T_SUBSECCION4,   'subseccion4',   'comando_estructura');
        $this->Agregar(T_TEXTO,         'texto',         'comando_estructura');
        $this->Agregar(T_RESUMEN,       'resumen',       'comando_estructura');
        $this->Agregar(T_INDICEGENERAL, 'indicegeneral', 'comando_estructura');

        /* ── Comandos de formato inline ────────────────────── */
        $this->Agregar(T_FUERTE,    'fuerte',    'comando_formato');
        $this->Agregar(T_CURSIVA,   'cursiva',   'comando_formato');
        $this->Agregar(T_SUBRAYADO, 'subrayado', 'comando_formato');
        $this->Agregar(T_RESALTADO, 'resaltado', 'comando_formato');

        /* ── Listas y multimedia ─────────────────────────── */
        $this->Agregar(T_LISTA,  'lista',  'comando_datos');
        $this->Agregar(T_IMAGEN, 'imagen', 'comando_datos');

        /* ── Tablas ──────────────────────────────────────── */
        $this->Agregar(T_TABLA, 'tabla', 'comando_tabla');
        $this->Agregar(T_FILA,  'fila',  'comando_tabla');
        $this->Agregar(T_CELDA, 'celda', 'comando_tabla');

        /* ── Matemáticas ─────────────────────────────────── */
        $this->Agregar(T_SUPERINDICE, 'superindice', 'comando_matematica');
        $this->Agregar(T_SUBINDICE,   'subindice',   'comando_matematica');
        $this->Agregar(T_FRACCION,    'fraccion',    'comando_matematica');
        $this->Agregar(T_ECUACION,    'ecuacion',    'comando_matematica');
        $this->Agregar(T_MAS,         '+',           'operador');
        $this->Agregar(T_MENOS,       '-',           'operador');
        $this->Agregar(T_POR,         '*',           'operador');
        $this->Agregar(T_DIV,         '/',           'operador');

        /* ── Control de flujo y macros ───────────────────── */
        $this->Agregar(T_SALTO,       'salto',       'comando_control');
        $this->Agregar(T_NUEVAPAGINA, 'nuevapagina', 'comando_control');
        $this->Agregar(T_DEFINIR,     'definir',     'comando_macro');
        $this->Agregar(T_IMPRIMIR,    'imprimir',    'comando_macro');
        $this->Agregar(T_REFERENCIA,  'referencia',  'comando_referencia');

        /* ── Entornos especiales ─────────────────────────── */
        $this->Agregar(T_CAJA, 'caja', 'comando_entorno');

        /* ── Nombres de atributos ─────────────────────────── */
        $this->Agregar(T_ATTR_COLOR,       'color',       'atributo');
        $this->Agregar(T_ATTR_TAMAÑO,      'tamaño',      'atributo');
        $this->Agregar(T_ATTR_TAMAÑO,      'tamano',      'atributo'); // alias sin tilde
        $this->Agregar(T_ATTR_FUENTE,      'fuente',      'atributo');
        $this->Agregar(T_ATTR_FUENTE,      'fuente_attr', 'atributo'); // alias de compatibilidad
        $this->Agregar(T_ATTR_ALINEACION,  'alineacion',  'atributo');
        $this->Agregar(T_ATTR_RUTA,        'ruta',        'atributo');
        $this->Agregar(T_ATTR_ANCHO,       'ancho',       'atributo');
        $this->Agregar(T_ATTR_ALTO,        'alto',        'atributo');
        $this->Agregar(T_ATTR_BORDES,      'bordes',      'atributo');
        $this->Agregar(T_ATTR_FONDO,       'fondo',       'atributo');
        $this->Agregar(T_ATTR_TIPO,        'tipo',        'atributo');
        $this->Agregar(T_ATTR_NUMERADOR,   'numerador',   'atributo');
        $this->Agregar(T_ATTR_DENOMINADOR, 'denominador', 'atributo');
        $this->Agregar(T_ATTR_ID,          'id',          'atributo');

        /* ── Valores de atributos ─────────────────────────── */
        $this->Agregar(T_VAL_PUNTO,       'punto',       'valor_atributo');
        $this->Agregar(T_VAL_NUMERO_ATTR, 'numero',      'valor_atributo');
        $this->Agregar(T_VAL_DERECHA,     'derecha',     'valor_atributo');
        $this->Agregar(T_VAL_CENTRO,      'centro',      'valor_atributo');
        $this->Agregar(T_VAL_CENTRO,      'centrado',    'valor_atributo'); // alias según caso 19 del PDF
        $this->Agregar(T_VAL_JUSTIFICADO, 'justificado', 'valor_atributo');
        $this->Agregar(T_VAL_IZQUIERDA,   'izquierda',   'valor_atributo');
        $this->Agregar(T_VAL_VERDADERO,   'verdadero',   'valor_atributo');
        $this->Agregar(T_VAL_FALSO,       'falso',       'valor_atributo');
        $this->Agregar(T_VAL_AUTO,        'auto',        'valor_atributo');
        $this->Agregar(T_VAL_GRIS,        'gris',        'valor_atributo');
        $this->Agregar(T_VAL_ROJO,        'rojo',        'valor_atributo');
        $this->Agregar(T_VAL_AZUL,        'azul',        'valor_atributo');
        $this->Agregar(T_VAL_VERDE,       'verde',       'valor_atributo');
        $this->Agregar(T_VAL_AMARILLO,    'amarillo',    'valor_atributo');
        $this->Agregar(T_VAL_NEGRO,       'negro',       'valor_atributo');
        $this->Agregar(T_VAL_BLANCO,      'blanco',      'valor_atributo');
        $this->Agregar(T_VAL_ALERTA,      'alerta',      'valor_atributo');
        $this->Agregar(T_VAL_INFO,        'info',        'valor_atributo');
        $this->Agregar(T_VAL_EXITO,       'éxito',       'valor_atributo');
        $this->Agregar(T_VAL_EXITO,       'exito',       'valor_atributo');
    }

    /** Registra un par (token, lexema) en la tabla. */
    private function Agregar(int $token, string $lexema, string $tipo): void
    {
        $dato = ['token' => $token, 'lexema' => $lexema, 'tipo' => $tipo];
        $this->porToken[$token]  = $dato; // alias: último gana, suficiente para búsqueda por token
        $this->porLexema[$lexema] = $dato;
    }

    /** Busca por código de token. */
    public function BuscarPorToken(int $token): ?array
    {
        return $this->porToken[$token] ?? null;
    }

    /** Busca por lexema textual. */
    public function BuscarPorLexema(string $lexema): ?array
    {
        return $this->porLexema[$lexema] ?? null;
    }

    /**
     * Busca el lexema fijo más largo que coincida en la posición dada.
     * Solo para símbolos de un carácter (no palabras).
     */
    public function BuscarLexemaMasLargo(string $codigo, int $posicion): ?array
    {
        $encontrado = null;
        foreach ($this->porLexema as $lexema => $datos) {
            $longitud = mb_strlen($lexema, 'UTF-8');
            if ($longitud > 1 && ctype_alpha($lexema[0])) continue;
            $fragmento = mb_substr($codigo, $posicion, $longitud, 'UTF-8');
            if ($fragmento === $lexema) {
                if ($encontrado === null || $longitud > $encontrado['longitud']) {
                    $encontrado = [
                        'token'    => $datos['token'],
                        'lexema'   => $lexema,
                        'tipo'     => $datos['tipo'],
                        'longitud' => $longitud,
                    ];
                }
            }
        }
        return $encontrado;
    }

    /**
     * Retorna toda la tabla como array (para exponer en la API de debug).
     */
    public function exportar(): array
    {
        $resultado = [];
        $vistos = [];
        foreach ($this->porLexema as $lexema => $datos) {
            $clave = $datos['token'] . '|' . $lexema;
            if (isset($vistos[$clave])) continue;
            $vistos[$clave] = true;
            $resultado[] = [
                'token'       => $datos['token'],
                'nombre'      => NombreToken($datos['token']),
                'lexema'      => $lexema,
                'tipo'        => $datos['tipo'],
                'categoria'   => $datos['tipo'],
                'descripcion' => DescripcionToken($datos['token']),
            ];
        }
        usort($resultado, fn($a, $b) => $a['token'] <=> $b['token']);
        return $resultado;
    }
}

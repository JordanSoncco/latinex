<?php

namespace Latinex;

/* ============================================================
 * ANALIZADOR SINTÁCTICO DEL LENGUAJE LATINEX
 * ============================================================
 *
 * Autómata con pila de 22 estados (0 al 21) adaptado del patrón
 * de Graficador para validar las construcciones del lenguaje
 * LATINEX (bloques anidados, atributos, listas, fracciones,
 * tablas, ecuaciones, macros y referencias cruzadas).
 *
 * Consume su propia instancia de Tokenizador (ignorando T_COMENTARIO)
 * y genera la traza paso a paso para el visor IDE y diagnóstico
 * de errores sintácticos con línea, columna y tokens esperados.
 * ============================================================
 */
class AnalizadorSintactico
{
    private Tokenizador $tokenizador;

    private int $estadoActual = 0;

    private bool $seguimientoActivo;

    private array $seguimiento = [];

    private ?array $error = null;

    private array $transiciones;

    /** Pila auxiliar para controlar el retorno al cerrar llaves '}' en bloques anidados. */
    private array $pilaBloques = [];

    private int $contextoRetorno = 21;

    private int $tipoBloquePendiente = 15;

    private bool $comandoAtomicoEnAtributos = false;

    public function __construct(
        Tokenizador $tokenizador,
        bool $seguimientoActivo = true
    ) {
        $this->tokenizador       = $tokenizador;
        $this->seguimientoActivo = $seguimientoActivo;

        $transicionesNivelDocumento = [
            T_TITULO        => 1,
            T_AUTOR         => 1,
            T_FECHA         => 1,
            T_SECCION       => 1,
            T_SUBSECCION    => 1,
            T_SUBSECCION2   => 1,
            T_SUBSECCION3   => 1,
            T_SUBSECCION4   => 1,
            T_TEXTO         => 1,
            T_RESUMEN       => 1,
            T_FUERTE        => 1,
            T_CURSIVA       => 1,
            T_SUBRAYADO     => 1,
            T_RESALTADO     => 1,
            T_CAJA          => 1,
            T_ECUACION      => 1,
            T_DEFINIR       => 2,
            T_LISTA         => 3,
            T_FRACCION      => 4,
            T_TABLA         => 5,
            T_IMAGEN        => 6,
            T_IMPRIMIR      => 7,
            T_REFERENCIA    => 7,
            T_SALTO         => 21,
            T_NUEVAPAGINA   => 21,
            T_INDICEGENERAL => 21,
        ];

        $atributosClave = [
            T_ATTR_COLOR       => 12,
            T_ATTR_TAMANO      => 12,
            T_ATTR_FUENTE      => 12,
            T_ATTR_ALINEACION  => 12,
            T_ATTR_RUTA        => 12,
            T_ATTR_ANCHO       => 12,
            T_ATTR_ALTO        => 12,
            T_ATTR_BORDES      => 12,
            T_ATTR_FONDO       => 12,
            T_ATTR_TIPO        => 12,
            T_ATTR_NUMERADOR   => 12,
            T_ATTR_DENOMINADOR => 12,
            T_ATTR_ID          => 12,
            T_ID               => 12,
        ];

        $banderasPosicionales = [
            T_VAL_PUNTO       => 14,
            T_VAL_NUMERO_ATTR => 14,
            T_VAL_DERECHA     => 14,
            T_VAL_CENTRO      => 14,
            T_VAL_JUSTIFICADO => 14,
            T_VAL_IZQUIERDA   => 14,
            T_VAL_VERDADERO   => 14,
            T_VAL_FALSO       => 14,
            T_VAL_AUTO        => 14,
            T_VAL_GRIS        => 14,
            T_VAL_ROJO        => 14,
            T_VAL_AZUL        => 14,
            T_VAL_VERDE       => 14,
            T_VAL_AMARILLO    => 14,
            T_VAL_NEGRO       => 14,
            T_VAL_BLANCO      => 14,
            T_VAL_ALERTA      => 14,
            T_VAL_INFO        => 14,
            T_VAL_EXITO       => 14,
        ];

        $valoresAtributo = $banderasPosicionales + [
            T_COLOR_HEX => 14,
            T_NUMERO    => 14,
            T_CADENA    => 14,
            T_ID        => 14,
        ] + array_fill_keys(array_keys($atributosClave), 14);

        $identificadoresEnDefinir = [
            T_ID     => 10,
            T_NUMERO => 10,
        ] + array_fill_keys(array_keys($atributosClave + $banderasPosicionales), 10);

        $identificadoresEnReferencia = [
            T_ID     => 16,
            T_NUMERO => 16,
        ] + array_fill_keys(array_keys($atributosClave + $banderasPosicionales), 16);

        $this->transiciones = [
            // 0: Estado inicial del documento
            0  => $transicionesNivelDocumento,

            // 1: Comando de bloque leído (#titulo, #texto, #seccion..#subseccion4, #fuerte, #caja, etc.)
            1  => [
                T_APAR   => 11,
                T_ALLAVE => 15,
            ],

            // 2: Comando #definir leído
            2  => [T_APAR => 8],

            // 3: Comando #lista leído
            3  => [
                T_APAR   => 11,
                T_ALLAVE => 18,
            ],

            // 4: Comando #fraccion leído
            4  => [
                T_APAR   => 11,
                T_ALLAVE => 18,
            ],

            // 5: Comando #tabla leído
            5  => [
                T_APAR   => 11,
                T_ALLAVE => 19,
            ],

            // 6: Comando atómico #imagen leído
            6  => [T_APAR => 11],

            // 7: Comando #imprimir o #referencia leído
            7  => [T_APAR => 9],

            // 8: Apertura de parámetro en #definir(
            8  => $identificadoresEnDefinir,

            // 9: Apertura de parámetro en #imprimir( o #referencia(
            9  => $identificadoresEnReferencia,

            // 10: Identificador de #definir(id leído
            10 => [T_CPAR => 17],

            // 11: Inicio de atributo o bandera dentro de ( ... )
            11 => $atributosClave + $banderasPosicionales,

            // 12: Nombre de atributo leído; espera '='
            12 => [T_IGUAL => 13],

            // 13: Operador '=' leído; espera valor de atributo
            13 => $valoresAtributo,

            // 14: Valor de atributo leído; espera ',', operador o ')'
            14 => [
                T_COMA  => 11,
                T_MAS   => 13,
                T_MENOS => 13,
                T_POR   => 13,
                T_DIV   => 13,
                T_CPAR  => 17,
            ],

            // 15: Interior de bloque de contenido { ... }
            15 => [
                T_TEXTO_PLANO   => 15,
                T_ESCAPE_CHAR   => 15,
                T_COLOR_HEX     => 15,
                T_SECCION       => 1,
                T_SUBSECCION    => 1,
                T_SUBSECCION2   => 1,
                T_SUBSECCION3   => 1,
                T_SUBSECCION4   => 1,
                T_FUERTE        => 1,
                T_CURSIVA       => 1,
                T_SUBRAYADO     => 1,
                T_RESALTADO     => 1,
                T_SUPERINDICE   => 1,
                T_SUBINDICE     => 1,
                T_TEXTO         => 1,
                T_CAJA          => 1,
                T_ECUACION      => 1,
                T_LISTA         => 3,
                T_FRACCION      => 4,
                T_TABLA         => 5,
                T_IMAGEN        => 6,
                T_IMPRIMIR      => 7,
                T_REFERENCIA    => 7,
                T_SALTO         => 15,
                T_NUEVAPAGINA   => 15,
                T_INDICEGENERAL => 15,
                T_CLLAVE        => 21,
            ],

            // 16: Identificador de #imprimir(id o #referencia(id leído
            16 => [T_CPAR => 21],

            // 17: Cierre de paréntesis ')' de atributos; espera '{' de bloque
            17 => [T_ALLAVE => 15],

            // 18: Interior de contenedor de bloques anónimos (#lista o #fraccion)
            18 => [
                T_ALLAVE => 15,
                T_CLLAVE => 21,
            ],

            // 19: Interior de #tabla { ... }; espera #fila o '}'
            19 => [
                T_FILA   => 1,
                T_CLLAVE => 21,
            ],

            // 20: Interior de #fila { ... }; espera #celda o '}'
            20 => [
                T_CELDA  => 1,
                T_CLLAVE => 19,
            ],

            // 21: Estado de aceptación / Nivel documento
            21 => $transicionesNivelDocumento + [
                T_FIN => 21,
            ],
        ];
    }

    /** Ejecuta el análisis sintáctico completo y devuelve true si el código es aceptado. */
    public function Analizar(): bool
    {
        $this->estadoActual              = 0;
        $this->seguimiento               = [];
        $this->error                     = null;
        $this->pilaBloques               = [];
        $this->contextoRetorno           = 21;
        $this->tipoBloquePendiente       = 15;
        $this->comandoAtomicoEnAtributos = false;
        $paso = 1;

        while (true) {
            $token = $this->tokenizador->getToken();

            // Los comentarios de línea (//) son transparentes para la gramática
            if ($token->token === T_COMENTARIO) {
                continue;
            }

            $estadoAnterior = $this->estadoActual;

            if ($token->token === T_ERROR) {
                $this->RegistrarPaso($paso, $estadoAnterior, $token, null);
                $this->error = $this->CrearError(
                    'lexico',
                    $token,
                    'El analizador léxico encontró un lexema no válido.'
                );
                return false;
            }

            $estadoSiguiente = $this->ResolverTransicion($estadoAnterior, $token->token);
            $this->RegistrarPaso($paso, $estadoAnterior, $token, $estadoSiguiente);

            if ($estadoSiguiente === null) {
                $this->error = $this->CrearError(
                    'sintactico',
                    $token,
                    $this->DescribirError($estadoAnterior, $token)
                );
                return false;
            }

            $this->estadoActual = $estadoSiguiente;

            if ($token->token === T_FIN) {
                return true;
            }

            $paso++;
        }
    }

    private function ResolverTransicion(int $estadoAnterior, int $token): ?int
    {
        $estadoSiguiente = $this->transiciones[$estadoAnterior][$token] ?? null;

        if ($estadoSiguiente === null) {
            return null;
        }

        // Registrar retorno y tipo de bloque al iniciar un comando en nivel documento o contenedor
        if (in_array($estadoAnterior, [0, 15, 19, 20, 21], true)) {
            $retornoPorDefecto = ($estadoAnterior === 0) ? 21 : $estadoAnterior;

            if (in_array($estadoSiguiente, [1, 2, 3, 4, 5, 6, 7], true)) {
                $this->contextoRetorno           = $retornoPorDefecto;
                $this->comandoAtomicoEnAtributos = in_array($token, [T_IMAGEN, T_FRACCION], true);

                $this->tipoBloquePendiente = match ($token) {
                    T_LISTA, T_FRACCION => 18,
                    T_TABLA             => 19,
                    T_FILA              => 20,
                    default             => 15,
                };
            }
        }

        // Si #fraccion abre directamente con '{', actúa como bloque con sub-bloques anónimos
        if ($estadoAnterior === 4 && $token === T_ALLAVE) {
            $this->comandoAtomicoEnAtributos = false;
        }

        // Al cerrar ')' en atributos: si el comando es atómico concluye, sino exige '{'
        if ($estadoAnterior === 14 && $token === T_CPAR) {
            if ($this->comandoAtomicoEnAtributos) {
                $this->comandoAtomicoEnAtributos = false;
                return $this->contextoRetorno;
            }
            return 17;
        }

        // Al cerrar ')' de #imprimir(id) o #referencia(id), vuelve al contexto invocador
        if ($estadoAnterior === 16 && $token === T_CPAR) {
            return $this->contextoRetorno;
        }

        // Apilar el estado de retorno al abrir un bloque con '{'
        if ($token === T_ALLAVE) {
            if ($estadoAnterior === 18) {
                $this->pilaBloques[] = 18;
                return 15;
            }
            if (in_array($estadoAnterior, [1, 3, 4, 5, 17], true)) {
                $this->pilaBloques[] = $this->contextoRetorno;
                return $this->tipoBloquePendiente;
            }
        }

        // Desapilar el estado de retorno al cerrar un bloque con '}'
        if ($token === T_CLLAVE && in_array($estadoAnterior, [15, 18, 19, 20], true)) {
            if (empty($this->pilaBloques)) {
                return null;
            }
            return array_pop($this->pilaBloques);
        }

        return $estadoSiguiente;
    }

    public function getEstadoActual(): int
    {
        return $this->estadoActual;
    }

    public function getSeguimiento(): array
    {
        return $this->seguimiento;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function getTransiciones(): array
    {
        return $this->transiciones;
    }

    public static function NombreToken(int $token): string
    {
        return NombreToken($token);
    }

    private function RegistrarPaso(
        int $paso,
        int $estadoActual,
        Token $token,
        ?int $estadoSiguiente
    ): void {
        if (!$this->seguimientoActivo) {
            return;
        }

        $this->seguimiento[] = [
            'paso'            => $paso,
            'estadoActual'    => $estadoActual,
            'token'           => $token->token,
            'nombreToken'     => self::NombreToken($token->token),
            'lexema'          => $token->lexema,
            'linea'           => $token->linea,
            'columna'         => $token->columna,
            'estadoSiguiente' => $estadoSiguiente,
        ];
    }

    private function CrearError(string $tipo, Token $token, string $descripcion): array
    {
        $esperados      = $this->TokensEsperados($this->estadoActual);
        $nombre         = self::NombreToken($token->token);
        $lexema         = $token->lexema === null ? 'null' : "'{$token->lexema}'";
        $listaEsperados = empty($esperados) ? 'ninguno' : implode(', ', $esperados);

        return [
            'tipo'        => $tipo,
            'linea'       => $token->linea,
            'columna'     => $token->columna,
            'estado'      => $this->estadoActual,
            'token'       => $token->token,
            'nombreToken' => $nombre,
            'lexema'      => $token->lexema,
            'esperados'   => $esperados,
            'descripcion' => $descripcion,
            'mensaje'     => sprintf(
                'Error %s en línea %d, columna %d: recibido %s (%s) en el estado %d; se esperaba: %s. %s',
                $tipo,
                $token->linea,
                $token->columna,
                $nombre,
                $lexema,
                $this->estadoActual,
                $listaEsperados,
                $descripcion
            ),
        ];
    }

    private function TokensEsperados(int $estado): array
    {
        $tokens = array_keys($this->transiciones[$estado] ?? []);
        return array_map(fn(int $token): string => self::NombreToken($token), $tokens);
    }

    private function DescribirError(int $estado, Token $token): string
    {
        if ($token->token === T_FIN) {
            return match ($estado) {
                0                  => 'La entrada está vacía; el documento debe iniciar con un comando #etiqueta.',
                1, 3, 4, 5, 17     => 'Falta abrir el bloque de contenido con una llave {.',
                2, 6, 7            => 'Falta el paréntesis de apertura ( después del comando.',
                8, 9, 11           => 'Falta especificar el atributo o identificador dentro de los paréntesis.',
                10, 14, 16         => 'Falta el paréntesis de cierre ) de los atributos.',
                12                 => 'Falta el operador de asignación = después del nombre del atributo.',
                13                 => 'Falta el valor del atributo después del signo =.',
                15, 18, 19, 20     => 'Falta cerrar un bloque abierto con una llave }.',
                default            => 'El documento terminó antes de completar la estructura esperada.',
            };
        }

        return match ($estado) {
            0          => 'El documento debe comenzar con un comando válido de LATINEX (#titulo, #texto, #seccion, etc.).',
            1, 3, 4, 5 => 'Después del comando debe aparecer ( para atributos o { para iniciar el bloque.',
            2, 6, 7    => 'Este comando requiere obligatoriamente paréntesis de apertura (.',
            8, 9       => 'Se esperaba un identificador válido dentro de los paréntesis.',
            10, 16     => 'Se esperaba el paréntesis de cierre ) después del identificador.',
            11         => 'Se esperaba un nombre de atributo o bandera posicional válida.',
            12         => 'Después del nombre de un atributo debe aparecer el operador =.',
            13         => 'Se esperaba un valor válido para el atributo (color, número, cadena o valor reservado).',
            14         => 'Se esperaba una coma para separar atributos o ) para cerrar los atributos.',
            15         => 'Token no permitido dentro del bloque de contenido.',
            17         => 'Después de cerrar los atributos con ) debe abrirse el bloque con {.',
            18         => 'Dentro de #lista o #fraccion se esperaban bloques anónimos { ... } o } de cierre.',
            19         => 'Dentro de #tabla solo se permiten comandos #fila o la llave de cierre }.',
            20         => 'Dentro de #fila solo se permiten comandos #celda o la llave de cierre }.',
            21         => 'En el nivel principal del documento solo se permiten comandos #etiqueta.',
            default    => 'El token recibido no tiene una transición válida.',
        };
    }
}

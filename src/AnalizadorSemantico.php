<?php

namespace Latinex;

/* ============================================================
 * ANALIZADOR SEMÁNTICO DEL COMPILADOR LATINEX
 * ============================================================
 *
 * Verifica las reglas semánticas del código fuente ya aceptado
 * sintácticamente, empleando TablaSimbolos y siguiendo el
 * esquema de diagnóstico estructurado de Graficador:
 *
 *   - Pasada A: Pre-registro de etiquetas #seccion*(id=...)
 *               para soportar forward references (Error 1502).
 *   - Pasada B: Validación secuencial de macros #definir/#imprimir
 *               (Errores 1500 / 1600), referencias #referencia
 *               (Error 1501), metadatos y atributos de comandos
 *               (#imagen, #lista, #fraccion, #caja, #tabla,
 *               color/fondo, alineacion y tamaño: Errores 1700–1707).
 * ============================================================
 */
class AnalizadorSemantico
{
    private Tokenizador $tokenizador;

    private TablaSimbolos $tablaSimbolos;

    private ?array $error = null;

    /** @var array<int, string> */
    private array $advertencias = [];

    /** @var array<string, string[]> Grafo de dependencias entre macros (#definir -> #imprimir) */
    private array $dependenciasMacros = [];

    private const TOKENS_SECCION = [
        T_SECCION,
        T_SUBSECCION,
        T_SUBSECCION2,
        T_SUBSECCION3,
        T_SUBSECCION4,
    ];

    private const COLORES_NOMINALES = [
        T_VAL_ROJO,
        T_VAL_AZUL,
        T_VAL_VERDE,
        T_VAL_AMARILLO,
        T_VAL_GRIS,
        T_VAL_NEGRO,
        T_VAL_BLANCO,
    ];

    private const ALINEACIONES_VALIDAS = [
        T_VAL_IZQUIERDA,
        T_VAL_CENTRO,
        T_VAL_DERECHA,
        T_VAL_JUSTIFICADO,
    ];

    public function __construct(
        Tokenizador $tokenizador,
        ?TablaSimbolos $tablaSimbolos = null
    ) {
        $this->tokenizador   = $tokenizador;
        $this->tablaSimbolos = $tablaSimbolos ?? new TablaSimbolos();
    }

    /** Ejecuta el análisis semántico en dos pasadas internas. */
    public function Analizar(): bool
    {
        $this->error              = null;
        $this->advertencias       = [];
        $this->dependenciasMacros = [];

        $tokens = $this->LeerFlujoTokens();
        if ($tokens === null) {
            return false;
        }

        if (!$this->RegistrarEtiquetasSecciones($tokens)) {
            return false;
        }

        return $this->ValidarSentencias($tokens);
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function getTablaSimbolos(): TablaSimbolos
    {
        return $this->tablaSimbolos;
    }

    public function getAdvertencias(): array
    {
        return $this->advertencias;
    }

    /** Consume el flujo de tokens descartando comentarios y deteniéndose ante T_ERROR. */
    private function LeerFlujoTokens(): ?array
    {
        $tokens = [];
        while (true) {
            $token = $this->tokenizador->getToken();
            if ($token->token === T_COMENTARIO) {
                continue;
            }
            if ($token->token === T_ERROR) {
                $this->error = $this->CrearError(
                    T_ERROR,
                    $token,
                    'Error léxico encontrado durante el análisis semántico.'
                );
                return null;
            }
            $tokens[] = $token;
            if ($token->token === T_FIN) {
                break;
            }
        }
        return $tokens;
    }

    /**
     * Pasada A: pre-registra todas las etiquetas de #seccion*(id=...)
     * para habilitar referencias cruzadas hacia adelante (forward references).
     */
    private function RegistrarEtiquetasSecciones(array $tokens): bool
    {
        $total = count($tokens);
        for ($i = 0; $i < $total; $i++) {
            $tok = $tokens[$i];
            if (!in_array($tok->token, self::TOKENS_SECCION, true)) {
                continue;
            }

            $pos       = $i + 1;
            $atributos = $this->LeerAtributos($tokens, $pos);
            $idAttr    = $this->BuscarAtributoPorToken($atributos, T_ATTR_ID);
            if ($idAttr === null || empty($idAttr['valores'])) {
                continue;
            }

            $tokenId    = $idAttr['valores'][0];
            $nombreId   = trim((string) $tokenId->lexema);
            $tituloText = '';

            if ($pos < $total && $tokens[$pos]->token === T_ALLAVE) {
                $cierre     = $this->EncontrarLlaveCierre($tokens, $pos);
                $tituloText = $this->ExtraerTextoPlanoRango($tokens, $pos + 1, $cierre - 1);
            }

            if ($this->tablaSimbolos->Buscar($nombreId, 'ETIQUETA') !== null) {
                $this->error = $this->CrearError(
                    1502,
                    $tokenId,
                    sprintf("Etiqueta de sección '%s' duplicada.", $nombreId)
                );
                return false;
            }

            $this->tablaSimbolos->Insertar(
                $nombreId,
                $tituloText !== '' ? $tituloText : $nombreId,
                'asignado',
                'ETIQUETA',
                T_ATTR_ID,
                $tokenId->linea,
                $tokenId->columna
            );
        }

        return true;
    }

    /**
     * Pasada B: recorre todos los comandos (incluyendo bloques anidados)
     * validando macros, referencias, metadatos y reglas de atributos.
     */
    private function ValidarSentencias(array $tokens): bool
    {
        $total = count($tokens);
        $i     = 0;

        while ($i < $total) {
            $tok = $tokens[$i];

            if ($tok->token === T_FIN) {
                return true;
            }

            if ($tok->token === T_DEFINIR) {
                $i = $this->ProcesarDefinir($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_IMPRIMIR) {
                $i = $this->ProcesarImprimir($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_REFERENCIA) {
                $i = $this->ProcesarReferencia($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_IMAGEN) {
                $i = $this->ProcesarImagen($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_LISTA) {
                $i = $this->ProcesarLista($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_FRACCION) {
                $i = $this->ProcesarFraccion($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_CAJA) {
                $i = $this->ProcesarCaja($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($tok->token === T_TABLA) {
                $i = $this->ProcesarTabla($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if (in_array($tok->token, [T_TITULO, T_AUTOR, T_FECHA], true)) {
                $i = $this->ProcesarMetadato($tokens, $i);
                if ($i < 0) {
                    return false;
                }
                continue;
            }

            if ($this->EsComandoConAtributos($tok->token)) {
                $pos       = $i + 1;
                $atributos = $this->LeerAtributos($tokens, $pos);
                if (!$this->ValidarAtributosComunes($atributos)) {
                    return false;
                }
                $i = $pos;
                continue;
            }

            $i++;
        }

        return true;
    }

    private function ProcesarDefinir(array $tokens, int $idx): int
    {
        // #definir ( id ) { contenido }
        $tokenId = $tokens[$idx + 2] ?? null;
        if ($tokenId === null) {
            return -1;
        }

        $nombreMacro = trim((string) $tokenId->lexema);
        $pos         = $idx + 4; // posición esperada de '{'

        if (!isset($tokens[$pos]) || $tokens[$pos]->token !== T_ALLAVE) {
            return $pos;
        }

        $cierre = $this->EncontrarLlaveCierre($tokens, $pos);

        // Detectar autorreferencia o ciclos indirectos (ej. a -> a, o a -> b -> a)
        $invocadas = [];
        for ($k = $pos + 1; $k < $cierre; $k++) {
            if ($tokens[$k]->token === T_IMPRIMIR && isset($tokens[$k + 2])) {
                $depTok = $tokens[$k + 2];
                $depId  = trim((string) $depTok->lexema);
                if ($depId !== '') {
                    if ($depId === $nombreMacro || $this->AlcanzaMacro($depId, $nombreMacro)) {
                        $this->error = $this->CrearError(
                            1601,
                            $depTok,
                            sprintf(
                                "Recursión cíclica detectada en la macro '%s' al invocar '#imprimir(%s)'.",
                                $nombreMacro,
                                $depId
                            )
                        );
                        return -1;
                    }
                    $invocadas[$depId] = true;
                }
            }
        }
        $this->dependenciasMacros[$nombreMacro] = array_keys($invocadas);

        $valor  = $this->ExtraerTextoPlanoRango($tokens, $pos + 1, $cierre - 1);

        if ($valor === '') {
            $estado         = 'no_asignado';
            $valorGuardado  = null;
            $this->advertencias[] = sprintf(
                "La macro '%s' (línea %d) fue definida vacía y quedó en estado 'no_asignado'.",
                $nombreMacro,
                $tokenId->linea
            );
        } else {
            $estado        = 'asignado';
            $valorGuardado = $valor;
        }

        if ($this->tablaSimbolos->Buscar($nombreMacro, 'MACRO') !== null) {
            $this->tablaSimbolos->ActualizarValor($nombreMacro, $valorGuardado, 'MACRO');
            $this->tablaSimbolos->ActualizarEstado($nombreMacro, $estado, 'MACRO');
        } else {
            $this->tablaSimbolos->Insertar(
                $nombreMacro,
                $valorGuardado,
                $estado,
                'MACRO',
                T_ID,
                $tokenId->linea,
                $tokenId->columna
            );
        }

        // Entrar al cuerpo '{' para validar posibles comandos anidados en su interior
        return $pos + 1;
    }

    /** Verifica mediante búsqueda en grafo si $origen depende directa o indirectamente de $objetivo. */
    private function AlcanzaMacro(string $origen, string $objetivo): bool
    {
        $visitados = [];
        $cola      = [$origen];

        while (!empty($cola)) {
            $actual = array_shift($cola);
            if ($actual === $objetivo) {
                return true;
            }
            if (isset($visitados[$actual])) {
                continue;
            }
            $visitados[$actual] = true;

            foreach ($this->dependenciasMacros[$actual] ?? [] as $sig) {
                if (!isset($visitados[$sig])) {
                    $cola[] = $sig;
                }
            }
        }

        return false;
    }

    private function ProcesarImprimir(array $tokens, int $idx): int
    {
        // #imprimir ( id )
        $tokenId = $tokens[$idx + 2] ?? null;
        if ($tokenId === null) {
            return -1;
        }

        $nombreMacro = trim((string) $tokenId->lexema);
        $simbolo     = $this->tablaSimbolos->Buscar($nombreMacro, 'MACRO');

        if ($simbolo === null) {
            $this->error = $this->CrearError(
                1500,
                $tokenId,
                sprintf("Macro '%s' no registrada en la tabla de símbolos.", $nombreMacro)
            );
            return -1;
        }

        if ($simbolo['estado'] !== 'asignado') {
            $this->error = $this->CrearError(
                1600,
                $tokenId,
                sprintf("Macro '%s' registrada pero sin valor asignado.", $nombreMacro)
            );
            return -1;
        }

        return $idx + 4; // saltar después de ')'
    }

    private function ProcesarReferencia(array $tokens, int $idx): int
    {
        // #referencia ( id )
        $tokenId = $tokens[$idx + 2] ?? null;
        if ($tokenId === null) {
            return -1;
        }

        $etiqueta = trim((string) $tokenId->lexema);
        $simbolo  = $this->tablaSimbolos->Buscar($etiqueta, 'ETIQUETA');

        if ($simbolo === null) {
            $this->error = $this->CrearError(
                1501,
                $tokenId,
                sprintf("Referencia cruzada a etiqueta '%s' no registrada.", $etiqueta)
            );
            return -1;
        }

        return $idx + 4; // saltar después de ')'
    }

    private function ProcesarImagen(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos)) {
            return -1;
        }

        $rutaAttr = $this->BuscarAtributoPorToken($atributos, T_ATTR_RUTA);
        if ($rutaAttr === null || empty($rutaAttr['valores'])) {
            $this->error = $this->CrearError(
                1700,
                $cmdTok,
                "El comando #imagen requiere obligatoriamente el atributo 'ruta'."
            );
            return -1;
        }

        $tokenRuta = $rutaAttr['valores'][0];
        $ruta      = trim((string) $tokenRuta->lexema);
        if ($ruta === '' || preg_match('/\.jpe?g$/i', $ruta) !== 1) {
            $this->error = $this->CrearError(
                1700,
                $tokenRuta,
                sprintf("Ruta de imagen '%s' inválida; solo se admiten archivos JPEG (.jpg o .jpeg).", $ruta)
            );
            return -1;
        }

        foreach ([T_ATTR_ANCHO, T_ATTR_ALTO] as $attrDim) {
            $dim = $this->BuscarAtributoPorToken($atributos, $attrDim);
            if ($dim === null) {
                continue;
            }
            if (count($dim['valores']) !== 1 || !$this->EsDimensionValida($dim['valores'][0])) {
                $errTok = $dim['valores'][0] ?? $cmdTok;
                $this->error = $this->CrearError(
                    1700,
                    $errTok,
                    sprintf("Dimensión inválida en #imagen para '%s'; se esperaba 'auto' o un número positivo.", $dim['clave'])
                );
                return -1;
            }
        }

        return $pos;
    }

    private function ProcesarLista(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos, true)) {
            return -1;
        }

        foreach ($atributos as $attr) {
            $esTipoOPosicional = $attr['claveToken'] === null || $attr['claveToken'] === T_ATTR_TIPO;
            if (!$esTipoOPosicional) {
                continue;
            }
            $valTok = $attr['valores'][0] ?? $cmdTok;
            if (count($attr['valores']) !== 1 || !in_array($valTok->token, [T_VAL_PUNTO, T_VAL_NUMERO_ATTR], true)) {
                $this->error = $this->CrearError(
                    1701,
                    $valTok,
                    sprintf("Tipo de lista '%s' inválido; solo se permite 'punto' o 'numero'.", (string) $valTok->lexema)
                );
                return -1;
            }
        }

        if (!isset($tokens[$pos]) || $tokens[$pos]->token !== T_ALLAVE) {
            $this->error = $this->CrearError(1701, $cmdTok, 'El comando #lista debe abrir un bloque { ... }.');
            return -1;
        }

        $bloques = $this->ContarBloquesAnonimosDirectos($tokens, $pos);
        if ($bloques < 1) {
            $this->error = $this->CrearError(
                1701,
                $tokens[$pos],
                'El comando #lista debe contener al menos un bloque anónimo { ... } con elementos.'
            );
            return -1;
        }

        // Continuar dentro de '{' para validar cualquier comando anidado en los ítems
        return $pos + 1;
    }

    private function ProcesarFraccion(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos)) {
            return -1;
        }

        if (!empty($atributos)) {
            $num = $this->BuscarAtributoPorToken($atributos, T_ATTR_NUMERADOR);
            $den = $this->BuscarAtributoPorToken($atributos, T_ATTR_DENOMINADOR);
            if ($num === null || empty($num['valores']) || $den === null || empty($den['valores'])) {
                $this->error = $this->CrearError(
                    1702,
                    $cmdTok,
                    "El formato con paréntesis de #fraccion requiere los atributos 'numerador' y 'denominador'."
                );
                return -1;
            }
            return $pos;
        }

        if (!isset($tokens[$pos]) || $tokens[$pos]->token !== T_ALLAVE) {
            $this->error = $this->CrearError(
                1702,
                $cmdTok,
                'El comando #fraccion requiere { {numerador} {denominador} } o atributos (numerador=..., denominador=...).'
            );
            return -1;
        }

        $bloques = $this->ContarBloquesAnonimosDirectos($tokens, $pos);
        if ($bloques !== 2) {
            $this->error = $this->CrearError(
                1702,
                $tokens[$pos],
                sprintf('El comando #fraccion requiere exactamente 2 bloques anónimos {numerador} {denominador}; se encontraron %d.', $bloques)
            );
            return -1;
        }

        // Continuar dentro de '{' para validar etiquetas anidadas (p. ej. #superindice)
        return $pos + 1;
    }

    private function ProcesarCaja(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos, true)) {
            return -1;
        }

        foreach ($atributos as $attr) {
            if ($attr['claveToken'] === null || $attr['claveToken'] === T_ATTR_TIPO) {
                $valTok = $attr['valores'][0] ?? $cmdTok;
                if (count($attr['valores']) !== 1 || !in_array($valTok->token, [T_VAL_ALERTA, T_VAL_INFO, T_VAL_EXITO], true)) {
                    $this->error = $this->CrearError(
                        1703,
                        $valTok,
                        sprintf("Tipo de caja '%s' inválido; solo se permite 'alerta', 'info' o 'exito'.", (string) $valTok->lexema)
                    );
                    return -1;
                }
            }
        }

        return $pos;
    }

    private function ProcesarTabla(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos)) {
            return -1;
        }

        foreach ($atributos as $attr) {
            if ($attr['claveToken'] === T_ATTR_BORDES) {
                $valTok = $attr['valores'][0] ?? $cmdTok;
                if (count($attr['valores']) !== 1 || !in_array($valTok->token, [T_VAL_VERDADERO, T_VAL_FALSO], true)) {
                    $this->error = $this->CrearError(
                        1704,
                        $valTok,
                        sprintf("Valor de bordes '%s' inválido en #tabla; se esperaba 'verdadero' o 'falso'.", (string) $valTok->lexema)
                    );
                    return -1;
                }
            }
        }

        return $pos;
    }

    private function ProcesarMetadato(array $tokens, int $idx): int
    {
        $cmdTok    = $tokens[$idx];
        $pos       = $idx + 1;
        $atributos = $this->LeerAtributos($tokens, $pos);

        if (!$this->ValidarAtributosComunes($atributos)) {
            return -1;
        }

        if (isset($tokens[$pos]) && $tokens[$pos]->token === T_ALLAVE) {
            $cierre = $this->EncontrarLlaveCierre($tokens, $pos);
            $texto  = $this->ExtraerTextoPlanoRango($tokens, $pos + 1, $cierre - 1);
            $clave  = (string) $cmdTok->lexema;

            if ($this->tablaSimbolos->Buscar($clave, 'METADATO') !== null) {
                $this->tablaSimbolos->ActualizarValor($clave, $texto, 'METADATO');
                $this->tablaSimbolos->ActualizarEstado($clave, $texto !== '' ? 'asignado' : 'no_asignado', 'METADATO');
            } else {
                $this->tablaSimbolos->Insertar(
                    $clave,
                    $texto !== '' ? $texto : null,
                    $texto !== '' ? 'asignado' : 'no_asignado',
                    'METADATO',
                    $cmdTok->token,
                    $cmdTok->linea,
                    $cmdTok->columna
                );
            }

            return $pos + 1;
        }

        return $pos;
    }

    /**
     * Valida atributos transversales (color, fondo, alineacion, tamaño) y banderas posicionales.
     *
     * @param array<int, array{claveToken: ?int, clave: ?string, valores: Token[]}> $atributos
     */
    private function ValidarAtributosComunes(array $atributos, bool $permitirBanderasEspeciales = false): bool
    {
        foreach ($atributos as $attr) {
            $claveToken = $attr['claveToken'];
            $valores    = $attr['valores'];

            if (empty($valores)) {
                continue;
            }

            $primerValor = $valores[0];

            // Banderas posicionales fuera de contexto (p. ej. #texto(alerta) o #texto(punto))
            if ($claveToken === null) {
                if (!$permitirBanderasEspeciales && !in_array($primerValor->token, self::ALINEACIONES_VALIDAS, true)) {
                    $this->advertencias[] = sprintf(
                        "La bandera posicional '%s' (línea %d) no aplica a este comando y será ignorada.",
                        (string) $primerValor->lexema,
                        $primerValor->linea
                    );
                }
                continue;
            }

            // Advertir atributos desconocidos (typos como alineasion=...) sin romper compilación
            if ($claveToken === T_ID) {
                $this->advertencias[] = sprintf(
                    "El atributo '%s' no pertenece al catálogo oficial de LATINEX y será ignorado.",
                    (string) $attr['clave']
                );
                continue;
            }

            // Color o fondo (#RRGGBB / #RGB o color reservado)
            if ($claveToken === T_ATTR_COLOR || $claveToken === T_ATTR_FONDO) {
                if (count($valores) !== 1 || !$this->EsColorValido($primerValor)) {
                    $this->error = $this->CrearError(
                        1705,
                        $primerValor,
                        sprintf("Valor de color '%s' inválido en '%s'; use #RRGGBB o un color reservado en español.", (string) $primerValor->lexema, (string) $attr['clave'])
                    );
                    return false;
                }
                continue;
            }

            // Alineación explícita
            if ($claveToken === T_ATTR_ALINEACION) {
                if (count($valores) !== 1 || !in_array($primerValor->token, self::ALINEACIONES_VALIDAS, true)) {
                    $this->error = $this->CrearError(
                        1706,
                        $primerValor,
                        sprintf("Valor de alineación '%s' inválido; se esperaba izquierda, centro, derecha o justificado.", (string) $primerValor->lexema)
                    );
                    return false;
                }
                continue;
            }

            // Tamaño tipográfico positivo
            if ($claveToken === T_ATTR_TAMANO) {
                if (count($valores) !== 1 || !$this->EsNumeroPositivo($primerValor, false)) {
                    $this->error = $this->CrearError(
                        1707,
                        $primerValor,
                        sprintf("Tamaño '%s' inválido; debe ser un número positivo mayor a 0.", (string) $primerValor->lexema)
                    );
                    return false;
                }
                continue;
            }
        }

        return true;
    }

    /**
     * Parsea opcionalmente '( ... )' avanzando $pos hasta después de ')'.
     *
     * @return array<int, array{claveToken: ?int, clave: ?string, valores: Token[]}>
     */
    private function LeerAtributos(array $tokens, int &$pos): array
    {
        $resultado = [];
        $total     = count($tokens);

        if ($pos >= $total || $tokens[$pos]->token !== T_APAR) {
            return $resultado;
        }

        $pos++; // consumir '('

        while ($pos < $total && $tokens[$pos]->token !== T_CPAR) {
            if ($tokens[$pos]->token === T_COMA) {
                $pos++;
                continue;
            }

            if ($pos + 1 < $total && $tokens[$pos + 1]->token === T_IGUAL) {
                $claveTok = $tokens[$pos];
                $pos += 2; // saltar clave e '='
                $valores = [];
                while ($pos < $total && $tokens[$pos]->token !== T_COMA && $tokens[$pos]->token !== T_CPAR) {
                    $valores[] = $tokens[$pos];
                    $pos++;
                }
                $resultado[] = [
                    'claveToken' => $claveTok->token,
                    'clave'      => (string) $claveTok->lexema,
                    'valores'    => $valores,
                ];
            } else {
                $resultado[] = [
                    'claveToken' => null,
                    'clave'      => null,
                    'valores'    => [$tokens[$pos]],
                ];
                $pos++;
            }
        }

        if ($pos < $total && $tokens[$pos]->token === T_CPAR) {
            $pos++; // consumir ')'
        }

        return $resultado;
    }

    private function BuscarAtributoPorToken(array $atributos, int $tokenClave): ?array
    {
        foreach ($atributos as $attr) {
            if ($attr['claveToken'] === $tokenClave) {
                return $attr;
            }
        }
        return null;
    }

    private function EncontrarLlaveCierre(array $tokens, int $posApertura): int
    {
        $total = count($tokens);
        $nivel = 0;
        for ($i = $posApertura; $i < $total; $i++) {
            if ($tokens[$i]->token === T_ALLAVE) {
                $nivel++;
            } elseif ($tokens[$i]->token === T_CLLAVE) {
                $nivel--;
                if ($nivel === 0) {
                    return $i;
                }
            }
        }
        return $total - 1;
    }

    /** Cuenta bloques anónimos directos `{ ... }` dentro del contenedor abierto en `$posApertura`. */
    private function ContarBloquesAnonimosDirectos(array $tokens, int $posApertura): int
    {
        $total   = count($tokens);
        $nivel   = 0;
        $bloques = 0;

        for ($i = $posApertura; $i < $total; $i++) {
            if ($tokens[$i]->token === T_ALLAVE) {
                $nivel++;
                if ($nivel === 2) {
                    $bloques++;
                }
            } elseif ($tokens[$i]->token === T_CLLAVE) {
                $nivel--;
                if ($nivel === 0) {
                    break;
                }
            }
        }

        return $bloques;
    }

    /** Reconstruye texto legible dentro de un rango de tokens (expande macros asignadas si existen). */
    private function ExtraerTextoPlanoRango(array $tokens, int $inicio, int $fin): string
    {
        $partes = [];
        for ($i = $inicio; $i <= $fin; $i++) {
            $t = $tokens[$i];
            if ($t->token === T_TEXTO_PLANO || $t->token === T_ESCAPE_CHAR || $t->token === T_COLOR_HEX) {
                $partes[] = (string) $t->lexema;
            } elseif ($t->token === T_IMPRIMIR && isset($tokens[$i + 2])) {
                $macro = $this->tablaSimbolos->Buscar((string) $tokens[$i + 2]->lexema, 'MACRO');
                if ($macro !== null && $macro['estado'] === 'asignado') {
                    $partes[] = (string) $macro['valor'];
                } else {
                    $partes[] = '#imprimir(' . $tokens[$i + 2]->lexema . ')';
                }
                $i += 3;
            }
        }
        return trim(preg_replace('/\s+/u', ' ', implode(' ', $partes)) ?? '');
    }

    private function EsColorValido(Token $token): bool
    {
        return $token->token === T_COLOR_HEX
            || in_array($token->token, self::COLORES_NOMINALES, true);
    }

    private function EsDimensionValida(Token $token): bool
    {
        if ($token->token === T_VAL_AUTO) {
            return true;
        }
        return $this->EsNumeroPositivo($token, true);
    }

    private function EsNumeroPositivo(Token $token, bool $permitirPorcentaje): bool
    {
        if ($token->token !== T_NUMERO) {
            return false;
        }
        $lexema = (string) $token->lexema;
        if (str_ends_with($lexema, '%')) {
            if (!$permitirPorcentaje) {
                return false;
            }
            $lexema = substr($lexema, 0, -1);
        }
        return is_numeric($lexema) && (float) $lexema > 0;
    }

    private function EsComandoConAtributos(int $token): bool
    {
        return in_array($token, [
            T_SECCION,
            T_SUBSECCION,
            T_SUBSECCION2,
            T_SUBSECCION3,
            T_SUBSECCION4,
            T_TEXTO,
            T_RESUMEN,
            T_FUERTE,
            T_CURSIVA,
            T_SUBRAYADO,
            T_RESALTADO,
            T_FILA,
            T_CELDA,
            T_SUPERINDICE,
            T_SUBINDICE,
            T_ECUACION,
        ], true);
    }

    private function CrearError(int $codigo, Token $token, string $descripcion): array
    {
        $nombre = NombreToken($token->token);
        $lexema = $token->lexema ?? 'null';

        return [
            'tipo'        => 'semantico',
            'codigo'      => $codigo,
            'token'       => $token->token,
            'nombreToken' => $nombre,
            'lexema'      => $lexema,
            'linea'       => $token->linea,
            'columna'     => $token->columna,
            'descripcion' => $descripcion,
            'mensaje'     => sprintf(
                'Error %d (semántico) en línea %d, columna %d: %s',
                $codigo,
                $token->linea,
                $token->columna,
                $descripcion
            ),
        ];
    }
}

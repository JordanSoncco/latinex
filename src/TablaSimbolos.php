<?php

namespace Latinex;

/* ============================================================
 * TABLA DE SÍMBOLOS DEL COMPILADOR LATINEX
 * ============================================================
 *
 * Administra los símbolos declarados por el usuario durante
 * el análisis semántico y la ejecución Two-Pass:
 *   - MACRO    : variables definidas mediante #definir(nombre){valor}
 *   - ETIQUETA : identificadores de sección (#seccion(id=...))
 *   - METADATO : metadatos de documento (#titulo, #autor, #fecha)
 *
 * Mantiene la interfaz estándar de Graficador (Insertar, Buscar,
 * ActualizarEstado, ActualizarValor, ObtenerTodos) añadiendo
 * soporte opcional de ámbito por tipo sin colisiones de nombre.
 * ============================================================
 */
class TablaSimbolos
{
    /**
     * @var array<string, array{
     *     lexema: string,
     *     token: int,
     *     tipo: string,
     *     valor: mixed,
     *     estado: string,
     *     linea: ?int,
     *     columna: ?int
     * }>
     */
    private array $simbolos = [];

    private function Clave(string $lexema, string $tipo): string
    {
        return strtoupper($tipo) . ':' . $lexema;
    }

    /**
     * Inserta un nuevo símbolo evitando duplicados en la misma categoría.
     */
    public function Insertar(
        string $lexema,
        mixed $valor = null,
        string $estado = 'no_asignado',
        string $tipo = 'MACRO',
        int $token = T_ID,
        ?int $linea = null,
        ?int $columna = null
    ): bool {
        $tipoNormalizado = strtoupper($tipo);
        $clave           = $this->Clave($lexema, $tipoNormalizado);

        if (isset($this->simbolos[$clave])) {
            return false;
        }

        $this->simbolos[$clave] = [
            'lexema'  => $lexema,
            'token'   => $token,
            'tipo'    => $tipoNormalizado,
            'valor'   => $valor,
            'estado'  => $estado,
            'linea'   => $linea,
            'columna' => $columna,
        ];

        return true;
    }

    /**
     * Recupera un símbolo por lexema (y opcionalmente por tipo).
     * Si $tipo es null, busca en orden MACRO, ETIQUETA, METADATO o primera coincidencia.
     */
    public function Buscar(string $lexema, ?string $tipo = null): ?array
    {
        if ($tipo !== null) {
            return $this->simbolos[$this->Clave($lexema, $tipo)] ?? null;
        }

        foreach (['MACRO', 'ETIQUETA', 'METADATO'] as $prioridad) {
            $clave = $this->Clave($lexema, $prioridad);
            if (isset($this->simbolos[$clave])) {
                return $this->simbolos[$clave];
            }
        }

        foreach ($this->simbolos as $registro) {
            if ($registro['lexema'] === $lexema) {
                return $registro;
            }
        }

        return null;
    }

    /** Actualiza el estado ('asignado' o 'no_asignado') de un símbolo existente. */
    public function ActualizarEstado(string $lexema, string $estado, ?string $tipo = null): bool
    {
        $clave = $this->ResolverClaveExistente($lexema, $tipo);
        if ($clave === null) {
            return false;
        }

        $this->simbolos[$clave]['estado'] = $estado;
        return true;
    }

    /** Actualiza el valor asociado a un símbolo existente. */
    public function ActualizarValor(string $lexema, mixed $valor, ?string $tipo = null): bool
    {
        $clave = $this->ResolverClaveExistente($lexema, $tipo);
        if ($clave === null) {
            return false;
        }

        $this->simbolos[$clave]['valor'] = $valor;
        return true;
    }

    /** Devuelve todos los registros indexados secuencialmente para JSON/UI. */
    public function ObtenerTodos(): array
    {
        return array_values($this->simbolos);
    }

    /** Filtra los símbolos por categoría ('MACRO', 'ETIQUETA', 'METADATO'). */
    public function ObtenerPorTipo(string $tipo): array
    {
        $tipoNormalizado = strtoupper($tipo);
        return array_values(array_filter(
            $this->simbolos,
            fn(array $s): bool => $s['tipo'] === $tipoNormalizado
        ));
    }

    private function ResolverClaveExistente(string $lexema, ?string $tipo): ?string
    {
        if ($tipo !== null) {
            $clave = $this->Clave($lexema, $tipo);
            return isset($this->simbolos[$clave]) ? $clave : null;
        }

        foreach (['MACRO', 'ETIQUETA', 'METADATO'] as $prioridad) {
            $clave = $this->Clave($lexema, $prioridad);
            if (isset($this->simbolos[$clave])) {
                return $clave;
            }
        }

        foreach ($this->simbolos as $clave => $registro) {
            if ($registro['lexema'] === $lexema) {
                return $clave;
            }
        }

        return null;
    }
}

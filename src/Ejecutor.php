<?php

namespace Latinex;

/* ============================================================
 * EJECUTOR / MOTOR GEOMÉTRICO TWO-PASS — LATINEX (FRENTE 2)
 * ============================================================
 *
 * Responsable asignado: William Dominic Tito Monteagudo
 * Rama asignada:        feat/frente2-ejecutor-pdf
 *
 * Equivalente en arquitectura a la clase Ejecutor de graficador.php:
 *   - Recorre las instrucciones ya validadas sintáctica y
 *     semánticamente con su propia instancia de Tokenizador.
 *   - Resuelve macros, tabla de símbolos, jerarquía de secciones,
 *     pila de estilos (StyleStack), métricas tipográficas AFM,
 *     paginación A4 (595 x 842 pt, margen sup. 50, inf. 792),
 *     referencias cruzadas e #indicegeneral (Pasada 1).
 *   - Produce el arreglo estructurado $elementos (IR geométrico)
 *     que consumirá PdfGenerator para emitir %PDF-1.4 nativo.
 * ============================================================
 */
class Ejecutor
{
    private Tokenizador $tokenizador;

    private TablaSimbolos $tablaSimbolos;

    private string $assetsDir;

    /** @var array<int, array<string, mixed>> */
    private array $elementos = [];

    private ?array $error = null;

    /** @var array<string, string> */
    private array $metadatos = [];

    private int $totalPaginas = 0;

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
     * Ejecuta la Pasada 1 (interpretación semántica + layout geométrico).
     *
     * TODO (William - Frente 2):
     *   1. Mantener StyleStack con push/pop para fuentes (/F1 Helvetica,
     *      /F2 Bold, /F3 Oblique, /F5 Courier) y colores RGB 0..1.
     *   2. Implementar tabla de anchos AFM y ajuste de líneas (word wrap),
     *      alineación izquierda/centro/derecha y justificada (Tw = delta/k).
     *   3. Soportar los 29 casos del documento de alcance:
     *      - Básicos (01–10): #texto, #fuerte/#cursiva/#subrayado/#resaltado,
     *        #titulo/#autor/#fecha, #seccion..#subseccion4, #salto, #nuevapagina,
     *        escapes y alineaciones.
     *      - Intermedios (11–19): #lista(punto|numero), #RRGGBB, #definir/#imprimir,
     *        #imagen JPEG, #superindice/#subindice, #fraccion, atributos combinados.
     *      - Avanzados (20–29): paginación automática A4, #tabla/#fila/#celda,
     *        #referencia forward, #indicegeneral, anidamientos, #ecuacion, #caja.
     *   4. Cambiar 'implementado' a true en getResumen() cuando esté listo.
     */
    public function Ejecutar(): bool
    {
        $this->elementos    = [];
        $this->error        = null;
        $this->metadatos    = [];
        $this->totalPaginas = 0;

        // Esqueleto contractual: mientras Frente 2 implementa la Pasada 1,
        // consumimos el flujo para poblar TablaSimbolos básica sin errores.
        while (true) {
            $token = $this->tokenizador->getToken();
            if ($token->token === T_ERROR) {
                $this->error = [
                    'tipo'        => 'ejecucion',
                    'codigo'      => T_ERROR,
                    'token'       => $token->token,
                    'nombreToken' => NombreToken($token->token),
                    'lexema'      => $token->lexema,
                    'linea'       => $token->linea,
                    'columna'     => $token->columna,
                    'descripcion' => 'Error léxico encontrado durante la ejecución.',
                    'mensaje'     => sprintf(
                        'Error %d (ejecución) en línea %d, columna %d: error léxico.',
                        T_ERROR,
                        $token->linea,
                        $token->columna
                    ),
                ];
                return false;
            }
            if ($token->token === T_FIN) {
                break;
            }
        }

        return true;
    }

    /**
     * Devuelve el arreglo ordenado de instrucciones geométricas resueltas
     * (equivalente a getFiguras() en Graficador, listo para PdfGenerator e IDE).
     *
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
            'implementado'   => false,
            'estado'         => 'Pendiente Frente 2 (William): Motor Geométrico Two-Pass',
            'totalPaginas'   => $this->totalPaginas,
            'totalElementos' => count($this->elementos),
            'metadatos'      => $this->metadatos,
        ];
    }
}

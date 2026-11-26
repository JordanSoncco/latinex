<?php

namespace Latinex;

/* ============================================================
 * GENERADOR DE CÓDIGO DESTINO NATIVO %PDF-1.4 — LATINEX (FASE 4.2)
 * ============================================================
 *
 * Implementa la Pasada 2 del compilador sin librerías externas
 * (sin FPDF ni TCPDF), construyendo directamente el formato
 * binario estándar %PDF-1.4:
 *   - Objetos estructurales /Catalog, /Pages, /Page (A4: [0 0 595 842]).
 *   - Fuentes base Type1 con codificación /WinAnsiEncoding:
 *       /F1 Helvetica, /F2 Helvetica-Bold, /F3 Helvetica-Oblique,
 *       /F4 Helvetica-BoldOblique, /F5 Courier, /F6 Times-Roman.
 *   - Diccionario /Info con metadatos del documento (/Title, /Author).
 *   - Objetos /XObject /Subtype /Image /Filter /DCTDecode para flujos
 *     JPEG crudos (DeviceGray, DeviceRGB o DeviceCMYK).
 *   - Flujos de contenido PostScript (BT, ET, Tf, Td, Tj, Tw, rg, RG,
 *     w, re, m, l, h, S, f, B, q ... cm /ImN Do Q).
 *   - Conversión UTF-8 a Windows-1252 con escape de paréntesis y barras.
 *   - Tabla de referencias cruzadas binaria exacta (xref) y trailer.
 * ============================================================
 */
class PdfGenerator
{
    /** Fuentes estándar Type1 registradas en los recursos de cada página */
    private const FUENTES_BASE = [
        'F1' => 'Helvetica',
        'F2' => 'Helvetica-Bold',
        'F3' => 'Helvetica-Oblique',
        'F4' => 'Helvetica-BoldOblique',
        'F5' => 'Courier',
        'F6' => 'Times-Roman',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $elementos;

    /** @var array<string, string> */
    private array $metadatos;

    private string $assetsDir;

    private int $paginasEmitidas = 0;

    private int $objetosPdf = 0;

    private int $bytesGenerados = 0;

    /**
     * @param array<int, array<string, mixed>> $elementos Salida geométrica de Ejecutor::getElementos()
     * @param array<string, string>            $metadatos Metadatos /Info (/Title, /Author, /Subject)
     * @param string                           $assetsDir Directorio de imágenes de la sesión
     */
    public function __construct(
        array $elementos = [],
        array $metadatos = [],
        string $assetsDir = ''
    ) {
        $this->elementos = $elementos;
        $this->metadatos = $metadatos;
        $this->assetsDir = $assetsDir;
    }

    public function isImplemented(): bool
    {
        return true;
    }

    /**
     * Emite y retorna el binario completo %PDF-1.4.
     */
    public function generate(): string
    {
        // 1. Agrupar elementos por página (1..N); garantizar al menos 1 página A4
        $porPagina = [];
        $maxPagina = 1;

        foreach ($this->elementos as $el) {
            $p = max(1, (int) ($el['pagina'] ?? 1));
            $maxPagina = max($maxPagina, $p);
            $porPagina[$p][] = $el;
        }
        for ($p = 1; $p <= $maxPagina; $p++) {
            if (!isset($porPagina[$p])) {
                $porPagina[$p] = [];
            }
        }
        ksort($porPagina);

        // 2. Numeración determinística de objetos PDF
        //    1: /Catalog
        //    2: /Pages
        //    3..8: /Font (/F1 .. /F6)
        $objetos = [];
        $nextId  = 3;

        $fontObjIds = [];
        foreach (self::FUENTES_BASE as $alias => $baseFont) {
            $id = $nextId++;
            $fontObjIds[$alias] = $id;
            $objetos[$id] = sprintf(
                "<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>",
                $baseFont
            );
        }

        // 3. Registrar XObjects JPEG únicos (/Im1, /Im2, ...) con flujo crudo /DCTDecode
        $imagenesPorRuta = [];
        $xObjectRefs     = [];
        $contadorImg     = 1;

        foreach ($this->elementos as $el) {
            if (($el['tipo'] ?? '') !== 'image') {
                continue;
            }
            $ruta = (string) ($el['ruta'] ?? '');
            if ($ruta === '' || isset($imagenesPorRuta[$ruta]) || !is_file($ruta)) {
                continue;
            }
            $bytesJpeg = @file_get_contents($ruta);
            if ($bytesJpeg === false || $bytesJpeg === '') {
                continue;
            }

            $aliasIm   = 'Im' . $contadorImg++;
            $idImgObj  = $nextId++;
            $pxAncho   = max(1, (int) ($el['pxAncho'] ?? 1));
            $pxAlto    = max(1, (int) ($el['pxAlto'] ?? 1));
            $canales   = (int) ($el['canales'] ?? 3);
            $espacioColor = match ($canales) {
                1       => '/DeviceGray',
                4       => '/DeviceCMYK',
                default => '/DeviceRGB',
            };

            $lenBytes = strlen($bytesJpeg);
            $objetos[$idImgObj] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $pxAncho,
                $pxAlto,
                $espacioColor,
                $lenBytes,
                $bytesJpeg
            );

            $imagenesPorRuta[$ruta] = $aliasIm;
            $xObjectRefs[$aliasIm]  = $idImgObj;
        }

        // Diccionario compartido de recursos /Font y /XObject
        $partesFontRes = [];
        foreach ($fontObjIds as $alias => $idObj) {
            $partesFontRes[] = sprintf('/%s %d 0 R', $alias, $idObj);
        }
        $fontDict = '<< ' . implode(' ', $partesFontRes) . ' >>';

        $xObjDict = '';
        if (!empty($xObjectRefs)) {
            $partesXObj = [];
            foreach ($xObjectRefs as $aliasIm => $idObj) {
                $partesXObj[] = sprintf('/%s %d 0 R', $aliasIm, $idObj);
            }
            $xObjDict = ' /XObject << ' . implode(' ', $partesXObj) . ' >>';
        }

        $resourcesDict = sprintf('<< /Font %s%s >>', $fontDict, $xObjDict);

        // 4. Construir flujos PostScript y objetos /Page para cada hoja A4
        $kidsRefs = [];
        foreach ($porPagina as $numPag => $listaElem) {
            $streamTxt = $this->ConstruirStreamPagina($listaElem, $imagenesPorRuta);
            $lenStream = strlen($streamTxt);

            $idContent = $nextId++;
            $objetos[$idContent] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                $lenStream,
                $streamTxt
            );

            $idPage = $nextId++;
            $objetos[$idPage] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources %s /Contents %d 0 R >>",
                $resourcesDict,
                $idContent
            );

            $kidsRefs[] = sprintf('%d 0 R', $idPage);
        }

        // 5. Objeto /Info con metadatos del documento (#titulo, #autor, #fecha)
        $idInfo = $nextId++;
        $objetos[$idInfo] = $this->ConstruirObjetoInfo();

        // Completar objetos raíz 1 (/Catalog) y 2 (/Pages)
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = sprintf(
            '<< /Type /Pages /Kids [ %s ] /Count %d >>',
            implode(' ', $kidsRefs),
            count($kidsRefs)
        );
        ksort($objetos);

        // 6. Ensamblar archivo binario %PDF-1.4 con tabla xref exacta en bytes
        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objetos as $id => $cuerpo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $cuerpo);
        }

        $xrefOffset = strlen($pdf);
        $totalSize  = $nextId;

        $pdf .= sprintf("xref\n0 %d\n", $totalSize);
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id < $totalSize; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            $totalSize,
            $idInfo,
            $xrefOffset
        );

        $this->paginasEmitidas = count($kidsRefs);
        $this->objetosPdf      = count($objetos);
        $this->bytesGenerados  = strlen($pdf);

        return $pdf;
    }

    public function getStats(): array
    {
        return [
            'implementado' => $this->isImplemented(),
            'paginas'      => $this->paginasEmitidas,
            'bytes'        => $this->bytesGenerados,
            'objetosPdf'   => $this->objetosPdf,
        ];
    }

    /* ============================================================
     * SÍNTESIS DE OPERADORES POSTSCRIPT POR PÁGINA
     * ============================================================ */

    /**
     * @param array<int, array<string, mixed>> $elementosPagina
     * @param array<string, string>            $imagenesPorRuta
     */
    private function ConstruirStreamPagina(array $elementosPagina, array $imagenesPorRuta): string
    {
        $ops = [];

        foreach ($elementosPagina as $el) {
            $tipo = $el['tipo'] ?? '';

            if ($tipo === 'text') {
                $fuente = (string) ($el['fuente'] ?? '/F1');
                if ($fuente === '' || $fuente[0] !== '/') {
                    $fuente = '/' . $fuente;
                }
                $tamano = (float) ($el['tamano'] ?? 12.0);
                $x      = (float) ($el['x'] ?? 50.0);
                $yPdf   = isset($el['yPdf'])
                    ? (float) $el['yPdf']
                    : (842.0 - (float) ($el['yLogica'] ?? 62.0));
                $rgb    = $this->FormatearColor($el['color'] ?? [0.0, 0.0, 0.0]);
                $tw     = (float) ($el['tw'] ?? 0.0);
                $cadena = $this->CodificarCadenaPdf((string) ($el['texto'] ?? ''));

                if (abs($tw) > 0.0005) {
                    $ops[] = sprintf(
                        'BT %s %.2f Tf %s rg %.3f Tw %.2f %.2f Td (%s) Tj 0 Tw ET',
                        $fuente,
                        $tamano,
                        $rgb,
                        $tw,
                        $x,
                        $yPdf,
                        $cadena
                    );
                } else {
                    $ops[] = sprintf(
                        'BT %s %.2f Tf %s rg %.2f %.2f Td (%s) Tj ET',
                        $fuente,
                        $tamano,
                        $rgb,
                        $x,
                        $yPdf,
                        $cadena
                    );
                }
                continue;
            }

            if ($tipo === 'rect') {
                $x       = (float) ($el['x'] ?? 0.0);
                $ancho   = (float) ($el['ancho'] ?? 0.0);
                $alto    = (float) ($el['alto'] ?? 0.0);
                $yPdf    = isset($el['yPdf'])
                    ? (float) $el['yPdf']
                    : (842.0 - ((float) ($el['yLogica'] ?? 0.0) + $alto));
                $relleno = $el['relleno'] ?? null;
                $borde   = $el['borde'] ?? null;
                $grosor  = max(0.2, (float) ($el['grosorBorde'] ?? 0.75));

                if ($relleno !== null && $borde !== null) {
                    $ops[] = sprintf(
                        'q %s rg %s RG %.2f w %.2f %.2f %.2f %.2f re B Q',
                        $this->FormatearColor($relleno),
                        $this->FormatearColor($borde),
                        $grosor,
                        $x,
                        $yPdf,
                        $ancho,
                        $alto
                    );
                } elseif ($relleno !== null) {
                    $ops[] = sprintf(
                        'q %s rg %.2f %.2f %.2f %.2f re f Q',
                        $this->FormatearColor($relleno),
                        $x,
                        $yPdf,
                        $ancho,
                        $alto
                    );
                } elseif ($borde !== null) {
                    $ops[] = sprintf(
                        'q %s RG %.2f w %.2f %.2f %.2f %.2f re S Q',
                        $this->FormatearColor($borde),
                        $grosor,
                        $x,
                        $yPdf,
                        $ancho,
                        $alto
                    );
                }
                continue;
            }

            if ($tipo === 'line') {
                $x1    = (float) ($el['x1'] ?? 0.0);
                $x2    = (float) ($el['x2'] ?? 0.0);
                $y1Pdf = isset($el['y1Pdf']) ? (float) $el['y1Pdf'] : (842.0 - (float) ($el['y1Logica'] ?? 0.0));
                $y2Pdf = isset($el['y2Pdf']) ? (float) $el['y2Pdf'] : (842.0 - (float) ($el['y2Logica'] ?? 0.0));
                $rgb   = $this->FormatearColor($el['color'] ?? [0.0, 0.0, 0.0]);
                $gros  = max(0.2, (float) ($el['grosor'] ?? 0.8));

                $ops[] = sprintf('q %s RG %.2f w %.2f %.2f m %.2f %.2f l S Q', $rgb, $gros, $x1, $y1Pdf, $x2, $y2Pdf);
                continue;
            }

            if ($tipo === 'poly') {
                $puntos = $el['puntos'] ?? [];
                if (count($puntos) < 2) {
                    continue;
                }
                $trazos = [];
                foreach ($puntos as $idx => $pt) {
                    $px    = (float) ($pt[0] ?? 0.0);
                    $pyPdf = 842.0 - (float) ($pt[1] ?? 0.0);
                    $trazos[] = sprintf('%.2f %.2f %s', $px, $pyPdf, $idx === 0 ? 'm' : 'l');
                }
                if (!empty($el['cerrado'])) {
                    $trazos[] = 'h';
                }

                $relleno = $el['relleno'] ?? null;
                $borde   = $el['borde'] ?? null;
                $grosor  = max(0.2, (float) ($el['grosorBorde'] ?? 1.0));

                $pref = ['q'];
                if ($relleno !== null) {
                    $pref[] = $this->FormatearColor($relleno) . ' rg';
                }
                if ($borde !== null) {
                    $pref[] = $this->FormatearColor($borde) . ' RG';
                    $pref[] = sprintf('%.2f w', $grosor);
                }

                $opPintado = ($relleno !== null && $borde !== null)
                    ? 'B'
                    : ($relleno !== null ? 'f' : 'S');

                $ops[] = implode(' ', $pref) . ' ' . implode(' ', $trazos) . ' ' . $opPintado . ' Q';
                continue;
            }

            if ($tipo === 'image') {
                $ruta = (string) ($el['ruta'] ?? '');
                if (!isset($imagenesPorRuta[$ruta])) {
                    continue;
                }
                $aliasIm = $imagenesPorRuta[$ruta];
                $ancho   = (float) ($el['ancho'] ?? 100.0);
                $alto    = (float) ($el['alto'] ?? 100.0);
                $x       = (float) ($el['x'] ?? 50.0);
                $yPdf    = isset($el['yPdf'])
                    ? (float) $el['yPdf']
                    : (842.0 - ((float) ($el['yLogica'] ?? 50.0) + $alto));

                $ops[] = sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q', $ancho, $alto, $x, $yPdf, $aliasIm);
                continue;
            }
        }

        return implode("\n", $ops);
    }

    private function ConstruirObjetoInfo(): string
    {
        $entradas = [
            '/Creator (' . $this->CodificarCadenaPdf('Compilador LATINEX — EPIS UNAP') . ')',
            '/Producer (' . $this->CodificarCadenaPdf('LATINEX Native %PDF-1.4 Backend (PHP 8)') . ')',
        ];

        if (!empty($this->metadatos['Title'])) {
            $entradas[] = '/Title (' . $this->CodificarCadenaPdf($this->metadatos['Title']) . ')';
        }
        if (!empty($this->metadatos['Author'])) {
            $entradas[] = '/Author (' . $this->CodificarCadenaPdf($this->metadatos['Author']) . ')';
        }
        if (!empty($this->metadatos['Subject'])) {
            $entradas[] = '/Subject (' . $this->CodificarCadenaPdf($this->metadatos['Subject']) . ')';
        }

        return '<< ' . implode(' ', $entradas) . ' >>';
    }

    /**
     * Convierte una cadena UTF-8 a Windows-1252 (WinAnsiEncoding) y escapa \, ( y ).
     */
    private function CodificarCadenaPdf(string $texto): string
    {
        // Normalizar símbolos matemáticos Unicode fuera de CP1252 antes de convertir
        $mapaPrevio = [
            '−' => '-', // signo menos matemático U+2212
            '×' => 'x',
            '÷' => '/',
            '≤' => '<=',
            '≥' => '>=',
            '≠' => '!=',
        ];
        $limpio = strtr($texto, $mapaPrevio);

        $win1252 = false;
        if (function_exists('mb_convert_encoding')) {
            $win1252 = @mb_convert_encoding($limpio, 'Windows-1252', 'UTF-8');
        }
        if ($win1252 === false && function_exists('iconv')) {
            $win1252 = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $limpio);
        }
        if (!is_string($win1252)) {
            $win1252 = $limpio;
        }

        return strtr($win1252, [
            '\\' => '\\\\',
            '('  => '\\(',
            ')'  => '\\)',
            "\r" => '\\r',
            "\n" => '\\n',
        ]);
    }

    /**
     * @param array<int, float|int> $rgb
     */
    private function FormatearColor(array $rgb): string
    {
        $r = max(0.0, min(1.0, (float) ($rgb[0] ?? 0.0)));
        $g = max(0.0, min(1.0, (float) ($rgb[1] ?? 0.0)));
        $b = max(0.0, min(1.0, (float) ($rgb[2] ?? 0.0)));
        return sprintf('%.3f %.3f %.3f', $r, $g, $b);
    }
}

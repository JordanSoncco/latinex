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
    /** Fuentes estándar Type1 con WinAnsiEncoding registradas en los recursos de cada página */
    private const FUENTES_BASE = [
        'F1' => 'Helvetica',
        'F2' => 'Helvetica-Bold',
        'F3' => 'Helvetica-Oblique',
        'F4' => 'Helvetica-BoldOblique',
        'F5' => 'Courier',
        'F6' => 'Times-Roman',
    ];

    /**
     * Mapa de caracteres Unicode científicos y griegos al código octal/byte de la
     * fuente estándar PDF Type1 /Symbol (Adobe SymbolEncoding, sin librerías externas).
     */
    private const MAPA_SYMBOL = [
        // Alfabeto griego mayúsculas
        'Α' => "\x41", 'Β' => "\x42", 'Γ' => "\x47", 'Δ' => "\x44", 'Ε' => "\x45",
        'Ζ' => "\x5A", 'Η' => "\x48", 'Θ' => "\x51", 'Ι' => "\x49", 'Κ' => "\x4B",
        'Λ' => "\x4C", 'Μ' => "\x4D", 'Ν' => "\x4E", 'Ξ' => "\x58", 'Ο' => "\x4F",
        'Π' => "\x50", 'Ρ' => "\x52", 'Σ' => "\x53", 'Τ' => "\x54", 'Υ' => "\x55",
        'Φ' => "\x46", 'Χ' => "\x43", 'Ψ' => "\x59", 'Ω' => "\x57",
        // Alfabeto griego minúsculas
        'α' => "\x61", 'β' => "\x62", 'γ' => "\x67", 'δ' => "\x64", 'ε' => "\x65",
        'ζ' => "\x7A", 'η' => "\x68", 'θ' => "\x71", 'ι' => "\x69", 'κ' => "\x6B",
        'λ' => "\x6C", 'μ' => "\x6D", 'ν' => "\x6E", 'ξ' => "\x78", 'ο' => "\x6F",
        'π' => "\x70", 'ρ' => "\x72", 'ς' => "\x56", 'σ' => "\x73", 'τ' => "\x74",
        'υ' => "\x75", 'φ' => "\x66", 'χ' => "\x63", 'ψ' => "\x79", 'ω' => "\x77",
        // Operadores matemáticos y lógicos estándar
        '∀' => "\x22", '∃' => "\x24", '≅' => "\x40", '∴' => "\x5C", '⊥' => "\x5E",
        '≤' => "\xA3", '∞' => "\xA5", '↔' => "\xAB", '←' => "\xAC", '↑' => "\xAD",
        '→' => "\xAE", '↓' => "\xAF", '≥' => "\xB3", '∝' => "\xB5", '∂' => "\xB6",
        '≠' => "\xB9", '≡' => "\xBA", '≈' => "\xBB", '∩' => "\xC7", '∪' => "\xC8",
        '⊂' => "\xCC", '⊆' => "\xCD", '∈' => "\xCE", '∉' => "\xCF", '∇' => "\xD1",
        '∏' => "\xD5", '√' => "\xD6", '∧' => "\xD9", '∨' => "\xDA", '⇔' => "\xDB",
        '⇐' => "\xDC", '⇑' => "\xDD", '⇒' => "\xDE", '⇓' => "\xDF", '∑' => "\xE5",
        '∫' => "\xF2",
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

        // Registrar /F7 (/Symbol con su codificación intrínseca SymbolEncoding) solo si se emplean glifos matemáticos/griegos
        if ($this->ContieneGlifosSymbol()) {
            $idSymbol = $nextId++;
            $fontObjIds['F7'] = $idSymbol;
            $objetos[$idSymbol] = '<< /Type /Font /Subtype /Type1 /BaseFont /Symbol >>';
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
        $kidsRefs     = [];
        $totalPaginas = count($porPagina);
        $mostrarPie   = !empty($this->elementos);

        foreach ($porPagina as $numPag => $listaElem) {
            $streamTxt = $this->ConstruirStreamPagina($listaElem, $imagenesPorRuta, (int) $numPag, $totalPaginas, $mostrarPie);
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
    private function ConstruirStreamPagina(
        array $elementosPagina,
        array $imagenesPorRuta,
        int $numPagina = 1,
        int $totalPaginas = 1,
        bool $incluirPie = false
    ): string {
        $ops = [];

        foreach ($elementosPagina as $el) {
            $tipo = $el['tipo'] ?? '';

            if ($tipo === 'text') {
                $fuente = (string) ($el['fuente'] ?? '/F1');
                if ($fuente === '' || $fuente[0] !== '/') {
                    $fuente = '/' . $fuente;
                }
                $tamano   = (float) ($el['tamano'] ?? 12.0);
                $x        = (float) ($el['x'] ?? 50.0);
                $yPdf     = isset($el['yPdf'])
                    ? (float) $el['yPdf']
                    : (842.0 - (float) ($el['yLogica'] ?? 62.0));
                $rgb      = $this->FormatearColor($el['color'] ?? [0.0, 0.0, 0.0]);
                $tw       = (float) ($el['tw'] ?? 0.0);
                $rawTexto = (string) ($el['texto'] ?? '');

                if ($this->TextoContieneSymbol($rawTexto)) {
                    $ops[] = $this->ConstruirOperacionTextoMixto($fuente, $tamano, $rgb, $tw, $x, $yPdf, $rawTexto);
                    continue;
                }

                $cadena = $this->CodificarCadenaPdf($rawTexto);

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

        // Pie de página editorial automático ("Página X de Y") en el margen inferior A4
        if ($incluirPie) {
            $ops[]    = 'q 0.820 0.855 0.902 RG 0.45 w 50.00 38.00 m 545.00 38.00 l S Q';
            $etiqueta = sprintf('Página %d de %d', $numPagina, $totalPaginas);
            $anchoEst = strlen($etiqueta) * 4.4;
            $xPie     = max(50.0, (595.0 - $anchoEst) / 2.0);
            $ops[]    = sprintf(
                'BT /F1 8.50 Tf 0.420 0.475 0.545 rg %.2f 25.00 Td (%s) Tj ET',
                $xPie,
                $this->CodificarCadenaPdf($etiqueta)
            );
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

    private function ContieneGlifosSymbol(): bool
    {
        foreach ($this->elementos as $el) {
            if (($el['tipo'] ?? '') === 'text' && $this->TextoContieneSymbol((string) ($el['texto'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    private function TextoContieneSymbol(string $texto): bool
    {
        if ($texto === '') {
            return false;
        }
        foreach (self::MAPA_SYMBOL as $utf8Char => $_byte) {
            if (str_contains($texto, $utf8Char)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Emite una secuencia continua dentro de `BT ... ET` alternando entre la fuente
     * base (`/F1`..`/F6`) y la fuente científica nativa `/F7` (`/Symbol`).
     */
    private function ConstruirOperacionTextoMixto(
        string $fuenteBase,
        float $tamano,
        string $rgb,
        float $tw,
        float $x,
        float $yPdf,
        string $texto
    ): string {
        $chars = mb_str_split($texto, 1, 'UTF-8');
        $runs  = [];

        foreach ($chars as $ch) {
            $esSym = isset(self::MAPA_SYMBOL[$ch]);
            $n     = count($runs);
            if ($n > 0 && $runs[$n - 1]['sym'] === $esSym) {
                $runs[$n - 1]['txt'] .= $ch;
            } else {
                $runs[] = ['sym' => $esSym, 'txt' => $ch];
            }
        }

        $partes = ['BT', $rgb . ' rg'];
        if (abs($tw) > 0.0005) {
            $partes[] = sprintf('%.3f Tw', $tw);
        }
        $partes[] = sprintf('%.2f %.2f Td', $x, $yPdf);

        foreach ($runs as $run) {
            if ($run['sym']) {
                $bytesSym = strtr($run['txt'], self::MAPA_SYMBOL);
                $escapado = strtr($bytesSym, [
                    '\\' => '\\\\',
                    '('  => '\\(',
                    ')'  => '\\)',
                    "\r" => '\\r',
                    "\n" => '\\n',
                ]);
                $partes[] = sprintf('/F7 %.2f Tf (%s) Tj', $tamano, $escapado);
            } else {
                $partes[] = sprintf('%s %.2f Tf (%s) Tj', $fuenteBase, $tamano, $this->CodificarCadenaPdf($run['txt']));
            }
        }

        if (abs($tw) > 0.0005) {
            $partes[] = '0 Tw';
        }
        $partes[] = 'ET';

        return implode(' ', $partes);
    }

    /**
     * Convierte una cadena UTF-8 a Windows-1252 (WinAnsiEncoding) y escapa \, ( y ).
     */
    private function CodificarCadenaPdf(string $texto): string
    {
        // Normalizar guiones/signos matemáticos básicos antes de convertir a CP1252
        $mapaPrevio = [
            '−' => '-', // signo menos matemático U+2212
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

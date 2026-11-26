<?php

declare(strict_types=1);

namespace Latinex\Infraestructura;

use PDO;

/**
 * Repositorio transaccional de documentos .ltx e imágenes adjuntas por usuario.
 */
class RepositorioDocumentos
{
    public const MAX_DOCUMENTOS_POR_USUARIO = 10;
    public const MAX_BYTES_DOCUMENTO      = 65536;
    public const MAX_IMAGENES_POR_DOCUMENTO = 5;
    public const MAX_BYTES_IMAGEN           = 2097152;

    private PDO $pdo;
    private string $storageBaseDir;

    public function __construct(PDO $pdo, ?string $storageBaseDir = null)
    {
        $this->pdo            = $pdo;
        $base                 = $storageBaseDir ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'proyectos');
        $this->storageBaseDir = rtrim($base, "/\\");
    }

    public function normalizarNombreArchivo(string $nombre, string $respaldo = 'documento'): string
    {
        $limpio = trim(basename($nombre));
        $limpio = preg_replace('/\.ltx$/i', '', $limpio) ?? '';
        $limpio = preg_replace('/[^a-zA-Z0-9._-]/', '_', $limpio) ?? '';
        $limpio = trim($limpio, '._-');
        if ($limpio === '') {
            $limpio = $respaldo;
        }
        $limpio = substr($limpio, 0, 150);
        return $limpio . '.ltx';
    }

    public static function contenidoInicialBienvenida(string $nombreUsuario): string
    {
        $autorSeguro = str_replace(['#', '{', '}', '\\', "\r", "\n"], ' ', trim($nombreUsuario));
        $autorSeguro = trim(preg_replace('/\s+/u', ' ', $autorSeguro) ?? '');
        if ($autorSeguro === '') {
            $autorSeguro = 'Autor LATINEX';
        }

        return "// Documento inicial creado automáticamente en LATINEX Cloud\n"
            . "#definir(curso) { Compiladores }\n"
            . "#titulo(color=#1A73E8) { Mi Primer Documento en LATINEX }\n"
            . "#autor { {$autorSeguro} }\n\n"
            . "#seccion(id=inicio) { Introducción }\n"
            . "#texto(justificado) {\n"
            . "    Bienvenido a tu espacio en la nube de #fuerte{LATINEX}. Puedes editar este archivo, compilar con las 4 fases del analizador y guardar hasta 10 documentos .ltx en el servidor.\n"
            . "}\n\n"
            . "#lista(punto) {\n"
            . "    {Sintaxis declarativa en español para #imprimir(curso)}\n"
            . "    {Compilación modular: Léxico, Sintáctico, Semántico y Ejecución}\n"
            . "    {Adjunta imágenes .jpg y tablas nativas directamente en el panel izquierdo}\n"
            . "}\n\n"
            . "#caja(tipo=info) { Consejo: presiona Ctrl+Enter para compilar y Ctrl+S para guardar tus cambios. }\n";
    }

    public function rutaAssetsDocumento(int $usuarioId, int $documentoId): string
    {
        return $this->storageBaseDir
            . DIRECTORY_SEPARATOR . $usuarioId
            . DIRECTORY_SEPARATOR . $documentoId
            . DIRECTORY_SEPARATOR;
    }

    /**
     * Lista documentos del usuario con su conteo de imágenes en una sola consulta SQL.
     */
    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.titulo, d.nombre_archivo, d.bytes_tamano, d.actualizado_en, d.creado_en,
                    COUNT(i.id)::int AS total_imagenes
             FROM documentos d
             LEFT JOIN imagenes_documento i ON i.documento_id = d.id
             WHERE d.usuario_id = :uid
             GROUP BY d.id
             ORDER BY d.actualizado_en DESC, d.id DESC'
        );
        $stmt->execute([':uid' => $usuarioId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id']             = (int) $row['id'];
            $row['bytes_tamano']   = (int) $row['bytes_tamano'];
            $row['total_imagenes'] = (int) $row['total_imagenes'];
        }

        return $rows;
    }

    public function contarPorUsuario(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*)::int FROM documentos WHERE usuario_id = :uid');
        $stmt->execute([':uid' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    public function obtenerDocumento(int $usuarioId, int $documentoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario_id, titulo, nombre_archivo, contenido_ltx, bytes_tamano, actualizado_en, creado_en
             FROM documentos
             WHERE id = :doc_id AND usuario_id = :uid'
        );
        $stmt->execute([':doc_id' => $documentoId, ':uid' => $usuarioId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            return null;
        }

        $doc['id']           = (int) $doc['id'];
        $doc['usuario_id']   = (int) $doc['usuario_id'];
        $doc['bytes_tamano'] = (int) $doc['bytes_tamano'];
        $doc['imagenes']     = $this->listarImagenesDocumento($documentoId);

        return $doc;
    }

    private function listarImagenesDocumento(int $documentoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre_archivo, ruta_relativa, bytes_tamano, creado_en
             FROM imagenes_documento
             WHERE documento_id = :doc_id
             ORDER BY id ASC'
        );
        $stmt->execute([':doc_id' => $documentoId]);
        $imagenes = $stmt->fetchAll();

        foreach ($imagenes as &$img) {
            $img['id']           = (int) $img['id'];
            $img['bytes_tamano'] = (int) $img['bytes_tamano'];
        }

        return $imagenes;
    }

    public function crearDocumento(
        int $usuarioId,
        string $titulo,
        string $nombreArchivo,
        string $contenidoLtx = '',
        bool $usarTransaccionExterna = false
    ): array {
        $tituloLimpio = trim($titulo);
        if ($tituloLimpio === '' || mb_strlen($tituloLimpio) > 160) {
            throw new ExcepcionDominio(
                'El título del documento es obligatorio y debe tener entre 1 y 160 caracteres.',
                422,
                'VALIDACION_TITULO'
            );
        }

        $bytes = strlen($contenidoLtx);
        if ($bytes > self::MAX_BYTES_DOCUMENTO) {
            throw new ExcepcionDominio(
                'El archivo .ltx excede el máximo permitido de 64 KB (65536 bytes).',
                422,
                'CONTENIDO_MUY_GRANDE'
            );
        }

        $archivoNormalizado = $this->normalizarNombreArchivo($nombreArchivo !== '' ? $nombreArchivo : $tituloLimpio);

        $iniciadaAqui = false;
        if (!$usarTransaccionExterna && !$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $iniciadaAqui = true;
        }

        try {
            $lockUser = $this->pdo->prepare('SELECT id FROM usuarios WHERE id = :uid FOR UPDATE');
            $lockUser->execute([':uid' => $usuarioId]);
            if (!$lockUser->fetchColumn()) {
                throw new ExcepcionDominio('Usuario no encontrado.', 404, 'USUARIO_NO_ENCONTRADO');
            }

            $totalActual = $this->contarPorUsuario($usuarioId);
            if ($totalActual >= self::MAX_DOCUMENTOS_POR_USUARIO) {
                throw new ExcepcionDominio(
                    'Límite del prototipo alcanzado: máximo 10 documentos .ltx por usuario.',
                    409,
                    'LIMITE_ARCHIVOS_ALCANZADO'
                );
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO documentos (usuario_id, titulo, nombre_archivo, contenido_ltx, bytes_tamano)
                 VALUES (:uid, :titulo, :archivo, :contenido, :bytes)
                 RETURNING id, usuario_id, titulo, nombre_archivo, contenido_ltx, bytes_tamano, actualizado_en, creado_en'
            );
            $insert->execute([
                ':uid'       => $usuarioId,
                ':titulo'    => $tituloLimpio,
                ':archivo'   => $archivoNormalizado,
                ':contenido' => $contenidoLtx,
                ':bytes'     => $bytes,
            ]);

            $creado = $insert->fetch();
            $creado['id']           = (int) $creado['id'];
            $creado['usuario_id']   = (int) $creado['usuario_id'];
            $creado['bytes_tamano'] = (int) $creado['bytes_tamano'];
            $creado['imagenes']     = [];

            if ($iniciadaAqui) {
                $this->pdo->commit();
            }

            return $creado;
        } catch (\Throwable $e) {
            if ($iniciadaAqui && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function actualizarDocumento(int $usuarioId, int $documentoId, array $campos): ?array
    {
        $actual = $this->obtenerDocumento($usuarioId, $documentoId);
        if ($actual === null) {
            return null;
        }

        $titulo = array_key_exists('titulo', $campos)
            ? trim((string) $campos['titulo'])
            : $actual['titulo'];

        if ($titulo === '' || mb_strlen($titulo) > 160) {
            throw new ExcepcionDominio(
                'El título del documento debe tener entre 1 y 160 caracteres.',
                422,
                'VALIDACION_TITULO'
            );
        }

        $nombreArchivo = array_key_exists('nombre_archivo', $campos)
            ? $this->normalizarNombreArchivo((string) $campos['nombre_archivo'], $titulo)
            : $actual['nombre_archivo'];

        $contenidoLtx = array_key_exists('contenido_ltx', $campos)
            ? (string) $campos['contenido_ltx']
            : $actual['contenido_ltx'];

        $bytes = strlen($contenidoLtx);
        if ($bytes > self::MAX_BYTES_DOCUMENTO) {
            throw new ExcepcionDominio(
                'El archivo .ltx excede el máximo permitido de 64 KB (65536 bytes).',
                422,
                'CONTENIDO_MUY_GRANDE'
            );
        }

        $stmt = $this->pdo->prepare(
            'UPDATE documentos
             SET titulo = :titulo,
                 nombre_archivo = :archivo,
                 contenido_ltx = :contenido,
                 bytes_tamano = :bytes,
                 actualizado_en = NOW()
             WHERE id = :doc_id AND usuario_id = :uid
             RETURNING id, usuario_id, titulo, nombre_archivo, contenido_ltx, bytes_tamano, actualizado_en, creado_en'
        );
        $stmt->execute([
            ':titulo'    => $titulo,
            ':archivo'   => $nombreArchivo,
            ':contenido' => $contenidoLtx,
            ':bytes'     => $bytes,
            ':doc_id'    => $documentoId,
            ':uid'       => $usuarioId,
        ]);

        $actualizado = $stmt->fetch();
        if (!$actualizado) {
            return null;
        }

        $actualizado['id']           = (int) $actualizado['id'];
        $actualizado['usuario_id']   = (int) $actualizado['usuario_id'];
        $actualizado['bytes_tamano'] = (int) $actualizado['bytes_tamano'];
        $actualizado['imagenes']     = $actual['imagenes'];

        return $actualizado;
    }

    public function eliminarDocumento(int $usuarioId, int $documentoId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM documentos WHERE id = :doc_id AND usuario_id = :uid RETURNING id'
        );
        $stmt->execute([':doc_id' => $documentoId, ':uid' => $usuarioId]);
        $eliminado = $stmt->fetchColumn();

        if (!$eliminado) {
            return false;
        }

        $dir = rtrim($this->rutaAssetsDocumento($usuarioId, $documentoId), "/\\");
        $this->eliminarDirectorioRecursivo($dir);
        return true;
    }

    public function subirImagen(int $usuarioId, int $documentoId, array $archivoSubido): array
    {
        $error = (int) ($archivoSubido['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ExcepcionDominio(
                'No se recibió correctamente el archivo de imagen.',
                400,
                'ARCHIVO_SUBIDA_ERROR'
            );
        }

        $tmpPath = (string) ($archivoSubido['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new ExcepcionDominio('Archivo temporal inexistente.', 400, 'ARCHIVO_INEXISTENTE');
        }

        $bytes = (int) ($archivoSubido['size'] ?? filesize($tmpPath));
        if ($bytes <= 0 || $bytes > self::MAX_BYTES_IMAGEN) {
            throw new ExcepcionDominio(
                'La imagen debe pesar entre 1 byte y 2 MB (2097152 bytes).',
                422,
                'IMAGEN_MUY_GRANDE'
            );
        }

        $nombreOriginal = basename((string) ($archivoSubido['name'] ?? ''));
        if (!preg_match('/\.(jpe?g)$/i', $nombreOriginal)) {
            throw new ExcepcionDominio(
                'Solo se admiten imágenes JPEG con extensión .jpg o .jpeg.',
                422,
                'EXTENSION_IMAGEN_INVALIDA'
            );
        }

        $infoImagen = @getimagesize($tmpPath);
        if (!is_array($infoImagen) || ($infoImagen[2] ?? 0) !== IMAGETYPE_JPEG) {
            throw new ExcepcionDominio(
                'El contenido del archivo no corresponde a un JPEG válido.',
                422,
                'BINARIO_IMAGEN_INVALIDO'
            );
        }

        $nombreLimpio = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal) ?? 'imagen.jpg';
        $nombreLimpio = ltrim($nombreLimpio, '._-');
        if ($nombreLimpio === '' || !preg_match('/\.(jpe?g)$/i', $nombreLimpio)) {
            $nombreLimpio = 'imagen_' . bin2hex(random_bytes(4)) . '.jpg';
        }
        $nombreLimpio = substr($nombreLimpio, 0, 150);

        $this->pdo->beginTransaction();
        try {
            $lockDoc = $this->pdo->prepare(
                'SELECT id FROM documentos WHERE id = :doc_id AND usuario_id = :uid FOR UPDATE'
            );
            $lockDoc->execute([':doc_id' => $documentoId, ':uid' => $usuarioId]);
            if (!$lockDoc->fetchColumn()) {
                throw new ExcepcionDominio('Documento no encontrado.', 404, 'DOCUMENTO_NO_ENCONTRADO');
            }

            $countStmt = $this->pdo->prepare('SELECT COUNT(*)::int FROM imagenes_documento WHERE documento_id = :doc_id');
            $countStmt->execute([':doc_id' => $documentoId]);
            if ((int) $countStmt->fetchColumn() >= self::MAX_IMAGENES_POR_DOCUMENTO) {
                throw new ExcepcionDominio(
                    'Límite alcanzado: máximo 5 imágenes JPEG por documento.',
                    409,
                    'LIMITE_IMAGENES_ALCANZADO'
                );
            }

            $dupStmt = $this->pdo->prepare(
                'SELECT id FROM imagenes_documento WHERE documento_id = :doc_id AND nombre_archivo = :nombre'
            );
            $dupStmt->execute([':doc_id' => $documentoId, ':nombre' => $nombreLimpio]);
            if ($dupStmt->fetchColumn()) {
                throw new ExcepcionDominio(
                    "Ya existe una imagen llamada '{$nombreLimpio}' en este documento.",
                    409,
                    'IMAGEN_DUPLICADA'
                );
            }

            $dirDestino = $this->rutaAssetsDocumento($usuarioId, $documentoId);
            if (!is_dir($dirDestino) && !mkdir($dirDestino, 0775, true) && !is_dir($dirDestino)) {
                throw new \RuntimeException('No se pudo crear el directorio de almacenamiento de imágenes.');
            }

            $rutaDestino = $dirDestino . $nombreLimpio;
            $movido = is_uploaded_file($tmpPath)
                ? move_uploaded_file($tmpPath, $rutaDestino)
                : copy($tmpPath, $rutaDestino);

            if (!$movido) {
                throw new \RuntimeException('No se pudo almacenar la imagen en disco.');
            }

            $rutaRelativa = $usuarioId . '/' . $documentoId . '/' . $nombreLimpio;
            $insert = $this->pdo->prepare(
                'INSERT INTO imagenes_documento (documento_id, nombre_archivo, ruta_relativa, bytes_tamano)
                 VALUES (:doc_id, :nombre, :ruta, :bytes)
                 RETURNING id, nombre_archivo, ruta_relativa, bytes_tamano, creado_en'
            );
            $insert->execute([
                ':doc_id' => $documentoId,
                ':nombre' => $nombreLimpio,
                ':ruta'   => $rutaRelativa,
                ':bytes'  => $bytes,
            ]);

            $creada = $insert->fetch();
            $creada['id']           = (int) $creada['id'];
            $creada['bytes_tamano'] = (int) $creada['bytes_tamano'];

            $this->pdo->commit();
            return $creada;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function eliminarImagen(int $usuarioId, int $documentoId, int $imagenId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM imagenes_documento i
             USING documentos d
             WHERE i.id = :img_id
               AND i.documento_id = :doc_id
               AND d.id = i.documento_id
               AND d.usuario_id = :uid
             RETURNING i.nombre_archivo'
        );
        $stmt->execute([
            ':img_id' => $imagenId,
            ':doc_id' => $documentoId,
            ':uid'    => $usuarioId,
        ]);

        $nombreArchivo = $stmt->fetchColumn();
        if ($nombreArchivo === false) {
            return false;
        }

        $ruta = $this->rutaAssetsDocumento($usuarioId, $documentoId) . basename((string) $nombreArchivo);
        if (is_file($ruta)) {
            @unlink($ruta);
        }

        return true;
    }

    private function eliminarDirectorioRecursivo(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $ruta = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($ruta)) {
                $this->eliminarDirectorioRecursivo($ruta);
            } else {
                @unlink($ruta);
            }
        }
        @rmdir($dir);
    }
}

-- Esquema inicial de LATINEX Cloud en PostgreSQL 17
-- Persiste usuarios registrados, tope de documentos .ltx e imágenes adjuntas.

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    correo VARCHAR(180) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS documentos (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    titulo VARCHAR(160) NOT NULL,
    nombre_archivo VARCHAR(160) NOT NULL,
    contenido_ltx TEXT NOT NULL DEFAULT '',
    bytes_tamano INT NOT NULL DEFAULT 0 CHECK (bytes_tamano >= 0 AND bytes_tamano <= 65536),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_documentos_usuario_actualizado
    ON documentos (usuario_id, actualizado_en DESC);

CREATE TABLE IF NOT EXISTS imagenes_documento (
    id SERIAL PRIMARY KEY,
    documento_id INT NOT NULL REFERENCES documentos(id) ON DELETE CASCADE,
    nombre_archivo VARCHAR(160) NOT NULL,
    ruta_relativa TEXT NOT NULL,
    bytes_tamano INT NOT NULL CHECK (bytes_tamano > 0 AND bytes_tamano <= 2097152),
    creado_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (documento_id, nombre_archivo)
);

CREATE INDEX IF NOT EXISTS idx_imagenes_documento_doc
    ON imagenes_documento (documento_id);

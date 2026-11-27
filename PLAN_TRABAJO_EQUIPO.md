# Plan de Trabajo del Equipo — Compilador LATINEX (Concurso 2026-II)

**Proyecto:** LATINEX — Prototipo de Compilador Nativo de Lenguaje de Marcado en Español a PDF (`%PDF-1.4`)  
**Curso:** Compiladores — Grupo A (EPIS, Universidad Nacional del Altiplano - Puno)  
**Docente Asesor:** Ing. Bejar Gonzales Victor Hugo  
**Fecha límite de exposición y premiación:** Viernes **27 de noviembre de 2026**  
**Repositorio oficial:** `https://github.com/JordanSoncco/latinex`  
**Carpeta pública de evidencias (Drive):** `https://drive.google.com/drive/folders/1lXjV6Lcgu83O3mMFjYIzdwGW5F5hc3DD`

---

## 1. Arquitectura General y Reglas de Oro (Patrón del Docente)

Siguiendo los manuales oficiales de estudio ([graficador.php](../graficador.php), `1. Manual Analizador Semantico Graficador.docx`, `2. Manual Ejecucion Graficador.docx`) y el IDE de referencia (`https://xurainc.com/graficador/`), **LATINEX Cloud** opera en **Docker Compose** (`php:8.5-fpm-alpine` + `postgres:17-alpine` + `caddy:2.9-alpine`) listo para desplegar en **AWS EC2 (`t4g.small` ARM64 + Elastic IP en `us-east-2`)**. El núcleo compilador sigue dividido en **4 fases secuenciales independientes** bajo el namespace `Latinex`:

```text
Código fuente (.ltx / UTF-8)
  ├── [1] Léxico     → TablaLexica + Token + Tokenizador::getToken()
  ├── [2] Sintáctico → AnalizadorSintactico (Autómata 22 estados 0–21 + pilaBloques)
  ├── [3] Semántico  → TablaSimbolos + AnalizadorSemantico (Dos pasadas internas: etiquetas + reglas)
  └── [4] Ejecución  → Ejecutor (Pasada 1: Layout geométrico A4) → PdfGenerator (Pasada 2: %PDF-1.4 nativo)

Capa Plataforma Cloud (Overleaf-style en Docker + AWS EC2)
  ├── public/auth.php + ServicioAutenticacion    → Registro / Login Argon2id + CSRF + bienvenida.ltx
  ├── public/projects.php + RepositorioDocumentos → CRUD atómico PostgreSQL 17 (máx. 10 .ltx y 5 .jpg/doc)
  └── public/index.html + styles.css + app.js    → Sidebar Overleaf + Inserción rápida + Visor + Cajón 4 fases
```

> [!IMPORTANT]
> Cada fase recibe **su propia instancia** de `new Tokenizador($codigo, $tablaLexica)` porque `getToken()` avanza un cursor interno secuencial, y solo se ejecuta si la fase anterior devolvió `ACEPTADO` (sin errores).

---

## 2. Reparto de Trabajo en 3 Frentes (Cero Conflictos de Merge)

Para trabajar en paralelo hasta el 27 de noviembre sin pisarse archivos, cada integrante es propietario exclusivo de una rama y un conjunto acotado de archivos:

| Frente y Rama Git | Responsable | Archivos Asignados (Propiedad Exclusiva) | Responsabilidad Técnica |
| :--- | :--- | :--- | :--- |
| **Frente 1 + Nube AWS/Docker — Compilador Frontend, Auth y 10 Documentos**<br>`feat/frente1-sintactico-semantico` | **Soncco Machaca Jordan Oliber** *(Líder)* | `Dockerfile`, `docker-compose.yml`, `docker/`, `deploy/`<br>`src/Infraestructura/*`, `public/auth.php`, `public/projects.php`, `api.php`<br>`src/TablaLexica.php`, `src/Tokenizador.php`, `src/AnalizadorSintactico.php`, `src/TablaSimbolos.php`, `src/AnalizadorSemantico.php`, `test_frente1.php`, `test_auth_proyectos.php` | Entorno Docker (`PHP 8.5` + `PostgreSQL 17` + `Caddy 2`), despliegue AWS EC2 (`t4g.small` + Elastic IP en `us-east-2`), Auth Argon2id/CSRF, límite de 10 `.ltx`, análisis léxico/sintáctico/semántico (`1500`–`1707`) y base IDE Overleaf. |
| **Frente 2 — Backend Two-Pass y Generador `%PDF-1.4` Nativo**<br>`feat/frente2-ejecutor-pdf` | **Tito Monteagudo William Dominic** | `src/Ejecutor.php`<br>`src/PdfGenerator.php`<br>`test_pdf.php` | Implementar Pasada 1 en `Ejecutor` (IR geométrico, `StyleStack`, métricas AFM, paginación A4 `595×842 pt`, macros, índice, referencias) y Pasada 2 en `PdfGenerator` (`%PDF-1.4` nativo sin FPDF/TCPDF sobre PHP 8.5). |
| **Frente 3 — IDE Overleaf (4 Pestañas), Casos 01–29 y QA**<br>`feat/frente3-ide-qa` | **Mendoza Coila Jamiel Romario** | `public/index.html`, `public/styles.css`, `public/app.js`<br>`test_e2e.php`<br>*Evidencias Drive + Diapositivas* | Pulir UX estilo Overleaf (sidebar de 10 `.ltx`, inserción rápida visual, visor PDF e inspector plegable de 4 pestañas), validar los 29 casos + flujos de usuario registrados/invitado en E2E y subir evidencias a Drive. |

> [!NOTE]
> **Nota técnica crítica para William (Frente 2) sobre espacios tras `}` en `Tokenizador`:**  
> Actualmente `Tokenizador::OmitirEspacios()` consume los espacios en blanco inmediatamente posteriores a una llave de cierre `}` en expresiones inline (por ejemplo, `m#superindice{2} y` emite `CLLAVE('}')` seguido de `TEXTO_PLANO('y')` sin el espacio inicial). Para no romper la suite léxica ni sintáctica (`test_lexico.php` y `test_frente1.php`), al construir `Ejecutor.php` (Frente 2) puedes:
> 1. Añadir en `Tokenizador` un flag opcional `bool $preservarEspaciosContenido = false` en `__construct(...)` que, cuando `nivelLlaves > 0` y `$preservarEspaciosContenido === true`, evite invocar `OmitirEspacios()` antes de `LeerTokenContenido()`, **o**
> 2. Usar las columnas `Token::$linea` y `Token::$columna` junto con `mb_strlen` del lexema anterior para reconstruir el espaciado exacto entre tokens contiguos en modo contenido sin tocar `Tokenizador.php`.

---

## 3. Contratos Congelados entre Módulos

### 3.1 Contratos del Frente 1 (Ya disponibles para William y Jamiel)

- **`AnalizadorSintactico`** (`src/AnalizadorSintactico.php`):
  - `__construct(Tokenizador $tokenizador, bool $seguimientoActivo = true)`
  - `Analizar(): bool`
  - `getEstadoActual(): int`
  - `getSeguimiento(): array` — arreglo de pasos `{paso, estadoActual, token, nombreToken, lexema, linea, columna, estadoSiguiente}`.
  - `getError(): ?array` — `{tipo, linea, columna, estado, token, nombreToken, lexema, esperados, descripcion, mensaje}`.
  - `getTransiciones(): array`
  - `AnalizadorSintactico::NombreToken(int $token): string`

- **`TablaSimbolos`** (`src/TablaSimbolos.php`):
  - `Insertar(string $lexema, mixed $valor = null, string $estado = 'no_asignado', string $tipo = 'MACRO', int $token = T_ID, ?int $linea = null, ?int $columna = null): bool`
  - `Buscar(string $lexema, ?string $tipo = null): ?array`
  - `ActualizarEstado(string $lexema, string $estado, ?string $tipo = null): bool`
  - `ActualizarValor(string $lexema, mixed $valor, ?string $tipo = null): bool`
  - `ObtenerTodos(): array` — retorna registros `{lexema, token, tipo, valor, estado, linea, columna}` listos para la tabla de 5 columnas del IDE.
  - `ObtenerPorTipo(string $tipo): array` (`'MACRO'`, `'ETIQUETA'`, `'METADATO'`)

- **`AnalizadorSemantico`** (`src/AnalizadorSemantico.php`):
  - `__construct(Tokenizador $tokenizador, ?TablaSimbolos $tablaSimbolos = null)`
  - `Analizar(): bool`
  - `getError(): ?array` — `{tipo: 'semantico', codigo, token, nombreToken, lexema, linea, columna, descripcion, mensaje}`
  - `getTablaSimbolos(): TablaSimbolos`
  - `getAdvertencias(): array`

#### Tabla Oficial de Códigos de Error Semánticos

| Código | Causa Exacta | Ejemplo que lo dispara |
| :---: | :--- | :--- |
| **`1500`** | Macro invocada en `#imprimir(id)` no registrada en `TablaSimbolos`. | `#texto { #imprimir(curso) }` sin `#definir(curso)` previo |
| **`1600`** | Macro registrada (`#definir(id) { }`) pero vacía (`estado = 'no_asignado'`). | `#definir(curso) { } #texto { #imprimir(curso) }` |
| **`1601`** | Recursión cíclica directa o mutua entre macros (`#definir` ↔ `#imprimir`). | `#definir(a){1} #definir(a){#imprimir(a)}` |
| **`1501`** | `#referencia(id)` apunta a una etiqueta de sección inexistente. | `#texto { Ver #referencia(sec_faltante) }` |
| **`1502`** | Etiqueta `id` duplicada en dos `#seccion` / `#subseccion*`. | `#seccion(id=s1){A} #seccion(id=s1){B}` |
| **`1700`** | `#imagen` sin `ruta`, extensión distinta a `.jpg`/`.jpeg`, o `ancho`/`alto` inválido. | `#imagen(ancho=50%)` o `#imagen(ruta=foto.png)` |
| **`1701`** | `#lista` con tipo inválido (solo `punto` / `numero`) o sin bloques `{ }`. | `#lista(viñeta) { {A} }` o `#lista(punto) { }` |
| **`1702`** | `#fraccion` sin 2 bloques `{num} {den}` ni par `numerador` + `denominador`. | `#fraccion { {x+1} }` o `#fraccion(numerador=x)` |
| **`1703`** | `#caja` con `tipo` inválido (solo `alerta`, `info`, `éxito`/`exito`). | `#caja(tipo=peligro) { Hola }` |
| **`1704`** | `#tabla` con `bordes` no booleano (solo `verdadero` / `falso`). | `#tabla(bordes=si) { #fila { #celda { A } } }` |
| **`1705`** | Atributo `color` o `fondo` con valor no reconocido (no `#RGB`/`#RRGGBB` ni color reservado). | `#texto(color=morado) { Hola }` |
| **`1706`** | Atributo `alineacion` inválido (solo `izquierda`, `centro`/`centrado`, `derecha`, `justificado`). | `#texto(alineacion=oblicuo) { Hola }` |
| **`1707`** | Atributo `tamaño`/`tamano` con valor no numérico o `<= 0`. | `#texto(tamaño=0) { Hola }` |

### 3.2 Contratos del Frente 2 (William — `Ejecutor.php` y `PdfGenerator.php`)

- **`Ejecutor`** (`src/Ejecutor.php`):
  - `__construct(Tokenizador $tokenizador, ?TablaSimbolos $tablaSimbolos = null, string $assetsDir = '')`
  - `Ejecutar(): bool`
  - `getElementos(): array` — arreglo ordenado de instrucciones geométricas resueltas (páginas A4 `595×842 pt`, bloques de texto posicionados con `X`, `Y_pdf`, fuente `/F1`..`/F5`, colores RGB `0..1`, `Tw`, primitivas vectoriales `re`/`m`/`l`/`S`/`f`, XObjects JPEG, índice general y referencias resueltas).
  - `getTablaSimbolos(): TablaSimbolos` — tabla final resuelta en ejecución (incluyendo páginas reales de `#seccion(id=...)` para `#referencia` e `#indicegeneral`).
  - `getError(): ?array`
  - `getResumen(): array` — `{implementado: bool, estado: string, totalPaginas: int, totalElementos: int, metadatos: array}`

- **`PdfGenerator`** (`src/PdfGenerator.php`):
  - `__construct(array $elementos = [], array $metadatos = [], string $assetsDir = '')`
  - `isImplemented(): bool` — cambiar a `true` cuando el emisor `%PDF-1.4` esté listo.
  - `generate(): string` — retorna el binario `%PDF-1.4` completo (objetos `/Catalog`, `/Pages`, `/Page`, `/Font`, `/Info`, `/XObject /DCTDecode`, `xref` y `trailer`).
  - `getStats(): array` — `{implementado: bool, paginas: int, bytes: int, objetosPdf: int}`

### 3.3 Contrato JSON de `POST /api.php` (Jamiel — Frente 3)

`api.php` conserva las claves legacy (`status`, `sessionId`, `message`, `totalTokens`, `errores`, `tokens`) y expone además la estructura completa para el IDE de 4 pestañas:

```json
{
  "status": "success",
  "estado": "exito",
  "sessionId": "abc12345",
  "message": "Análisis Léxico, Sintáctico y Semántico ACEPTADOS (...",
  "totalTokens": 26,
  "errores": [],
  "tokens": [],
  "lexico": {
    "valido": true,
    "totalTokens": 26,
    "errores": [],
    "tokens": []
  },
  "sintactico": {
    "valido": true,
    "estado": "ACEPTADO",
    "estadoFinal": 21,
    "transiciones": [],
    "error": null
  },
  "semantico": {
    "valido": true,
    "estado": "ACEPTADO",
    "tablaSimbolos": [],
    "advertencias": [],
    "error": null
  },
  "ejecucion": {
    "valido": true,
    "implementado": false,
    "estado": "PENDIENTE_FRENTE_2",
    "figurasOElementos": [],
    "resumen": {},
    "pdfBase64": null,
    "error": null
  }
}
```

---

## 4. Checklist de los 29 Casos de Prueba Oficiales

| # | Caso de Prueba | Léxico | Sintáctico | Semántico | PDF Nativo (William) |
| :-: | :--- | :---: | :---: | :---: | :---: |
| 01 | Estructura de texto (`#texto`) | ✅ | ✅ | ✅ | ✅ |
| 02 | Formato negrita y cursiva (`#fuerte`, `#cursiva`) | ✅ | ✅ | ✅ | ✅ |
| 03 | Metadatos (`#titulo`, `#autor`) | ✅ | ✅ | ✅ | ✅ |
| 04 | Jerarquía estructural (`#seccion`..`#subseccion4`) | ✅ | ✅ | ✅ | ✅ |
| 05 | Salto de línea explícito (`#salto`) | ✅ | ✅ | ✅ | ✅ |
| 06 | Salto de página explícito (`#nuevapagina`) | ✅ | ✅ | ✅ | ✅ |
| 07 | Comentarios de línea (`//`) | ✅ | ✅ | ✅ | ✅ |
| 08 | Escape de caracteres (`\#`, `\{`, `\}`) | ✅ | ✅ | ✅ | ✅ |
| 09 | Alineación simple (`derecha`) | ✅ | ✅ | ✅ | ✅ |
| 10 | Alineación justificada (`justificado`) | ✅ | ✅ | ✅ | ✅ |
| 11 | Lista no ordenada con bloques anónimos (`#lista(punto)`) | ✅ | ✅ | ✅ | ✅ |
| 12 | Lista ordenada con bloques anónimos (`#lista(numero)`) | ✅ | ✅ | ✅ | ✅ |
| 13 | Uso de color `#RRGGBB` y `#resaltado` | ✅ | ✅ | ✅ | ✅ |
| 14 | Declaración de macros (`#definir`) | ✅ | ✅ | ✅ | ✅ |
| 15 | Invocación de macros (`#imprimir`) | ✅ | ✅ | ✅ | ✅ |
| 16 | Inserción de imágenes JPEG (`#imagen`) | ✅ | ✅ | ✅ | ✅ |
| 17 | Matemáticas en línea (`#superindice`, `#subindice`) | ✅ | ✅ | ✅ | ✅ |
| 18 | Entorno matemático (`#fraccion` con `{ }` y con atributos) | ✅ | ✅ | ✅ | ✅ |
| 19 | Atributos combinados complejos (`fuente`, `color`, `centrado`) | ✅ | ✅ | ✅ | ✅ |
| 20 | Salto de página automático (`Y_logica > 792 pt`) | ✅ | ✅ | ✅ | ✅ |
| 21 | Estructura base de tablas (`#tabla`, `#fila`, `#celda`) | ✅ | ✅ | ✅ | ✅ |
| 22 | Tablas completas (`bordes=verdadero`, `fondo=gris`) | ✅ | ✅ | ✅ | ✅ |
| 23 | Referencias cruzadas (`#seccion(id=...)` + `#referencia`) | ✅ | ✅ | ✅ | ✅ |
| 24 | Índice automático (`#indicegeneral`) | ✅ | ✅ | ✅ | ✅ |
| 25 | Anidamiento profundo 1 (`#lista` dentro de `#celda`) | ✅ | ✅ | ✅ | ✅ |
| 26 | Anidamiento profundo 2 (`StyleStack` colores y negrita) | ✅ | ✅ | ✅ | ✅ |
| 27 | Modo ecuación en bloque (`#ecuacion` numerada) | ✅ | ✅ | ✅ | ✅ |
| 28 | Cajas de advertencia con vectores PDF (`#caja`) | ✅ | ✅ | ✅ | ✅ |
| 29 | Prueba de estrés (documento académico integral) | ✅ | ✅ | ✅ | ✅ |
| 30 | Notación griega y operadores científicos (`/Symbol` `/F7`) | ✅ | ✅ | ✅ | ✅ |

---

## 5. Cronograma de Ejecución y Flujo Git (Mar 24 → Vie 27 Nov)

### Flujo de Ramas Git
```bash
# 1. Jordan sube Frente 1 + contratos congelados a main
git checkout -b feat/frente1-sintactico-semantico
git add -A && git commit -m "feat(frente1): integrar sintactico, tabla de simbolos, semantico y contratos"
git checkout main && git merge feat/frente1-sintactico-semantico && git push origin main

# 2. William y Jamiel crean sus ramas DESDE main actualizado
git checkout main && git pull origin main
git checkout -b feat/frente2-ejecutor-pdf   # Rama de William
git checkout -b feat/frente3-ide-qa         # Rama de Jamiel
```

### Cronograma Diario

| Día | Jordan (Frente 1 + Líder) | William (Frente 2 — Backend PDF) | Jamiel (Frente 3 — IDE Web & QA) |
| :--- | :--- | :--- | :--- |
| **Lun 23 noche / Mar 24** | Subir Frente 1 verificado (`29/29` casos + pruebas negativas) a `main`. | Crear rama `feat/frente2-ejecutor-pdf`. Implementar Pasada 1 básica en `Ejecutor.php` (`#titulo`, `#autor`, `#seccion`, `#texto`, `StyleStack`, métricas AFM, paginación A4) y núcleo `%PDF-1.4` en `PdfGenerator.php` (Casos 01–10). | Crear rama `feat/frente3-ide-qa`. Migrar `public/index.html` y `public/app.js` al IDE oscuro de 4 pestañas (CodeMirror, visor PDF + botón Descargar, selector de los 29 casos, tablas Léxico/Sintáctico/Semántico/Ejecución). |
| **Mié 25** | Apoyar revisión de casos complejos (`#fraccion`, `#ecuacion`, `#indicegeneral`, *forward references*). | Completar Casos 11–29: listas, colores/resaltado, macros, JPEG (`/DCTDecode`), tablas/celdas recursivas, fracciones/ecuaciones, `#caja` vectorial, `#indicegeneral` y `#referencia` Two-Pass (`test_pdf.php`). | Conectar `Ejecutor` + `PdfGenerator` (`pdfBase64` / visor Blob) en `api.php` y `app.js`; armar `test_e2e.php` con pruebas positivas y de error en el endpoint HTTP. |
| **Jue 26** | Integrar ramas `frente2` y `frente3` en `main` y ejecutar verificación total. | Validar los 29 PDFs generados en un visor real (Chrome/Edge/Adobe) y corregir métricas/alineaciones. | Subir los PDFs generados, capturas del IDE y diapositivas a la carpeta Drive del concurso; preparar la presentación. |
| **Vie 27** | **Exposición oficial ante el jurado.** Liderar demo de arquitectura y compilación en vivo. | Exponer generación binaria `%PDF-1.4` nativa y motor geométrico Two-Pass. | Operar el IDE Web en vivo, mostrar los 29 casos/errores y exponer métricas finales. |

---

## 6. Guion Breve de Exposición (Alineado a Rúbrica Anexo 2)

1. **Jordan (2–3 min — Criterios 1, 2 y 5):** Problema (LaTeX vs. editores WYSIWYG), por qué LATINEX usa gramática en español sin librerías PDF externas, arquitectura 4 fases / 2 pasadas, Tokenizador multimodo (78 tokens), Autómata Sintáctico (22 estados + `pilaBloques`), `TablaSimbolos` y detección semántica (`1500`–`1707`), además de la proyección futura a **Rust**.
2. **William (2–3 min — Criterios 1 y 2):** Cómo Pasada 1 (`Ejecutor`) calcula métricas AFM, paginación A4 y resuelve *forward references* / `#indicegeneral`, y cómo Pasada 2 (`PdfGenerator`) emite objetos `%PDF-1.4`, operadores PostScript (`BT`, `Tf`, `Td`, `Tj`, `Tw`), vectores `#caja`, imágenes `/DCTDecode` y la tabla `xref`.
3. **Jamiel (2–3 min — Criterios 3 y 4):** Demostración en vivo en el **IDE Web** compilando un documento completo (Caso 29), inspeccionando las 4 pestañas (*Léxico*, *Sintáctico*, *Semántico*, *Ejecución*) + descarga del PDF nativo, y demostrando la captura clara de un error sintáctico o semántico (`1500`/`1501`).

# Compilador LATINEX — Lenguaje de Marcado en Español a PDF

Proyecto académico del curso de **Compiladores**  
**Universidad Nacional del Altiplano - Puno (UNAP)**  
Docente: **Ing. Bejar Gonzales Victor Hugo**  
Grupo: **Grupo A - Grupo 3**

---

## 📌 Descripción General

**LATINEX** es un lenguaje de marcado en español diseñado para compilar documentos estructurados de alta calidad directamente a PDF. Utiliza una sintaxis limpia y declarativa basada en comandos `#etiqueta(atributos) { contenido }`.

El proyecto cuenta con:
1. **Analizador Léxico Multimodo**: capaz de distinguir automáticamente entre modo normal, modo atributos (`()`), y modo contenido (`{}`).
2. **Tabla Léxica Oficial**: con 64 tokens categorizados (símbolos, metadatos, formato inline, tablas, matemáticas, macros, atributos y valores).
3. **API REST en PHP**: que permite analizar código fuente, devolver diagnósticos léxicos detallados y gestionar assets/imágenes de sesión.
4. **Interfaz Web Interactiva**: con editor de código, carga de imágenes, selector de 25 casos de prueba oficiales y visor en tiempo real de tokens o de la tabla léxica completa.
5. **Suite de Pruebas Automatizada**: para verificar la ausencia total de errores léxicos en toda la gramática.

---

## 🏗️ Estructura del Proyecto

```text
LATINEX/
├── Dockerfile               # PHP 8.5-FPM Alpine con PDO PostgreSQL 17 y OPcache nativo
├── docker-compose.yml       # Stack web (Caddy 2.9) + app (PHP 8.5) + db (PostgreSQL 17)
├── api.php                  # Endpoint REST del pipeline completo (soporta Modo Invitado y documentoId)
├── test_lexico.php          # Suite CLI de 25 casos léxicos
├── test_frente1.php         # Suite integral Frente 1 (31 válidos + 20 negativos 1500–1707)
├── test_auth_proyectos.php  # Suite HTTP Auth + límite de 10 documentos + compilación
├── deploy/                  # Scripts IaC para AWS EC2 t4g.small + Elastic IP + SSM (us-east-2)
├── docker/                  # Caddyfile + esquema init.sql de PostgreSQL 17
├── public/
│   ├── index.html           # IDE web estilo Overleaf con cajón plegable de 4 fases
│   ├── styles.css           # Sistema visual oscuro moderno + diálogos modales nativos
│   ├── app.js               # Vue 3: proyectos (10 .ltx), imágenes .jpg, visor e inspector
│   ├── auth.php             # Registro / Login / Logout con Argon2id y CSRF
│   ├── projects.php         # CRUD de hasta 10 documentos .ltx e imágenes por usuario
│   └── api.php              # Wrapper FastCGI hacia ../api.php
└── src/
    ├── Infraestructura/     # ConexionBD, ServicioAutenticacion, RepositorioDocumentos, ControladorHttp
    ├── TablaLexica.php      # 78 definiciones léxicas oficiales
    ├── Tokenizador.php      # Scanner/Lexer UTF-8 multimodo
    ├── AnalizadorSintactico.php # Autómata con pila de 22 estados (0–21)
    ├── TablaSimbolos.php    # Tabla de símbolos con ámbitos (MACRO, ETIQUETA, METADATO)
    ├── AnalizadorSemantico.php  # Validación semántica de 2 pasadas internas (1500–1707)
    ├── Ejecutor.php         # Contrato Pasada 1 (Frente 2)
    └── PdfGenerator.php     # Contrato Emisor %PDF-1.4 nativo (Frente 2)
```

---

## 🏷️ Clasificación de Tokens (64 Tokens Registrados)

| Rango / Código | Categoría | Descripción y Ejemplos |
|---|---|---|
| **0 – 9** | `simbolo` / `operador` | Símbolos de delimitación: `#`, `(`, `)`, `{`, `}`, `=`, `,`, `.`, `\`, `%` |
| **10 – 19** | `literal` / `especial` | Identificadores, números, cadenas `"..."`, texto plano, comentarios `//`, `#RRGGBB`, escapes `\#` |
| **20 – 29** | `comando_estructura` | Metadatos y estructura: `#titulo`, `#autor`, `#fecha`, `#seccion`, `#subseccion`, `#subseccion2`, `#texto`, `#resumen`, `#indicegeneral` |
| **30 – 39** | `comando_formato` | Formato inline: `#fuerte` (negrita), `#cursiva`, `#subrayado`, `#resaltado` |
| **40 – 49** | `comando_datos` | Listas y multimedia: `#lista`, `#imagen` |
| **50 – 59** | `comando_tabla` | Tablas de datos: `#tabla`, `#fila`, `#celda` |
| **60 – 69** | `comando_matematica` | Matemáticas y operadores: `#superindice`, `#subindice`, `#fraccion`, `#ecuacion`, `+`, `-`, `*`, `/` |
| **70 – 79** | `comando_control` / `macro` | Salto de línea `#salto`, salto de página `#nuevapagina`, macros `#definir`, `#imprimir`, `#referencia` |
| **80 – 89** | `comando_entorno` | Cajas de alerta y entornos: `#caja` |
| **90 – 109** | `atributo` | Atributos: `color`, `tamaño`, `fuente_attr`, `alineacion`, `ruta`, `ancho`, `alto`, `bordes`, `fondo`, `tipo`, `numerador`, `denominador`, `id` |
| **110 – 129** | `valor_atributo` | Valores reservados: `punto`, `numero`, `derecha`, `centro`, `justificado`, `izquierda`, `verdadero`, `falso`, `auto`, `gris`, `alerta` |
| **666 / 999** | `fin` / `error` | `T_FIN` (fin de archivo) y `T_ERROR` (error léxico) |

---

## 🚀 Cómo Ejecutar (Entorno Modernizado en Docker — PHP 8.5 + PostgreSQL 17 + Caddy 2)

### 1. Levantar toda la plataforma tipo Overleaf
Desde la carpeta `LATINEX`:
```bash
cp .env.example .env
docker compose up -d --build
```
Luego abre tu navegador en `http://localhost:8080/` (o en la Elastic IP pública asignada en AWS EC2).

### 2. Ejecutar Suites de Pruebas dentro de PHP 8.5
```bash
docker compose exec app php -v
docker compose exec app php test_lexico.php
docker compose exec app php test_frente1.php
docker compose exec app php test_auth_proyectos.php
```

Desde la interfaz Overleaf podrás:
- Trabajar al instante en **Modo Invitado** o registrarte para guardar y continuar editando hasta **10 documentos `.ltx`** con sus imágenes `.jpg`.
- Insertar comandos (`#titulo`, `#seccion`, `#lista`, `#tabla`, `#fraccion`, `#ecuacion`, `#caja`) con un clic desde la barra superior.
- Cargar cualquiera de los **29 casos oficiales** y ejemplos de error sintáctico/semántico.
- Desplegar el cajón inferior **Inspector del Compilador (4 Fases)** para auditar Léxico, Sintáctico (22 estados), Semántico (`1500`–`1707`) y Ejecución.

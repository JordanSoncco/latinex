const { createApp, ref, reactive, computed, onMounted, onUnmounted, watch, nextTick, provide, inject } = Vue;

const STORAGE_GUEST_DRAFT = 'latinex_guest_draft_v1';
const STORAGE_AUTO_COMPILE = 'latinex_auto_compile_v1';
const STORAGE_LAYOUT_PREFS = 'latinex_layout_prefs_v1';
const STORAGE_THEME = 'latinex_theme_v1';
const STORAGE_EXPLORER = 'latinex_explorer_open_v2';

function aplicarTemaEnDocumento(modo) {
  const limpio = modo === 'dark' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', limpio);
  const meta = document.querySelector('meta[name="color-scheme"]');
  if (meta) meta.setAttribute('content', limpio === 'dark' ? 'dark' : 'light');
  return limpio;
}

const DEFAULT_LAYOUT_PREFS = {
  insertores: 'menu',      // 'menu' | 'editor' | 'preview' | 'superior'
  casos: 'menu',           // 'menu' | 'editor' | 'preview'
  analizadores: 'ventana', // 'ventana' | 'editor' | 'preview' | 'inferior'
  archivos: 'menu',        // 'menu' | 'izquierda'
  acciones: 'menu'         // 'menu' | 'editor' | 'preview'
};

function cargarPreferenciasLayout() {
  try {
    const raw = localStorage.getItem(STORAGE_LAYOUT_PREFS);
    if (!raw) return { ...DEFAULT_LAYOUT_PREFS };
    const parsed = JSON.parse(raw);
    return { ...DEFAULT_LAYOUT_PREFS, ...(parsed && typeof parsed === 'object' ? parsed : {}) };
  } catch {
    return { ...DEFAULT_LAYOUT_PREFS };
  }
}

const DEFAULT_GUEST_CODE = `// Un pequeño comienzo para grandes ideas.
// Escribe con claridad. Crea sin límites.

#definir(curso) { Compiladores — EPIS UNAP }
#titulo(color=#1E191A) { La belleza de lo simple }
#autor { Equipo Grupo 3 }

#seccion(id=intro) { Introducción }
#texto(justificado) {
    Las grandes ideas merecen un espacio donde crecer. Latinex es un lenguaje de marcado en español que combina la expresividad del código con la belleza de un documento bien diseñado en #fuerte{%PDF-1.4}.
}

#subseccion { Una nueva forma de escribir }
#lista(punto) {
    {Curso académico: #imprimir(curso)}
    {Referencias cruzadas en dos pasadas hacia #referencia(intro)}
    {Símbolos científicos nativos: π, α, β, ∑, ∫, √, ∞}
}

#subseccion { Las matemáticas, en su elemento }
#ecuacion {
    e#superindice{iπ} + 1 = 0   ⇒   ∑ #fraccion { {1} {n#superindice{2}} } = #fraccion { {π#superindice{2}} {6} }
}

#caja(tipo=info) { La simplicidad es la máxima sofisticación. }`;

const CASOS_OFICIALES = [
  { key: 'plantilla_folio', label: 'Plantilla Editorial: La belleza de lo simple', code: DEFAULT_GUEST_CODE },
  { key: 'caso01', label: 'Caso 01: Estructura de texto', code: `#texto {\n    Hola Mundo\n}` },
  { key: 'caso02', label: 'Caso 02: Negrita y Cursiva', code: `#texto {\n    Los Compañeritos #fuerte{Jordan} y #cursiva{Dominic}.\n}` },
  { key: 'caso03', label: 'Caso 03: Metadatos (#titulo, #autor)', code: `#titulo { Tesis }\n#autor { Grupo 3 }` },
  { key: 'caso04', label: 'Caso 04: Jerarquía de secciones', code: `#seccion { Marco Teórico }\n#subseccion { Antecedentes }\n#subseccion2 { Investigaciones Previas }` },
  { key: 'caso05', label: 'Caso 05: Salto de línea (#salto)', code: `#texto { Línea 1 } #salto #texto { Línea 2 }` },
  { key: 'caso06', label: 'Caso 06: Salto de página (#nuevapagina)', code: `#texto { Página 1 } #nuevapagina #texto { Página 2 }` },
  { key: 'caso07', label: 'Caso 07: Comentarios (//)', code: `// Este texto no debe aparecer en el PDF.\n#texto { Solo esto se compila. }` },
  { key: 'caso08', label: 'Caso 08: Escape de caracteres (\\#)', code: `#texto {\n    Usamos el símbolo del numeral así: \\#\n}` },
  { key: 'caso09', label: 'Caso 09: Alineación derecha', code: `#texto(derecha) {\n    Azángaro, 2026\n}` },
  { key: 'caso10', label: 'Caso 10: Alineación justificada', code: `#texto(justificado) {\n    Párrafo con texto largo que debe tocar obligatoriamente ambos márgenes del documento para mostrar la justificación de texto PDF.\n}` },
  { key: 'caso11', label: 'Caso 11: Lista no ordenada (punto)', code: `#lista(punto) { {Elemento A} {Elemento B} }` },
  { key: 'caso12', label: 'Caso 12: Lista ordenada (numero)', code: `#lista(numero) { {Inicio} {Fin} }` },
  { key: 'caso13', label: 'Caso 13: Color #HEX y #resaltado', code: `#texto(color=#FF0000) { Alerta } #salto\n#resaltado(color=#FFFF00) { Dato } #salto\n#texto(color=#1A73E8) { Texto azul institucional }` },
  { key: 'caso14', label: 'Caso 14: Declaración #definir', code: `#definir(curso) { Compiladores }` },
  { key: 'caso15', label: 'Caso 15: Invocación #imprimir', code: `#definir(curso) { Compiladores }\n#texto { Proyecto del curso de #imprimir(curso). }` },
  { key: 'caso16', label: 'Caso 16: Imagen JPEG (#imagen)', code: `#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)` },
  { key: 'caso17', label: 'Caso 17: Superíndice y Subíndice', code: `#texto { El área es m#superindice{2} y el agua es H#subindice{2}O }` },
  { key: 'caso18', label: 'Caso 18: Fracción bloques { }{ }', code: `#fraccion { {x+1} {y-2} }` },
  { key: 'caso18b', label: 'Caso 18b: Fracción con atributos', code: `#fraccion(numerador=x+1, denominador=y-2)` },
  { key: 'caso19', label: 'Caso 19: Atributos combinados', code: `#texto(fuente=Courier, color=#0000FF, centrado) {\n    Código final\n}` },
  { key: 'caso20', label: 'Caso 20: Salto automático (>792 pt)', code: `#texto { Párrafo 1 con contenido extenso. }\n#texto { Párrafo 2 adicional. }\n#texto { Párrafo 3 automático. }` },
  { key: 'caso21', label: 'Caso 21: Tabla base (#tabla/#fila/#celda)', code: `#tabla {\n    #fila {\n        #celda{ A }\n        #celda{ B }\n    }\n}` },
  { key: 'caso22', label: 'Caso 22: Tabla completa con bordes/fondo', code: `#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{ID} #celda{Nombre} }\n    #fila { #celda{1} #celda{LATINEX} }\n}` },
  { key: 'caso23', label: 'Caso 23: Referencia cruzada', code: `#seccion(id=sec1) { Inicio }\n#texto { Ver la #referencia(sec1). }` },
  { key: 'caso23b', label: 'Caso 23b: Referencia hacia adelante', code: `#texto { Ver sección final en #referencia(sec_fin). }\n#seccion(id=sec_fin) { Cierre }` },
  { key: 'caso24', label: 'Caso 24: Índice general automático', code: `#indicegeneral\n#seccion { Capítulo 1 }\n#texto { Contenido inicial. }` },
  { key: 'caso25', label: 'Caso 25: Anidamiento lista en celda', code: `#tabla(bordes=verdadero) {\n    #fila {\n        #celda { #lista(punto) { {Item 1} {Item 2} } }\n    }\n}` },
  { key: 'caso26', label: 'Caso 26: Anidamiento de estilos', code: `#texto(color=#0000FF) {\n    Todo azul pero esto es #fuerte(color=#FF0000){rojo y negrita} y vuelve a ser azul.\n}` },
  { key: 'caso27', label: 'Caso 27: Modo Ecuación en bloque', code: `#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {y - 2} } = 0\n}` },
  { key: 'caso28', label: 'Caso 28: Cajas de alerta (#caja)', code: `#caja(tipo=alerta) { Error crítico del sistema detectado en el módulo. }` },
  { key: 'caso29', label: 'Caso 29: Prueba de estrés integral', code: `// Documento integral\n#definir(version) { 1.0 }\n#titulo(color=#0000FF) { Manual del Compilador }\n#autor { Equipo de Compiladores }\n#indicegeneral\n#seccion(id=intro) { Uso en versión #imprimir(version) }\n#texto(justificado) {\n    Pruebas integrales del #fuerte(color=#FF0000){lenguaje de marcado} descrito en la página #referencia(intro).\n}` },
  { key: 'caso30', label: 'Caso 30: Griego y Operadores /Symbol (π, ∑, ∫, ∞)', code: `// Demostración científica con fuente nativa PDF Type1 /Symbol (/F7) y pie de página\n#titulo(color=#1A73E8) { Notación Científica y Matemática en LATINEX }\n#autor { Grupo 3 — EPIS UNAP }\n\n#seccion(id=analisis) { Series, Integrales y Alfabeto Griego }\n#texto(justificado) {\n    Para todo ε > 0 existe δ > 0 tal que si |x - α| ≤ δ entonces |f(x) - λ| ≠ ∞. La identidad gaussiana relaciona π, √π y la suma infinita ∑:\n}\n\n#ecuacion {\n    ∫ e#superindice{-x#superindice{2}} dx = √π   ⇒   ∑ #fraccion { {1} {n#superindice{2}} } = #fraccion { {π#superindice{2}} {6} }\n}\n\n#caja(tipo=exito) { Glifos nativos /Symbol: α β γ Δ Θ λ μ π σ Ω · Operadores: ∀ ∃ ∈ ⊆ ≤ ≥ ≠ ≈ → ⇒ ∞ ∂ ∇ ∑ ∏ ∫ √ }` },
  { key: 'err_sint', label: 'Demo Error Sintáctico: falta llave {', code: `#texto {\n    Bloque sin llave de cierre` },
  { key: 'err_1500', label: 'Demo Error Semántico 1500: Macro faltante', code: `#texto { Curso de #imprimir(macroInexistente). }` },
  { key: 'err_1501', label: 'Demo Error Semántico 1501: Ref. inexistente', code: `#texto { Ver la #referencia(etiqueta_perdida). }` }
];

const QUICK_SNIPPETS = [
  { label: '#titulo', desc: 'Insertar título principal', code: `\n#titulo(color=#1A73E8) { Título del Documento }\n` },
  { label: '#autor', desc: 'Insertar autor', code: `\n#autor { Nombre del Autor }\n` },
  { label: '#seccion', desc: 'Insertar sección con id', code: `\n#seccion(id=sec1) { Nueva Sección }\n` },
  { label: '#texto', desc: 'Insertar párrafo justificado', code: `\n#texto(justificado) {\n    Escribe aquí tu párrafo.\n}\n` },
  { label: '#fuerte', desc: 'Texto en negrita', code: `#fuerte{texto destacado}` },
  { label: '#cursiva', desc: 'Texto en cursiva', code: `#cursiva{texto cursivo}` },
  { label: '#lista', desc: 'Lista con viñetas', code: `\n#lista(punto) {\n    {Primer elemento}\n    {Segundo elemento}\n}\n` },
  { label: '#tabla', desc: 'Tabla con bordes y encabezado', code: `\n#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{Columna 1} #celda{Columna 2} }\n    #fila { #celda{Dato 1} #celda{Dato 2} }\n}\n` },
  { label: '#fraccion', desc: 'Fracción matemática', code: `#fraccion { {x+1} {y-2} }` },
  { label: '#ecuacion', desc: 'Ecuación en bloque', code: `\n#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {2} } = 0\n}\n` },
  { label: '#caja', desc: 'Caja informativa', code: `\n#caja(tipo=info) { Nota importante para el lector. }\n` },
  { label: '#definir', desc: 'Definir macro', code: `\n#definir(miMacro) { Valor }\n` },
  { label: '#referencia', desc: 'Referencia cruzada', code: `#referencia(sec1)` },
  { label: '#indicegeneral', desc: 'Tabla de contenido automática', code: `\n#indicegeneral\n` }
];

const COLOR_PRESETS = [
  { hex: '#B82035', nombre: 'Carmesí Peruano' },
  { hex: '#EF4444', nombre: 'Rojo Vivo' },
  { hex: '#1A73E8', nombre: 'Azul Académico' },
  { hex: '#16A34A', nombre: 'Verde Esmeralda' },
  { hex: '#EA580C', nombre: 'Ámbar Andino' },
  { hex: '#7C3AED', nombre: 'Violeta' },
  { hex: '#FACC15', nombre: 'Amarillo Resaltado' }
];

function hsvToHex(h, s, v) {
  const sn = Math.max(0, Math.min(100, s)) / 100;
  const vn = Math.max(0, Math.min(100, v)) / 100;
  const c = vn * sn;
  const hh = ((h % 360) + 360) % 360;
  const x = c * (1 - Math.abs(((hh / 60) % 2) - 1));
  const m = vn - c;
  let r = 0, g = 0, b = 0;
  if (hh < 60) { r = c; g = x; }
  else if (hh < 120) { r = x; g = c; }
  else if (hh < 180) { g = c; b = x; }
  else if (hh < 240) { g = x; b = c; }
  else if (hh < 300) { r = x; b = c; }
  else { r = c; b = x; }
  const toHex = (val) => Math.round((val + m) * 255).toString(16).padStart(2, '0').toUpperCase();
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

function hexToHsv(hexStr) {
  let clean = String(hexStr || '').trim().replace(/^#/, '');
  if (clean.length === 3) {
    clean = clean.split('').map((ch) => ch + ch).join('');
  }
  if (!/^[0-9a-fA-F]{6}$/.test(clean)) return null;
  const r = parseInt(clean.slice(0, 2), 16) / 255;
  const g = parseInt(clean.slice(2, 4), 16) / 255;
  const b = parseInt(clean.slice(4, 6), 16) / 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const d = max - min;
  let h = 0;
  if (d !== 0) {
    if (max === r) h = 60 * (((g - b) / d) % 6);
    else if (max === g) h = 60 * ((b - r) / d + 2);
    else h = 60 * ((r - g) / d + 4);
  }
  if (h < 0) h += 360;
  const s = max === 0 ? 0 : (d / max) * 100;
  const v = max * 100;
  return {
    h: Math.round(h),
    s: Math.round(s),
    v: Math.max(5, Math.round(v)),
    hex: `#${clean.toUpperCase()}`
  };
}

const MATH_SYMBOLS = [
  { char: 'π', desc: 'Pi (π)' },
  { char: 'α', desc: 'Alfa (α)' },
  { char: 'β', desc: 'Beta (β)' },
  { char: 'Δ', desc: 'Delta mayúscula (Δ)' },
  { char: 'Ω', desc: 'Omega mayúscula (Ω)' },
  { char: '∑', desc: 'Sumatoria (∑)' },
  { char: '∫', desc: 'Integral (∫)' },
  { char: '√', desc: 'Raíz cuadrada (√)' },
  { char: '∞', desc: 'Infinito (∞)' },
  { char: '≤', desc: 'Menor o igual (≤)' },
  { char: '≥', desc: 'Mayor o igual (≥)' },
  { char: '≠', desc: 'Distinto (≠)' },
  { char: '≈', desc: 'Aproximado (≈)' },
  { char: '→', desc: 'Flecha derecha (→)' },
  { char: '⇒', desc: 'Implica (⇒)' },
  { char: '∈', desc: 'Pertenece a (∈)' }
];

const CATALOGO_AUTOCOMPLETADO = [
  { cmd: 'titulo', firma: '#titulo { ... }', desc: 'Título principal del documento', insert: '#titulo {  }', cursorBack: 2 },
  { cmd: 'autor', firma: '#autor { ... }', desc: 'Autor en portada y metadato PDF', insert: '#autor {  }', cursorBack: 2 },
  { cmd: 'fecha', firma: '#fecha { ... }', desc: 'Fecha del documento', insert: '#fecha {  }', cursorBack: 2 },
  { cmd: 'resumen', firma: '#resumen { ... }', desc: 'Bloque de resumen / abstract', insert: '#resumen {\n    \n}', cursorBack: 2 },
  { cmd: 'indicegeneral', firma: '#indicegeneral', desc: 'Tabla de contenidos automática (Two-Pass)', insert: '#indicegeneral', cursorBack: 0 },
  { cmd: 'seccion', firma: '#seccion(id=...) { ... }', desc: 'Sección numerada nivel 1', insert: '#seccion(id=sec1) {  }', cursorBack: 2 },
  { cmd: 'subseccion', firma: '#subseccion { ... }', desc: 'Subsección numerada nivel 2', insert: '#subseccion {  }', cursorBack: 2 },
  { cmd: 'subseccion2', firma: '#subseccion2 { ... }', desc: 'Subsección numerada nivel 3', insert: '#subseccion2 {  }', cursorBack: 2 },
  { cmd: 'subseccion3', firma: '#subseccion3 { ... }', desc: 'Subsección numerada nivel 4', insert: '#subseccion3 {  }', cursorBack: 2 },
  { cmd: 'subseccion4', firma: '#subseccion4 { ... }', desc: 'Subsección numerada nivel 5', insert: '#subseccion4 {  }', cursorBack: 2 },
  { cmd: 'texto', firma: '#texto(justificado) { ... }', desc: 'Párrafo de texto con alineación/color', insert: '#texto(justificado) {\n    \n}', cursorBack: 2 },
  { cmd: 'fuerte', firma: '#fuerte{...}', desc: 'Formato inline en negrita', insert: '#fuerte{}', cursorBack: 1 },
  { cmd: 'cursiva', firma: '#cursiva{...}', desc: 'Formato inline en cursiva', insert: '#cursiva{}', cursorBack: 1 },
  { cmd: 'subrayado', firma: '#subrayado{...}', desc: 'Formato inline subrayado', insert: '#subrayado{}', cursorBack: 1 },
  { cmd: 'resaltado', firma: '#resaltado(color=#FFFF00){...}', desc: 'Texto con fondo resaltado', insert: '#resaltado(color=#FFFF00){}', cursorBack: 1 },
  { cmd: 'lista', firma: '#lista(punto|numero) { ... }', desc: 'Lista con viñetas o numerada', insert: '#lista(punto) {\n    {}\n}', cursorBack: 3 },
  { cmd: 'imagen', firma: '#imagen(ruta=..., ancho=50%)', desc: 'Insertar imagen JPEG nativa (/DCTDecode)', insert: '#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)', cursorBack: 0 },
  { cmd: 'tabla', firma: '#tabla(bordes=verdadero) { ... }', desc: 'Tabla con filas y celdas recursivas', insert: '#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{Col 1} #celda{Col 2} }\n    #fila { #celda{Dato 1} #celda{Dato 2} }\n}', cursorBack: 0 },
  { cmd: 'fila', firma: '#fila { #celda{...} }', desc: 'Fila dentro de #tabla', insert: '#fila { #celda{} }', cursorBack: 3 },
  { cmd: 'celda', firma: '#celda{...}', desc: 'Celda dentro de #fila', insert: '#celda{}', cursorBack: 1 },
  { cmd: 'ecuacion', firma: '#ecuacion { ... }', desc: 'Ecuación centrada y numerada (1)', insert: '#ecuacion {\n    \n}', cursorBack: 2 },
  { cmd: 'fraccion', firma: '#fraccion { {num} {den} }', desc: 'Fracción matemática con barra vectorial', insert: '#fraccion { {} {} }', cursorBack: 6 },
  { cmd: 'superindice', firma: '#superindice{...}', desc: 'Exponente matemático (ej. x²)', insert: '#superindice{}', cursorBack: 1 },
  { cmd: 'subindice', firma: '#subindice{...}', desc: 'Subíndice matemático (ej. H₂O)', insert: '#subindice{}', cursorBack: 1 },
  { cmd: 'caja', firma: '#caja(tipo=info|alerta|exito)', desc: 'Caja destacada con ícono vectorial PDF', insert: '#caja(tipo=info) {  }', cursorBack: 2 },
  { cmd: 'definir', firma: '#definir(id) { ... }', desc: 'Declarar macro reutilizable', insert: '#definir(miMacro) {  }', cursorBack: 2 },
  { cmd: 'imprimir', firma: '#imprimir(id)', desc: 'Expandir valor de una macro definida', insert: '#imprimir()', cursorBack: 1 },
  { cmd: 'referencia', firma: '#referencia(id)', desc: 'Referencia cruzada al número de página', insert: '#referencia()', cursorBack: 1 },
  { cmd: 'salto', firma: '#salto', desc: 'Salto de línea explícito', insert: '#salto', cursorBack: 0 },
  { cmd: 'nuevapagina', firma: '#nuevapagina', desc: 'Salto de página explícito A4', insert: '#nuevapagina', cursorBack: 0 }
];

const CMD_SECTION = new Set(['seccion', 'subseccion', 'subseccion1', 'subseccion2', 'subseccion3', 'subseccion4']);
const CMD_STRUCT = new Set(['titulo', 'autor', 'fecha', 'texto', 'resumen', 'indicegeneral']);
const CMD_FMT = new Set(['fuerte', 'cursiva', 'subrayado', 'resaltado']);
const CMD_MATH = new Set(['ecuacion', 'fraccion', 'superindice', 'subindice']);
const CMD_DATA = new Set(['tabla', 'fila', 'celda', 'lista', 'imagen']);
const CMD_CTRL = new Set(['definir', 'imprimir', 'referencia', 'caja', 'salto', 'nuevapagina']);
const ATTR_NAMES = new Set(['color', 'tamaño', 'tamano', 'fuente', 'fuente_attr', 'alineacion', 'ruta', 'ancho', 'alto', 'bordes', 'fondo', 'tipo', 'numerador', 'denominador', 'id']);
const ATTR_VALS = new Set(['punto', 'numero', 'derecha', 'centro', 'centrado', 'justificado', 'izquierda', 'verdadero', 'falso', 'auto', 'gris', 'rojo', 'azul', 'verde', 'amarillo', 'negro', 'blanco', 'alerta', 'info', 'exito', 'éxito']);

function escaparHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function resaltarCodigoLatinex(source) {
  if (!source) return '\n';
  const tokenRe = /((?<!:)\/\/[^\n]*)|(\\[#{}()\\])|(#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b)|(#[a-zA-ZáéíóúÁÉÍÓÚñÑ_][a-zA-Z0-9áéíóúÁÉÍÓÚñÑ_]*)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|([(){}])|(\b\d+(?:\.\d+)?%?)|([a-zA-ZáéíóúÁÉÍÓÚñÑ_][a-zA-Z0-9áéíóúÁÉÍÓÚñÑ_.]*)|([Α-Ωα-ω∀∃≅∴⊥≤∞↔←↑→↓≥∝∂≠≡≈∩∪⊂⊆∈∉∇∏√∧∨⇔⇐⇑⇒⇓∑∫])/gu;

  let out = '';
  let lastIdx = 0;
  let parenDepth = 0;
  let m;

  while ((m = tokenRe.exec(source)) !== null) {
    if (m.index > lastIdx) {
      out += escaparHtml(source.slice(lastIdx, m.index));
    }
    const raw = m[0];

    if (m[1] !== undefined) {
      out += `<span class="hl-comment">${escaparHtml(raw)}</span>`;
    } else if (m[2] !== undefined) {
      out += `<span class="hl-escape">${escaparHtml(raw)}</span>`;
    } else if (m[3] !== undefined) {
      out += `<span class="hl-hex" style="border-bottom: 2px solid ${escaparHtml(raw)};">${escaparHtml(raw)}</span>`;
    } else if (m[4] !== undefined) {
      const name = raw.slice(1);
      let cls = 'hl-cmd-unknown';
      if (CMD_SECTION.has(name)) cls = 'hl-cmd-section';
      else if (CMD_STRUCT.has(name)) cls = 'hl-cmd-struct';
      else if (CMD_FMT.has(name)) cls = 'hl-cmd-fmt';
      else if (CMD_MATH.has(name)) cls = 'hl-cmd-math';
      else if (CMD_DATA.has(name)) cls = 'hl-cmd-data';
      else if (CMD_CTRL.has(name)) cls = 'hl-cmd-ctrl';
      out += `<span class="${cls}">${escaparHtml(raw)}</span>`;
    } else if (m[5] !== undefined) {
      out += parenDepth > 0 ? `<span class="hl-str">${escaparHtml(raw)}</span>` : escaparHtml(raw);
    } else if (m[6] !== undefined) {
      if (raw === '(') parenDepth++;
      else if (raw === ')') parenDepth = Math.max(0, parenDepth - 1);
      out += `<span class="hl-delim">${raw}</span>`;
    } else if (m[7] !== undefined) {
      out += parenDepth > 0 ? `<span class="hl-num">${escaparHtml(raw)}</span>` : escaparHtml(raw);
    } else if (m[8] !== undefined) {
      if (parenDepth > 0) {
        const after = source.slice(tokenRe.lastIndex);
        if (/^\s*=/.test(after) && ATTR_NAMES.has(raw)) {
          out += `<span class="hl-attr">${escaparHtml(raw)}</span>`;
        } else if (ATTR_VALS.has(raw)) {
          out += `<span class="hl-val">${escaparHtml(raw)}</span>`;
        } else if (/\.[a-zA-Z0-9]+$/.test(raw)) {
          out += `<span class="hl-str">${escaparHtml(raw)}</span>`;
        } else {
          out += escaparHtml(raw);
        }
      } else {
        out += escaparHtml(raw);
      }
    } else if (m[9] !== undefined) {
      out += `<span class="hl-sym">${escaparHtml(raw)}</span>`;
    } else {
      out += escaparHtml(raw);
    }

    lastIdx = tokenRe.lastIndex;
  }

  if (lastIdx < source.length) {
    out += escaparHtml(source.slice(lastIdx));
  }

  return out + '\n';
}

const TOKEN_COLORS_DARK = {
  simbolo: '#94a3b8',
  operador: '#f59e0b',
  separador: '#f59e0b',
  comando_estructura: '#c084fc',
  comando_formato: '#7dd3fc',
  comando_datos: '#34d399',
  comando_tabla: '#2dd4bf',
  comando_matematica: '#6ee7b7',
  comando_control: '#fb923c',
  comando_macro: '#facc15',
  comando_referencia: '#c084fc',
  comando_entorno: '#f87171',
  atributo: '#93c5fd',
  valor_atributo: '#86efac',
  literal_color: '#fde68a',
  literal_numero: '#fdba74',
  literal_cadena: '#a7f3d0',
  literal_archivo: '#d9f99d',
  texto: '#e9efe9',
  comentario: '#6e8073',
  escape: '#f0abfc',
  identificador: '#cbd5e1',
  fin: '#617065',
  error: '#f87171'
};

const TOKEN_COLORS_LIGHT = {
  simbolo: '#6e5c61',
  operador: '#a35729',
  separador: '#a35729',
  comando_estructura: '#6e3f82',
  comando_formato: '#6e3f82',
  comando_datos: '#4c5c43',
  comando_tabla: '#2b5b84',
  comando_matematica: '#b82035',
  comando_control: '#9c4221',
  comando_macro: '#975a16',
  comando_referencia: '#8b2642',
  comando_entorno: '#b82035',
  atributo: '#4f4649',
  valor_atributo: '#3d6947',
  literal_color: '#a35729',
  literal_numero: '#a35729',
  literal_cadena: '#4f4649',
  literal_archivo: '#b82035',
  texto: '#231b1d',
  comentario: '#8f8688',
  escape: '#8b3d64',
  identificador: '#4a3e41',
  fin: '#8f8688',
  error: '#c53030'
};

function installLightDismissFallback(dialogEl) {
  if (!dialogEl || 'closedBy' in HTMLDialogElement.prototype) return;
  if (dialogEl.getAttribute('closedby') !== 'any') return;

  let pressedOutside = false;
  const isOutside = (e) => {
    const r = dialogEl.getBoundingClientRect();
    return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
  };
  dialogEl.addEventListener('pointerdown', (e) => {
    pressedOutside = isOutside(e);
  });
  dialogEl.addEventListener('pointerup', (e) => {
    if (pressedOutside && isOutside(e)) dialogEl.close();
  });
}

const app = createApp({
  setup() {
    const theme = ref(aplicarTemaEnDocumento(localStorage.getItem(STORAGE_THEME) || 'light'));
    const explorerOpen = ref(localStorage.getItem(STORAGE_EXPLORER) !== '0');
    const resourcesExpanded = ref(false);
    const outlineExpanded = ref(true);
    const previewFullscreen = ref(false);
    const compileDurationMs = ref(0);

    const sidebarOpen = ref(false);
    const menuDrawerOpen = ref(false);
    const layoutPrefs = reactive(cargarPreferenciasLayout());
    const inspectorOpen = ref(false);
    const activeTab = ref('lexico');
    const showLexicalRef = ref(false);
    const lexicalReference = ref([]);

    const user = ref(null);
    const csrfToken = ref('');
    const quota = ref({ usados: 0, maximo: 10 });
    const documents = ref([]);
    const activeDoc = ref(null);
    const saveState = ref('saved');
    const isSaving = ref(false);

    const sessionId = ref(
      Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 10)
    );
    const guestImages = ref([]);

    const borradorGuardado = localStorage.getItem(STORAGE_GUEST_DRAFT);
    const codigoFuente = ref(borradorGuardado !== null ? borradorGuardado : DEFAULT_GUEST_CODE);
    const autoCompile = ref(localStorage.getItem(STORAGE_AUTO_COMPILE) === '1');
    const importLtxInputRef = ref(null);
    const selectedCaseKey = ref('');
    const isCompiling = ref(false);
    const compileResult = ref(null);
    const pdfUrl = ref(null);
    const previewZoom = ref(0.75);

    const cursorLn = ref(1);
    const cursorCol = ref(1);
    const editorRef = ref(null);
    const gutterRef = ref(null);
    const highlightRef = ref(null);

    // Estado del Selector Cromático #HEX
    const colorPickerOpen = ref(false);
    const colorPopoverRef = ref(null);
    const colorWheelRef = ref(null);
    const pickerHue = ref(352);
    const pickerSat = ref(83);
    const pickerVal = ref(72);
    const pickerHex = ref('#B82035');
    const pickerHexInput = ref('#B82035');
    const hexCopied = ref(false);
    let wheelDragging = false;

    const wheelThumbPos = computed(() => {
      const rad = (pickerHue.value * Math.PI) / 180;
      const ratio = Math.max(0, Math.min(1, pickerSat.value / 100));
      return {
        x: 50 + Math.cos(rad) * ratio * 50,
        y: 50 + Math.sin(rad) * ratio * 50
      };
    });

    // Estado de autocompletado interactivo (#comando)
    const autocompleteOpen = ref(false);
    const autocompleteItems = ref([]);
    const autocompleteIndex = ref(0);
    const autocompleteRange = ref({ start: 0, end: 0 });
    let blurAutocompleteTimer = null;

    // Modales
    const authDialogRef = ref(null);
    const newDocDialogRef = ref(null);
    const renameDocDialogRef = ref(null);
    const confirmDeleteDialogRef = ref(null);
    const blockDialogRef = ref(null);

    const activeBlock = ref('espacio');
    const blockDialogOpen = ref(false);
    const caseSearchQuery = ref('');
    const closeOnInsert = ref(true);

    const TITULOS_BLOQUES = {
      archivo: 'Archivo y exportación',
      documentos: 'Documentos e imágenes',
      casos: 'Cargar casos de prueba',
      insertores: 'Insertores y símbolos',
      analizadores: 'Analizadores del compilador',
      espacio: 'Espacio y paneles'
    };

    const tituloBloqueActivo = computed(() => TITULOS_BLOQUES[activeBlock.value] || 'Opciones');

    const casosFiltrados = computed(() => {
      const q = caseSearchQuery.value.trim().toLowerCase();
      if (!q) return CASOS_OFICIALES;
      return CASOS_OFICIALES.filter(
        (c) => c.label.toLowerCase().includes(q) || c.key.toLowerCase().includes(q)
      );
    });

    const authMode = ref('login');
    const authError = ref('');
    const authSubmitting = ref(false);
    const showLoginPass = ref(false);
    const showRegPass = ref(false);
    const loginForm = ref({ correo: '', password: '' });
    const registerForm = ref({ nombre: '', correo: '', password: '' });

    const docModalError = ref('');
    const newDocForm = ref({ titulo: 'Nuevo Documento', nombre_archivo: 'nuevo_documento.ltx', plantilla: 'academico' });
    const renameDocForm = ref({ id: 0, titulo: '', nombre_archivo: '' });
    const deleteTarget = ref(null);

    let autosaveTimer = null;
    let autocompileTimer = null;
    let compileSeq = 0;

    const limpiarResultadoCompilacion = () => {
      compileSeq++;
      if (autocompileTimer) {
        clearTimeout(autocompileTimer);
        autocompileTimer = null;
      }
      isCompiling.value = false;
      compileResult.value = null;
      compileDurationMs.value = 0;
      if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value);
        pdfUrl.value = null;
      }
    };

    const dialogMap = () => ({
      auth: authDialogRef.value,
      newDoc: newDocDialogRef.value,
      renameDoc: renameDocDialogRef.value,
      confirmDelete: confirmDeleteDialogRef.value,
      block: blockDialogRef.value
    });

    const cerrarDialog = (name) => {
      const dlg = dialogMap()[name];
      if (dlg && dlg.open) dlg.close();
    };

    const totalLineas = computed(() => Math.max(1, codigoFuente.value.split('\n').length));
    const codigoBytes = computed(() => new TextEncoder().encode(codigoFuente.value).length);
    const codigoResaltadoHtml = computed(() => resaltarCodigoLatinex(codigoFuente.value));

    const totalPalabras = computed(() => {
      const limpio = codigoFuente.value
        .replace(/(?<!:)\/\/[^\n]*/g, ' ')
        .replace(/#[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9_]+(?:\([^)]*\))?/g, ' ')
        .replace(/[{}()\\]/g, ' ')
        .trim();
      if (!limpio) return 0;
      return limpio.split(/\s+/).length;
    });

    const activeFilename = computed(() => {
      if (user.value && activeDoc.value) return activeDoc.value.nombre_archivo;
      return 'mi_primer_documento.ltx';
    });

    const userInitials = computed(() => {
      if (!user.value || !user.value.nombre) return 'IN';
      const partes = user.value.nombre.trim().split(/\s+/).filter(Boolean);
      if (partes.length >= 2) return (partes[0][0] + partes[1][0]).toUpperCase();
      return partes[0].slice(0, 2).toUpperCase();
    });

    const activeDocTitle = computed(() => {
      if (user.value && activeDoc.value?.titulo) return activeDoc.value.titulo;
      const m = codigoFuente.value.match(/#titulo(?:\([^)]*\))?\s*\{([^{}]+)\}/i);
      if (m && m[1].trim()) return m[1].trim();
      return 'Mi primer documento';
    });

    const activeDocSubtitle = computed(() => {
      const m = codigoFuente.value.match(/#autor(?:\([^)]*\))?\s*\{([^{}]+)\}/i);
      if (m && m[1].trim()) return `${m[1].trim()} · Prototipo de compilador nativo · Lenguaje de marcado en español a PDF`;
      return 'Prototipo de compilador nativo · Lenguaje de marcado en español a PDF';
    });

    const documentoEstructura = computed(() => {
      const src = codigoFuente.value;
      const items = [];
      const re = /#(seccion|subseccion2|subseccion3|subseccion4|subseccion)(?:\([^)]*\))?\s*\{([^{}]*)\}/gi;
      let m;
      while ((m = re.exec(src)) !== null) {
        const cmd = m[1].toLowerCase();
        const texto = m[2].replace(/\s+/g, ' ').trim();
        if (!texto) continue;
        const linea = src.slice(0, m.index).split('\n').length;
        let nivel = 'H1';
        if (cmd === 'subseccion') nivel = 'H2';
        else if (cmd.startsWith('subseccion')) nivel = 'H3';
        items.push({ nivel, texto, linea });
      }
      return items;
    });

    const compileDurationText = computed(() => {
      const ms = compileDurationMs.value;
      if (!ms || ms < 1) return '0.05 s';
      return `${(ms / 1000).toFixed(2)} s`;
    });

    const activeImages = computed(() => activeDoc.value?.imagenes || []);

    const tokensAnalizados = computed(() => compileResult.value?.lexico?.tokens || []);
    const sintacticoPasos = computed(() => compileResult.value?.sintactico?.transiciones || []);
    const tablaSimbolos = computed(() => compileResult.value?.semantico?.tablaSimbolos || []);
    const advertenciasSemanticas = computed(() => compileResult.value?.semantico?.advertencias || []);

    const pdfDownloadBase64 = computed(() => compileResult.value?.ejecucion?.pdfBase64 || null);

    const ejecucionInspector = computed(() => {
      const ej = compileResult.value?.ejecucion;
      if (!ej) return {};
      const copiaElem = Array.isArray(ej.figurasOElementos)
        ? ej.figurasOElementos.map((el) => {
            if (!el || !el.dataUrl) return el;
            const { dataUrl, ...resto } = el;
            return { ...resto, dataUrl: '[JPEG embebido para vista previa]' };
          })
        : [];
      const bytes = ej.pdfStats?.bytes || (ej.pdfBase64 ? Math.floor((ej.pdfBase64.length * 3) / 4) : 0);
      return {
        ...ej,
        figurasOElementos: copiaElem,
        pdfBase64: ej.pdfBase64
          ? `[Binario %PDF-1.4 omitido en inspector — ${(bytes / 1024).toFixed(2)} KB, listo para descarga]`
          : null
      };
    });

    const tieneElementosDocumento = computed(
      () => (compileResult.value?.ejecucion?.figurasOElementos?.length || 0) > 0
    );

    const paginasDocumento = computed(() => {
      const res = compileResult.value;
      if (!res || !res.ejecucion || !res.ejecucion.valido) return [];
      const elems = res.ejecucion.figurasOElementos || [];
      const totalPags = Math.max(
        1,
        Number(res.ejecucion.resumen?.totalPaginas || res.ejecucion.pdfStats?.paginas || 1)
      );
      const mapa = Array.from({ length: totalPags }, (_, idx) => ({
        numero: idx + 1,
        elementos: []
      }));
      for (const el of elems) {
        const p = Math.min(totalPags, Math.max(1, Number(el.pagina || 1)));
        mapa[p - 1].elementos.push(el);
      }
      return mapa;
    });

    const svgRgb = (arr) => {
      if (!Array.isArray(arr) || arr.length < 3) return '#000000';
      const r = Math.round(Math.max(0, Math.min(1, Number(arr[0] || 0))) * 255);
      const g = Math.round(Math.max(0, Math.min(1, Number(arr[1] || 0))) * 255);
      const b = Math.round(Math.max(0, Math.min(1, Number(arr[2] || 0))) * 255);
      return `rgb(${r}, ${g}, ${b})`;
    };

    const svgPoints = (pts) => {
      if (!Array.isArray(pts)) return '';
      return pts.map((pt) => `${pt[0]},${pt[1]}`).join(' ');
    };

    const svgFontProps = (fuente) => {
      const code = String(fuente || '/F1').replace(/^\//, '');
      if (code === 'F2') return { family: 'Helvetica, Arial, sans-serif', weight: '700', style: 'normal' };
      if (code === 'F3') return { family: 'Helvetica, Arial, sans-serif', weight: '400', style: 'italic' };
      if (code === 'F4') return { family: 'Helvetica, Arial, sans-serif', weight: '700', style: 'italic' };
      if (code === 'F5') return { family: "'Courier New', Courier, monospace", weight: '400', style: 'normal' };
      if (code === 'F6') return { family: "'Times New Roman', Times, Georgia, serif", weight: '400', style: 'normal' };
      return { family: 'Helvetica, Arial, sans-serif', weight: '400', style: 'normal' };
    };

    const resolverImagenPreview = (el) => {
      if (el?.dataUrl) return el.dataUrl;
      return null;
    };

    const ajustarZoom = (delta) => {
      previewZoom.value = Math.round(Math.max(0.5, Math.min(1.6, previewZoom.value + delta)) * 100) / 100;
    };

    const fijarZoomPorcentaje = (porcentaje) => {
      const num = Number(porcentaje);
      if (!Number.isFinite(num)) return;
      previewZoom.value = Math.max(0.5, Math.min(1.6, num / 100));
    };

    const resetZoom = () => {
      previewZoom.value = 0.75;
    };

    const cambiarTema = (nuevoTema) => {
      theme.value = aplicarTemaEnDocumento(nuevoTema);
      localStorage.setItem(STORAGE_THEME, theme.value);
    };

    const alternarTema = () => {
      cambiarTema(theme.value === 'light' ? 'dark' : 'light');
    };

    const alternarExplorador = () => {
      explorerOpen.value = !explorerOpen.value;
      localStorage.setItem(STORAGE_EXPLORER, explorerOpen.value ? '1' : '0');
    };

    const alClicAvatar = () => {
      if (user.value) {
        abrirBloqueCentral('documentos');
      } else {
        abrirAuthDialog('login');
      }
    };

    const errorPanel = computed(() => {
      const res = compileResult.value;
      if (!res) return null;

      if (res.estado === 'error_lexico') {
        const msg = (res.lexico?.errores || [res.message]).join(' ');
        const m = msg.match(/Ln\s+(\d+),\s*Col\s+(\d+)/i);
        return {
          titulo: 'Error en Fase 1: Análisis Léxico',
          meta: m ? `Línea ${m[1]}, Columna ${m[2]}` : 'Token no reconocido',
          mensaje: msg,
          linea: m ? Number(m[1]) : 0
        };
      }
      if (res.estado === 'error_sintactico') {
        const err = res.sintactico?.error || {};
        return {
          titulo: 'Error en Fase 2: Análisis Sintáctico',
          meta: `Estado Q${err.estado ?? '?'} · Línea ${err.linea ?? '?'}, Columna ${err.columna ?? '?'}`,
          mensaje: err.mensaje || res.message,
          linea: Number(err.linea || 0)
        };
      }
      if (res.estado === 'error_semantico') {
        const err = res.semantico?.error || {};
        return {
          titulo: `Error en Fase 3: Análisis Semántico (Cód. ${err.codigo ?? '—'})`,
          meta: `Token ${err.nombreToken ?? ''} · Línea ${err.linea ?? '?'}, Columna ${err.columna ?? '?'}`,
          mensaje: err.mensaje || res.message,
          linea: Number(err.linea || 0)
        };
      }
      if (res.estado === 'error_ejecucion') {
        const err = res.ejecucion?.error || {};
        return {
          titulo: 'Error en Fase 4: Ejecución',
          meta: err.linea ? `Línea ${err.linea}` : 'Motor Two-Pass',
          mensaje: err.mensaje || res.message,
          linea: Number(err.linea || 0)
        };
      }
      return null;
    });

    const errorLine = computed(() => errorPanel.value?.linea || 0);

    const compilePillClass = computed(() => {
      if (!compileResult.value) return 'idle';
      return errorPanel.value ? 'error' : 'ok';
    });

    const compileStatusText = computed(() => {
      if (isCompiling.value) return 'Compilando...';
      if (!compileResult.value) return 'Sin compilar';
      if (errorPanel.value) return errorPanel.value.titulo;
      if (compileResult.value.ejecucion?.implementado) return '4 Fases ACEPTADAS · PDF listo';
      return 'Fases 1–3 ACEPTADAS';
    });

    const previewData = computed(() => {
      const code = codigoFuente.value;
      const cleanInline = (raw) => {
        let s = raw;
        for (let pass = 0; pass < 4; pass++) {
          const next = s.replace(/#(?:fuerte|cursiva|subrayado|resaltado|superindice|subindice)(?:\([^)]*\))?\s*\{([^{}]*)\}/gi, '$1');
          if (next === s) break;
          s = next;
        }
        return s.replace(/\\([#{}])/g, '$1').replace(/\s+/g, ' ').trim();
      };

      const bloques = [];
      let tituloDoc = '';
      let autorDoc = '';

      const headRe = /#(titulo|autor|seccion|subseccion\d*|texto|caja)(?:\(([^)]*)\))?\s*\{/gi;
      let match;
      while ((match = headRe.exec(code)) !== null) {
        const cmd = match[1].toLowerCase();
        const attrs = (match[2] || '').toLowerCase();
        const bodyStart = headRe.lastIndex;
        let depth = 1;
        let idx = bodyStart;

        while (idx < code.length && depth > 0) {
          const ch = code[idx];
          if (ch === '\\') {
            idx += 2;
            continue;
          }
          if (ch === '{') depth++;
          else if (ch === '}') depth--;
          idx++;
        }

        const bodyEnd = depth === 0 ? idx - 1 : idx;
        headRe.lastIndex = idx;
        const cleanBody = cleanInline(code.slice(bodyStart, bodyEnd));
        if (!cleanBody) continue;

        if (cmd === 'titulo' && !tituloDoc) {
          tituloDoc = cleanBody;
        } else if (cmd === 'autor' && !autorDoc) {
          autorDoc = cleanBody;
        } else if (cmd.startsWith('seccion') || cmd.startsWith('subseccion')) {
          bloques.push({ tipo: 'seccion', texto: cleanBody });
        } else if (cmd === 'caja') {
          let estilo = 'info';
          if (attrs.includes('alerta')) estilo = 'alerta';
          else if (attrs.includes('exito') || attrs.includes('éxito')) estilo = 'exito';
          bloques.push({ tipo: 'caja', estilo, texto: cleanBody });
        } else if (cmd === 'texto') {
          bloques.push({ tipo: 'texto', texto: cleanBody });
        }
      }

      return {
        titulo: tituloDoc || activeFilename.value,
        autor: autorDoc,
        bloques
      };
    });

    const estadoFaseClass = (fase) => {
      const res = compileResult.value;
      if (!res) return '';
      if (fase === 'lexico') return res.lexico?.valido ? 'ok' : 'err';
      if (fase === 'sintactico') {
        if (res.estado === 'error_lexico') return '';
        return res.sintactico?.valido ? 'ok' : 'err';
      }
      if (fase === 'semantico') {
        if (res.estado === 'error_lexico' || res.estado === 'error_sintactico') return '';
        return res.semantico?.valido ? 'ok' : 'err';
      }
      if (fase === 'ejecucion') {
        if (!res.ejecucion || res.ejecucion.estado.startsWith('OMITIDO')) return '';
        if (!res.ejecucion.valido) return 'err';
        return res.ejecucion.implementado ? 'ok' : 'pending';
      }
      return '';
    };

    const colorDeToken = (cat) => {
      const paleta = theme.value === 'dark' ? TOKEN_COLORS_DARK : TOKEN_COLORS_LIGHT;
      return paleta[cat] || (theme.value === 'dark' ? '#e9efe9' : '#1c241e');
    };
    const badgeStyle = (cat) => {
      const c = colorDeToken(cat);
      return { backgroundColor: `${c}1c`, color: c, borderColor: `${c}48` };
    };

    const apiJson = async (url, options = {}) => {
      const headers = { ...(options.headers || {}) };
      if (csrfToken.value && options.method && options.method !== 'GET') {
        headers['X-CSRF-Token'] = csrfToken.value;
      }
      const res = await fetch(url, { credentials: 'same-origin', ...options, headers });
      const data = await res.json();
      if (data.csrfToken) csrfToken.value = data.csrfToken;
      if (!res.ok) {
        const err = new Error(data.message || `Error HTTP ${res.status}`);
        err.code = data.code || '';
        err.status = res.status;
        throw err;
      }
      return data;
    };

    const cargarSesionYDocumentos = async () => {
      try {
        const me = await apiJson('./auth.php?action=me');
        user.value = me.autenticado ? me.usuario : null;
        csrfToken.value = me.csrfToken || '';
        quota.value = {
          usados: me.limites?.documentosUsados ?? 0,
          maximo: me.limites?.maxDocumentos ?? 10
        };

        if (user.value) {
          await recargarListaDocumentos(true);
        } else {
          documents.value = [];
          activeDoc.value = null;
          codigoFuente.value = localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE;
          saveState.value = 'saved';
        }
      } catch (err) {
        console.warn('No se pudo consultar sesión al servidor (modo invitado local):', err.message);
      }
    };

    const recargarListaDocumentos = async (cargarPrimero = false) => {
      const listRes = await apiJson('./projects.php');
      documents.value = listRes.documentos || [];
      quota.value = listRes.cuota || quota.value;
      if (cargarPrimero && documents.value.length > 0) {
        await seleccionarDocumento(documents.value[0].id, true);
      } else if (documents.value.length === 0) {
        activeDoc.value = null;
      }
    };

    const seleccionarDocumento = async (docId, omitirGuardadoPrevio = false) => {
      if (!omitirGuardadoPrevio && user.value && activeDoc.value && saveState.value === 'dirty') {
        await guardarDocumentoActual(false);
      }
      const detalle = await apiJson(`./projects.php?id=${docId}`);
      activeDoc.value = detalle.documento;
      codigoFuente.value = detalle.documento.contenido_ltx;
      saveState.value = 'saved';
      compileResult.value = null;
      if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value);
        pdfUrl.value = null;
      }
      await compilar();
    };

    const guardarDocumentoActual = async (compilarDespues = false) => {
      if (!user.value || !activeDoc.value) return;
      if (autosaveTimer) {
        clearTimeout(autosaveTimer);
        autosaveTimer = null;
      }
      isSaving.value = true;
      saveState.value = 'saving';
      try {
        const res = await apiJson(`./projects.php?id=${activeDoc.value.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ contenido_ltx: codigoFuente.value })
        });
        activeDoc.value = res.documento;
        const idx = documents.value.findIndex((d) => d.id === res.documento.id);
        if (idx >= 0) {
          documents.value[idx] = { ...documents.value[idx], bytes_tamano: res.documento.bytes_tamano, actualizado_en: res.documento.actualizado_en };
        }
        saveState.value = 'saved';
        if (compilarDespues) await compilar();
      } catch (err) {
        saveState.value = 'dirty';
        alert(err.message);
      } finally {
        isSaving.value = false;
      }
    };

    const cerrarAutocompletado = () => {
      autocompleteOpen.value = false;
      autocompleteItems.value = [];
      autocompleteIndex.value = 0;
    };

    const programarCierreAutocompletado = () => {
      if (blurAutocompleteTimer) clearTimeout(blurAutocompleteTimer);
      blurAutocompleteTimer = setTimeout(cerrarAutocompletado, 150);
    };

    const actualizarAutocompletado = () => {
      const ta = editorRef.value;
      if (!ta || ta.selectionStart !== ta.selectionEnd) {
        cerrarAutocompletado();
        return;
      }
      const pos = ta.selectionStart || 0;
      const antes = codigoFuente.value.slice(0, pos);
      const lineaActual = antes.slice(antes.lastIndexOf('\n') + 1);
      if (lineaActual.includes('//')) {
        cerrarAutocompletado();
        return;
      }
      const m = antes.match(/(?:^|[\s{])(#[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9_]*)$/);
      if (!m) {
        cerrarAutocompletado();
        return;
      }
      const tokenHash = m[1];
      if (/^#[0-9a-fA-F]{3,6}$/.test(tokenHash)) {
        cerrarAutocompletado();
        return;
      }
      const prefijo = tokenHash.slice(1).toLowerCase();
      const filtrados = CATALOGO_AUTOCOMPLETADO.filter((item) => item.cmd.startsWith(prefijo));
      if (filtrados.length === 0 || (filtrados.length === 1 && filtrados[0].cmd === prefijo)) {
        cerrarAutocompletado();
        return;
      }
      autocompleteRange.value = { start: pos - tokenHash.length, end: pos };
      autocompleteItems.value = filtrados.slice(0, 8);
      autocompleteIndex.value = 0;
      autocompleteOpen.value = true;
    };

    const aplicarAutocompletado = async (sug) => {
      const ta = editorRef.value;
      const { start, end } = autocompleteRange.value;
      codigoFuente.value = codigoFuente.value.slice(0, start) + sug.insert + codigoFuente.value.slice(end);
      cerrarAutocompletado();
      persistirCambioCodigo();
      await nextTick();
      if (ta) {
        ta.focus();
        const nuevaPos = start + sug.insert.length - (sug.cursorBack || 0);
        ta.setSelectionRange(nuevaPos, nuevaPos);
        sincronizarScroll();
        actualizarPosicionCursor();
      }
    };

    const persistirCambioCodigo = () => {
      if (user.value && activeDoc.value) {
        saveState.value = 'dirty';
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => {
          guardarDocumentoActual(false);
        }, 1200);
      } else {
        localStorage.setItem(STORAGE_GUEST_DRAFT, codigoFuente.value);
      }

      if (!codigoFuente.value.trim()) {
        limpiarResultadoCompilacion();
        return;
      }

      if (autoCompile.value) {
        if (autocompileTimer) clearTimeout(autocompileTimer);
        autocompileTimer = setTimeout(() => {
          if (!isCompiling.value) compilar();
        }, 650);
      }
    };

    const alternarAutoCompilar = () => {
      autoCompile.value = !autoCompile.value;
      localStorage.setItem(STORAGE_AUTO_COMPILE, autoCompile.value ? '1' : '0');
      if (!codigoFuente.value.trim()) {
        limpiarResultadoCompilacion();
        return;
      }
      if (autoCompile.value && !isCompiling.value) {
        compilar();
      }
    };

    const alEditarCodigo = () => {
      actualizarPosicionCursor();
      actualizarAutocompletado();
      persistirCambioCodigo();
    };

    const compilar = async () => {
      if (!codigoFuente.value.trim()) {
        limpiarResultadoCompilacion();
        return;
      }
      const seqActual = ++compileSeq;
      isCompiling.value = true;
      const t0 = performance.now();
      try {
        const fd = new FormData();
        fd.append('sessionId', sessionId.value);
        fd.append('codigo', codigoFuente.value);
        if (user.value && activeDoc.value) {
          fd.append('documentoId', String(activeDoc.value.id));
        } else {
          guestImages.value.forEach((img, i) => fd.append(`images[${i}]`, img));
        }

        const resp = await fetch('./api.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        });
        const data = await resp.json();
        if (seqActual !== compileSeq) return;
        compileDurationMs.value = Math.max(1, performance.now() - t0);
        compileResult.value = data;

        if (pdfUrl.value) {
          URL.revokeObjectURL(pdfUrl.value);
          pdfUrl.value = null;
        }
        if (data.ejecucion?.pdfBase64) {
          const binStr = atob(data.ejecucion.pdfBase64);
          const bytes = new Uint8Array(binStr.length);
          for (let i = 0; i < binStr.length; i++) bytes[i] = binStr.charCodeAt(i);
          const blob = new Blob([bytes], { type: 'application/pdf' });
          pdfUrl.value = URL.createObjectURL(blob);
        }

        if (data.estado === 'error_lexico') {
          activeTab.value = 'lexico';
          showLexicalRef.value = false;
          if (layoutPrefs.analizadores !== 'ventana') inspectorOpen.value = true;
        } else if (data.estado === 'error_sintactico') {
          activeTab.value = 'sintactico';
          if (layoutPrefs.analizadores !== 'ventana') inspectorOpen.value = true;
        } else if (data.estado === 'error_semantico') {
          activeTab.value = 'semantico';
          if (layoutPrefs.analizadores !== 'ventana') inspectorOpen.value = true;
        }
      } catch (err) {
        console.error('Error compilando:', err);
        alert('No se pudo conectar con /api.php.');
      } finally {
        isCompiling.value = false;
      }
    };

    const descargarPdf = () => {
      if (!pdfUrl.value) return;
      const a = document.createElement('a');
      a.href = pdfUrl.value;
      a.download = activeFilename.value.replace(/\.ltx$/i, '') + '.pdf';
      a.click();
    };

    const descargarLtx = () => {
      const blob = new Blob([codigoFuente.value], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = activeFilename.value.endsWith('.ltx') ? activeFilename.value : `${activeFilename.value}.ltx`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const importarArchivoLtx = async (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      if (file.size > 65536) {
        alert('El archivo .ltx supera el límite permitido de 64 KB (65536 bytes).');
        e.target.value = '';
        return;
      }
      try {
        const texto = await file.text();
        codigoFuente.value = texto.replace(/\r\n/g, '\n');
        cerrarAutocompletado();
        persistirCambioCodigo();
        await nextTick();
        sincronizarScroll();
        actualizarPosicionCursor();
        await compilar();
      } catch (err) {
        alert('No se pudo leer el archivo .ltx seleccionado.');
      } finally {
        e.target.value = '';
      }
    };

    const dispararImportarLtx = () => {
      importLtxInputRef.value?.click();
    };

    const abrirBloqueCentral = (bloque) => {
      activeBlock.value = bloque || 'espacio';
      menuDrawerOpen.value = false;
      blockDialogOpen.value = true;
      if (!blockDialogRef.value?.open) {
        blockDialogRef.value?.showModal();
      }
    };

    const abrirOpcionesDialog = () => {
      abrirBloqueCentral('espacio');
    };

    const alternarAnalizadores = () => {
      menuDrawerOpen.value = false;
      if (layoutPrefs.analizadores === 'ventana') {
        if (blockDialogRef.value?.open && activeBlock.value === 'analizadores') {
          cerrarDialog('block');
        } else {
          abrirBloqueCentral('analizadores');
        }
      } else {
        inspectorOpen.value = !inspectorOpen.value;
      }
    };

    const irAFaseInspector = (fase) => {
      activeTab.value = fase || 'lexico';
      if (activeTab.value === 'lexico') showLexicalRef.value = false;
      if (layoutPrefs.analizadores === 'ventana') {
        abrirBloqueCentral('analizadores');
      } else {
        inspectorOpen.value = true;
      }
    };

    const cambiarUbicacionAnalizadores = (nuevaUbic) => {
      layoutPrefs.analizadores = nuevaUbic;
      if (nuevaUbic !== 'ventana') {
        if (activeBlock.value === 'analizadores') cerrarDialog('block');
        inspectorOpen.value = true;
      }
    };

    const ciclarUbicacion = (campo, opciones) => {
      const idx = opciones.indexOf(layoutPrefs[campo]);
      const sig = opciones[(idx + 1) % opciones.length];
      if (campo === 'analizadores') {
        cambiarUbicacionAnalizadores(sig);
      } else {
        layoutPrefs[campo] = sig;
      }
    };

    const etiquetaUbicacion = (val) => {
      const mapa = {
        menu: 'En menú',
        editor: 'En Editor',
        preview: 'En Vista Previa',
        superior: 'Arriba',
        inferior: 'Abajo',
        ventana: 'Ventana',
        izquierda: 'Fijo izq.'
      };
      return mapa[val] || val;
    };

    const presetActivo = computed(() => {
      if (
        layoutPrefs.insertores === 'menu' &&
        layoutPrefs.casos === 'menu' &&
        layoutPrefs.analizadores === 'ventana' &&
        layoutPrefs.archivos === 'menu' &&
        layoutPrefs.acciones === 'menu'
      ) {
        return 'enfoque';
      }
      if (
        layoutPrefs.insertores === 'editor' &&
        layoutPrefs.casos === 'editor' &&
        layoutPrefs.analizadores === 'ventana' &&
        layoutPrefs.archivos === 'menu'
      ) {
        return 'redaccion';
      }
      if (
        layoutPrefs.casos === 'editor' &&
        layoutPrefs.analizadores === 'preview' &&
        layoutPrefs.archivos === 'menu'
      ) {
        return 'compilador';
      }
      if (
        layoutPrefs.archivos === 'izquierda' &&
        layoutPrefs.insertores === 'superior' &&
        layoutPrefs.analizadores === 'inferior'
      ) {
        return 'clasico';
      }
      return '';
    });

    const aplicarPresetVista = (preset) => {
      if (preset === 'enfoque') {
        Object.assign(layoutPrefs, DEFAULT_LAYOUT_PREFS);
        explorerOpen.value = true;
        localStorage.setItem(STORAGE_EXPLORER, '1');
        inspectorOpen.value = false;
      } else if (preset === 'redaccion') {
        Object.assign(layoutPrefs, {
          insertores: 'editor',
          casos: 'editor',
          analizadores: 'ventana',
          archivos: 'menu',
          acciones: 'editor'
        });
        explorerOpen.value = false;
        localStorage.setItem(STORAGE_EXPLORER, '0');
      } else if (preset === 'compilador') {
        Object.assign(layoutPrefs, {
          insertores: 'menu',
          casos: 'editor',
          analizadores: 'preview',
          archivos: 'menu',
          acciones: 'menu'
        });
        explorerOpen.value = false;
        localStorage.setItem(STORAGE_EXPLORER, '0');
        if (activeBlock.value === 'analizadores') cerrarDialog('block');
        inspectorOpen.value = true;
      } else if (preset === 'clasico') {
        Object.assign(layoutPrefs, {
          insertores: 'superior',
          casos: 'editor',
          analizadores: 'inferior',
          archivos: 'izquierda',
          acciones: 'preview'
        });
        explorerOpen.value = true;
        localStorage.setItem(STORAGE_EXPLORER, '1');
        if (activeBlock.value === 'analizadores') cerrarDialog('block');
        inspectorOpen.value = true;
      }
    };

    const cargarTablaLexicaOficial = async () => {
      if (lexicalReference.value.length > 0) return;
      const resp = await fetch('./api.php?action=tabla');
      const data = await resp.json();
      if (data.status === 'success') lexicalReference.value = data.tabla || [];
    };

    const abrirTablaLexicaOficial = async () => {
      await cargarTablaLexicaOficial();
      activeTab.value = 'lexico';
      showLexicalRef.value = true;
      if (layoutPrefs.analizadores === 'ventana') {
        abrirBloqueCentral('analizadores');
      } else {
        cerrarDialog('block');
        inspectorOpen.value = true;
      }
    };

    const abrirTablaLexicaRapida = () => abrirTablaLexicaOficial();

    const alternarReferenciaLexica = async () => {
      if (!showLexicalRef.value) await cargarTablaLexicaOficial();
      showLexicalRef.value = !showLexicalRef.value;
    };

    const seleccionarTab = (tab) => {
      if (inspectorOpen.value && activeTab.value === tab) {
        inspectorOpen.value = false;
      } else {
        activeTab.value = tab;
        inspectorOpen.value = true;
      }
    };

    const cargarCasoDirecto = (key) => {
      const encontrado = CASOS_OFICIALES.find((c) => c.key === key);
      if (!encontrado) return;
      codigoFuente.value = encontrado.code;
      alEditarCodigo();
      caseSearchQuery.value = '';
      menuDrawerOpen.value = false;
      cerrarDialog('block');
      compilar();
    };

    const insertarDesdeModal = (snippetText) => {
      if (closeOnInsert.value) {
        cerrarDialog('block');
      }
      insertarSnippet(snippetText);
    };

    const cargarCasoSeleccionado = (cerrarMenu = false) => {
      const encontrado = CASOS_OFICIALES.find((c) => c.key === selectedCaseKey.value);
      if (!encontrado) return;
      codigoFuente.value = encontrado.code;
      alEditarCodigo();
      selectedCaseKey.value = '';
      if (cerrarMenu === true) menuDrawerOpen.value = false;
      compilar();
    };

    const insertarSnippet = async (snippet) => {
      const ta = editorRef.value;
      if (!ta) {
        codigoFuente.value += snippet;
        alEditarCodigo();
        return;
      }
      const start = ta.selectionStart ?? codigoFuente.value.length;
      const end = ta.selectionEnd ?? codigoFuente.value.length;
      codigoFuente.value = codigoFuente.value.slice(0, start) + snippet + codigoFuente.value.slice(end);
      alEditarCodigo();
      await nextTick();
      ta.focus();
      const pos = start + snippet.length;
      ta.setSelectionRange(pos, pos);
      actualizarPosicionCursor();
    };

    const actualizarPosicionCursor = () => {
      const ta = editorRef.value;
      if (!ta) return;
      const pos = ta.selectionStart || 0;
      const hastaCursor = codigoFuente.value.slice(0, pos);
      const lineas = hastaCursor.split('\n');
      cursorLn.value = lineas.length;
      cursorCol.value = lineas[lineas.length - 1].length + 1;

      // Si el cursor reposa sobre un literal #HEX en el código, sincroniza la muestra cromática
      const ventanaIni = Math.max(0, pos - 7);
      const ventanaFin = Math.min(codigoFuente.value.length, pos + 7);
      const frag = codigoFuente.value.slice(ventanaIni, ventanaFin);
      const reHex = /#[0-9a-fA-F]{6}\b/g;
      let mh;
      while ((mh = reHex.exec(frag)) !== null) {
        const absIni = ventanaIni + mh.index;
        const absFin = absIni + mh[0].length;
        if (pos >= absIni && pos <= absFin) {
          seleccionarColorPreset(mh[0], false);
          break;
        }
      }
    };

    // ── Lógica del Selector Cromático #HEX y Atajos B / I / U ─────
    const actualizarHexDesdeHsv = () => {
      const nuevo = hsvToHex(pickerHue.value, pickerSat.value, pickerVal.value);
      pickerHex.value = nuevo;
      pickerHexInput.value = nuevo;
    };

    const seleccionarColorPreset = (hexStr, cerrarCopia = true) => {
      const parsed = hexToHsv(hexStr);
      if (!parsed) return;
      pickerHue.value = parsed.h;
      pickerSat.value = parsed.s;
      pickerVal.value = parsed.v;
      pickerHex.value = parsed.hex;
      pickerHexInput.value = parsed.hex;
      if (cerrarCopia) hexCopied.value = false;
    };

    const alEscribirHexManual = (rawVal) => {
      let val = String(rawVal || '').toUpperCase();
      if (val && !val.startsWith('#')) val = '#' + val;
      pickerHexInput.value = val.slice(0, 7);
      if (/^#[0-9A-F]{6}$/.test(pickerHexInput.value)) {
        seleccionarColorPreset(pickerHexInput.value);
      }
    };

    const actualizarRuedaPorEvento = (ev) => {
      const el = colorWheelRef.value;
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      const radius = rect.width / 2;
      const dx = ev.clientX - cx;
      const dy = ev.clientY - cy;
      const angleDeg = ((Math.atan2(dy, dx) * 180) / Math.PI + 360) % 360;
      const distRatio = Math.min(1, Math.hypot(dx, dy) / radius);
      pickerHue.value = Math.round(angleDeg);
      pickerSat.value = Math.round(distRatio * 100);
      actualizarHexDesdeHsv();
    };

    const alMoverRuedaGlobal = (ev) => {
      if (!wheelDragging) return;
      actualizarRuedaPorEvento(ev);
    };

    const finalizarArrastreRueda = () => {
      wheelDragging = false;
      window.removeEventListener('pointermove', alMoverRuedaGlobal);
      window.removeEventListener('pointerup', finalizarArrastreRueda);
    };

    const iniciarArrastreRueda = (ev) => {
      wheelDragging = true;
      actualizarRuedaPorEvento(ev);
      window.addEventListener('pointermove', alMoverRuedaGlobal);
      window.addEventListener('pointerup', finalizarArrastreRueda);
    };

    const alternarSelectorColor = () => {
      colorPickerOpen.value = !colorPickerOpen.value;
      hexCopied.value = false;
    };

    const copiarHexPortapapeles = async () => {
      try {
        await navigator.clipboard.writeText(pickerHex.value);
        hexCopied.value = true;
        setTimeout(() => {
          hexCopied.value = false;
        }, 1400);
      } catch {
        hexCopied.value = false;
      }
    };

    const envolverComandoInline = async (cmd) => {
      const ta = editorRef.value;
      if (!ta) return;
      const start = ta.selectionStart ?? 0;
      const end = ta.selectionEnd ?? 0;
      const sel = codigoFuente.value.slice(start, end);
      const reemplazo = `#${cmd}{${sel}}`;
      codigoFuente.value = codigoFuente.value.slice(0, start) + reemplazo + codigoFuente.value.slice(end);
      alEditarCodigo();
      await nextTick();
      ta.focus();
      const nuevaPos = sel ? start + reemplazo.length : start + cmd.length + 2;
      ta.setSelectionRange(nuevaPos, nuevaPos);
      actualizarPosicionCursor();
    };

    const aplicarAtributoColor = async () => {
      const hex = pickerHex.value;
      const ta = editorRef.value;
      const src = codigoFuente.value;
      const start = ta ? (ta.selectionStart ?? src.length) : src.length;
      const end = ta ? (ta.selectionEnd ?? src.length) : src.length;

      // 1. Si hay texto seleccionado
      if (end > start) {
        const sel = src.slice(start, end);
        if (/^#[0-9a-fA-F]{3,6}$/.test(sel.trim())) {
          codigoFuente.value = src.slice(0, start) + hex + src.slice(end);
        } else {
          const wrapped = `#texto(color=${hex}) { ${sel} }`;
          codigoFuente.value = src.slice(0, start) + wrapped + src.slice(end);
        }
        alEditarCodigo();
        colorPickerOpen.value = false;
        if (autoCompile.value) compilar();
        return;
      }

      // 2. Si el cursor está sobre o junto a un #HEX existente en la línea actual, lo actualiza en el sitio
      const inicioLinea = src.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
      const finLineaRaw = src.indexOf('\n', start);
      const finLinea = finLineaRaw === -1 ? src.length : finLineaRaw;
      const lineaTexto = src.slice(inicioLinea, finLinea);

      const reColorAttr = /color\s*=\s*(#[0-9a-fA-F]{3,6}|[a-zA-Záéíóúñ]+)/i;
      const mAttr = reColorAttr.exec(lineaTexto);
      if (mAttr) {
        const nuevaLinea = lineaTexto.replace(reColorAttr, `color=${hex}`);
        codigoFuente.value = src.slice(0, inicioLinea) + nuevaLinea + src.slice(finLinea);
        alEditarCodigo();
        colorPickerOpen.value = false;
        await nextTick();
        if (ta) ta.focus();
        return;
      }

      // 3. Si la línea actual tiene #titulo, #texto, #fuerte, #cursiva, #subrayado o #resaltado sin atributos ()
      const reCmdSinParen = /#(titulo|texto|fuerte|cursiva|subrayado|resaltado)\s*\{/i;
      if (reCmdSinParen.test(lineaTexto)) {
        const nuevaLinea = lineaTexto.replace(reCmdSinParen, `#$1(color=${hex}) {`);
        codigoFuente.value = src.slice(0, inicioLinea) + nuevaLinea + src.slice(finLinea);
        alEditarCodigo();
        colorPickerOpen.value = false;
        await nextTick();
        if (ta) ta.focus();
        return;
      }

      // 4. Si la línea tiene #comando(...) con otros atributos, agrega color=#HEX dentro de (...)
      const reCmdConParen = /#(titulo|texto|fuerte|cursiva|subrayado|resaltado)\(([^)]*)\)/i;
      if (reCmdConParen.test(lineaTexto)) {
        const nuevaLinea = lineaTexto.replace(reCmdConParen, (_, cmd, inner) => {
          const limpio = inner.trim();
          return limpio ? `#${cmd}(${limpio}, color=${hex})` : `#${cmd}(color=${hex})`;
        });
        codigoFuente.value = src.slice(0, inicioLinea) + nuevaLinea + src.slice(finLinea);
        alEditarCodigo();
        colorPickerOpen.value = false;
        await nextTick();
        if (ta) ta.focus();
        return;
      }

      // 5. Fallback general: inserta color=#HEX en el cursor
      colorPickerOpen.value = false;
      await insertarSnippet(`color=${hex}`);
    };

    const insertarTextoConColor = async () => {
      const hex = pickerHex.value;
      const ta = editorRef.value;
      colorPickerOpen.value = false;
      if (ta && ta.selectionEnd > ta.selectionStart) {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const sel = codigoFuente.value.slice(start, end);
        const bloque = `#texto(color=${hex}) { ${sel} }`;
        codigoFuente.value = codigoFuente.value.slice(0, start) + bloque + codigoFuente.value.slice(end);
        alEditarCodigo();
        await nextTick();
        ta.focus();
        return;
      }
      const tpl = `\n#texto(color=${hex}) {\n    Texto en color\n}\n`;
      await insertarSnippet(tpl);
    };

    const insertarResaltadoConColor = async () => {
      const hex = pickerHex.value;
      const ta = editorRef.value;
      colorPickerOpen.value = false;
      if (ta && ta.selectionEnd > ta.selectionStart) {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const sel = codigoFuente.value.slice(start, end);
        const bloque = `#resaltado(color=${hex}){${sel}}`;
        codigoFuente.value = codigoFuente.value.slice(0, start) + bloque + codigoFuente.value.slice(end);
        alEditarCodigo();
        await nextTick();
        ta.focus();
        return;
      }
      await insertarSnippet(`#resaltado(color=${hex}){texto resaltado}`);
    };

    const sincronizarScroll = () => {
      const ta = editorRef.value;
      if (!ta) return;
      if (gutterRef.value) {
        gutterRef.value.scrollTop = ta.scrollTop;
      }
      if (highlightRef.value) {
        highlightRef.value.scrollTop = ta.scrollTop;
        highlightRef.value.scrollLeft = ta.scrollLeft;
      }
    };

    const alSoltarTeclaEditor = (e) => {
      actualizarPosicionCursor();
      if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
        cerrarAutocompletado();
      }
    };

    const irALinea = async (lineaObjetivo) => {
      const ta = editorRef.value;
      if (!ta || lineaObjetivo <= 0) return;
      const lineas = codigoFuente.value.split('\n');
      let pos = 0;
      for (let i = 0; i < Math.min(lineaObjetivo - 1, lineas.length); i++) {
        pos += lineas[i].length + 1;
      }
      await nextTick();
      ta.focus();
      ta.setSelectionRange(pos, pos + (lineas[lineaObjetivo - 1]?.length || 0));
      ta.scrollTop = Math.max(0, (lineaObjetivo - 3) * 20);
      sincronizarScroll();
      actualizarPosicionCursor();
    };

    const manejarAtajosEditor = (e) => {
      if (autocompleteOpen.value && autocompleteItems.value.length > 0) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          autocompleteIndex.value = (autocompleteIndex.value + 1) % autocompleteItems.value.length;
          return;
        }
        if (e.key === 'ArrowUp') {
          e.preventDefault();
          autocompleteIndex.value = (autocompleteIndex.value - 1 + autocompleteItems.value.length) % autocompleteItems.value.length;
          return;
        }
        if ((e.key === 'Enter' && !e.ctrlKey && !e.metaKey) || e.key === 'Tab') {
          e.preventDefault();
          aplicarAutocompletado(autocompleteItems.value[autocompleteIndex.value]);
          return;
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          cerrarAutocompletado();
          return;
        }
      }

      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        cerrarAutocompletado();
        compilar();
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (user.value && activeDoc.value) guardarDocumentoActual(false);
      } else if (e.key === 'Tab') {
        e.preventDefault();
        insertarSnippet('    ');
      }
    };

    // ── Modales Auth y Documentos ─────────────────────────────
    const abrirAuthDialog = (mode) => {
      authMode.value = mode;
      authError.value = '';
      authDialogRef.value?.showModal();
    };

    const enviarLogin = async () => {
      authError.value = '';
      authSubmitting.value = true;
      try {
        await apiJson('./auth.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(loginForm.value)
        });
        loginForm.value.password = '';
        cerrarDialog('auth');
        await cargarSesionYDocumentos();
      } catch (err) {
        authError.value = err.message;
      } finally {
        authSubmitting.value = false;
      }
    };

    const enviarRegistro = async () => {
      authError.value = '';
      authSubmitting.value = true;
      const borradorInvitadoPrevio = !user.value ? codigoFuente.value : '';
      const tieneBorradorPersonalizado =
        borradorInvitadoPrevio.trim() !== '' && borradorInvitadoPrevio.trim() !== DEFAULT_GUEST_CODE.trim();

      try {
        await apiJson('./auth.php?action=register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(registerForm.value)
        });
        registerForm.value.password = '';
        cerrarDialog('auth');
        await cargarSesionYDocumentos();

        if (tieneBorradorPersonalizado && user.value && quota.value.usados < quota.value.maximo) {
          try {
            const importado = await apiJson('./projects.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                titulo: 'Borrador Importado (Invitado)',
                nombre_archivo: 'borrador_invitado.ltx',
                contenido_ltx: borradorInvitadoPrevio
              })
            });
            localStorage.removeItem(STORAGE_GUEST_DRAFT);
            await recargarListaDocumentos(false);
            await seleccionarDocumento(importado.documento.id, true);
          } catch (importErr) {
            console.warn('No se pudo migrar automáticamente el borrador de invitado:', importErr.message);
          }
        }
      } catch (err) {
        authError.value = err.message;
      } finally {
        authSubmitting.value = false;
      }
    };

    const cerrarSesion = async () => {
      try {
        await apiJson('./auth.php?action=logout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
      } catch (err) {
        console.warn(err);
      }
      user.value = null;
      documents.value = [];
      activeDoc.value = null;
      codigoFuente.value = localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE;
      await compilar();
    };

    const abrirNuevoDocDialog = () => {
      docModalError.value = '';
      const siguiente = quota.value.usados + 1;
      newDocForm.value = {
        titulo: `Documento ${siguiente}`,
        nombre_archivo: `documento_${siguiente}.ltx`,
        plantilla: 'academico'
      };
      newDocDialogRef.value?.showModal();
    };

    const confirmarNuevoDoc = async () => {
      docModalError.value = '';
      const t = newDocForm.value.titulo.trim();
      let contenido = `#titulo { ${t} }\n#texto { Contenido inicial. }\n`;
      if (newDocForm.value.plantilla === 'academico') {
        contenido = `#titulo(color=#1A73E8) { ${t} }\n#autor { ${user.value?.nombre || 'Autor'} }\n#seccion(id=intro) { Introducción }\n#texto(justificado) {\n    Redacta aquí el desarrollo de tu informe académico.\n}\n#caja(tipo=info) { Referencia activa hacia #referencia(intro). }\n`;
      } else if (newDocForm.value.plantilla === 'matematico') {
        contenido = `#titulo { ${t} }\n#seccion { Desarrollo Matemático }\n#texto { Sea la función cuadrática: }\n#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {y - 2} } = 0\n}\n`;
      }
      try {
        const res = await apiJson('./projects.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            titulo: t,
            nombre_archivo: newDocForm.value.nombre_archivo,
            contenido_ltx: contenido
          })
        });
        cerrarDialog('newDoc');
        await recargarListaDocumentos(false);
        await seleccionarDocumento(res.documento.id, true);
      } catch (err) {
        docModalError.value = err.message;
      }
    };

    const abrirRenombrarDialog = (doc) => {
      docModalError.value = '';
      renameDocForm.value = {
        id: doc.id,
        titulo: doc.titulo,
        nombre_archivo: doc.nombre_archivo
      };
      renameDocDialogRef.value?.showModal();
    };

    const confirmarRenombrarDoc = async () => {
      docModalError.value = '';
      try {
        const res = await apiJson(`./projects.php?id=${renameDocForm.value.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            titulo: renameDocForm.value.titulo,
            nombre_archivo: renameDocForm.value.nombre_archivo
          })
        });
        if (activeDoc.value && activeDoc.value.id === res.documento.id) {
          activeDoc.value = res.documento;
        }
        await recargarListaDocumentos(false);
        cerrarDialog('renameDoc');
      } catch (err) {
        docModalError.value = err.message;
      }
    };

    const abrirEliminarDialog = (doc) => {
      deleteTarget.value = doc;
      confirmDeleteDialogRef.value?.showModal();
    };

    const confirmarEliminarDoc = async () => {
      if (!deleteTarget.value) return;
      const targetId = deleteTarget.value.id;
      try {
        await apiJson(`./projects.php?id=${targetId}`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        cerrarDialog('confirmDelete');
        deleteTarget.value = null;
        await recargarListaDocumentos(activeDoc.value?.id === targetId);
      } catch (err) {
        alert(err.message);
      }
    };

    const subirImagen = async (e) => {
      const files = e.target.files;
      if (!files || files.length === 0) return;

      if (user.value && activeDoc.value) {
        const fd = new FormData();
        fd.append('imagen', files[0]);
        fd.append('csrfToken', csrfToken.value);
        try {
          const res = await fetch(`./projects.php?action=upload_image&id=${activeDoc.value.id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrfToken.value },
            body: fd
          });
          const data = await res.json();
          if (!res.ok) throw new Error(data.message || 'No se pudo subir la imagen.');
          activeDoc.value.imagenes = [...activeImages.value, data.imagen];
        } catch (err) {
          alert(err.message);
        }
      } else {
        for (let i = 0; i < files.length; i++) guestImages.value.push(files[i]);
      }
      e.target.value = '';
    };

    const eliminarImagenDocumento = async (imageId) => {
      if (!user.value || !activeDoc.value) return;
      try {
        await apiJson(`./projects.php?action=delete_image&id=${activeDoc.value.id}&imageId=${imageId}`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        activeDoc.value.imagenes = activeImages.value.filter((img) => img.id !== imageId);
      } catch (err) {
        alert(err.message);
      }
    };

    watch(codigoFuente, () => {
      nextTick(sincronizarScroll);
    });

    watch(
      layoutPrefs,
      () => {
        localStorage.setItem(STORAGE_LAYOUT_PREFS, JSON.stringify(layoutPrefs));
      },
      { deep: true }
    );

    const manejarEscapeGlobal = (e) => {
      if (e.key === 'Escape') {
        if (colorPickerOpen.value) colorPickerOpen.value = false;
        if (menuDrawerOpen.value) menuDrawerOpen.value = false;
      }
    };

    const manejarClicFueraPopover = (e) => {
      if (!colorPickerOpen.value) return;
      if (e.target?.closest?.('.color-trigger-pill')) return;
      if (colorPopoverRef.value && !colorPopoverRef.value.contains(e.target)) {
        colorPickerOpen.value = false;
      }
    };

    onMounted(async () => {
      window.addEventListener('keydown', manejarEscapeGlobal);
      window.addEventListener('pointerdown', manejarClicFueraPopover);
      Object.values(dialogMap()).forEach((dlg) => {
        if (!dlg) return;
        installLightDismissFallback(dlg);
        dlg.addEventListener('close', () => {
          dlg.returnValue = '';
        });
      });

      await cargarSesionYDocumentos();
      if (!compileResult.value && codigoFuente.value.trim()) {
        await compilar();
      }
    });

    onUnmounted(() => {
      window.removeEventListener('keydown', manejarEscapeGlobal);
      window.removeEventListener('pointerdown', manejarClicFueraPopover);
      finalizarArrastreRueda();
      if (autosaveTimer) clearTimeout(autosaveTimer);
      if (autocompileTimer) clearTimeout(autocompileTimer);
      if (blurAutocompleteTimer) clearTimeout(blurAutocompleteTimer);
      if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
    });

    const ideContext = {
      theme,
      explorerOpen,
      resourcesExpanded,
      outlineExpanded,
      previewFullscreen,
      compileDurationText,
      sidebarOpen,
      menuDrawerOpen,
      layoutPrefs,
      presetActivo,
      inspectorOpen,
      activeTab,
      showLexicalRef,
      lexicalReference,
      user,
      userInitials,
      quota,
      documents,
      activeDoc,
      activeDocTitle,
      activeDocSubtitle,
      documentoEstructura,
      saveState,
      isSaving,
      guestImages,
      activeImages,
      activeFilename,
      codigoFuente,
      codigoResaltadoHtml,
      autoCompile,
      importLtxInputRef,
      totalLineas,
      totalPalabras,
      codigoBytes,
      cursorLn,
      cursorCol,
      editorRef,
      gutterRef,
      highlightRef,
      colorPickerOpen,
      colorPopoverRef,
      colorWheelRef,
      pickerHue,
      pickerSat,
      pickerVal,
      pickerHex,
      pickerHexInput,
      hexCopied,
      wheelThumbPos,
      colorPresets: COLOR_PRESETS,
      autocompleteOpen,
      autocompleteItems,
      autocompleteIndex,
      selectedCaseKey,
      casosDePrueba: CASOS_OFICIALES,
      quickSnippets: QUICK_SNIPPETS,
      mathSymbols: MATH_SYMBOLS,
      isCompiling,
      compileResult,
      pdfUrl,
      previewZoom,
      paginasDocumento,
      tieneElementosDocumento,
      pdfDownloadBase64,
      ejecucionInspector,
      tokensAnalizados,
      sintacticoPasos,
      tablaSimbolos,
      advertenciasSemanticas,
      errorPanel,
      errorLine,
      compilePillClass,
      compileStatusText,
      previewData,
      authDialogRef,
      newDocDialogRef,
      renameDocDialogRef,
      confirmDeleteDialogRef,
      blockDialogRef,
      activeBlock,
      blockDialogOpen,
      tituloBloqueActivo,
      caseSearchQuery,
      casosFiltrados,
      closeOnInsert,
      authMode,
      authError,
      authSubmitting,
      showLoginPass,
      showRegPass,
      loginForm,
      registerForm,
      docModalError,
      newDocForm,
      renameDocForm,
      deleteTarget,
      cerrarDialog,
      estadoFaseClass,
      colorDeToken,
      badgeStyle,
      seleccionarDocumento,
      guardarDocumentoActual,
      alEditarCodigo,
      compilar,
      alternarAutoCompilar,
      descargarPdf,
      ajustarZoom,
      fijarZoomPorcentaje,
      resetZoom,
      cambiarTema,
      alternarTema,
      alternarExplorador,
      alClicAvatar,
      svgRgb,
      svgPoints,
      svgFontProps,
      resolverImagenPreview,
      descargarLtx,
      importarArchivoLtx,
      dispararImportarLtx,
      abrirBloqueCentral,
      abrirOpcionesDialog,
      alternarAnalizadores,
      irAFaseInspector,
      cambiarUbicacionAnalizadores,
      ciclarUbicacion,
      etiquetaUbicacion,
      aplicarPresetVista,
      abrirTablaLexicaOficial,
      abrirTablaLexicaRapida,
      alternarReferenciaLexica,
      seleccionarTab,
      cargarCasoDirecto,
      cargarCasoSeleccionado,
      insertarDesdeModal,
      insertarSnippet,
      actualizarHexDesdeHsv,
      seleccionarColorPreset,
      alEscribirHexManual,
      iniciarArrastreRueda,
      alternarSelectorColor,
      copiarHexPortapapeles,
      envolverComandoInline,
      aplicarAtributoColor,
      insertarTextoConColor,
      insertarResaltadoConColor,
      actualizarPosicionCursor,
      alSoltarTeclaEditor,
      sincronizarScroll,
      irALinea,
      manejarAtajosEditor,
      cerrarAutocompletado,
      programarCierreAutocompletado,
      aplicarAutocompletado,
      abrirAuthDialog,
      enviarLogin,
      enviarRegistro,
      cerrarSesion,
      abrirNuevoDocDialog,
      confirmarNuevoDoc,
      abrirRenombrarDialog,
      confirmarRenombrarDoc,
      abrirEliminarDialog,
      confirmarEliminarDoc,
      subirImagen,
      eliminarImagenDocumento
    };

    provide('ide', ideContext);
    return ideContext;
  }
});

app.component('barra-insertores', {
  template: '#tpl-barra-insertores',
  setup() {
    return inject('ide');
  }
});

app.component('selector-casos', {
  props: {
    cerrarMenuAlElegir: { type: Boolean, default: false }
  },
  template: '#tpl-selector-casos',
  setup() {
    return inject('ide');
  }
});

app.component('acciones-archivo', {
  template: '#tpl-acciones-archivo',
  setup() {
    return inject('ide');
  }
});

app.component('panel-archivos', {
  template: '#tpl-panel-archivos',
  setup() {
    return inject('ide');
  }
});

app.component('inspector-fases', {
  props: {
    ubicacion: { type: String, default: 'ventana' },
    forzarAbierto: { type: Boolean, default: false }
  },
  template: '#tpl-inspector-fases',
  setup() {
    return inject('ide');
  }
});

app.mount('#app');

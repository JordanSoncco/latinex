const { createApp, ref, computed, onMounted, onUnmounted, watch, nextTick } = Vue;

const STORAGE_GUEST_DRAFT = 'latinex_guest_draft_v1';
const MAX_HISTORY = 150;

if (window.pdfjsLib) {
  window.pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

const DEFAULT_GUEST_CODE = `// Bienvenido a LATINEX Cloud (Modo Invitado)
#definir(curso) { Compiladores — UNAP Puno }
#titulo(color=#1A73E8) { Prototipo LATINEX Cloud }
#autor { Equipo Grupo 3 }

#seccion(id=intro) { Introducción }
#texto(justificado) {
    LATINEX traduce un lenguaje de marcado en español directamente a PDF sin librerías externas, ejecutando cuatro fases: Léxico, Sintáctico, Semántico y Ejecución.
}

#lista(punto) {
    {Curso: #imprimir(curso)}
    {Compilación en 2 pasadas con referencias cruzadas en #referencia(intro)}
    {Entorno web estilo Overleaf en PHP 8.5 + Docker + AWS EC2}
}

#caja(tipo=info) { Selecciona o haz doble clic sobre cualquier texto en la hoja A4 para saltar al código que lo generó. }`;

const CASOS_OFICIALES = [
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
  { key: 'err_sint', label: 'Demo Error Sintáctico: falta llave {', code: `#texto {\n    Bloque sin llave de cierre` },
  { key: 'err_1500', label: 'Demo Error Semántico 1500: Macro faltante', code: `#texto { Curso de #imprimir(macroInexistente). }` },
  { key: 'err_1501', label: 'Demo Error Semántico 1501: Ref. inexistente', code: `#texto { Ver la #referencia(etiqueta_perdida). }` }
];

const QUICK_SNIPPET_GROUPS = [
  {
    titulo: 'Estructura',
    items: [
      { label: '#titulo', desc: 'Título principal del documento', code: `\n#titulo(color=#1A73E8) { Título del Documento }\n` },
      { label: '#autor', desc: 'Autor o equipo', code: `\n#autor { Nombre del Autor }\n` },
      { label: '#fecha', desc: 'Fecha del documento', code: `\n#fecha { Noviembre 2026 }\n` },
      { label: '#resumen', desc: 'Resumen o abstract', code: `\n#resumen {\n    Síntesis breve del documento.\n}\n` },
      { label: '#seccion', desc: 'Sección nivel 1 con id', code: `\n#seccion(id=sec1) { Nueva Sección }\n` },
      { label: '#subseccion', desc: 'Subsección nivel 2', code: `\n#subseccion { Subsección }\n` },
      { label: '#subseccion2', desc: 'Subsección nivel 3', code: `\n#subseccion2 { Apartado }\n` },
      { label: '#texto', desc: 'Párrafo justificado', code: `\n#texto(justificado) {\n    Escribe aquí tu párrafo.\n}\n` }
    ]
  },
  {
    titulo: 'Formato',
    items: [
      { label: '#fuerte', desc: 'Texto en negrita', code: `#fuerte{negrita}` },
      { label: '#cursiva', desc: 'Texto en cursiva', code: `#cursiva{cursiva}` },
      { label: '#subrayado', desc: 'Texto subrayado', code: `#subrayado{subrayado}` },
      { label: '#resaltado', desc: 'Texto con fondo resaltado', code: `#resaltado(color=#FFFF00){destacado}` }
    ]
  },
  {
    titulo: 'Datos y Tablas',
    items: [
      { label: '#lista(punto)', desc: 'Lista con viñetas', code: `\n#lista(punto) {\n    {Primer elemento}\n    {Segundo elemento}\n}\n` },
      { label: '#lista(numero)', desc: 'Lista numerada', code: `\n#lista(numero) {\n    {Paso uno}\n    {Paso dos}\n}\n` },
      { label: '#tabla', desc: 'Tabla con bordes y encabezado', code: `\n#tabla(bordes=verdadero) {\n    #fila(fondo=gris) { #celda{Columna 1} #celda{Columna 2} }\n    #fila { #celda{Dato 1} #celda{Dato 2} }\n}\n` },
      { label: '#imagen', desc: 'Insertar imagen JPEG', code: `\n#imagen(ruta=logo.jpg, ancho=50%, alto=auto, alineacion=centro)\n` }
    ]
  },
  {
    titulo: 'Matemáticas',
    items: [
      { label: '#superindice', desc: 'Superíndice (ej. m²)', code: `#superindice{2}` },
      { label: '#subindice', desc: 'Subíndice (ej. H₂O)', code: `#subindice{2}` },
      { label: '#fraccion', desc: 'Fracción matemática', code: `#fraccion { {x+1} {y-2} }` },
      { label: '#ecuacion', desc: 'Ecuación numerada en bloque', code: `\n#ecuacion {\n    #fraccion { {x#superindice{2} + 1} {2} } = 0\n}\n` }
    ]
  },
  {
    titulo: 'Control, Cajas y Macros',
    items: [
      { label: '#caja(info)', desc: 'Caja informativa', code: `\n#caja(tipo=info) { Nota informativa para el lector. }\n` },
      { label: '#caja(alerta)', desc: 'Caja de advertencia', code: `\n#caja(tipo=alerta) { Advertencia importante. }\n` },
      { label: '#caja(exito)', desc: 'Caja de confirmación', code: `\n#caja(tipo=exito) { Validación completada correctamente. }\n` },
      { label: '#salto', desc: 'Salto de línea explícito', code: ` #salto ` },
      { label: '#nuevapagina', desc: 'Salto de página explícito', code: `\n#nuevapagina\n` },
      { label: '#definir', desc: 'Definir macro', code: `\n#definir(miMacro) { Valor }\n` },
      { label: '#imprimir', desc: 'Invocar macro', code: `#imprimir(miMacro)` },
      { label: '#referencia', desc: 'Referencia cruzada a sección', code: `#referencia(sec1)` },
      { label: '#indicegeneral', desc: 'Índice automático', code: `\n#indicegeneral\n` }
    ]
  }
];

const PRESET_COLORS = [
  { hex: '#1A73E8', name: 'Azul Institucional' },
  { hex: '#DC2626', name: 'Rojo Alerta' },
  { hex: '#16A34A', name: 'Verde Éxito' },
  { hex: '#D97706', name: 'Ámbar Destacado' },
  { hex: '#7C3AED', name: 'Violeta Académico' },
  { hex: '#0891B2', name: 'Cian Técnico' },
  { hex: '#FFFF00', name: 'Amarillo Resaltador' },
  { hex: '#0F172A', name: 'Negro Pizarra' }
];

const TOKEN_COLORS = {
  simbolo: '#94a3b8',
  operador: '#f59e0b',
  separador: '#f59e0b',
  comando_estructura: '#60a5fa',
  comando_formato: '#a78bfa',
  comando_datos: '#34d399',
  comando_tabla: '#2dd4bf',
  comando_matematica: '#f472b6',
  comando_control: '#fb923c',
  comando_macro: '#facc15',
  comando_referencia: '#c084fc',
  comando_entorno: '#f87171',
  atributo: '#93c5fd',
  valor_atributo: '#86efac',
  literal_color: '#fde68a',
  literal_numero: '#6ee7b7',
  literal_cadena: '#fca5a5',
  literal_archivo: '#d9f99d',
  texto: '#f8fafc',
  comentario: '#64748b',
  escape: '#e879f9',
  identificador: '#cbd5e1',
  fin: '#475569',
  error: '#ef4444'
};

// ── Conversión matemática HSV <-> #RRGGBB para la rueda cromática ──
function hsvToRgb(h, s, v) {
  const sn = s / 100;
  const vn = v / 100;
  const c = vn * sn;
  const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
  const m = vn - c;
  let r1 = 0, g1 = 0, b1 = 0;
  if (h < 60) [r1, g1, b1] = [c, x, 0];
  else if (h < 120) [r1, g1, b1] = [x, c, 0];
  else if (h < 180) [r1, g1, b1] = [0, c, x];
  else if (h < 240) [r1, g1, b1] = [0, x, c];
  else if (h < 300) [r1, g1, b1] = [x, 0, c];
  else [r1, g1, b1] = [c, 0, x];
  return [
    Math.round((r1 + m) * 255),
    Math.round((g1 + m) * 255),
    Math.round((b1 + m) * 255)
  ];
}

function rgbToHex(r, g, b) {
  return (
    '#' +
    [r, g, b]
      .map((n) => Math.max(0, Math.min(255, n)).toString(16).padStart(2, '0'))
      .join('')
      .toUpperCase()
  );
}

function hexToHsv(hex) {
  let clean = hex.replace(/^#/, '');
  if (clean.length === 3) {
    clean = clean.split('').map((c) => c + c).join('');
  }
  if (!/^[0-9A-Fa-f]{6}$/.test(clean)) return { h: 214, s: 89, v: 91 };
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
  return { h: Math.round(h), s: Math.round(s), v: Math.max(10, Math.round(v)) };
}

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

createApp({
  setup() {
    const sidebarOpen = ref(true);
    const snippetsPopoverRef = ref(null);
    const inspectorOpen = ref(false);
    const activeTab = ref('lexico');
    const showLexicalRef = ref(false);
    const lexicalReference = ref([]);
    const lexicalSearch = ref('');

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

    const initialDraft = localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE;
    const codigoFuente = ref(initialDraft);
    const selectedCaseKey = ref('');
    const isCompiling = ref(false);
    const compileResult = ref(null);
    const pdfUrl = ref(null);
    const justCompiledPulse = ref(false);

    // ── Pila Transaccional Undo / Redo (preserva Ctrl+Z y Ctrl+Y siempre) ──
    const undoStack = ref([]);
    const redoStack = ref([]);
    let lastCommittedSnapshot = { code: initialDraft, start: 0, end: 0 };
    let typingHistoryTimer = null;

    const cursorLn = ref(1);
    const cursorCol = ref(1);
    const syncHighlightLine = ref(0);
    const editorRef = ref(null);
    const gutterRef = ref(null);
    const highlightRef = ref(null);

    // ── Estado Visor PDF.js + SyncTeX ──
    const previewStageRef = ref(null);
    const pdfjsFallback = ref(!window.pdfjsLib);
    const pdfPagesCount = ref(0);
    const currentPage = ref(1);
    const pdfZoom = ref(1.0);
    const activeSyncHit = ref(null);
    const pdfCanvasMap = new Map();
    let pdfDocInstance = null;
    let activeCompileAbort = null;
    let renderTokenCounter = 0;
    let syncFlashTimer = null;
    let pdfSyncFlashTimer = null;

    // ── Estado Rueda Cromática HSV (#HEX) ──
    const colorPopoverRef = ref(null);
    const colorWheelCanvasRef = ref(null);
    const pickerHue = ref(214);
    const pickerSat = ref(89);
    const pickerVal = ref(91);
    const pickerHexInput = ref('#1A73E8');
    const hexCopiado = ref(false);
    let wheelBaseImage = null;
    let wheelCachedVal = -1;
    let targetHexRangeInEditor = null;

    // ── Modales ──
    const authDialogRef = ref(null);
    const newDocDialogRef = ref(null);
    const renameDocDialogRef = ref(null);
    const confirmDeleteDialogRef = ref(null);

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

    const dialogMap = () => ({
      auth: authDialogRef.value,
      newDoc: newDocDialogRef.value,
      renameDoc: renameDocDialogRef.value,
      confirmDelete: confirmDeleteDialogRef.value
    });

    const cerrarDialog = (name) => {
      const dlg = dialogMap()[name];
      if (dlg && dlg.open) dlg.close();
    };

    const pickerHex = computed(() => {
      const [r, g, b] = hsvToRgb(pickerHue.value, pickerSat.value, pickerVal.value);
      return rgbToHex(r, g, b);
    });

    const canUndo = computed(() => undoStack.value.length > 0);
    const canRedo = computed(() => redoStack.value.length > 0);
    const undoCount = computed(() => undoStack.value.length);

    const totalLineas = computed(() => Math.max(1, codigoFuente.value.split('\n').length));
    const codigoBytes = computed(() => new TextEncoder().encode(codigoFuente.value).length);

    // Resaltado de sintaxis LATINEX en tiempo real estilo Overleaf (single-pass seguro)
    const CMD_CLASS_MAP = {
      titulo: 'hl-cmd-struct', autor: 'hl-cmd-struct', fecha: 'hl-cmd-struct', resumen: 'hl-cmd-struct',
      seccion: 'hl-cmd-struct', subseccion: 'hl-cmd-struct', subseccion2: 'hl-cmd-struct',
      subseccion3: 'hl-cmd-struct', subseccion4: 'hl-cmd-struct', texto: 'hl-cmd-struct', indicegeneral: 'hl-cmd-struct',
      fuerte: 'hl-cmd-fmt', cursiva: 'hl-cmd-fmt', subrayado: 'hl-cmd-fmt', resaltado: 'hl-cmd-fmt',
      lista: 'hl-cmd-data', imagen: 'hl-cmd-data', tabla: 'hl-cmd-data', fila: 'hl-cmd-data', celda: 'hl-cmd-data', caja: 'hl-cmd-data',
      superindice: 'hl-cmd-math', subindice: 'hl-cmd-math', fraccion: 'hl-cmd-math', ecuacion: 'hl-cmd-math',
      salto: 'hl-cmd-ctrl', nuevapagina: 'hl-cmd-ctrl', definir: 'hl-cmd-ctrl', imprimir: 'hl-cmd-ctrl', referencia: 'hl-cmd-ctrl'
    };

    const highlightedCode = computed(() => {
      const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      const tokenRe = /(\/\/.*$|\\[#{}]|#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})(?![0-9A-Fa-f])|#[a-zA-Z0-9_áéíóúñÁÉÍÓÚÑ]+|\b(?:color|tamaño|tamano|fuente|alineacion|ruta|ancho|alto|bordes|fondo|tipo|numerador|denominador|id)\b(?=\s*=)|\b(?:punto|numero|izquierda|centrado|centro|derecha|justificado|verdadero|falso|auto|alerta|info|éxito|exito|rojo|azul|verde|negro|blanco|amarillo|gris)\b|[{}()])/gi;

      const lines = codigoFuente.value.split('\n');
      const rendered = lines.map((line) => {
        let out = '';
        let last = 0;
        tokenRe.lastIndex = 0;
        let m;
        while ((m = tokenRe.exec(line)) !== null) {
          if (m.index > last) out += esc(line.slice(last, m.index));
          const tok = m[0];
          if (tok.startsWith('//')) {
            out += `<span class="hl-comment">${esc(tok)}</span>`;
          } else if (tok.startsWith('\\')) {
            out += `<span class="hl-escape">${esc(tok)}</span>`;
          } else if (/^#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/.test(tok)) {
            out += `<span class="hl-hex" style="border-bottom: 2px solid ${tok};">${esc(tok)}</span>`;
          } else if (tok.startsWith('#')) {
            const cmdCls = CMD_CLASS_MAP[tok.slice(1).toLowerCase()];
            out += cmdCls ? `<span class="${cmdCls}">${esc(tok)}</span>` : esc(tok);
          } else if (tok === '{' || tok === '}' || tok === '(' || tok === ')') {
            out += `<span class="hl-punct">${tok}</span>`;
          } else if (/^(?:color|tamaño|tamano|fuente|alineacion|ruta|ancho|alto|bordes|fondo|tipo|numerador|denominador|id)$/i.test(tok)) {
            out += `<span class="hl-attr">${esc(tok)}</span>`;
          } else {
            out += `<span class="hl-val">${esc(tok)}</span>`;
          }
          last = m.index + tok.length;
        }
        if (last < line.length) out += esc(line.slice(last));
        return out;
      });

      return rendered.join('\n') + (codigoFuente.value.endsWith('\n') ? ' ' : '');
    });

    const activeFilename = computed(() => {
      if (user.value && activeDoc.value) return activeDoc.value.nombre_archivo;
      return 'borrador_invitado.ltx';
    });

    const activeImages = computed(() => activeDoc.value?.imagenes || []);
    const tokensAnalizados = computed(() => compileResult.value?.lexico?.tokens || []);
    const sintacticoPasos = computed(() => compileResult.value?.sintactico?.transiciones || []);
    const tablaSimbolos = computed(() => compileResult.value?.semantico?.tablaSimbolos || []);
    const pdfDownloadBase64 = computed(() => compileResult.value?.ejecucion?.pdfBase64 || null);

    const lexicalCount = computed(() => lexicalReference.value.length || 79);

    const filteredLexicalReference = computed(() => {
      const q = lexicalSearch.value.trim().toLowerCase();
      if (!q) return lexicalReference.value;
      return lexicalReference.value.filter((item) =>
        String(item.token).includes(q) ||
        (item.nombre || '').toLowerCase().includes(q) ||
        (item.lexema || '').toLowerCase().includes(q) ||
        (item.categoria || '').toLowerCase().includes(q) ||
        (item.descripcion || '').toLowerCase().includes(q)
      );
    });

    // Detecta si la línea donde está el cursor tiene un color #HEX para ofrecer edición en 1 clic
    const lineaActivaHex = computed(() => {
      const lineas = codigoFuente.value.split('\n');
      const actual = lineas[cursorLn.value - 1] || '';
      const m = actual.match(/#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})\b/);
      return m ? m[0].toUpperCase() : null;
    });

    // Esquema en vivo del documento (File outline tipo Overleaf)
    const documentOutline = computed(() => {
      const items = [];
      const lines = codigoFuente.value.split('\n');
      const re = /#(titulo|seccion|subseccion([2-4])?)(?:\([^)]*\))?\s*\{([^}]*)/gi;

      for (let i = 0; i < lines.length; i++) {
        const rawLine = lines[i];
        const commentIdx = rawLine.indexOf('//');
        const cleanLine = commentIdx >= 0 ? rawLine.slice(0, commentIdx) : rawLine;
        if (!cleanLine.includes('#')) continue;

        re.lastIndex = 0;
        let m;
        while ((m = re.exec(cleanLine)) !== null) {
          const cmd = m[1].toLowerCase();
          let level = 1;
          let badge = '§';
          if (cmd === 'titulo') {
            level = 0;
            badge = 'T';
          } else if (cmd === 'subseccion') {
            level = 2;
            badge = '1.1';
          } else if (cmd.startsWith('subseccion')) {
            level = Math.min(4, Number(m[2] || 2) + 1);
            badge = '▸';
          }
          const titleClean = (m[3] || '')
            .replace(/#[a-zA-Z0-9_]+(?:\([^)]*\))?\s*\{?/g, '')
            .replace(/[{}]/g, '')
            .trim();
          items.push({
            level,
            badge,
            title: titleClean || `#${cmd}`,
            line: i + 1
          });
        }
      }
      return items;
    });

    // Tarjetas KPI y tabla geométrica para la pestaña 4 (Ejecución)
    const ejecucionResumenKpis = computed(() => {
      const ej = compileResult.value?.ejecucion;
      if (!ej) {
        return [
          { label: 'Motor Two-Pass', value: 'Sin ejecutar' },
          { label: 'Páginas A4', value: '0' },
          { label: 'Objetos %PDF-1.4', value: '0' },
          { label: 'Tamaño Binario', value: '0 KB' }
        ];
      }
      const bytes = ej.pdfStats?.bytes || 0;
      return [
        { label: 'Motor Two-Pass', value: ej.estado || '—' },
        { label: 'Páginas A4 (595×842)', value: String(ej.pdfStats?.paginas ?? ej.resumen?.totalPaginas ?? 0) },
        { label: 'Objetos %PDF-1.4', value: String(ej.pdfStats?.objetosPdf ?? 0) },
        { label: 'Tamaño Binario', value: bytes > 0 ? `${(bytes / 1024).toFixed(2)} KB` : '0 KB' }
      ];
    });

    const ejecucionElementosLista = computed(() => {
      const elems = compileResult.value?.ejecucion?.figurasOElementos || [];
      return elems.map((el) => {
        let coords = '';
        let detalle = '';
        if (el.tipo === 'text') {
          coords = `X=${el.x}, Y=${el.yLogica}`;
          detalle = `${el.fuente} (${el.tamano}pt): "${el.texto}"`;
        } else if (el.tipo === 'rect') {
          coords = `X=${el.x}, Y=${el.yLogica} (${el.ancho}×${el.alto})`;
          detalle = el.relleno ? 'Rectángulo con relleno' : 'Contorno vectorial';
        } else if (el.tipo === 'image') {
          coords = `X=${el.x}, Y=${el.yLogica} (${el.ancho}×${el.alto})`;
          detalle = `JPEG /DCTDecode: ${el.nombre} (${el.pxAncho}×${el.pxAlto}px)`;
        } else if (el.tipo === 'line') {
          coords = `(${el.x1},${el.y1Logica}) → (${el.x2},${el.y2Logica})`;
          detalle = `Trazo ${el.grosor}pt`;
        } else {
          coords = `Polígono (${(el.puntos || []).length} vértices)`;
          detalle = 'Ícono vectorial nativo PDF';
        }
        return {
          pagina: el.pagina || 1,
          tipo: el.tipo || 'ir',
          coords,
          detalle,
          linea: Number(el.linea || 0)
        };
      });
    });

    // Mapa de regiones clicables SyncTeX sin sobreposición sobre cada hoja A4 (595 x 842 pt)
    const syncHitsAll = computed(() => {
      const elems = compileResult.value?.ejecucion?.figurasOElementos || [];
      const rawHits = [];

      elems.forEach((el, idx) => {
        const linea = Number(el.linea || 0);
        if (el.tipo === 'text' && el.texto && el.texto.trim()) {
          const size = Number(el.tamano || 12);
          const xPt = Number(el.x || 50);
          const yBase = Number(el.yLogica || 60);
          const topPt = Math.max(0, yBase - size * 0.88);
          const heightPt = Math.max(10, size * 1.22);
          const rawWidth = Number(el.ancho || 0) > 0
            ? Number(el.ancho)
            : Math.max(12, el.texto.length * size * 0.48);
          const widthPt = Math.min(595 - xPt - 10, Math.max(8, rawWidth));

          rawHits.push({
            id: `hit-${idx}`,
            kind: 'text',
            pagina: Number(el.pagina || 1),
            xPt,
            topPt,
            widthPt,
            heightPt,
            yLogica: yBase,
            texto: el.texto.trim(),
            linea: linea > 0 ? linea : inferirLineaPorTexto(el.texto)
          });
        } else if (el.tipo === 'image') {
          rawHits.push({
            id: `hit-${idx}`,
            kind: 'image',
            pagina: Number(el.pagina || 1),
            xPt: Number(el.x || 50),
            topPt: Number(el.yLogica || 50),
            widthPt: Number(el.ancho || 120),
            heightPt: Number(el.alto || 80),
            yLogica: Number(el.yLogica || 50),
            texto: `#imagen(${el.nombre || 'jpg'})`,
            linea: linea > 0 ? linea : inferirLineaPorTexto(el.nombre || '#imagen')
          });
        }
      });

      // Evitar cualquier sobreposición entre fragmentos contiguos en la misma línea base
      const textHits = rawHits.filter((h) => h.kind === 'text');
      const byPage = new Map();
      for (const h of textHits) {
        if (!byPage.has(h.pagina)) byPage.set(h.pagina, []);
        byPage.get(h.pagina).push(h);
      }

      for (const list of byPage.values()) {
        list.sort((a, b) => (a.yLogica - b.yLogica) || (a.xPt - b.xPt));
        for (let i = 0; i < list.length; i++) {
          const cur = list[i];
          for (let j = i + 1; j < list.length; j++) {
            const nxt = list[j];
            if (Math.abs(nxt.yLogica - cur.yLogica) >= 4.0) break;
            if (nxt.xPt > cur.xPt) {
              cur.widthPt = Math.max(4, Math.min(cur.widthPt, nxt.xPt - cur.xPt - 0.5));
              break;
            }
          }
        }
      }

      return rawHits.map((h) => ({
        id: h.id,
        pagina: h.pagina,
        leftPct: (h.xPt / 595) * 100,
        topPct: (h.topPt / 842) * 100,
        widthPct: (h.widthPt / 595) * 100,
        heightPct: (h.heightPt / 842) * 100,
        yLogica: h.yLogica,
        texto: h.texto,
        linea: h.linea
      }));
    });

    function inferirLineaPorTexto(fragmento) {
      const needle = (fragmento || '').trim().slice(0, 24).toLowerCase();
      if (!needle) return 1;
      const lines = codigoFuente.value.split('\n');
      for (let i = 0; i < lines.length; i++) {
        if (lines[i].toLowerCase().includes(needle)) return i + 1;
      }
      return 1;
    }

    const elementosPorPagina = (pageNum) =>
      syncHitsAll.value.filter((h) => h.pagina === pageNum && h.linea > 0);

    const estiloCajaSync = (hit) => ({
      left: `${hit.leftPct.toFixed(2)}%`,
      top: `${hit.topPct.toFixed(2)}%`,
      width: `${hit.widthPct.toFixed(2)}%`,
      height: `${hit.heightPct.toFixed(2)}%`
    });

    const estiloHojaA4 = computed(() => {
      const baseWidth = Math.round(595 * pdfZoom.value);
      const baseHeight = Math.round(842 * pdfZoom.value);
      return {
        inlineSize: `${baseWidth}px`,
        blockSize: `${baseHeight}px`
      };
    });

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
      if (compileResult.value.ejecucion?.implementado) return '✓ 4 Fases · PDF listo';
      return '✓ Fases 1–3 OK';
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

    const colorDeToken = (cat) => TOKEN_COLORS[cat] || '#e2e8f0';
    const badgeStyle = (cat) => {
      const c = colorDeToken(cat);
      return { backgroundColor: `${c}22`, color: c, borderColor: `${c}55` };
    };

    // ── Funciones del Gestor Undo / Redo ────────────────────────────
    const reiniciarHistorial = (codigoBase) => {
      if (typingHistoryTimer) {
        clearTimeout(typingHistoryTimer);
        typingHistoryTimer = null;
      }
      undoStack.value = [];
      redoStack.value = [];
      lastCommittedSnapshot = { code: codigoBase, start: 0, end: 0 };
    };

    const consolidarEscrituraPendiente = () => {
      if (typingHistoryTimer) {
        clearTimeout(typingHistoryTimer);
        typingHistoryTimer = null;
      }
      if (codigoFuente.value !== lastCommittedSnapshot.code) {
        undoStack.value.push({ ...lastCommittedSnapshot });
        if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift();
        const ta = editorRef.value;
        lastCommittedSnapshot = {
          code: codigoFuente.value,
          start: ta?.selectionStart ?? codigoFuente.value.length,
          end: ta?.selectionEnd ?? codigoFuente.value.length
        };
      }
    };

    const aplicarCambioTransaccional = async (nuevoCodigo, selStart, selEnd) => {
      consolidarEscrituraPendiente();
      if (nuevoCodigo === codigoFuente.value) return;

      const ta = editorRef.value;
      undoStack.value.push({
        code: codigoFuente.value,
        start: ta?.selectionStart ?? lastCommittedSnapshot.start,
        end: ta?.selectionEnd ?? lastCommittedSnapshot.end
      });
      if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift();
      redoStack.value = [];

      codigoFuente.value = nuevoCodigo;
      lastCommittedSnapshot = { code: nuevoCodigo, start: selStart, end: selEnd };
      persistirBorradorCambio();

      await nextTick();
      if (ta) {
        ta.focus();
        ta.setSelectionRange(selStart, selEnd);
        actualizarPosicionCursor();
      }
    };

    const deshacer = async () => {
      consolidarEscrituraPendiente();
      if (undoStack.value.length === 0) return;

      const ta = editorRef.value;
      const actual = {
        code: codigoFuente.value,
        start: ta?.selectionStart ?? 0,
        end: ta?.selectionEnd ?? 0
      };
      const previo = undoStack.value.pop();
      redoStack.value.push(actual);

      codigoFuente.value = previo.code;
      lastCommittedSnapshot = { ...previo };
      persistirBorradorCambio();

      await nextTick();
      if (ta) {
        ta.focus();
        ta.setSelectionRange(previo.start, previo.end);
        actualizarPosicionCursor();
      }
    };

    const rehacer = async () => {
      if (redoStack.value.length === 0) return;
      const ta = editorRef.value;
      undoStack.value.push({
        code: codigoFuente.value,
        start: ta?.selectionStart ?? 0,
        end: ta?.selectionEnd ?? 0
      });

      const siguiente = redoStack.value.pop();
      codigoFuente.value = siguiente.code;
      lastCommittedSnapshot = { ...siguiente };
      persistirBorradorCambio();

      await nextTick();
      if (ta) {
        ta.focus();
        ta.setSelectionRange(siguiente.start, siguiente.end);
        actualizarPosicionCursor();
      }
    };

    const persistirBorradorCambio = () => {
      if (user.value && activeDoc.value) {
        saveState.value = 'dirty';
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => {
          guardarDocumentoActual(false);
        }, 1200);
      } else {
        localStorage.setItem(STORAGE_GUEST_DRAFT, codigoFuente.value);
      }
    };

    const alEditarCodigo = (e) => {
      actualizarPosicionCursor();
      persistirBorradorCambio();

      redoStack.value = [];
      const charInsertado = e?.data || '';
      if (typingHistoryTimer) clearTimeout(typingHistoryTimer);

      const registrarSnapshot = () => {
        if (codigoFuente.value !== lastCommittedSnapshot.code) {
          undoStack.value.push({ ...lastCommittedSnapshot });
          if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift();
          const ta = editorRef.value;
          lastCommittedSnapshot = {
            code: codigoFuente.value,
            start: ta?.selectionStart ?? 0,
            end: ta?.selectionEnd ?? 0
          };
        }
      };

      if (charInsertado === ' ' || charInsertado === '\n' || e?.inputType === 'insertLineBreak') {
        registrarSnapshot();
      } else {
        typingHistoryTimer = setTimeout(registrarSnapshot, 320);
      }
    };

    // ── Rueda Cromática HSV (<canvas>) ──────────────────────────────
    const dibujarRuedaCromatica = () => {
      const canvas = colorWheelCanvasRef.value;
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      const size = canvas.width;
      const cx = size / 2;
      const cy = size / 2;
      const radius = size / 2 - 6;

      if (!wheelBaseImage || wheelCachedVal !== pickerVal.value) {
        const img = ctx.createImageData(size, size);
        const data = img.data;
        for (let py = 0; py < size; py++) {
          for (let px = 0; px < size; px++) {
            const dx = px - cx;
            const dy = py - cy;
            const dist = Math.sqrt(dx * dx + dy * dy);
            const offset = (py * size + px) * 4;
            if (dist <= radius + 1) {
              let angle = (Math.atan2(dy, dx) * 180) / Math.PI;
              if (angle < 0) angle += 360;
              const sat = Math.min(100, (dist / radius) * 100);
              const [r, g, b] = hsvToRgb(angle, sat, pickerVal.value);
              const edgeAlpha = dist > radius ? Math.max(0, 1 - (dist - radius)) : 1;
              data[offset] = r;
              data[offset + 1] = g;
              data[offset + 2] = b;
              data[offset + 3] = Math.round(edgeAlpha * 255);
            }
          }
        }
        wheelBaseImage = img;
        wheelCachedVal = pickerVal.value;
      }

      ctx.clearRect(0, 0, size, size);
      ctx.putImageData(wheelBaseImage, 0, 0);

      // Anillo exterior sutil
      ctx.beginPath();
      ctx.arc(cx, cy, radius, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      // Indicador de selección actual (estilo Adobe Color)
      const rad = (pickerHue.value * Math.PI) / 180;
      const rSel = (pickerSat.value / 100) * radius;
      const tx = cx + Math.cos(rad) * rSel;
      const ty = cy + Math.sin(rad) * rSel;

      // Cono sutil desde el centro hacia el nodo activo
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.lineTo(tx, ty);
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(tx, ty, 8.5, 0, Math.PI * 2);
      ctx.fillStyle = pickerHex.value;
      ctx.fill();
      ctx.lineWidth = 2.6;
      ctx.strokeStyle = '#ffffff';
      ctx.stroke();
    };

    const actualizarDesdeCoordenadaRueda = (clientX, clientY) => {
      const canvas = colorWheelCanvasRef.value;
      if (!canvas) return;
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / rect.width;
      const scaleY = canvas.height / rect.height;
      const x = (clientX - rect.left) * scaleX;
      const y = (clientY - rect.top) * scaleY;
      const cx = canvas.width / 2;
      const cy = canvas.height / 2;
      const radius = canvas.width / 2 - 6;
      const dx = x - cx;
      const dy = y - cy;
      let angle = (Math.atan2(dy, dx) * 180) / Math.PI;
      if (angle < 0) angle += 360;
      const sat = Math.min(100, (Math.sqrt(dx * dx + dy * dy) / radius) * 100);
      pickerHue.value = Math.round(angle);
      pickerSat.value = Math.round(sat);
      pickerHexInput.value = pickerHex.value;
      dibujarRuedaCromatica();
    };

    const iniciarArrastreRueda = (e) => {
      e.preventDefault();
      actualizarDesdeCoordenadaRueda(e.clientX, e.clientY);
      const onMove = (ev) => actualizarDesdeCoordenadaRueda(ev.clientX, ev.clientY);
      const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
      };
      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onUp);
    };

    const actualizarDesdeBrillo = () => {
      pickerHexInput.value = pickerHex.value;
      dibujarRuedaCromatica();
    };

    const sincronizarHsvDesdeHex = (hexStr) => {
      const hsv = hexToHsv(hexStr);
      pickerHue.value = hsv.h;
      pickerSat.value = hsv.s;
      pickerVal.value = hsv.v;
      pickerHexInput.value = pickerHex.value;
      nextTick(dibujarRuedaCromatica);
    };

    const seleccionarColorPreset = (hex) => {
      const hsv = hexToHsv(hex);
      pickerHue.value = hsv.h;
      pickerSat.value = hsv.s;
      pickerVal.value = hsv.v;
      pickerHexInput.value = hex.toUpperCase();
      dibujarRuedaCromatica();
    };

    const aplicarHexManual = () => {
      let val = pickerHexInput.value.trim();
      if (!val.startsWith('#')) val = '#' + val;
      if (/^#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/.test(val)) {
        sincronizarHsvDesdeHex(val);
      } else {
        pickerHexInput.value = pickerHex.value;
      }
    };

    const prepararColorPopover = () => {
      targetHexRangeInEditor = null;
      const ta = editorRef.value;
      if (ta) {
        const pos = ta.selectionStart ?? 0;
        const ventanaInicio = Math.max(0, pos - 12);
        const ventanaFin = Math.min(codigoFuente.value.length, pos + 12);
        const sub = codigoFuente.value.slice(ventanaInicio, ventanaFin);
        const re = /#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})\b/g;
        let m;
        while ((m = re.exec(sub)) !== null) {
          const absStart = ventanaInicio + m.index;
          const absEnd = absStart + m[0].length;
          if (pos >= absStart && pos <= absEnd) {
            targetHexRangeInEditor = { start: absStart, end: absEnd };
            sincronizarHsvDesdeHex(m[0]);
            break;
          }
        }
      }
      nextTick(dibujarRuedaCromatica);
    };

    const editarHexDeLinea = (hex) => {
      const lineas = codigoFuente.value.split('\n');
      let offset = 0;
      for (let i = 0; i < cursorLn.value - 1; i++) offset += lineas[i].length + 1;
      const lineaStr = lineas[cursorLn.value - 1] || '';
      const idx = lineaStr.toUpperCase().indexOf(hex.toUpperCase());
      if (idx >= 0) {
        targetHexRangeInEditor = { start: offset + idx, end: offset + idx + hex.length };
      } else {
        targetHexRangeInEditor = null;
      }
      sincronizarHsvDesdeHex(hex);
    };

    const cerrarPopoverColor = () => {
      if (colorPopoverRef.value?.matches?.(':popover-open')) {
        colorPopoverRef.value.hidePopover();
      }
    };

    const copiarHexPortapapeles = async () => {
      try {
        await navigator.clipboard.writeText(pickerHex.value);
        hexCopiado.value = true;
        setTimeout(() => {
          hexCopiado.value = false;
        }, 1400);
      } catch {}
    };

    const insertarColorAtributo = async () => {
      const hex = pickerHex.value;
      if (targetHexRangeInEditor) {
        const { start, end } = targetHexRangeInEditor;
        const nuevo = codigoFuente.value.slice(0, start) + hex + codigoFuente.value.slice(end);
        targetHexRangeInEditor = { start, end: start + hex.length };
        cerrarPopoverColor();
        await aplicarCambioTransaccional(nuevo, start, start + hex.length);
        return;
      }

      const code = codigoFuente.value;
      const ta = editorRef.value;
      const start = ta?.selectionStart ?? code.length;
      const end = ta?.selectionEnd ?? start;
      const antes = code.slice(0, start);

      // 1. Verificar si el cursor está dentro de paréntesis de atributos #comando(...)
      const lastOpenPar = antes.lastIndexOf('(');
      const lastClosePar = antes.lastIndexOf(')');
      const dentroParentesis = lastOpenPar > lastClosePar && /#[a-zA-Z0-9_]+\($/.test(code.slice(Math.max(0, lastOpenPar - 24), lastOpenPar + 1));

      if (dentroParentesis) {
        const interiorPrevio = code.slice(lastOpenPar + 1, start).trim();
        let insercion = `color=${hex}`;
        if (/color\s*=\s*$/i.test(antes)) {
          insercion = hex;
        } else if (interiorPrevio.length > 0 && !interiorPrevio.endsWith(',')) {
          insercion = `, color=${hex}`;
        }
        const nuevo = code.slice(0, start) + insercion + code.slice(end);
        cerrarPopoverColor();
        const finPos = start + insercion.length;
        await aplicarCambioTransaccional(nuevo, finPos, finPos);
        return;
      }

      // 2. Verificar si está fuera de paréntesis: determinar nivel de llaves { }
      let braceDepth = 0;
      for (let i = 0; i < start; i++) {
        if (code[i] === '\\') { i++; continue; }
        if (code[i] === '{') braceDepth++;
        else if (code[i] === '}') braceDepth = Math.max(0, braceDepth - 1);
      }

      const sel = code.slice(start, end) || 'Texto con color';
      const bloque = braceDepth > 0
        ? `#fuerte(color=${hex}){${sel}}`
        : `#texto(color=${hex}) { ${sel} }`;
      const prefixLen = braceDepth > 0
        ? `#fuerte(color=${hex}){`.length
        : `#texto(color=${hex}) { `.length;

      const nuevo = code.slice(0, start) + bloque + code.slice(end);
      cerrarPopoverColor();
      await aplicarCambioTransaccional(nuevo, start + prefixLen, start + prefixLen + sel.length);
    };

    const envolverConColor = async (comando) => {
      const hex = pickerHex.value;
      const ta = editorRef.value;
      const start = ta?.selectionStart ?? codigoFuente.value.length;
      const end = ta?.selectionEnd ?? start;
      const seleccionado = codigoFuente.value.slice(start, end) || 'Texto con color';
      const bloque = `#${comando}(color=${hex}) { ${seleccionado} }`;
      const nuevo = codigoFuente.value.slice(0, start) + bloque + codigoFuente.value.slice(end);
      cerrarPopoverColor();
      const innerStart = start + `#${comando}(color=${hex}) { `.length;
      await aplicarCambioTransaccional(nuevo, innerStart, innerStart + seleccionado.length);
    };

    // ── Formato e Inserción Rápida (Preservando Undo/Redo) ──────────
    const envolverFormato = async (cmd, placeholder) => {
      const ta = editorRef.value;
      const start = ta?.selectionStart ?? codigoFuente.value.length;
      const end = ta?.selectionEnd ?? start;
      const sel = codigoFuente.value.slice(start, end) || placeholder;
      const reemplazo = `#${cmd}{${sel}}`;
      const nuevo = codigoFuente.value.slice(0, start) + reemplazo + codigoFuente.value.slice(end);
      const selInicio = start + cmd.length + 2;
      await aplicarCambioTransaccional(nuevo, selInicio, selInicio + sel.length);
    };

    const insertarSnippet = async (snippet) => {
      if (snippetsPopoverRef.value?.matches?.(':popover-open')) {
        snippetsPopoverRef.value.hidePopover();
      }
      const ta = editorRef.value;
      const start = ta?.selectionStart ?? codigoFuente.value.length;
      const end = ta?.selectionEnd ?? start;
      const nuevo = codigoFuente.value.slice(0, start) + snippet + codigoFuente.value.slice(end);
      const pos = start + snippet.length;
      await aplicarCambioTransaccional(nuevo, pos, pos);
    };

    // ── Renderizador PDF.js Double-Buffered de Solo Lectura + SyncTeX ─
    const setPdfCanvasRef = (pageNum, el) => {
      if (el) pdfCanvasMap.set(pageNum, el);
      else pdfCanvasMap.delete(pageNum);
    };

    const renderizarPaginasPdfJs = async (tokenActual, docRef) => {
      const targetDoc = docRef || pdfDocInstance;
      if (!targetDoc) return;
      const dpr = Math.min(window.devicePixelRatio || 1, 2.2);

      for (let p = 1; p <= targetDoc.numPages; p++) {
        if (tokenActual !== renderTokenCounter) return;

        const page = await targetDoc.getPage(p);
        if (tokenActual !== renderTokenCounter) return;

        const cssViewport = page.getViewport({ scale: pdfZoom.value });
        const hiViewport = page.getViewport({ scale: pdfZoom.value * dpr });

        // Renderizar en un canvas offscreen aislado evita colisiones concurrentes y parpadeos
        const offscreen = document.createElement('canvas');
        offscreen.width = Math.floor(hiViewport.width);
        offscreen.height = Math.floor(hiViewport.height);
        const offCtx = offscreen.getContext('2d');
        await page.render({ canvasContext: offCtx, viewport: hiViewport }).promise;

        if (tokenActual !== renderTokenCounter) return;
        await nextTick();
        const visibleCanvas = pdfCanvasMap.get(p);
        if (!visibleCanvas) continue;

        visibleCanvas.width = offscreen.width;
        visibleCanvas.height = offscreen.height;
        visibleCanvas.style.width = `${Math.floor(cssViewport.width)}px`;
        visibleCanvas.style.height = `${Math.floor(cssViewport.height)}px`;
        const visCtx = visibleCanvas.getContext('2d');
        visCtx.drawImage(offscreen, 0, 0);
      }
    };

    const cargarBinarioEnPdfJs = async (bytes) => {
      if (!window.pdfjsLib) {
        pdfjsFallback.value = true;
        return;
      }
      const tokenActual = ++renderTokenCounter;
      try {
        const prevScroll = previewStageRef.value?.scrollTop ?? 0;
        const loadingTask = window.pdfjsLib.getDocument({ data: bytes });
        const loadedDoc = await loadingTask.promise;
        if (tokenActual !== renderTokenCounter) return;

        pdfDocInstance = loadedDoc;
        pdfjsFallback.value = false;
        pdfPagesCount.value = loadedDoc.numPages;
        if (currentPage.value > loadedDoc.numPages) {
          currentPage.value = loadedDoc.numPages;
        }

        await nextTick();
        await renderizarPaginasPdfJs(tokenActual, loadedDoc);

        if (tokenActual === renderTokenCounter && previewStageRef.value) {
          previewStageRef.value.scrollTop = prevScroll;
        }
      } catch (err) {
        if (tokenActual !== renderTokenCounter) return;
        console.warn('Error renderizando vista previa PDF.js:', err);
      }
    };

    const cambiarZoom = async (delta) => {
      const nuevo = Math.max(0.55, Math.min(1.85, Number((pdfZoom.value + delta).toFixed(2))));
      if (nuevo === pdfZoom.value) return;
      pdfZoom.value = nuevo;
      await nextTick();
      await renderizarPaginasPdfJs(++renderTokenCounter);
    };

    const ajustarAnchoPdf = async () => {
      const stage = previewStageRef.value;
      if (!stage) {
        pdfZoom.value = 1.0;
      } else {
        const disponible = Math.max(320, stage.clientWidth - 48);
        pdfZoom.value = Number(Math.max(0.6, Math.min(1.35, disponible / 595)).toFixed(2));
      }
      await nextTick();
      await renderizarPaginasPdfJs(++renderTokenCounter);
    };

    const irAPaginaPdf = (paginaDestino) => {
      const p = Math.max(1, Math.min(pdfPagesCount.value, paginaDestino));
      currentPage.value = p;
      const stage = previewStageRef.value;
      const card = stage?.querySelector?.(`.pdf-page-card[data-page="${p}"]`);
      if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    const alHacerScrollPdf = () => {
      const stage = previewStageRef.value;
      if (!stage || pdfPagesCount.value <= 1) return;
      const cards = stage.querySelectorAll('.pdf-page-card');
      const stageTop = stage.getBoundingClientRect().top;
      let mejorPag = 1;
      let menorDist = Infinity;
      cards.forEach((card) => {
        const dist = Math.abs(card.getBoundingClientRect().top - stageTop - 24);
        if (dist < menorDist) {
          menorDist = dist;
          mejorPag = Number(card.getAttribute('data-page') || 1);
        }
      });
      currentPage.value = mejorPag;
    };

    // ── Sincronización Bidireccional SyncTeX (Discreta estilo Overleaf) ─
    const destellarLineaEditor = (linea) => {
      syncHighlightLine.value = linea;
      if (syncFlashTimer) clearTimeout(syncFlashTimer);
      syncFlashTimer = setTimeout(() => {
        syncHighlightLine.value = 0;
      }, 1500);
    };

    const dispararPulsoPdf = (hit) => {
      activeSyncHit.value = null;
      nextTick(() => {
        activeSyncHit.value = hit;
        if (pdfSyncFlashTimer) clearTimeout(pdfSyncFlashTimer);
        pdfSyncFlashTimer = setTimeout(() => {
          activeSyncHit.value = null;
        }, 1100);
      });
    };

    const alDobleClicElementoPdf = (hit) => {
      const palabraSel = (window.getSelection()?.toString() || '').trim();
      window.getSelection()?.removeAllRanges();
      dispararPulsoPdf(hit);
      irALineaConTexto(hit.linea, palabraSel || hit.texto);
    };

    const sincronizarEditorAPdf = () => {
      if (syncHitsAll.value.length === 0) return;
      const objetivoLn = cursorLn.value;
      let mejor = syncHitsAll.value[0];
      let menorDiff = Math.abs(mejor.linea - objetivoLn);
      for (const h of syncHitsAll.value) {
        const diff = Math.abs(h.linea - objetivoLn);
        if (diff < menorDiff) {
          menorDiff = diff;
          mejor = h;
        }
      }
      dispararPulsoPdf(mejor);
      currentPage.value = mejor.pagina;

      const stage = previewStageRef.value;
      const card = stage?.querySelector?.(`.pdf-page-card[data-page="${mejor.pagina}"]`);
      if (stage && card) {
        const cardTop = card.offsetTop;
        const offsetDentro = (mejor.topPct / 100) * card.clientHeight;
        stage.scrollTo({
          top: Math.max(0, cardTop + offsetDentro - stage.clientHeight * 0.32),
          behavior: 'smooth'
        });
      }
    };

    const sincronizarPdfAEditorBoton = () => {
      const hitsPag = elementosPorPagina(currentPage.value);
      if (hitsPag.length > 0) {
        dispararPulsoPdf(hitsPag[0]);
        irALineaConTexto(hitsPag[0].linea, hitsPag[0].texto);
      }
    };

    const saltarAEsquema = async (item) => {
      await irALinea(item.line, true);
      sincronizarEditorAPdf();
    };

    // ── Comunicación HTTP, Proyectos y Compilación ──────────────────
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
          const draft = localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE;
          codigoFuente.value = draft;
          reiniciarHistorial(draft);
          saveState.value = 'saved';
        }
      } catch (err) {
        console.warn('Modo invitado activo:', err.message);
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
      selectedCaseKey.value = '';
      codigoFuente.value = detalle.documento.contenido_ltx;
      reiniciarHistorial(detalle.documento.contenido_ltx);
      saveState.value = 'saved';
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
          documents.value[idx] = {
            ...documents.value[idx],
            bytes_tamano: res.documento.bytes_tamano,
            actualizado_en: res.documento.actualizado_en
          };
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

    const compilar = async () => {
      if (!codigoFuente.value.trim()) return;
      if (activeCompileAbort) {
        activeCompileAbort.abort();
      }
      const abortCtrl = new AbortController();
      activeCompileAbort = abortCtrl;

      isCompiling.value = true;
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
          body: fd,
          signal: abortCtrl.signal
        });
        const data = await resp.json();
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

          await cargarBinarioEnPdfJs(bytes.slice());
          justCompiledPulse.value = true;
          setTimeout(() => {
            justCompiledPulse.value = false;
          }, 550);
        } else {
          pdfPagesCount.value = 0;
        }

        if (data.estado === 'error_lexico') {
          activeTab.value = 'lexico';
          showLexicalRef.value = false;
        } else if (data.estado === 'error_sintactico') {
          activeTab.value = 'sintactico';
        } else if (data.estado === 'error_semantico') {
          activeTab.value = 'semantico';
        }
      } catch (err) {
        if (err.name === 'AbortError') return;
        console.error('Error compilando:', err);
        alert('No se pudo conectar con /api.php.');
      } finally {
        if (activeCompileAbort === abortCtrl) {
          isCompiling.value = false;
          activeCompileAbort = null;
        }
      }
    };

    const descargarPdf = () => {
      if (!pdfUrl.value) return;
      const a = document.createElement('a');
      a.href = pdfUrl.value;
      a.download = activeFilename.value.replace(/\.ltx$/i, '') + '.pdf';
      a.click();
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
      inspectorOpen.value = true;
    };

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

    const cargarCasoSeleccionado = async () => {
      const encontrado = CASOS_OFICIALES.find((c) => c.key === selectedCaseKey.value);
      if (!encontrado) return;
      await aplicarCambioTransaccional(encontrado.code, 0, 0);
      await compilar();
    };

    const actualizarPosicionCursor = () => {
      const ta = editorRef.value;
      if (!ta) return;
      const hastaCursor = codigoFuente.value.slice(0, ta.selectionStart || 0);
      const lineas = hastaCursor.split('\n');
      cursorLn.value = lineas.length;
      cursorCol.value = lineas[lineas.length - 1].length + 1;
    };

    const sincronizarScroll = () => {
      if (!editorRef.value) return;
      if (gutterRef.value) {
        gutterRef.value.scrollTop = editorRef.value.scrollTop;
      }
      if (highlightRef.value) {
        highlightRef.value.scrollTop = editorRef.value.scrollTop;
        highlightRef.value.scrollLeft = editorRef.value.scrollLeft;
      }
    };

    const irALinea = async (lineaObjetivo, conDestello = false) => {
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
      ta.scrollTop = Math.max(0, (lineaObjetivo - 4) * 20);
      sincronizarScroll();
      actualizarPosicionCursor();
      if (conDestello) destellarLineaEditor(lineaObjetivo);
    };

    const irALineaConTexto = async (lineaObjetivo, textoBuscado = '') => {
      const ta = editorRef.value;
      if (!ta || lineaObjetivo <= 0) return;
      const lineas = codigoFuente.value.split('\n');
      const needle = (textoBuscado || '').trim();

      let targetLineIdx = Math.min(lineaObjetivo - 1, lineas.length - 1);
      let selStart = -1;
      let selEnd = -1;

      const buscarEnLinea = (idx, q) => {
        if (idx < 0 || idx >= lineas.length || !q) return -1;
        const posExacta = lineas[idx].indexOf(q);
        if (posExacta >= 0) return posExacta;
        return lineas[idx].toLowerCase().indexOf(q.toLowerCase());
      };

      const candidatos = [targetLineIdx, targetLineIdx + 1, targetLineIdx - 1];
      if (needle.length >= 2) {
        for (const cIdx of candidatos) {
          const found = buscarEnLinea(cIdx, needle);
          if (found >= 0) {
            targetLineIdx = cIdx;
            selStart = found;
            selEnd = found + needle.length;
            break;
          }
        }
        if (selStart < 0) {
          const palabras = needle
            .split(/\s+/)
            .filter((w) => w.length >= 3)
            .sort((a, b) => b.length - a.length);
          for (const pal of palabras) {
            for (const cIdx of candidatos) {
              const found = buscarEnLinea(cIdx, pal);
              if (found >= 0) {
                targetLineIdx = cIdx;
                selStart = found;
                selEnd = found + pal.length;
                break;
              }
            }
            if (selStart >= 0) break;
          }
        }
      }

      let lineOffset = 0;
      for (let i = 0; i < targetLineIdx; i++) {
        lineOffset += lineas[i].length + 1;
      }

      const finalStart = selStart >= 0 ? lineOffset + selStart : lineOffset;
      const finalEnd = selEnd >= 0 ? lineOffset + selEnd : lineOffset + (lineas[targetLineIdx]?.length || 0);

      await nextTick();
      ta.focus();
      ta.setSelectionRange(finalStart, finalEnd);
      ta.scrollTop = Math.max(0, (targetLineIdx - 3) * 20);
      sincronizarScroll();
      actualizarPosicionCursor();
      destellarLineaEditor(targetLineIdx + 1);
    };

    const manejarAtajosEditor = (e) => {
      const mod = e.ctrlKey || e.metaKey;
      const key = e.key.toLowerCase();

      if (mod && e.key === 'Enter') {
        e.preventDefault();
        compilar();
      } else if (mod && key === 'z' && !e.shiftKey) {
        e.preventDefault();
        deshacer();
      } else if ((mod && key === 'y') || (mod && key === 'z' && e.shiftKey)) {
        e.preventDefault();
        rehacer();
      } else if (mod && key === 'b') {
        e.preventDefault();
        envolverFormato('fuerte', 'texto en negrita');
      } else if (mod && key === 'i') {
        e.preventDefault();
        envolverFormato('cursiva', 'texto en cursiva');
      } else if (mod && key === 's') {
        e.preventDefault();
        if (user.value && activeDoc.value) guardarDocumentoActual(false);
      } else if (e.key === 'Tab') {
        e.preventDefault();
        insertarSnippet('    ');
      }
    };

    // ── Modales Auth y Documentos ───────────────────────────────────
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
      try {
        await apiJson('./auth.php?action=register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(registerForm.value)
        });
        registerForm.value.password = '';
        cerrarDialog('auth');
        await cargarSesionYDocumentos();
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
      const draft = localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE;
      codigoFuente.value = draft;
      reiniciarHistorial(draft);
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

    onMounted(async () => {
      Object.values(dialogMap()).forEach((dlg) => {
        if (!dlg) return;
        installLightDismissFallback(dlg);
        dlg.addEventListener('close', () => {
          dlg.returnValue = '';
        });
      });

      if (colorPopoverRef.value) {
        colorPopoverRef.value.addEventListener('toggle', (ev) => {
          if (ev.newState === 'open') {
            nextTick(dibujarRuedaCromatica);
          }
        });
      }

      if (previewStageRef.value) {
        const disp = Math.max(340, previewStageRef.value.clientWidth - 52);
        pdfZoom.value = Number(Math.max(0.68, Math.min(1.02, disp / 595)).toFixed(2));
      }

      cargarTablaLexicaOficial();
      await cargarSesionYDocumentos();
      if (!compileResult.value) {
        await compilar();
      }
    });

    onUnmounted(() => {
      if (autosaveTimer) clearTimeout(autosaveTimer);
      if (typingHistoryTimer) clearTimeout(typingHistoryTimer);
      if (syncFlashTimer) clearTimeout(syncFlashTimer);
      if (pdfSyncFlashTimer) clearTimeout(pdfSyncFlashTimer);
      if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
    });

    return {
      sidebarOpen,
      snippetsPopoverRef,
      inspectorOpen,
      activeTab,
      showLexicalRef,
      lexicalReference,
      lexicalSearch,
      filteredLexicalReference,
      lexicalCount,
      user,
      quota,
      documents,
      activeDoc,
      saveState,
      isSaving,
      guestImages,
      activeImages,
      activeFilename,
      codigoFuente,
      highlightedCode,
      totalLineas,
      codigoBytes,
      cursorLn,
      cursorCol,
      syncHighlightLine,
      lineaActivaHex,
      editorRef,
      gutterRef,
      highlightRef,
      canUndo,
      canRedo,
      undoCount,
      deshacer,
      rehacer,
      envolverFormato,
      selectedCaseKey,
      casosDePrueba: CASOS_OFICIALES,
      quickSnippetGroups: QUICK_SNIPPET_GROUPS,
      presetColors: PRESET_COLORS,
      documentOutline,
      saltarAEsquema,
      colorPopoverRef,
      colorWheelCanvasRef,
      pickerHue,
      pickerSat,
      pickerVal,
      pickerHex,
      pickerHexInput,
      hexCopiado,
      prepararColorPopover,
      editarHexDeLinea,
      iniciarArrastreRueda,
      actualizarDesdeBrillo,
      aplicarHexManual,
      seleccionarColorPreset,
      insertarColorAtributo,
      envolverConColor,
      copiarHexPortapapeles,
      isCompiling,
      justCompiledPulse,
      compileResult,
      pdfUrl,
      pdfDownloadBase64,
      pdfjsFallback,
      previewStageRef,
      pdfPagesCount,
      currentPage,
      pdfZoom,
      estiloHojaA4,
      setPdfCanvasRef,
      cambiarZoom,
      ajustarAnchoPdf,
      irAPaginaPdf,
      alHacerScrollPdf,
      elementosPorPagina,
      estiloCajaSync,
      activeSyncHit,
      alDobleClicElementoPdf,
      sincronizarEditorAPdf,
      sincronizarPdfAEditorBoton,
      tokensAnalizados,
      sintacticoPasos,
      tablaSimbolos,
      ejecucionResumenKpis,
      ejecucionElementosLista,
      errorPanel,
      errorLine,
      compilePillClass,
      compileStatusText,
      authDialogRef,
      newDocDialogRef,
      renameDocDialogRef,
      confirmDeleteDialogRef,
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
      descargarPdf,
      abrirTablaLexicaOficial,
      alternarReferenciaLexica,
      seleccionarTab,
      cargarCasoSeleccionado,
      insertarSnippet,
      actualizarPosicionCursor,
      sincronizarScroll,
      irALinea,
      manejarAtajosEditor,
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
  }
}).mount('#app');

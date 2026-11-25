const { createApp, ref, computed, onMounted, onUnmounted, watch, nextTick } = Vue;

const STORAGE_GUEST_DRAFT = 'latinex_guest_draft_v1';

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
    {Compilación en 2 pasadas con referencias cruzadas (#referencia(intro))}
    {Entorno web estilo Overleaf en PHP 8.5 + Docker + AWS EC2}
}

#caja(tipo=info) { Inicia sesión o crea tu perfil para guardar hasta 10 archivos .ltx en el servidor. }`;

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

    const codigoFuente = ref(localStorage.getItem(STORAGE_GUEST_DRAFT) || DEFAULT_GUEST_CODE);
    const selectedCaseKey = ref('');
    const isCompiling = ref(false);
    const compileResult = ref(null);
    const pdfUrl = ref(null);

    const cursorLn = ref(1);
    const cursorCol = ref(1);
    const editorRef = ref(null);
    const gutterRef = ref(null);

    // Modales
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

    const totalLineas = computed(() => Math.max(1, codigoFuente.value.split('\n').length));
    const codigoBytes = computed(() => new TextEncoder().encode(codigoFuente.value).length);

    const activeFilename = computed(() => {
      if (user.value && activeDoc.value) return activeDoc.value.nombre_archivo;
      return 'borrador_invitado.ltx';
    });

    const activeImages = computed(() => activeDoc.value?.imagenes || []);

    const tokensAnalizados = computed(() => compileResult.value?.lexico?.tokens || []);
    const sintacticoPasos = computed(() => compileResult.value?.sintactico?.transiciones || []);
    const tablaSimbolos = computed(() => compileResult.value?.semantico?.tablaSimbolos || []);

    const pdfDownloadBase64 = computed(() => compileResult.value?.ejecucion?.pdfBase64 || null);

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
      return 'Fases 1–3 ACEPTADAS';
    });

    const previewData = computed(() => {
      const code = codigoFuente.value;
      const matchSimple = (cmd) => {
        const re = new RegExp(`#${cmd}(?:\\([^)]*\\))?\\s*\\{([^}]*)\\}`, 'i');
        const m = code.match(re);
        return m ? m[1].replace(/#\w+(?:\([^)]*\))?\s*\{([^}]*)\}/g, '$1').trim() : '';
      };

      const bloques = [];
      const blockRe = /#(seccion|subseccion\d*|texto|caja)(?:\(([^)]*)\))?\s*\{([\s\S]*?)\}/gi;
      let match;
      while ((match = blockRe.exec(code)) !== null) {
        const cmd = match[1].toLowerCase();
        const attrs = (match[2] || '').toLowerCase();
        const cleanBody = match[3]
          .replace(/#(?:fuerte|cursiva|subrayado|resaltado|superindice|subindice)(?:\([^)]*\))?\s*\{([^}]*)\}/g, '$1')
          .replace(/\\([#{}])/g, '$1')
          .replace(/\s+/g, ' ')
          .trim();

        if (!cleanBody) continue;
        if (cmd.startsWith('seccion') || cmd.startsWith('subseccion')) {
          bloques.push({ tipo: 'seccion', texto: cleanBody });
        } else if (cmd === 'caja') {
          let estilo = 'info';
          if (attrs.includes('alerta')) estilo = 'alerta';
          else if (attrs.includes('exito') || attrs.includes('éxito')) estilo = 'exito';
          bloques.push({ tipo: 'caja', estilo, texto: cleanBody });
        } else {
          bloques.push({ tipo: 'texto', texto: cleanBody });
        }
      }

      return {
        titulo: matchSimple('titulo') || activeFilename.value,
        autor: matchSimple('autor'),
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

    const colorDeToken = (cat) => TOKEN_COLORS[cat] || '#e2e8f0';
    const badgeStyle = (cat) => {
      const c = colorDeToken(cat);
      return { backgroundColor: `${c}22`, color: c, borderColor: `${c}55` };
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

    const alEditarCodigo = () => {
      actualizarPosicionCursor();
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

    const compilar = async () => {
      if (!codigoFuente.value.trim()) return;
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
          body: fd
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

    const cargarCasoSeleccionado = () => {
      const encontrado = CASOS_OFICIALES.find((c) => c.key === selectedCaseKey.value);
      if (!encontrado) return;
      codigoFuente.value = encontrado.code;
      alEditarCodigo();
      selectedCaseKey.value = '';
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
      const hastaCursor = codigoFuente.value.slice(0, ta.selectionStart || 0);
      const lineas = hastaCursor.split('\n');
      cursorLn.value = lineas.length;
      cursorCol.value = lineas[lineas.length - 1].length + 1;
    };

    const sincronizarScroll = () => {
      if (gutterRef.value && editorRef.value) {
        gutterRef.value.scrollTop = editorRef.value.scrollTop;
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
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
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

    onMounted(async () => {
      Object.values(dialogMap()).forEach((dlg) => {
        if (!dlg) return;
        installLightDismissFallback(dlg);
        dlg.addEventListener('close', () => {
          dlg.returnValue = '';
        });
      });

      await cargarSesionYDocumentos();
      if (!compileResult.value) {
        await compilar();
      }
    });

    onUnmounted(() => {
      if (autosaveTimer) clearTimeout(autosaveTimer);
      if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
    });

    return {
      sidebarOpen,
      inspectorOpen,
      activeTab,
      showLexicalRef,
      lexicalReference,
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
      totalLineas,
      codigoBytes,
      cursorLn,
      cursorCol,
      editorRef,
      gutterRef,
      selectedCaseKey,
      casosDePrueba: CASOS_OFICIALES,
      quickSnippets: QUICK_SNIPPETS,
      isCompiling,
      compileResult,
      pdfUrl,
      pdfDownloadBase64,
      tokensAnalizados,
      sintacticoPasos,
      tablaSimbolos,
      errorPanel,
      errorLine,
      compilePillClass,
      compileStatusText,
      previewData,
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

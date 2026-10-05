const { createApp, ref, onMounted, onUnmounted } = Vue;

createApp({
    setup() {
        const codigoFuente = ref(`#titulo(size=24){Bienvenido a Latinex}
#autor{Tu Nombre}
#texto{Este es un lenguaje de marcado en español diseñado para ser simple y eficiente, sin bases de datos.}
`);
        const pdfUrl = ref(null);
        const isCompiling = ref(false);
        const images = ref([]);
        
        // Generar un ID de sesión efímero
        const sessionId = ref(Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15));

        const handleFileUpload = (event) => {
            const files = event.target.files;
            if (files) {
                for (let i = 0; i < files.length; i++) {
                    images.value.push(files[i]);
                }
            }
            // Limpiamos el input para permitir volver a subir el mismo archivo si se elimina
            event.target.value = '';
        };

        const removeImage = (index) => {
            images.value.splice(index, 1);
        };

        const compilar = async () => {
            if (!codigoFuente.value.trim()) return;
            
            isCompiling.value = true;
            
            try {
                const formData = new FormData();
                formData.append('sessionId', sessionId.value);
                formData.append('codigo', codigoFuente.value);
                
                images.value.forEach((img, index) => {
                    formData.append(`images[${index}]`, img);
                });

                // Apuntamos al api.php en la raíz del repositorio
                const response = await fetch('../api.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }

                const contentType = response.headers.get("content-type");
                
                // Si la API ya genera un PDF binario
                if (contentType && contentType.includes("application/pdf")) {
                    const blob = await response.blob();
                    if (pdfUrl.value) {
                        URL.revokeObjectURL(pdfUrl.value);
                    }
                    pdfUrl.value = URL.createObjectURL(blob);
                } 
                // Por ahora para pruebas, si la API retorna el JSON de los tokens
                else if (contentType && contentType.includes("application/json")) {
                    const data = await response.json();
                    
                    const tokensHtml = `
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: 'Fira Code', monospace; background: #f8fafc; color: #0f172a; padding: 2rem; }
                                h2 { color: #3b82f6; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; }
                                .token-card { background: white; border: 1px solid #e2e8f0; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                                pre { white-space: pre-wrap; margin: 0; background: #f1f5f9; padding: 1rem; border-radius: 0.25rem; font-size: 13px; border: 1px solid #cbd5e1; }
                                .badge { background: #e0e7ff; color: #4338ca; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <h2>Resultado de Compilación (Modo Debug)</h2>
                            <p><strong>Estado:</strong> ${data.message}</p>
                            <div class="token-card">
                                <h3>Árbol de Tokens Generado:</h3>
                                <pre>${JSON.stringify(data.tokens, null, 2)}</pre>
                            </div>
                        </body>
                        </html>
                    `;
                    const blob = new Blob([tokensHtml], { type: 'text/html' });
                    if (pdfUrl.value) {
                        URL.revokeObjectURL(pdfUrl.value);
                    }
                    pdfUrl.value = URL.createObjectURL(blob);
                } else {
                    const text = await response.text();
                    console.error("Respuesta no parseable:", text);
                    alert("El servidor no retornó un formato soportado.");
                }

            } catch (error) {
                console.error("Error compilando:", error);
                alert("Hubo un error al compilar el código. Verifica la consola.");
            } finally {
                isCompiling.value = false;
            }
        };

        // Prevenir fugas de memoria limpiando las URLs de los blobs
        onUnmounted(() => {
            if (pdfUrl.value) {
                URL.revokeObjectURL(pdfUrl.value);
            }
        });

        return {
            codigoFuente,
            pdfUrl,
            isCompiling,
            compilar,
            handleFileUpload,
            images,
            removeImage,
            sessionId
        };
    }
}).mount('#app');

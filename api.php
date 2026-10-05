<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/src/Tokenizador.php';

use Latinex\Tokenizador;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    $sessionId = $_POST['sessionId'] ?? 'default_session';

    // 1. Manejo de imágenes (Stateless/Efímero)
    $uploadDir = __DIR__ . '/temp_uploads/';
    $sessionUploadDir = $uploadDir . $sessionId . '/';
    
    if (!empty($_FILES['images'])) {
        if (!is_dir($sessionUploadDir)) {
            mkdir($sessionUploadDir, 0777, true);
        }
        
        // Re-estructuramos el array de $_FILES si vienen múltiples
        $files = $_FILES['images'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            $tmpName = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            // Limpieza del nombre de archivo
            $name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $name);
            
            if (is_uploaded_file($tmpName)) {
                move_uploaded_file($tmpName, $sessionUploadDir . $name);
            }
        }
    }

    // 2. Análisis Léxico (Tokenizador)
    require_once __DIR__ . '/src/TablaLexica.php';
    $tablaLexica = new Latinex\TablaLexica();
    $tokenizador = new Latinex\Tokenizador($codigo, $tablaLexica);
    
    $tokens = [];
    while (true) {
        $token = $tokenizador->getToken();
        $tokens[] = [
            'token' => $token->token,
            'lexema' => $token->lexema,
            'linea' => $token->linea,
            'columna' => $token->columna
        ];
        if ($token->token === Latinex\T_FIN) {
            break;
        }
    }

    // 3. Respuesta de Prueba (Retornamos JSON para validar el tokenizador)
    // Cuando PdfGenerator esté listo, esto se cambiará a Content-Type: application/pdf
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'sessionId' => $sessionId,
        'message' => 'Código analizado correctamente',
        'tokens' => $tokens
    ], JSON_PRETTY_PRINT);
    
    /*
    // ESQUELETO PARA LA GENERACIÓN REAL DE PDF
    require_once __DIR__ . '/src/PdfGenerator.php';
    $pdfGen = new Latinex\PdfGenerator($tokens);
    $pdfBinary = $pdfGen->generate();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="documento.pdf"');
    echo $pdfBinary;
    */
    
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);

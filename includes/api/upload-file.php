<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Prevent any output before JSON
ob_start();

try {
    require_once '../session-config.php';
    startSecureSession();

    // Include helper functions
    require_once '../db_helper.php';
    require_once '../config-helper.php';

    // Clear any output from includes
    ob_end_clean();

    header('Content-Type: application/json');

    // Log the request
    error_log('Upload request received: ' . print_r($_POST, true));
    error_log('Files: ' . print_r($_FILES, true));

    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
        exit();
    }

    $userId = $_SESSION['user_id'];

    // Check permissions
    $vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
    $canEditUI = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditUI);

    if (!$canEditUI) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit();
    }

    // Get upload type from POST
    $uploadType = $_POST['upload_type'] ?? '';

    // Validate upload type
    $validTypes = ['tabicon', 'logo', 'banner_image', 'gif'];
    if (!in_array($uploadType, $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiger Upload-Typ: ' . $uploadType]);
        exit();
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $errorMsg = isset($_FILES['file']) ? 'Upload-Fehler Code: ' . $_FILES['file']['error'] : 'Keine Datei hochgeladen';
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit();
    }

    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    
    // Check if file exists
    if (!file_exists($fileTmpPath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Temporäre Datei nicht gefunden']);
        exit();
    }
    
    $fileType = mime_content_type($fileTmpPath);

    // Validate file type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
    if (!in_array($fileType, $allowedMimes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiger Dateityp: ' . $fileType]);
        exit();
    }

    // Validate file size (max 5MB for most, 10MB for banner images)
    $maxSize = ($uploadType === 'banner_image') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        $maxMB = $maxSize / (1024 * 1024);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Datei zu groß. Maximum: {$maxMB}MB"]);
        exit();
    }

    // Get image dimensions
    $imageInfo = @getimagesize($fileTmpPath);
    if ($imageInfo === false) {
        // For ICO files, use default dimensions
        $dimensions = 'N/A';
    } else {
        $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
    }

    // Get file extension
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Generate unique filename
    $uniqueName = uniqid() . '_' . time() . '.' . $fileExtension;

    // Define upload directories and database tables
    $uploadPaths = [
        'tabicon' => [
            'dir' => '../../assets/icons/tabicons/',
            'table' => 'icons',
            'config_key' => 'tabicon',
            'display_name' => $fileName
        ],
        'logo' => [
            'dir' => '../../assets/icons/logos/',
            'table' => 'logos',
            'config_key' => 'logo',
            'display_name' => $fileName
        ],
        'banner_image' => [
            'dir' => '../../assets/images/banner/',
            'table' => 'banner_images',
            'config_key' => 'banner_image',
            'display_name' => $fileName
        ],
        'gif' => [
            'dir' => '../../assets/images/gifs/',
            'table' => 'gifs',
            'config_key' => 'current_gif',
            'display_name' => $fileName
        ]
    ];

    $uploadConfig = $uploadPaths[$uploadType];
    $targetDir = __DIR__ . '/' . $uploadConfig['dir'];
    $targetPath = $targetDir . $uniqueName;

    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Verzeichnis konnte nicht erstellt werden: ' . $targetDir]);
            exit();
        }
    }

    // Move uploaded file
    if (!move_uploaded_file($fileTmpPath, $targetPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden nach: ' . $targetPath]);
        exit();
    }

    // Add to history in database
    global $config;
    
    if ($config === null) {
        // Remove uploaded file
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Datenbankverbindung nicht verfügbar']);
        exit();
    }
    
    // For GIFs, get season from POST data
    $gifSeason = ($uploadType === 'gif' && isset($_POST['season'])) ? $_POST['season'] : null;
    
    // Determine the type to store
    $typeValue = ($uploadType === 'gif' && $gifSeason) ? $gifSeason : $fileExtension;
    
    // Extract name without extension for 'name' column
    $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
    
    // Insert into appropriate history table
    $tableName = $uploadConfig['table'];
    $sql = "INSERT INTO {$tableName} (name, link, dimensions, type, datum) VALUES (:name, :link, :dimensions, :type, :datum)";
    
    $stmt = $config->prepare($sql);
    if (!$stmt) {
        // Remove uploaded file
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Vorbereiten der SQL-Abfrage für Tabelle: ' . $tableName]);
        exit();
    }
    
    $stmt->bindValue(':name', $nameWithoutExt, SQLITE3_TEXT);
    $stmt->bindValue(':link', $uniqueName, SQLITE3_TEXT);
    $stmt->bindValue(':dimensions', $dimensions, SQLITE3_TEXT);
    $stmt->bindValue(':type', $typeValue, SQLITE3_TEXT);
    $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    
    $result = $stmt->execute();
    if (!$result) {
        // Remove uploaded file
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fehler beim Ausführen der Datenbankabfrage für Tabelle: ' . $tableName]);
        exit();
    }
    
    // Update current config value to use this file
    $configSuccess = setConfigValue($uploadConfig['config_key'], $uniqueName);
    if (!$configSuccess) {
        error_log('Warning: Config value not updated for key: ' . $uploadConfig['config_key']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Datei erfolgreich hochgeladen',
        'filename' => $uniqueName,
        'dimensions' => $dimensions
    ]);
    exit(); // Important: Stop execution here

} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log the error
    error_log('Upload exception: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server-Fehler: ' . $e->getMessage()]);
    exit();
}
?>

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
    exit();
}

$userId = $_SESSION['user_id'];

// Check permissions
$vorstandCanEditUI = filter_var(getConfigValue('vorstand_can_edit_UI'), FILTER_VALIDATE_BOOLEAN);
$canEditUI = hasAdminRole($userId) || (hasVorstandRole($userId) && $vorstandCanEditUI);

if (!$canEditUI) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit();
}

// Get upload type from POST
$uploadType = $_POST['upload_type'] ?? '';

// Validate upload type
$validTypes = ['tabicon', 'logo', 'banner_image', 'gif'];
if (!in_array($uploadType, $validTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Upload-Typ']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMsg = isset($_FILES['file']) ? 'Upload-Fehler: ' . $_FILES['file']['error'] : 'Keine Datei hochgeladen';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit();
}

$file = $_FILES['file'];
$fileName = basename($file['name']);
$fileTmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$fileType = mime_content_type($fileTmpPath);

// Validate file type
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
if (!in_array($fileType, $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Dateityp. Nur Bilder sind erlaubt.']);
    exit();
}

// Validate file size (max 5MB for most, 10MB for banner images)
$maxSize = ($uploadType === 'banner_image') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
if ($fileSize > $maxSize) {
    $maxMB = $maxSize / (1024 * 1024);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Datei zu groß. Maximum: {$maxMB}MB"]);
    exit();
}

// Get image dimensions
$imageInfo = getimagesize($fileTmpPath);
if ($imageInfo === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Bilddatei']);
    exit();
}
$dimensions = $imageInfo[0] . 'x' . $imageInfo[1];

// Get file extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Generate unique filename
$uniqueName = uniqid() . '_' . time() . '.' . $fileExtension;

// Define upload directories and database tables
$uploadPaths = [
    'tabicon' => [
        'dir' => '../../assets/icons/tabicons/',
        'table' => 'icons',
        'config_key' => 'tabicon',
        'display_name' => $fileName
    ],
    'logo' => [
        'dir' => '../../assets/icons/logos/',
        'table' => 'logos',
        'config_key' => 'logo',
        'display_name' => $fileName
    ],
    'banner_image' => [
        'dir' => '../../assets/images/banner/',
        'table' => 'banner_images',
        'config_key' => 'banner_image',
        'display_name' => $fileName
    ],
    'gif' => [
        'dir' => '../../assets/images/gifs/',
        'table' => 'gifs',
        'config_key' => 'current_gif',
        'display_name' => $fileName
    ]
];

$uploadConfig = $uploadPaths[$uploadType];
$targetDir = __DIR__ . '/' . $uploadConfig['dir'];
$targetPath = $targetDir . $uniqueName;

// Create directory if it doesn't exist
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Verzeichnis konnte nicht erstellt werden']);
        exit();
    }
}

// Move uploaded file
if (!move_uploaded_file($fileTmpPath, $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden']);
    exit();
}

// Add to history in database
try {
    global $config;
    
    if ($config === null) {
        throw new Exception('Datenbankverbindung nicht verfügbar');
    }
    
    // For GIFs, get season from POST data
    $gifSeason = ($uploadType === 'gif' && isset($_POST['season'])) ? $_POST['season'] : null;
    
    // Determine the typ/file extension to store
    $typ = ($uploadType === 'gif' && $gifSeason) ? $gifSeason : $fileExtension;
    
    // Insert into appropriate history table
    $tableName = $uploadConfig['table'];
    $sql = "INSERT INTO {$tableName} (name, link, dimensions, typ, datum) VALUES (:name, :link, :dimensions, :typ, :datum)";
    
    $stmt = $config->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage');
    }
    
    $stmt->bindValue(':name', $uploadConfig['display_name'], SQLITE3_TEXT);
    $stmt->bindValue(':link', $uniqueName, SQLITE3_TEXT);
    $stmt->bindValue(':dimensions', $dimensions, SQLITE3_TEXT);
    $stmt->bindValue(':typ', $typ, SQLITE3_TEXT);
    $stmt->bindValue(':datum', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception('Fehler beim Ausführen der Datenbankabfrage');
    }
    
    // Update current config value to use this file
    $configSuccess = setConfigValue($uploadConfig['config_key'], $uniqueName);
    if (!$configSuccess) {
        throw new Exception('Fehler beim Speichern des Config-Wertes');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Datei erfolgreich hochgeladen',
        'filename' => $uniqueName,
        'dimensions' => $dimensions
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Upload error: ' . $e->getMessage());
    
    // If database operation fails, remove the uploaded file
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
}
?>

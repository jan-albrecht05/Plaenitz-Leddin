<?php
    // Log helper functions (can be included by other scripts)
    require_once __DIR__ . '/db_helper.php';

    <?php
        // Log helper functions (can be included by other scripts)
        date_default_timezone_set('Europe/Berlin');
        require_once __DIR__ . '/db_helper.php';

        function getLogsDbConnection(){
            // connect to db
            $dbPath = __DIR__ . '/../assets/db/Logs.db';
            try {
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $pdo;
            } catch (Exception $e) {
                error_log('getLogsDbConnection error: ' . $e->getMessage());
                return null;
            }
        }
        // Function to log action into Logs.db
        function logAction($timecode, $action, $text = '', $ip = '', $user_id = '') {
            // If timecode is empty, use current time in Berlin
            if (empty($timecode)) {
                $dt = new DateTime('now', new DateTimeZone('Europe/Berlin'));
                $timecode = $dt->format('Y-m-d H:i:s');
            } else {
                // If timecode is not a valid datetime, try to parse and convert to Berlin
                try {
                    $dt = new DateTime($timecode);
                    $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
                    $timecode = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // fallback: use as-is
                }
            }
            $pdo = getLogsDbConnection();
            if (!$pdo) {
                return false;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO logs (timecode, action, text, ip, user_id) VALUES (:timecode, :action, :text, :ip, :user_id)");
                $stmt->bindParam(':timecode', $timecode);
                $stmt->bindParam(':action', $action);
                $stmt->bindParam(':text', $text);
                $stmt->bindParam(':ip', $ip);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                return true;
            } catch (Exception $e) {
                error_log('logAction error: ' . $e->getMessage());
                return false;
            }
        }

        // HTTP endpoint handling when this file is requested directly.
        // Accepts application/x-www-form-urlencoded and application/json POST bodies.
        if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
            // Only accept POST for the endpoint
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'text' => 'Method not allowed']);
                exit();
            }

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $timecode = '';
            $action = '';
            $text = '';
            $ip = '';
            $user_id = '';

            // parse JSON body
            if (stripos($contentType, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $timecode = $data['timecode'] ?? '';
                    $action = $data['action'] ?? '';
                    $text = $data['text'] ?? '';
                    $ip = $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                    $user_id = $data['user_id'] ?? '';
                }
            } else {
                // fallback to form-encoded
                $timecode = $_POST['timecode'] ?? '';
                $action = $_POST['action'] ?? '';
                $text = $_POST['text'] ?? '';
                $ip = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                $user_id = $_POST['user_id'] ?? '';
            }

            if ($action !== '') {
                $ok = logAction($timecode, $action, $text, $ip, $user_id);
                if ($ok) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'success']);
                } else {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'error', 'text' => 'Failed to write log']);
                }
            } else {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'text' => 'Missing required fields']);
            }
            exit();
        }
            $ok = logAction($timecode, $action, $text, $ip, $user_id);
            if ($ok) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'success']);
            } else {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'text' => 'Failed to write log']);
            }
        } else {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'text' => 'Missing required fields']);
        }
        exit();
    }
?>
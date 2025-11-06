<?php
// Inspect 'veranstaltungen' table schema and show last 10 rows
$dbPath = __DIR__ . '/../assets/db/veranstaltungen.db';
if (!file_exists($dbPath)) {
    echo "Database file not found: $dbPath\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "PRAGMA table_info('veranstaltungen'):\n";
    $stmt = $pdo->query("PRAGMA table_info('veranstaltungen')");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cols)) {
        echo "Table 'veranstaltungen' not found or has no columns.\n";
    } else {
        foreach ($cols as $c) {
            printf("%2d %-20s %-10s notnull=%s default=%s\n", $c['cid'], $c['name'], $c['type'], $c['notnull'], $c['dflt_value']);
        }
    }

    echo "\nLast 10 rows from 'veranstaltungen':\n";
    $stmt2 = $pdo->query("SELECT * FROM veranstaltungen ORDER BY id DESC LIMIT 10");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "(no rows)\n";
    } else {
        foreach ($rows as $r) {
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
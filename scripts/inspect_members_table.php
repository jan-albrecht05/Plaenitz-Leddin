<?php
// Simple script to inspect the `mitglieder` table schema in member.db
require_once __DIR__ . '/../includes/db_helper.php';
$pdo = getMemberDbConnection();
if (!$pdo) {
    echo "Cannot open member DB\n";
    exit(1);
}

try {
    $stmt = $pdo->query("PRAGMA table_info('mitglieder')");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "Table 'mitglieder' not found or has no columns.\n";
    } else {
        echo "mitglieder table schema:\n";
        foreach ($rows as $col) {
            echo sprintf("%s | %s | %s | %s | %s\n", $col['cid'], $col['name'], $col['type'], $col['notnull'], $col['dflt_value']);
        }
    }
} catch (Exception $e) {
    echo "Error reading schema: " . $e->getMessage() . "\n";
    exit(1);
}

?>
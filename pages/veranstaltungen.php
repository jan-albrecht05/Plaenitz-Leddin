<?php
    session_start();
    // Include database helper functions
    require_once '../includes/db_helper.php';

    // initialize role flags (safe defaults)
    $is_admin = false;
    $is_vorstand = false;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $is_admin = hasAdminRole($user_id);
        $is_vorstand = hasVorstandRole($user_id);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veranstaltungen | Plänitz-Leddin</title>
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/veranstaltungen.css">
    <link rel="stylesheet" href="../assets/css/heading.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
        #veranstaltungen a{
            color: var(--primary-color);
        }
        #veranstaltungen .line{
            background-color: var(--primary-color);
            width: 100%;
        }
    </style>
</head>
<body>
    <div id="heading">
        <?php include '../pages/heading.php'; ?>
    </div>
    <div class="banner">
        <h1>Zwei Dörfer, eine Gemeinschaft</h1>
    </div>
    <div id="main">
        <div id="controls">
            <?php
                if($is_admin || $is_vorstand){
                    echo '<a href="internes/veranstaltung-erstellen.php" class="button" id="veranstaltung-erstellen"><span class="material-symbols-outlined">add</span> Veranstaltung erstellen</a>';
                }
                echo '';
            ?>
            <div id="suchfeld">
                <input type="text" id="suchfeld-input" placeholder="Veranstaltungen durchsuchen..." onkeyup="filterVeranstaltungen()">
                <button onclick="filterVeranstaltungen()">
                    <span class="material-symbols-outlined">search</span>
                </button>
            </div>
            <div id="buttons">
                <button id="list" onclick="toggleListView()" class="active">
                    <span class="material-symbols-outlined">view_list</span>
                </button>
                <button id="grid" onclick="toggleGridView()">
                    <span class="material-symbols-outlined">grid_view</span>
                </button>
            </div>
        </div>
        <div id="css">
            <link rel="stylesheet" href="../assets/css/list-view.css">
            <!-- JS dynamically loads CSS for list/grid view toggling -->
        </div>
        <script src="../assets/js/veranstaltungen.js"></script>
        <div id="veranstaltungen">
            <?php
                // dynamic event loading
                $dbPath = __DIR__ . '/../assets/db/veranstaltungen.db';

                // Basic existence/readability checks so we can log a helpful error instead of a fatal exception
                if (!file_exists($dbPath)) {
                    error_log("event.php: database file not found: $dbPath");
                    $event = null;
                } elseif (!is_readable($dbPath)) {
                    error_log("event.php: database file not readable by PHP process: $dbPath");
                    $event = null;
                } else {
                    try {
                        // Use PDO with sqlite for safer, consistent parameter binding and exceptions
                        $pdo = new PDO('sqlite:' . $dbPath);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $stmt = $pdo->prepare('SELECT * FROM veranstaltungen ORDER BY datum DESC');
                        $stmt->execute();
                        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($events === false) {
                            $events = []; // no rows found
                        }
                    } catch (Exception $e) {
                        error_log('event.php: DB error - ' . $e->getMessage());
                        //$event = null;
                    }
                }
                if (!empty($events)) {
                    foreach($events as $event){
                    echo '<div class="event">';
                        echo '<div class="flex-column">';
                            echo '<h2 class="name">' . htmlspecialchars($event['titel']) . '</h2>';
                            $date = new DateTime($event['datum']);
                            echo '<h4 class="date">' . $date->format('d.m.Y') . '</h4>';
                        echo '</div>';
                        echo '<p class="description">' . htmlspecialchars($event['beschreibung']) . '</p>';
                        $now = new DateTime();
                        $event_date = new DateTime($event['datum']);
                        $interval = $now->diff($event_date);
                        $days_left = $interval->days;
                        if($event_date < $now){
                            echo '<h4 class="days-left">Veranstaltung vorbei</h4>';
                        } else {
                            echo '<h4 class="days-left">Noch ' . $days_left . ' Tage</h4>';
                        }
                        echo '<a href="event.php?id=' . urlencode($event['id']) . '" class="more-info">';
                            echo '<span class="text">Mehr erfahren</span>';
                            echo '<span class="material-symbols-outlined">arrow_forward</span>';
                        echo '</a>';
                        echo '<div class="line"></div>';
                    echo '</div>';
                    }
                } else {
                    echo '<p>Keine Veranstaltungen gefunden.</p>';
                }
            ?>
        </div>
    </div>
    <div id="footer" class="center">
        <?php include '../pages/footer.php'; ?>
    </div>
</body>
</html>
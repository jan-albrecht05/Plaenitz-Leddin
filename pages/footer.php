        <div id="left">
            <div id="mode-toggle">
                <span class="material-symbols-outlined">light_mode</span>
                <label class="switch">
                    <input type="checkbox" id="toggle-checkbox">
                    <span class="slider round"></span>
                </label>
                <span class="material-symbols-outlined">dark_mode</span>
                <script src="../assets/js/mode.js"></script>
            </div>
            <?php
            if($is_admin){
                echo '<a href="../pages/internes/admin.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>';
            }else{
                echo '<a href="../pages/internes/dashboard.php">Admin-Anmeldung<span class="material-symbols-outlined">open_in_new</span></a>';
            }
            ?>
        </div>
        <div id="middle">
            <span>&copy; 2025 Plänitz-Leddin. Alle Rechte vorbehalten.</span>
            <a href="https://github.com/jan-albrecht05/Plaenitz-Leddin/commits/main/">Version 0.5</a>
        </div>
        <div id="right">
            <a href="../pages/kontakt.php">Kontakt<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="../pages/datenschutz.php">Datenschutz<span class="material-symbols-outlined">open_in_new</span></a>
            <a href="../pages/impressum.php">Impressum<span class="material-symbols-outlined">open_in_new</span></a>
        </div>
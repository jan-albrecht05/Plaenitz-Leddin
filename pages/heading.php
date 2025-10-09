<div id="left">
            <a href="../index.php">
                <img src="../assets/icons/logo.png" alt="">
                <img src="../../assets/icons/logo.png" alt="">
            </a>
        </div>
        <div id="right">
            <div class="link" id="startseite">
                <a href="../index.php">Startseite</a>
                <span class="line"></span>
            </div>
            <div class="link" id="uber-uns">
                <a href="#">Über uns</a>
                <span class="line"></span>
            </div>
            <div class="link" id="veranstaltungen">
                <a href="../pages/veranstaltungen.php">Veranstaltungen</a>
                <span class="line"></span>
            </div>
            <div class="link" id="kontakt">
                <a href="../pages/kontakt.php">Kontakt</a>
                <span class="line"></span>
            </div>
            <button id="mitglied-werden" onclick="location.href='../pages/mitglied-werden.php'">Mitglied werden</button>
            <?php
<<<<<<< Updated upstream
            $is_admin = false;
            if($is_admin) {
=======
            $is_admin = true;
            $is_vorstand = false;
            if($is_admin || $is_vorstand){
>>>>>>> Stashed changes
                echo '<div id="admin-buttons">
                        <a id="admin-button" onclick="location.href=\'internes/admin.php\'">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                        </a>
                        <a id="notifications-button" onclick="showNotifications()">
                            <span class="material-symbols-outlined">notifications</span>
                            <span id="notification-indicator"></span>
                        </a>
                    </div>';
            }
            ?>
        </div>
        <script src="../assets/js/navbar.js"></script>
        <script src="../../assets/js/navbar.js"></script>
        <a href="javascript:void(0);" style="font-size:15px;" class="icon" onclick="dreibalkensymbol()">&#9776;</a>
    </div>
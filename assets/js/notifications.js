// Notifications popup helpers and mark-as-read handler
(function () {
function qs(id) { return document.getElementById(id); }

  // Delegate click to handle dynamically rendered button
    document.addEventListener('click', function (e) {
        if (!e.target || e.target.id !== 'mark-read') return;
            var btn = e.target;
            var userId = btn.getAttribute('data-user-id') || '';
            var endpoint = btn.getAttribute('data-endpoint') || 'pages/internes/mark_notifications_read.php';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    if (typeof window.hideNotifications === 'function') {
                        window.hideNotifications();
                    }
                    var content = qs('notifications-content');
                    if (content) {
                        content.innerHTML = '<p>Keine neuen Benachrichtigungen.</p>';
                    }
                    var indicator = qs('notification-indicator');
                    if (indicator) {
                        indicator.style.display = 'none';
                    }
                    } else {
                        console.error('Mark notifications read failed', xhr.status, xhr.responseText);
                    }
                }
            };
        xhr.send('user_id=' + encodeURIComponent(userId));
    });
})();

// Log deletion function
            function deleteLogsByAction(action, count) {
                if (!confirm('Möchten Sie wirklich alle ' + count + ' Log-Einträge der Aktion "' + action + '" löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden!')) {
                    return;
                }

                const params = new URLSearchParams();
                params.append('action', 'delete_logs');
                params.append('log_type', action);

                fetch('../../includes/delete-logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: params.toString()
                })
                .then(async response => {
                    const raw = await response.text();
                    const cleaned = raw.replace(/^\uFEFF/, ''); // strip UTF-8 BOM if present
                    let data;
                    try {
                        data = JSON.parse(cleaned);
                    } catch (e) {
                        throw new Error('Ungültige Antwort vom Server: ' + raw.slice(0, 200));
                    }

                    if (!response.ok) {
                        const message = data.message || ('HTTP ' + response.status);
                        throw new Error(message);
                    }

                    if (data.success) {
                        const deletedCount = typeof data.deleted !== 'undefined' ? data.deleted : (data.count || 0);
                        const logPromise = logDeletionOutcome(true, action, deletedCount);
                        Promise.resolve(logPromise).finally(() => location.reload());
                    } else {
                        const message = data.message || 'Unbekannter Fehler';
                        alert('✗ Fehler beim Löschen: ' + message);
                        logDeletionOutcome(false, action, message);
                    }
                })
                .catch(error => {
                    console.error('Lösch-Fehler:', error);
                    alert('✗ Fehler beim Löschen der Logs:\n' + error.message + '\n\nMöglicherweise haben Sie keine Berechtigung oder die Verbindung wurde unterbrochen.');
                    logDeletionOutcome(false, action, error.message);
                });
            }

            function logDeletionOutcome(success, logType, detail) {
                const params = new URLSearchParams();
                const safeName = window.currentUserName || 'Admin';
                const safeDetail = (typeof detail === 'number' ? detail + ' Einträge' : detail || '');
                const text = success
                    ? safeName + ' hat ' + safeDetail + ' der Aktion "' + logType + '" gelöscht.'
                    : safeName + ' konnte Logs der Aktion "' + logType + '" nicht löschen: ' + safeDetail;

                params.append('action', success ? 'logs-delete-success' : 'logs-delete-error');
                params.append('text', text);
                if (window.currentUserId) {
                    params.append('user_id', window.currentUserId);
                }

                return fetch('../../includes/log-data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: params.toString()
                }).catch(() => {
                    // Logging failure is non-blocking for the UI
                });
            }
// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Helper function to show messages
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + type;
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background-color: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    // ===== DRAG AND DROP SETUP =====
    const dropZones = document.querySelectorAll('.file-drop-zone');
    
    dropZones.forEach(zone => {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop zone when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, () => {
                zone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, () => {
                zone.classList.remove('drag-over');
            }, false);
        });
        
        // Handle dropped files
        zone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            const fileInput = zone.querySelector('input[type="file"]');
            if (fileInput && files.length > 0) {
                fileInput.files = files;
                // Trigger change event to handle the file
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, false);
        
        // Click to open file dialog
        zone.addEventListener('click', function() {
            zone.querySelector('input[type="file"]').click();
        }, false);
    });

    // ===== TOGGLE SWITCHES =====
    const toggleSwitches = document.querySelectorAll('.switch input[data-config-key]');
    toggleSwitches.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const configKey = this.getAttribute('data-config-key');
            const value = this.checked ? '1' : '0';
            
            saveConfigValue(configKey, value);
        });
    });
    
    // ===== BANNER TEXT FORM =====
    const bannerTextForm = document.getElementById('banner-text-form');
    if (bannerTextForm) {
        bannerTextForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const bannerTextInput = document.getElementById('banner-text-input');
            const bannerText = bannerTextInput.value.trim();
            
            if (!bannerText) {
                showMessage('Bitte geben Sie einen Banner-Text ein.', 'error');
                return;
            }
            
            saveConfigValue('banner_text', bannerText);
        });
    }
    
    // ===== COLOR PICKER =====
    const colorSaveBtn = document.getElementById('color-save-btn');
    if (colorSaveBtn) {
        colorSaveBtn.addEventListener('click', function() {
            const colorInput = document.getElementById('color-input');
            const color = colorInput.value;
            
            saveConfigValue('primary_color', color);
        });
    }
    
    // ===== NOTIFICATION FORM =====
    const notificationForm = document.getElementById('notification-form');
    if (notificationForm) {
        notificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const heading = document.getElementById('notification-heading').value.trim();
            const text = document.getElementById('notification-text').value.trim();
            const startTime = document.getElementById('notification-start').value;
            const endTime = document.getElementById('notification-end').value;
            
            if (!heading || !text || !startTime || !endTime) {
                showMessage('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }
            
            submitNotification('notification', heading, text, startTime, endTime);
        });
    }
    
    // ===== MAINTENANCE MESSAGE FORM =====
    const maintenanceForm = document.getElementById('maintenance-form');
    if (maintenanceForm) {
        maintenanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const heading = document.getElementById('maintenance-heading').value.trim();
            const text = document.getElementById('maintenance-text').value.trim();
            const startTime = document.getElementById('maintenance-start').value;
            const endTime = document.getElementById('maintenance-end').value;
            
            if (!heading || !text || !startTime || !endTime) {
                showMessage('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }
            
            submitNotification('maintenance', heading, text, startTime, endTime);
        });
    }
    
    // ===== DELETE MESSAGE BUTTONS =====
    const deleteButtons = document.querySelectorAll('.delete-message-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            const messageType = this.getAttribute('data-message-type');
            
            if (confirm('Möchten Sie diese Nachricht wirklich löschen?')) {
                deleteMessage(messageId, messageType);
            }
        });
    });
    
    // ===== EDIT MESSAGE BUTTONS =====
    const editButtons = document.querySelectorAll('.edit-message-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            alert('Bearbeitungsfunktion wird noch implementiert. Message ID: ' + messageId);
        });
    });
});

// ===== API FUNCTIONS =====

function saveConfigValue(key, value) {
    fetch('../../includes/api/save-config-value.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            config_key: key,
            config_value: value
        })
    })
    .then(response => {
        // Log response for debugging
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', text);
            throw new Error('Ungültige JSON-Antwort vom Server');
        }
    })
    .then(data => {
        if (data.success) {
            showMessage('Einstellung erfolgreich gespeichert!', 'success');
            
            // Reload if certain values changed
            if (key === 'vorstand_can_edit_UI' || key === 'vorstand_can_edit_config') {
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Fehler beim Speichern der Einstellung: ' + error.message, 'error');
    });
}

function submitNotification(type, heading, text, startTime, endTime) {
    // Convert datetime-local format to proper datetime format
    const start = convertToDateTime(startTime);
    const end = convertToDateTime(endTime);
    
    fetch('../../includes/api/add-notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            typ: type,
            heading: heading,
            text: text,
            startzeit: start,
            endzeit: end
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(type === 'maintenance' ? 'Wartungsmitteilung erstellt!' : 'Benachrichtigung erstellt!', 'success');
            
            // Clear form
            if (type === 'notification') {
                document.getElementById('notification-form').reset();
            } else {
                document.getElementById('maintenance-form').reset();
            }
            
            // Reload list
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Fehler beim Erstellen der Nachricht.', 'error');
    });
}

function deleteMessage(messageId, messageType) {
    fetch('../../includes/api/delete-notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message_id: messageId,
            message_type: messageType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Nachricht gelöscht!', 'success');
            
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Fehler beim Löschen der Nachricht.', 'error');
    });
}

// Helper function to convert datetime-local to YYYY-MM-DD HH:MM:SS format
function convertToDateTime(dateTimeLocal) {
    if (!dateTimeLocal) return '';
    const date = new Date(dateTimeLocal);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

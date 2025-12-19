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
    // Create context menu element
    const contextMenu = document.createElement('div');
    contextMenu.className = 'context-menu';
    contextMenu.id = 'image-context-menu';
    contextMenu.innerHTML = `
        <div class="context-menu-item" data-action="use">
            <span class="material-symbols-outlined">check_circle</span>
            Als aktuell setzen
        </div>
        <div class="context-menu-item delete" data-action="delete">
            <span class="material-symbols-outlined">delete</span>
            Löschen
        </div>
    `;
    document.body.appendChild(contextMenu);

    // Close context menu when clicking outside
    document.addEventListener('click', function() {
        contextMenu.classList.remove('active');
    });

    // Prevent default context menu
    document.addEventListener('contextmenu', function(e) {
        const contextable = e.target.closest('.image-item-contextable');
        if (contextable) {
            e.preventDefault();
        }
    });

    // Initialize context menu for existing images on page load
    initializeContextMenuForExistingImages();

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

    // ===== FILE UPLOAD HANDLERS =====
    // Tabicon upload
    const tabiconInput = document.getElementById('tabicon-file-input');
    if (tabiconInput) {
        tabiconInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadFile(this.files[0], 'tabicon', null, 'icons');
            }
        });
    }

    // Logo upload
    const logoInput = document.getElementById('logo-file-input');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadFile(this.files[0], 'logo', null, 'logos');
            }
        });
    }

    // Banner image upload
    const bannerImageInput = document.getElementById('banner-image-file-input');
    if (bannerImageInput) {
        bannerImageInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadFile(this.files[0], 'banner_image', null, 'banner_images');
            }
        });
    }

    // GIF upload
    const gifInput = document.getElementById('gif-file-input');
    if (gifInput) {
        gifInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const season = prompt('Für welche Jahreszeit ist dieses GIF?\nMögliche Werte: Frühling, Sommer, Herbst, Winter, Ganzjährig');
                if (season) {
                    uploadFile(this.files[0], 'gif', season, 'gifs');
                }
            }
        });
    }

    // ===== VERSION FORM =====
    const versionForm = document.getElementById('version-form');
    if (versionForm) {
        versionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const versionInput = document.getElementById('version-input');
            const version = versionInput.value.trim();
            
            if (!version) {
                showMessage('Bitte geben Sie eine Version ein.', 'error');
                return;
            }
            
            saveConfigValue('system_version', version);
            versionInput.value = '';
        });
    }

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
    
    // ===== COST FORM =====
    const costForm = document.getElementById('cost-form');
    if (costForm) {
        costForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const costInput = document.getElementById('cost-input');
            const cost = costInput.value.trim();
            
            if (!cost) {
                showMessage('Bitte geben Sie die Kosten ein.', 'error');
                return;
            }
            
            // Validate that it's a number
            const costNumber = parseFloat(cost.replace(',', '.'));
            if (isNaN(costNumber) || costNumber < 0) {
                showMessage('Bitte geben Sie einen gültigen Betrag ein.', 'error');
                return;
            }
            
            saveConfigValue('kosten_pro_jahr', costNumber);
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
        credentials: 'same-origin', // Ensure cookies are sent
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

// Upload file function
function uploadFile(file, uploadType, season = null, listType = null) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
    if (!allowedTypes.includes(file.type)) {
        showMessage('Ungültiger Dateityp. Nur Bilder sind erlaubt.', 'error');
        return;
    }

    // Validate file size (max 5MB for most, 10MB for banner images)
    const maxSize = (uploadType === 'banner_image') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
    if (file.size > maxSize) {
        const maxMB = maxSize / (1024 * 1024);
        showMessage(`Datei zu groß. Maximum: ${maxMB}MB`, 'error');
        return;
    }

    // Create FormData
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', uploadType);
    if (season) {
        formData.append('season', season);
    }

    // Show loading message
    showMessage('Upload läuft...', 'success');

    // Upload file
    fetch('../../includes/api/upload-file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return text;
        });
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Ungültige JSON-Antwort');
        }
    })
    .then(data => {
        if (data.success) {
            showMessage('Datei erfolgreich hochgeladen!', 'success');
            
            // Reload the specific list instead of the whole page
            if (listType) {
                reloadImageList(listType);
            }
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        showMessage('Fehler beim Hochladen der Datei: ' + error.message, 'error');
    });
}

// Reload image list dynamically
function reloadImageList(listType) {
    const listMappings = {
        'icons': { containerId: 'tabicon-list', pathPrefix: '../../assets/icons/tabicons/' },
        'logos': { containerId: 'logos-list', pathPrefix: '../../assets/icons/logos/' },
        'banner_images': { containerId: 'banner-images-list', pathPrefix: '../../assets/images/banner/' },
        'gifs': { containerId: 'gifs-list', pathPrefix: '../../assets/images/gifs/' }
    };
    
    const mapping = listMappings[listType];
    if (!mapping) return;
    
    fetch(`../../includes/api/get-images.php?type=${listType}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const container = document.getElementById(mapping.containerId);
            if (!container) return;
            
            if (data.images.length === 0) {
                container.innerHTML = '<p class="no-items">Keine Einträge gefunden.</p>';
                return;
            }
            
            container.innerHTML = '';
            data.images.forEach((image, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = listType === 'icons' ? 'tabicon-item' : 
                                   listType === 'logos' ? 'logos-item' : 
                                   listType === 'banner_images' ? 'banner-image-item' : 'gif-item';
                
                // Add context menu class for all except first (current) image
                if (index > 0) {
                    itemDiv.classList.add('image-item-contextable');
                    itemDiv.dataset.imageId = image.id;
                    itemDiv.dataset.imageType = listType;
                }
                
                const imgPath = mapping.pathPrefix + image.link;
                let html = `<img src="${imgPath}" alt="${image.name}">`;
                
                if (listType !== 'banner_images') {
                    html += `<h4 class="bildname">${image.name}</h4>`;
                }
                
                if (listType === 'gifs' && image.type) {
                    html += `<p class="season">Jahreszeit: ${image.type}</p>`;
                }
                
                if (image.dimensions) {
                    html += `<p class="dimensions">${image.dimensions}</p>`;
                }
                
                if (image.datum) {
                    const date = new Date(image.datum);
                    const formattedDate = date.toLocaleDateString('de-DE');
                    html += `<p class="date">${formattedDate}</p>`;
                }
                
                itemDiv.innerHTML = html;
                
                // Add context menu event listener for items that can have it
                if (index > 0) {
                    itemDiv.addEventListener('contextmenu', function(e) {
                        e.preventDefault();
                        showContextMenu(e, image.id, listType);
                    });
                }
                
                container.appendChild(itemDiv);
            });
        }
    })
    .catch(error => {
        console.error('Error reloading list:', error);
    });
}

// Show context menu
function showContextMenu(event, imageId, imageType) {
    const contextMenu = document.getElementById('image-context-menu');
    
    if (!contextMenu) {
        return;
    }
    
    // Position context menu (use clientX/Y for fixed positioning)
    contextMenu.style.left = event.clientX + 'px';
    contextMenu.style.top = event.clientY + 'px';
    contextMenu.classList.add('active');
    
    // Remove old event listeners
    const newMenu = contextMenu.cloneNode(true);
    contextMenu.parentNode.replaceChild(newMenu, contextMenu);
    
    // Add new event listeners
    const useButton = newMenu.querySelector('[data-action="use"]');
    const deleteButton = newMenu.querySelector('[data-action="delete"]');
    
    useButton.addEventListener('click', function(e) {
        e.stopPropagation();
        setCurrentImage(imageId, imageType);
        newMenu.classList.remove('active');
    });
    
    deleteButton.addEventListener('click', function(e) {
        e.stopPropagation();
        if (confirm('Möchten Sie dieses Bild wirklich löschen?')) {
            deleteImage(imageId, imageType);
        }
        newMenu.classList.remove('active');
    });
}

// Set image as current
function setCurrentImage(imageId, imageType) {
    fetch('../../includes/api/set-current-image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            image_type: imageType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Bild wurde als aktuell gesetzt!', 'success');
            reloadImageList(imageType);
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        showMessage('Fehler beim Setzen des Bildes: ' + error.message, 'error');
    });
}

// Delete image
function deleteImage(imageId, imageType) {
    fetch('../../includes/api/delete-image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            image_type: imageType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Bild wurde gelöscht!', 'success');
            reloadImageList(imageType);
        } else {
            showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
        }
    })
    .catch(error => {
        showMessage('Fehler beim Löschen des Bildes: ' + error.message, 'error');
    });
}

// Initialize context menu for images that are already on the page
function initializeContextMenuForExistingImages() {
    const listMappings = {
        'tabicon-list': 'icons',
        'logos-list': 'logos',
        'banner-images-list': 'banner_images',
        'gifs-list': 'gifs'
    };
    
    Object.keys(listMappings).forEach(containerId => {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const imageType = listMappings[containerId];
        const items = container.children;
        
        // Skip first item (current image), add context menu to rest
        for (let i = 1; i < items.length; i++) {
            const item = items[i];
            
            // Skip if it's the "no items" message
            if (item.classList.contains('no-items')) continue;
            
            // Add context menu class
            item.classList.add('image-item-contextable');
            
            // Try to get image ID from data attribute
            const imageId = item.dataset.imageId;
            
            if (imageId) {
                item.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    showContextMenu(e, imageId, imageType);
                });
            }
        }
    });
}
// hide context menu on scroll, click somewhere else and [esc] key
window.addEventListener('scroll', function() {
    const contextMenu = document.getElementById('image-context-menu');
    if (contextMenu) {
        contextMenu.classList.remove('active');
    }
});

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const contextMenu = document.getElementById('image-context-menu');
        if (contextMenu) {
            contextMenu.classList.remove('active');
        }
    }
});

window.addEventListener('click', function() {
    const contextMenu = document.getElementById('image-context-menu');
    if (contextMenu) {
        contextMenu.classList.remove('active');
    }
});
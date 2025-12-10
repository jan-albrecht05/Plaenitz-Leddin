// Admin Member Management JavaScript
// This file handles member context menu and AJAX operations in admin.php

console.log('admin-members.js loaded successfully');

// AJAX submit function for member actions (expose globally)
window.submitContextActionAjax = async function(action, memberId, tempPassword) {
    try {
        const params = new URLSearchParams();
        params.append('action', action);
        params.append('member_id', memberId);
        if (tempPassword) {
            params.append('temp_password', tempPassword);
        }

        const res = await fetch('admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: params.toString()
        });

        const data = await res.json();
        if (!data.success) {
            alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
            return;
        }

        location.reload();
    } catch (err) {
        alert('Netzwerkfehler: ' + err.message);
    }
};

// Open context menu
function opencontextMenu(memberId, event) {
    console.log('opencontextMenu called with memberId:', memberId);
    
    const contextMenu = document.getElementById('member-context-menu');
    if (!contextMenu) {
        console.error('Context menu element not found!');
        return;
    }
    
    console.log('Context menu found, setting display to block');
    contextMenu.style.display = 'block';
    
    // Position context menu at mouse position (left/right)
    if (event && window.innerWidth - event.pageX < contextMenu.offsetWidth) {
        contextMenu.style.left = (event.pageX - contextMenu.offsetWidth) + 'px';
    } else if (event) {
        contextMenu.style.left = event.pageX + 'px';
    }
    if (event) {
        contextMenu.style.top = event.pageY + 'px';
    }
    contextMenu.setAttribute('data-member-id', memberId);
    document.getElementById("context-member-id").value = memberId;
    
    // Get member element
    const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
    if (!memberElement) return;
    
    const statusText = memberElement.querySelector('.status-text')?.innerText || '';
    const rolleText = memberElement.querySelector('.role-text')?.innerText || '';
    
    console.log('opencontextMenu - Status:', statusText, 'Rolle:', rolleText);
    
    // Get all button references
    const activateBtn = document.getElementById('activate-member');
    const deactivateBtn = document.getElementById('deactivate-member');
    const upBtn = document.getElementById('up-member');
    const downBtn = document.getElementById('down-member');
    const makeAdminBtn = document.getElementById('make-admin-member');
    const removeAdminBtn = document.getElementById('remove-admin-member');
    
    // STEP 1: Hide ALL buttons first (bulletproof reset)
    if (activateBtn) activateBtn.style.display = 'none';
    if (deactivateBtn) deactivateBtn.style.display = 'none';
    if (upBtn) upBtn.style.display = 'none';
    if (downBtn) downBtn.style.display = 'none';
    if (makeAdminBtn) makeAdminBtn.style.display = 'none';
    if (removeAdminBtn) removeAdminBtn.style.display = 'none';
    
    console.log('All buttons hidden');
    
    // STEP 2: Show only the correct buttons based on status and role
    
    // Status-based buttons
    if (statusText === 'Aktiv') {
        if (deactivateBtn) {
            deactivateBtn.style.display = 'flex';
            console.log('Showing deactivate button');
        }
    } else {
        if (activateBtn) {
            activateBtn.style.display = 'flex';
            console.log('Showing activate button');
        }
    }
    
    // Role-based buttons (only for active members)
    if (statusText === 'Aktiv') {
        if (rolleText === 'Mitglied') {
            // Mitglied kann zu Vorstand befördert werden
            if (upBtn) {
                upBtn.style.display = 'flex';
                console.log('Showing up button (Mitglied)');
            }
            // Mitglied kann Admin-Rolle bekommen
            if (makeAdminBtn) {
                makeAdminBtn.style.display = 'flex';
                console.log('Showing make-admin button (Mitglied)');
            }
        } else if (rolleText === 'Vorstand') {
            // Vorstand kann zu Mitglied degradiert werden
            if (downBtn) {
                downBtn.style.display = 'flex';
                console.log('Showing down button (Vorstand)');
            }
            // Vorstand kann Admin-Rolle bekommen
            if (makeAdminBtn) {
                makeAdminBtn.style.display = 'flex';
                console.log('Showing make-admin button (Vorstand)');
            }
        } else if (rolleText === 'Admin') {
            // Admin kann Admin-Rolle verlieren (wird dann zu Mitglied)
            if (removeAdminBtn) {
                removeAdminBtn.style.display = 'flex';
                console.log('Showing remove-admin button (Admin)');
            }
        }
    }
}

// Close context menu
function closeContextMenu() {
    const contextMenu = document.getElementById('member-context-menu');
    if (contextMenu) {
        contextMenu.style.display = 'none';
    }
}

// Open edit popup
function openEditPopup(memberId) {
    const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
    if (!memberElement) return;
    
    document.getElementById('edit-member-id').value = memberId;
    document.getElementById('edit-titel').value = memberElement.dataset.titel || '';
    document.getElementById('edit-name').value = memberElement.dataset.name || '';
    document.getElementById('edit-nachname').value = memberElement.dataset.nachname || '';
    document.getElementById('edit-strasse').value = memberElement.dataset.strasse || '';
    document.getElementById('edit-hausnummer').value = memberElement.dataset.hausnummer || '';
    document.getElementById('edit-adresszusatz').value = memberElement.dataset.adresszusatz || '';
    document.getElementById('edit-plz').value = memberElement.dataset.plz || '';
    document.getElementById('edit-ort').value = memberElement.dataset.ort || '';
    document.getElementById('edit-telefon').value = memberElement.dataset.festnetz || '';
    document.getElementById('edit-mobilnummer').value = memberElement.dataset.mobilnummer || '';
    document.getElementById('edit-email').value = memberElement.dataset.email || '';
    
    const popup = document.getElementById('member-edit-popup');
    if (popup) {
        popup.style.display = 'flex';
    }
}

// Close edit popup
function closeMemberEditPopup() {
    const popup = document.getElementById('member-edit-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}

// Show promote password popup
function showPromotePasswordPopup(memberId) {
    pendingPromoteMemberId = memberId;
    const popup = document.getElementById('promote-password-popup');
    if (popup) {
        popup.style.display = 'flex';
        const pwField = document.getElementById('promote-temp-password');
        const pwConfField = document.getElementById('promote-temp-password-confirm');
        if (pwField) pwField.value = '';
        if (pwConfField) pwConfField.value = '';
        if (pwField) pwField.focus();
    }
}

// Cancel promote
function cancelPromote() {
    pendingPromoteMemberId = null;
    const popup = document.getElementById('promote-password-popup');
    if (popup) {
        popup.style.display = 'none';
    }
}

// Confirm promote
function confirmPromote() {
    const password = document.getElementById('promote-temp-password')?.value;
    const passwordConfirm = document.getElementById('promote-temp-password-confirm')?.value;
    
    if (!password || password.length < 8) {
        alert('Das Passwort muss mindestens 8 Zeichen lang sein.');
        return;
    }
    if (password !== passwordConfirm) {
        alert('Die Passwörter stimmen nicht überein.');
        return;
    }
    if (pendingPromoteMemberId) {
        const popup = document.getElementById('promote-password-popup');
        if (popup) popup.style.display = 'none';
        submitContextActionAjax('promote', pendingPromoteMemberId, password);
        pendingPromoteMemberId = null;
    }
}

// Delete logs by action (moved from inline script)
function deleteLogsByAction(action, count) {
    if (!confirm('Möchten Sie wirklich alle ' + count + ' Log-Einträge der Aktion "' + action + '" löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden!')) {
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: 'delete_logs_action=' + encodeURIComponent(action)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✓ ' + data.count + ' Log-Einträge erfolgreich gelöscht.');
            location.reload();
        } else {
            alert('✗ Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        console.error('Lösch-Fehler:', error);
        alert('✗ Fehler beim Löschen der Logs:\n' + error.message);
    });
}

document.querySelectorAll('.member').forEach(member => {
    member.addEventListener('click', () => {
        member.classList.toggle('open');
    });
});
// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Edit member form submission
    const editForm = document.getElementById('member-edit-popup')?.querySelector('form');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const params = new URLSearchParams(formData);
            
            try {
                const res = await fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: params.toString()
                });
                
                const data = await res.json();
                if (!data.success) {
                    alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                    return;
                }
                
                closeMemberEditPopup();
                location.reload();
            } catch (err) {
                alert('Netzwerkfehler: ' + err.message);
            }
        });
    }
    
    // Context menu buttons
    document.getElementById('edit-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId) {
            openEditPopup(memberId);
            closeContextMenu();
        }
    });
    
    document.getElementById('activate-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId) {
            submitContextActionAjax('activate', memberId);
            closeContextMenu();
        }
    });

    document.getElementById('deactivate-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId) {
            submitContextActionAjax('deactivate', memberId);
            closeContextMenu();
        }
    });

    document.getElementById('up-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId) {
            showPromotePasswordPopup(memberId);
            closeContextMenu();
        }
    });

    document.getElementById('down-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId && confirm('Vorstandsrolle entfernen?')) {
            submitContextActionAjax('demote', memberId);
            closeContextMenu();
        }
    });

    document.getElementById('make-admin-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId && confirm('Diesen Benutzer zum Admin machen? Diese Aktion gibt dem Benutzer volle Rechte!')) {
            submitContextActionAjax('make_admin', memberId);
            closeContextMenu();
        }
    });

    document.getElementById('remove-admin-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId && confirm('Admin-Rolle entfernen? Der Benutzer wird zum normalen Mitglied.')) {
            submitContextActionAjax('remove_admin', memberId);
            closeContextMenu();
        }
    });

    document.getElementById('delete-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const memberId = document.getElementById('context-member-id')?.value;
        if (memberId && confirm('Möchten Sie dieses Mitglied wirklich löschen?')) {
            submitContextActionAjax('delete', memberId);
            closeContextMenu();
        }
    });

    // Close on outside click
    document.addEventListener('click', (event) => {
        const contextMenu = document.getElementById('member-context-menu');
        const editPopup = document.getElementById('member-edit-popup');
        const clickedEl = event.target;
        
        if (contextMenu && contextMenu.style.display !== 'none' && 
            !contextMenu.contains(clickedEl) && 
            !clickedEl.closest('.edit-button')) {
            closeContextMenu();
        }
        
        if (editPopup && editPopup.style.display !== 'none' && 
            !editPopup.querySelector('.popup-content')?.contains(clickedEl)) {
            closeMemberEditPopup();
        }
    });
});

// Make functions globally available
window.opencontextMenu = opencontextMenu;
window.closeContextMenu = closeContextMenu;
window.submitContextActionAjax = submitContextActionAjax;
window.cancelPromote = cancelPromote;
window.confirmPromote = confirmPromote;
window.closeMemberEditPopup = closeMemberEditPopup;
window.deleteLogsByAction = deleteLogsByAction;

// wait for click on class member to toggle class open
document.querySelectorAll('.member').forEach(member => {
    member.addEventListener('click', () => {
        member.classList.toggle('open');
    });
});

// function to open context menu
function opencontextMenu(memberId, event) {
    const contextMenu = document.getElementById('member-context-menu');
    contextMenu.style.display = 'block';
    // position context menu at mouse position (left/right)
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
    contextMenuOpen = true;
    
    // Show the "change password" button only if the context menu is opened on the current user's own account
    try {
        const editPassBtn = document.getElementById('edit-password-member');
        if (editPassBtn) {
            if (typeof window.currentUserId !== 'undefined' && parseInt(window.currentUserId, 10) === parseInt(memberId, 10)) {
                editPassBtn.style.display = 'flex';
            } else {
                editPassBtn.style.display = 'none';
            }
        }
    } catch (e) {
        console.warn('opencontextMenu: unable to toggle edit-password-member button', e);
    }
    
    // Get member element and extract status/role
    const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
    if (!memberElement) return;
    
    const statusText = memberElement.querySelector('.status-text')?.innerText || '';
    const rolleText = memberElement.querySelector('.role-text')?.innerText || '';
    
    // Get all button references
    const activateBtn = document.getElementById('activate-member');
    const deactivateBtn = document.getElementById('deactivate-member');
    const upBtn = document.getElementById('up-member');
    const downBtn = document.getElementById('down-member');
    const makeAdminBtn = document.getElementById('make-admin-member');
    const removeAdminBtn = document.getElementById('remove-admin-member');
    const deleteBtn = document.getElementById('delete-member');
    
    // STEP 1: Hide ALL buttons first (bulletproof reset)
    if (activateBtn) activateBtn.style.display = 'none';
    if (deactivateBtn) deactivateBtn.style.display = 'none';
    if (upBtn) upBtn.style.display = 'none';
    if (downBtn) downBtn.style.display = 'none';
    if (makeAdminBtn) makeAdminBtn.style.display = 'none';
    if (removeAdminBtn) removeAdminBtn.style.display = 'none';
    if (deleteBtn) deleteBtn.style.display = 'none';
    
    // STEP 2: Show correct buttons based on status
    if (statusText === 'Aktiv') {
        if (deactivateBtn) deactivateBtn.style.display = 'flex';
    } else {
        if (activateBtn) activateBtn.style.display = 'flex';
    }
    
    // STEP 3: Show role-specific buttons (only for active members)
    if (statusText === 'Aktiv') {
        const isAdmin = !!window.currentUserIsAdmin;
        const isVorstand = !!window.currentUserIsVorstand;
        const isOwnAccount = (window.currentUserId && parseInt(window.currentUserId, 10) === parseInt(memberId, 10));
        
        if (rolleText === 'Mitglied') {
            // Mitglied kann zu Vorstand befördert werden (nur von Admin)
            if (upBtn && isAdmin) upBtn.style.display = 'flex';
            // Mitglied kann Admin-Rolle bekommen
            if (makeAdminBtn) makeAdminBtn.style.display = 'flex';
        } else if (rolleText === 'Vorstand') {
            // Vorstand kann zu Mitglied degradiert werden (nur von Admin)
            if (downBtn && isAdmin) downBtn.style.display = 'flex';
            // Vorstand kann Admin-Rolle bekommen
            if (makeAdminBtn) makeAdminBtn.style.display = 'flex';
        } else if (rolleText === 'Admin') {
            // Admin kann Admin-Rolle verlieren (aber nicht bei sich selbst)
            if (removeAdminBtn && !isOwnAccount) removeAdminBtn.style.display = 'flex';
        }
        
        // Show delete button for admin or vorstand (aber nicht für den eigenen Account)
        if (deleteBtn && (isAdmin || isVorstand) && !isOwnAccount) {
            deleteBtn.style.display = 'flex';
        }
    }
    
    // Show the "change password" button (already handled above)
    try {
        const editPassBtn = document.getElementById('edit-password-member');
        if (editPassBtn && typeof window.currentUserId !== 'undefined' && 
            parseInt(window.currentUserId, 10) === parseInt(memberId, 10)) {
            editPassBtn.style.display = 'flex';
        }
    } catch (e) {
        console.warn('opencontextMenu: password button toggle failed', e);
    }
}

// Close context menu function
function closeContextMenu() {
    const contextMenu = document.getElementById('member-context-menu');
    contextMenu.style.display = 'none';
}

// Add event listeners to context menu buttons
document.addEventListener('DOMContentLoaded', () => {
    // Activate member
    document.getElementById('activate-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        if (memberId && window.submitContextActionAjax) {
            submitContextActionAjax('activate', memberId);
            closeContextMenu();
        }
    });

    // Deactivate member
    document.getElementById('deactivate-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        if (memberId && window.submitContextActionAjax) {
            submitContextActionAjax('deactivate', memberId);
            closeContextMenu();
        }
    });

    // Promote member
    document.getElementById('up-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        if (memberId && window.submitContextAction) {
            submitContextAction('promote', memberId);
            closeContextMenu();
        }
    });

    // Demote member
    document.getElementById('down-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        if (memberId && window.submitContextActionAjax) {
            submitContextActionAjax('demote', memberId);
            closeContextMenu();
        }
    });

    // Delete member
    document.getElementById('delete-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        if (memberId && confirm('Möchten Sie dieses Mitglied wirklich löschen?')) {
            if (window.submitContextActionAjax) {
                submitContextActionAjax('delete', memberId);
                closeContextMenu();
            }
        }
    });

    // Edit member
    document.getElementById('edit-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation(); // Prevent event from bubbling to document click handler
        const memberId = document.getElementById('context-member-id').value;
        if (memberId) {
            // Open edit popup
            const popup = document.getElementById('member-edit-popup');
            popup.style.display = 'flex';
            document.getElementById('edit-member-id').value = memberId;
            closeContextMenu();
            // Load member data into form
            loadMemberDataIntoForm(memberId);
        }        
    });

    // change own password
    document.getElementById('edit-password-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        // Open password change popup
        const popup = document.getElementById('password-change-popup');
        if (popup) {
            popup.style.display = 'flex';
        }
        closeContextMenu();
    });
});

// close context menu and member edit popup on click outside or escape key
document.addEventListener('click', (event) => {
    const contextMenu = document.getElementById('member-context-menu');
    // determine the clicked element (safely handle non-element targets)
    const clickedEl = (event.target && event.target.nodeType === Node.ELEMENT_NODE) ? event.target : event.target && event.target.parentElement;

    // Close context menu if click is outside and it's currently visible
    // but do NOT close when clicking the three-dots button (or its children)
    if (contextMenu && contextMenu.style.display !== 'none' && !contextMenu.contains(clickedEl) && !(clickedEl && clickedEl.closest && clickedEl.closest('.edit-button'))) {
        contextMenu.style.display = 'none';
    }

    const popup = document.getElementById('member-edit-popup');
    // Close popup if click is outside and it's currently visible
    if (popup && popup.style.display !== 'none' && !popup.contains(clickedEl)) {
        closeMemberEditPopup();
    }
});

document.addEventListener('keydown', (event) => {
    const contextMenu = document.getElementById('member-context-menu');
    if (event.key === 'Escape') {
        contextMenu.style.display = 'none';
    }
    const popup = document.getElementById('member-edit-popup');
    if (event.key === 'Escape') {
        closeMemberEditPopup();
    }
});

// Function to close member edit popup
function closeMemberEditPopup() {
    const popup = document.getElementById('member-edit-popup');
    popup.style.display = 'none';
}

// Function to load member data into edit form
function loadMemberDataIntoForm(memberId) {
    const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
    if (!memberElement) return;
    
    // Extract data from member element
    const titel = memberElement.getAttribute('data-titel') || '';
    const name = memberElement.getAttribute('data-name') || '';
    const nachname = memberElement.getAttribute('data-nachname') || '';
    const strasse = memberElement.getAttribute('data-strasse') || '';
    const hausnummer = memberElement.getAttribute('data-hausnummer') || '';
    const adresszusatz = memberElement.getAttribute('data-adresszusatz') || '';
    const plz = memberElement.getAttribute('data-plz') || '';
    const ort = memberElement.getAttribute('data-ort') || '';
    const festnetz = memberElement.getAttribute('data-festnetz') || '';
    const mobilnummer = memberElement.getAttribute('data-mobilnummer') || '';
    const email = memberElement.getAttribute('data-email') || '';
    
    // Fill form fields
    document.getElementById('edit-titel').value = titel;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-nachname').value = nachname;
    document.getElementById('edit-strasse').value = strasse;
    document.getElementById('edit-hausnummer').value = hausnummer;
    document.getElementById('edit-adresszusatz').value = adresszusatz;
    document.getElementById('edit-plz').value = plz;
    document.getElementById('edit-ort').value = ort;
    document.getElementById('edit-telefon').value = festnetz;
    document.getElementById('edit-mobilnummer').value = mobilnummer;
    document.getElementById('edit-email').value = email;
}
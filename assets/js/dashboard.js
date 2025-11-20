// wait for click on class member to toggle class open
document.querySelectorAll('.member').forEach(member => {
    member.addEventListener('click', () => {
        member.classList.toggle('open');
    });
});

// function to open context menu
function opencontextMenu(memberId) {
    const contextMenu = document.getElementById('member-context-menu');
    contextMenu.style.display = 'block';
    // position context menu at mouse position (left/right)
    if (window.innerWidth - event.pageX < contextMenu.offsetWidth) {
        contextMenu.style.left = (event.pageX - contextMenu.offsetWidth) + 'px';
    } else {
        contextMenu.style.left = event.pageX + 'px';
    }
    contextMenu.style.top = event.pageY + 'px';
    contextMenu.setAttribute('data-member-id', memberId);
    document.getElementById("context-member-id").value = memberId;
    contextMenuOpen = true;
    // show/hide context menu items based on member status
    const memberElement = document.querySelector(`.member[data-member-id='${memberId}']`);
    const statusText = memberElement.querySelector('.status-text').innerText;
    if (statusText === 'Aktiv') {
        document.getElementById('deactivate-member').style.display = 'flex';
        document.getElementById('activate-member').style.display = 'none';
    } else {
        document.getElementById('deactivate-member').style.display = 'none';
        document.getElementById('activate-member').style.display = 'flex';
        document.getElementById('up-member').style.display = 'none';
        document.getElementById('down-member').style.display = 'none';
    }
    const rolleText = memberElement.querySelector('.role-text').innerText;
    if (rolleText === 'Mitglied') {
        document.getElementById('up-member').style.display = 'flex';
        document.getElementById('down-member').style.display = 'none';
    } else if (rolleText === 'Vorstand') {
        document.getElementById('up-member').style.display = 'none';
        document.getElementById('down-member').style.display = 'flex';
    } else if (rolleText === 'Admin') {
        document.getElementById('member-context-menu').style.display = 'none';
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
});

// close context menu and member edit popup on click outside or escape key
document.addEventListener('click', (event) => {
    const contextMenu = document.getElementById('member-context-menu');
    // Close context menu if click is outside and it's currently visible
    if (contextMenu && contextMenu.style.display !== 'none' && !contextMenu.contains(event.target) && !event.target.classList.contains('edit-button')) {
        contextMenu.style.display = 'none';
    }
    
    const popup = document.getElementById('member-edit-popup');
    // Close popup if click is outside and it's currently visible
    if (popup && popup.style.display !== 'none' && !popup.contains(event.target)) {
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
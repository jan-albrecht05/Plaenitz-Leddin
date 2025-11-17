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
        if (memberId && window.submitContextActionAjax) {
            submitContextActionAjax('promote', memberId);
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

    // Edit member (placeholder for future implementation)
    document.getElementById('edit-member')?.addEventListener('click', (e) => {
        e.preventDefault();
        const memberId = document.getElementById('context-member-id').value;
        alert('Bearbeitungsfunktion wird in Kürze implementiert. Mitglied-ID: ' + memberId);
        closeContextMenu();
    });
});

// close context menu on click outside or escape key
document.addEventListener('click', (event) => {
    if (!event.target.classList.contains('edit-button')) return;
    const contextMenu = document.getElementById('member-context-menu');
    if (!contextMenu.contains(event.target)) {
        contextMenu.style.display = 'none';
    }
});
document.addEventListener('keydown', (event) => {
    const contextMenu = document.getElementById('member-context-menu');
    if (event.key === 'Escape') {
        contextMenu.style.display = 'none';
    }
});
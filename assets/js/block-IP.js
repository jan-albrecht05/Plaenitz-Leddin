// IP Blocking Functions (Global Scope)
let currentIP = '';
let blockedIPsList = [];

// Initialize blocked IPs list from PHP
document.addEventListener('DOMContentLoaded', () => {
    // Get blocked IPs from data attribute or inline script
    const ipMenuElement = document.getElementById('ip-context-menu');
    if (ipMenuElement && ipMenuElement.dataset.blockedIps) {
        try {
            blockedIPsList = JSON.parse(ipMenuElement.dataset.blockedIps);
        } catch (e) {
            console.error('Error parsing blocked IPs:', e);
            blockedIPsList = [];
        }
    }
    
    // Close IP menu on outside click
    document.addEventListener('click', (event) => {
        const ipMenu = document.getElementById('ip-context-menu');
        if (ipMenu && ipMenu.style.display !== 'none' && !ipMenu.contains(event.target) && !event.target.closest('.ip-address')) {
            closeIPContextMenu();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeIPContextMenu();
        }
    });
});

function showIPBlockMenu(event, ip) {
    event.preventDefault();
    event.stopPropagation();
    currentIP = ip;
    
    const menu = document.getElementById('ip-context-menu');
    const blockBtn = document.getElementById('block-ip-btn');
    const unblockBtn = document.getElementById('unblock-ip-btn');
    
    if (!menu || !blockBtn || !unblockBtn) {
        console.error('IP context menu elements not found');
        return;
    }
    
    // Check if IP is already blocked
    const isBlocked = blockedIPsList.includes(ip);
    
    if (isBlocked) {
        blockBtn.style.display = 'none';
        unblockBtn.style.display = 'flex';
    } else {
        blockBtn.style.display = 'flex';
        unblockBtn.style.display = 'none';
    }
    
    // Position menu at cursor
    menu.style.display = 'block';
    
    // Get viewport dimensions
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;
    
    // Calculate position (prevent overflow)
    let left = event.pageX;
    let top = event.pageY;
    
    // Adjust if menu would overflow right edge
    if (event.clientX + menuWidth > viewportWidth) {
        left = event.pageX - menuWidth;
    }
    
    // Adjust if menu would overflow bottom edge
    if (event.clientY + menuHeight > viewportHeight) {
        top = event.pageY - menuHeight;
    }
    
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
}

function closeIPContextMenu() {
    const menu = document.getElementById('ip-context-menu');
    if (menu) {
        menu.style.display = 'none';
    }
    currentIP = '';
}

function blockIPConfirm() {
    if (!currentIP) {
        closeIPContextMenu();
        return;
    }
    
    if (!confirm('Möchten Sie die IP-Adresse ' + currentIP + ' wirklich blockieren?\n\nDiese IP wird sofort in der .htaccess gesperrt.')) {
        closeIPContextMenu();
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'ip_action=block&ip_address=' + encodeURIComponent(currentIP)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ IP-Adresse ' + currentIP + ' wurde erfolgreich blockiert.');
            location.reload();
        } else {
            alert('✗ Fehler beim Blockieren: ' + (data.message || 'Unbekannter Fehler'));
        }
        closeIPContextMenu();
    })
    .catch(error => {
        alert('✗ Netzwerkfehler: ' + error);
        closeIPContextMenu();
    });
}

function unblockIPConfirm(ip) {
    const targetIP = ip || currentIP;
    
    if (!targetIP) {
        closeIPContextMenu();
        return;
    }
    
    if (!confirm('Möchten Sie die IP-Adresse ' + targetIP + ' wirklich entblocken?\n\nDiese IP kann dann wieder auf die Seite zugreifen.')) {
        closeIPContextMenu();
        return;
    }
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'ip_action=unblock&ip_address=' + encodeURIComponent(targetIP)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ IP-Adresse ' + targetIP + ' wurde erfolgreich entblockt.');
            location.reload();
        } else {
            alert('✗ Fehler beim Entblocken: ' + (data.message || 'Unbekannter Fehler'));
        }
        closeIPContextMenu();
    })
    .catch(error => {
        alert('✗ Netzwerkfehler: ' + error);
        closeIPContextMenu();
    });
}

function copyIPToClipboard() {
    if (!currentIP) return;
    
    navigator.clipboard.writeText(currentIP).then(() => {
        // Visual feedback
        const menu = document.getElementById('ip-context-menu');
        const originalContent = menu.innerHTML;
        menu.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--success-color);">✓ IP kopiert!</div>';
        
        setTimeout(() => {
            closeIPContextMenu();
        }, 800);
    }).catch(err => {
        alert('✗ Fehler beim Kopieren: ' + err);
        closeIPContextMenu();
    });
}

// Handle banner text form submission
document.addEventListener('DOMContentLoaded', function() {
    const bannerTextForm = document.getElementById('banner-text-form');
    
    if (bannerTextForm) {
        bannerTextForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission and page reload
            
            const bannerTextInput = document.getElementById('banner-text-input');
            const bannerText = bannerTextInput.value.trim();
            
            // Send banner text to backend via fetch
            fetch('../../pages/internes/api/save-banner-text.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    banner_text: bannerText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage('Banner-Text erfolgreich gespeichert!', 'success');
                    
                    // Optionally update the banner text list
                    if (data.reload_list) {
                        location.reload();
                    }
                } else {
                    showMessage('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Fehler beim Speichern des Banner-Texts.', 'error');
            });
        });
    }
    
    // Helper function to show messages
    function showMessage(message, type) {
        // Create message element
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
        
        // Remove after 3 seconds
        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                document.body.removeChild(messageDiv);
            }, 300);
        }, 3000);
    }
});

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

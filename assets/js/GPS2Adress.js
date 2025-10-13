function autoFillAddress() {
    if (!navigator.geolocation) {
        alert('Geolocation wird von Ihrem Browser nicht unterstützt.');
        return;
    }

    // Show loading state
    const button = document.getElementById('location-btn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Lädt...';
    }

    console.log("Requesting location...");
    navigator.geolocation.getCurrentPosition(async (position) => {
        try {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            console.log(`Got coordinates: ${lat}, ${lon}`);
            
            // Use local PHP proxy to avoid CORS issues
            console.log('Sending request to geocoding API...');
            const response = await fetch('../pages/api/geocode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ lat, lon })
            });
            
            console.log('Response status:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success && data.address) {
                // Fill form fields with geocoded address
                document.getElementById('strasse').value = data.address.road || '';
                document.getElementById('hausnummer').value = data.address.house_number || '';
                document.getElementById('plz').value = data.address.postcode || '';
                document.getElementById('ort').value = data.address.city || '';

                // Trigger input events to show checkmarks
                ['strasse', 'hausnummer', 'plz', 'ort'].forEach(id => {
                    const input = document.getElementById(id);
                    if (input && input.value.trim()) {
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                console.log('Address filled successfully:', data.address);
            } else {
                throw new Error(data.error || 'Adresse konnte nicht ermittelt werden.');
            }
        } catch (error) {
            console.error('Geocoding error:', error);
            alert('Fehler beim Ermitteln der Adresse: ' + error.message);
        } finally {
            // Reset button state
            if (button) {
                button.disabled = false;
                button.innerHTML = '<span class="material-symbols-outlined">my_location</span> Standort verwenden';
            }
        }
    }, (error) => {
        console.error('Geolocation error:', error);
        let message = 'Standort konnte nicht ermittelt werden.';
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = 'Standortzugriff wurde verweigert. Bitte erlauben Sie den Zugriff in den Browser-Einstellungen.';
                break;
            case error.POSITION_UNAVAILABLE:
                message = 'Standortinformationen sind nicht verfügbar.';
                break;
            case error.TIMEOUT:
                message = 'Zeitüberschreitung beim Ermitteln des Standorts.';
                break;
        }
        
        alert(message);
        
        // Reset button state
        if (button) {
            button.disabled = false;
            button.innerHTML = '<span class="material-symbols-outlined">my_location</span> Standort verwenden';
        }
    }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // 5 minutes cache
    });
}
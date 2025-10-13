// Add checkmark functionality for input fields
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-wrapper input, .input-wrapper textarea');
            
            // Define default values that should not count as "filled"
            const defaultValues = {
                'email': '@',
                'telefon': '+49 ',
                'mobil': '+49 '
            };
            
            inputs.forEach(input => {
                // Function to toggle checkmark visibility
                function toggleCheckmark() {
                    const trimmedValue = input.value.trim();
                    const defaultValue = defaultValues[input.id] || '';
                    const hasMinimumContent = getMinimumContentForField(input.id, trimmedValue);
                    
                    if (hasMinimumContent) {
                        input.classList.add('filled');
                    } else {
                        input.classList.remove('filled');
                    }
                }
                
                // Function to determine if field has minimum required content
                function getMinimumContentForField(fieldId, value) {
                    switch(fieldId) {
                        case 'email':
                            // Email needs more than just '@' and should contain a valid email pattern
                            return value.length > 1 && value.includes('@') && value.indexOf('@') > 0 && value.indexOf('@') < value.length - 1;
                        case 'telefon':
                        case 'mobil':
                            // Phone needs more than just '+49 ' - at least some digits after the prefix
                            return value.length > 4 && /\d/.test(value.substring(4));
                        case 'vorname':
                        case 'nachname':
                            // Names need at least 2 characters
                            return value.length >= 2;
                        case 'strasse':
                            // Street should have at least 3 characters
                            return value.length >= 3;
                        case 'plz':
                            // German PLZ should be 5 digits
                            return /^\d{5}$/.test(value);
                        case 'ort':
                            // City name should be at least 2 characters
                            return value.length >= 2;
                        case 'nachricht':
                            // Message can be optional, but if provided should have some content
                            return value.length > 0;
                        default:
                            // For other fields, just check if not empty
                            return value.length > 0;
                    }
                }
                
                // Check on input events
                input.addEventListener('input', toggleCheckmark);
                input.addEventListener('change', toggleCheckmark);
                input.addEventListener('blur', toggleCheckmark);
                
                // Don't check initial state to avoid showing checkmarks on default values
                // toggleCheckmark will be called when user interacts with fields
            });
        });
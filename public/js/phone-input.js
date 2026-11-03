/**
 * Inicializa intl-tel-input (banderas + código de país) en todos los
 * <input data-phone>. Al enviar el formulario, normaliza el valor a solo
 * dígitos con código de país y SIN el signo '+' (formato que usa la app,
 * ej. 573001234567).
 */
(function () {
    if (typeof window.intlTelInput !== 'function') {
        return; // la librería no cargó
    }

    var UTILS = window.ITI_UTILS || ''; // ruta local del utils.js (auto-hospedado)

    document.querySelectorAll('input[data-phone]').forEach(function (input) {
        // Valor guardado sin '+': anteponerlo para que detecte el país
        if (input.value && input.value.charAt(0) !== '+') {
            input.value = '+' + input.value.replace(/[^\d]/g, '');
        }

        var iti = window.intlTelInput(input, {
            initialCountry: 'co',
            preferredCountries: ['co', 'mx', 'us', 'es', 'pe', 'ar', 'cl', 'ec', 've'],
            separateDialCode: true,
            utilsScript: UTILS
        });

        if (input.form) {
            input.form.addEventListener('submit', function () {
                var full = '';
                try { full = iti.getNumber(); } catch (e) {}
                if (full) {
                    input.value = full.replace(/[^\d]/g, ''); // dígitos, sin '+'
                } else {
                    var dc = '';
                    try { dc = iti.getSelectedCountryData().dialCode || ''; } catch (e) {}
                    input.value = (dc + input.value).replace(/[^\d]/g, '');
                }
            });
        }
    });
})();

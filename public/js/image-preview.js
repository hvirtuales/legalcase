/**
 * Muestra una miniatura al seleccionar una imagen en cualquier
 * <input type="file" data-preview>. La miniatura se inserta dentro del mismo
 * contenedor del input.
 */
(function () {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file || !/^image\//.test(file.type)) {
                return;
            }

            var container = input.closest('.form-group') || input.parentNode;
            var img = container.querySelector('.js-file-preview');
            if (!img) {
                img = document.createElement('img');
                img.className = 'js-file-preview mt-2 max-h-32 rounded border border-line bg-canvas p-1';
                container.appendChild(img);
            }

            var oldUrl = img.getAttribute('data-url');
            if (oldUrl) {
                URL.revokeObjectURL(oldUrl);
            }
            var url = URL.createObjectURL(file);
            img.setAttribute('data-url', url);
            img.src = url;
            img.style.display = '';
        });
    });
})();

/**
 * Theme Manager
 * - Aplica el tema (claro/oscuro) respetando la elección manual del usuario
 *   (guardada en localStorage); si no hay elección, usa la preferencia del sistema.
 * - Expone un toggle para los botones con [data-theme-toggle].
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'theme';

    const ThemeManager = {
        getSystemTheme: function () {
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark' : 'light';
        },

        getStored: function () {
            try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
        },

        getCurrent: function () {
            return document.documentElement.getAttribute('data-theme')
                || this.getStored()
                || this.getSystemTheme();
        },

        // persist=true guarda la elección manual del usuario
        setTheme: function (theme, persist) {
            document.documentElement.setAttribute('data-theme', theme);
            if (persist) {
                try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
            }
            this.updateToggleIcon(theme);
        },

        toggle: function () {
            const next = this.getCurrent() === 'dark' ? 'light' : 'dark';
            this.setTheme(next, true);
        },

        // El icono muestra la acción: luna = "ir a oscuro", sol = "ir a claro"
        updateToggleIcon: function (theme) {
            document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
                icon.classList.remove('icon-moon-stars', 'icon-sun');
                icon.classList.add(theme === 'dark' ? 'icon-sun' : 'icon-moon-stars');
            });
        },

        // Si el usuario NO ha elegido manualmente, seguimos los cambios del sistema
        watchSystemTheme: function () {
            if (!window.matchMedia) return;
            const self = this;
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                if (!self.getStored()) self.setTheme(e.matches ? 'dark' : 'light', false);
            });
        },

        bindToggles: function () {
            const self = this;
            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.toggle();
                });
            });
            this.updateToggleIcon(this.getCurrent());
        },

        init: function () {
            // Tema inicial: elección guardada o preferencia del sistema
            this.setTheme(this.getStored() || this.getSystemTheme(), false);
            this.watchSystemTheme();

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.bindToggles.bind(this));
            } else {
                this.bindToggles();
            }
        }
    };

    ThemeManager.init();

    // Exponer para control manual si se requiere
    window.ThemeManager = ThemeManager;
})();

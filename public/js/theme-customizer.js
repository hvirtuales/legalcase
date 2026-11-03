/**
 * Theme Customizer
 * Handles theme color selection and preview
 */

(function () {
    'use strict';

    const ThemeCustomizer = {
        // Predefined color palette
        colors: [
            { name: 'Azul Claro', value: '#60A5FA' },
            { name: 'Gris', value: '#6B7280' },
            { name: 'Cyan', value: '#06B6D4' },
            { name: 'Teal', value: '#14B8A6' },
            { name: 'Azul', value: '#3B82F6' },
            { name: 'Rosa Pálido', value: '#D8B4A0' },
            { name: 'Rosa', value: '#EC4899' },
            { name: 'Amarillo', value: '#EAB308' },
            { name: 'Naranja', value: '#F97316' },
            { name: 'Negro', value: '#1F2937' },
            { name: 'Gris Oscuro', value: '#374151' },
            { name: 'Azul Marino', value: '#1E3A8A' },
            { name: 'Morado', value: '#7C3AED' },
            { name: 'Rojo', value: '#DC2626' },
            { name: 'Verde', value: '#059669' }
        ],

        // Convert hex to RGB
        hexToRgb: function (hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        },

        // Convert RGB to HSL
        rgbToHsl: function (r, g, b) {
            r /= 255;
            g /= 255;
            b /= 255;

            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;

            if (max === min) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

                switch (max) {
                    case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                    case g: h = (b - r) / d + 2; break;
                    case b: h = (r - g) / d + 4; break;
                }

                h /= 6;
            }

            return { h: h * 360, s: s * 100, l: l * 100 };
        },

        // Convert HSL to hex
        hslToHex: function (h, s, l) {
            s /= 100;
            l /= 100;

            const c = (1 - Math.abs(2 * l - 1)) * s;
            const x = c * (1 - Math.abs((h / 60) % 2 - 1));
            const m = l - c / 2;
            let r = 0, g = 0, b = 0;

            if (0 <= h && h < 60) {
                r = c; g = x; b = 0;
            } else if (60 <= h && h < 120) {
                r = x; g = c; b = 0;
            } else if (120 <= h && h < 180) {
                r = 0; g = c; b = x;
            } else if (180 <= h && h < 240) {
                r = 0; g = x; b = c;
            } else if (240 <= h && h < 300) {
                r = x; g = 0; b = c;
            } else if (300 <= h && h < 360) {
                r = c; g = 0; b = x;
            }

            const toHex = (n) => {
                const hex = Math.round((n + m) * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            };

            return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
        },

        // Generate color variants
        generateVariants: function (baseColor) {
            const rgb = this.hexToRgb(baseColor);
            if (!rgb) return null;

            const hsl = this.rgbToHsl(rgb.r, rgb.g, rgb.b);

            return {
                primary: baseColor,
                hover: this.hslToHex(hsl.h, hsl.s, Math.max(hsl.l - 10, 0)),
                light: this.hslToHex(hsl.h, Math.max(hsl.s - 40, 10), Math.min(hsl.l + 40, 95))
            };
        },

        // Apply theme preview
        applyPreview: function (primaryColor) {
            const variants = this.generateVariants(primaryColor);
            if (!variants) return;

            // Update CSS variables
            document.documentElement.style.setProperty('--color-primary', variants.primary);
            document.documentElement.style.setProperty('--color-primary-hover', variants.hover);
            document.documentElement.style.setProperty('--color-primary-light', variants.light);
        },

        // Reset to default
        resetToDefault: function () {
            document.documentElement.style.removeProperty('--color-primary');
            document.documentElement.style.removeProperty('--color-primary-hover');
            document.documentElement.style.removeProperty('--color-primary-light');
        },

        // Initialize
        init: function () {
            const colorCircles = document.querySelectorAll('.theme-color-circle');
            const customColorInput = document.getElementById('theme_custom_color');
            const primaryColorInput = document.getElementById('theme_color_primary');
            const resetButton = document.getElementById('reset_theme');

            // Handle predefined color selection
            colorCircles.forEach(circle => {
                circle.addEventListener('click', () => {
                    // Remove active class from all
                    colorCircles.forEach(c => c.classList.remove('active'));
                    // Add active to clicked
                    circle.classList.add('active');

                    const color = circle.dataset.color;
                    if (color) {
                        primaryColorInput.value = color;
                        customColorInput.value = color;
                        this.applyPreview(color);
                    }
                });
            });

            // Handle custom color input
            if (customColorInput) {
                customColorInput.addEventListener('input', (e) => {
                    const color = e.target.value;
                    primaryColorInput.value = color;

                    // Remove active from predefined colors
                    colorCircles.forEach(c => c.classList.remove('active'));

                    this.applyPreview(color);
                });
            }

            // Handle reset button
            if (resetButton) {
                resetButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    const defaultColor = '#e10e30';
                    primaryColorInput.value = defaultColor;
                    customColorInput.value = defaultColor;

                    // Find and activate default color circle
                    colorCircles.forEach(c => {
                        c.classList.remove('active');
                        if (c.dataset.color === defaultColor) {
                            c.classList.add('active');
                        }
                    });

                    this.resetToDefault();
                });
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ThemeCustomizer.init());
    } else {
        ThemeCustomizer.init();
    }

    // Expose to window
    window.ThemeCustomizer = ThemeCustomizer;
})();

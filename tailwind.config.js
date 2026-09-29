import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    primary: 'rgb(var(--brand-primary) / <alpha-value>)',
                    'primary-dark': 'rgb(var(--brand-primary-dark) / <alpha-value>)',
                    'primary-soft': 'rgb(var(--brand-primary-soft) / <alpha-value>)',
                    secondary: 'rgb(var(--brand-secondary) / <alpha-value>)',
                    'secondary-dark': 'rgb(var(--brand-secondary-dark) / <alpha-value>)',
                    'secondary-soft': 'rgb(var(--brand-secondary-soft) / <alpha-value>)',
                    accent: 'rgb(var(--brand-accent) / <alpha-value>)',
                    warm: 'rgb(var(--brand-warm) / <alpha-value>)',
                    surface: 'rgb(var(--brand-surface) / <alpha-value>)',
                    text: 'rgb(var(--brand-text) / <alpha-value>)',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};

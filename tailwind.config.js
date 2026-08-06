import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Sampled from the JolaxPay mark (logo.png) — a deep red
                // gradient disc — per the PRD's own palette note (§9:
                // "deep red, white, dark charcoal, grey carry meaning").
                // `brand` is the only palette that should carry brand
                // meaning; functional colors (success/warn/danger status
                // badges) stay on Tailwind's standard green/amber/red so
                // "failed" never gets confused with "this is a JolaxPay
                // button".
                brand: {
                    50: '#fdf2f3',
                    100: '#fce1e4',
                    200: '#f9c2c9',
                    300: '#f398a3',
                    400: '#e8576a',
                    500: '#d42440',
                    600: '#b8102c',
                    700: '#8f0e23',
                    800: '#6e0b1b',
                    900: '#4a0712',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)',
            },
        },
    },

    plugins: [forms],
};

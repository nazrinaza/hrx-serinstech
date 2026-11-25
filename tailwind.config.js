import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Serinstech palette — see docs/brand.md */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                mist: '#EAEFEF',
                slate: { DEFAULT: '#BFC9D1' },
                ink: { DEFAULT: '#25343F', 700: '#2F4250', 900: '#1B262E' },
                ember: { DEFAULT: '#FF9B51', 600: '#F2843A', 50: '#FFF3EA' },
            },
        },
    },

    plugins: [forms],
};

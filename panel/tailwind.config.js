const colors = require('tailwindcss/colors');

const gray = {
    50: '#eceff4',
    100: '#d4dce8',
    200: '#b0bccf',
    300: '#8896b0',
    400: '#6a7a96',
    500: '#50607c',
    600: '#3f4c64',
    700: '#2a3348',
    800: '#1e2432',
    900: '#111117',
};

const purple = {
    50: '#f5f3ff',
    100: '#ede9fe',
    200: '#ddd6fe',
    300: '#c4b5fd',
    400: '#a78bfa',
    500: '#8b5cf6',
    600: '#7c3aed',
    700: '#6d28d9',
    800: '#5b21b6',
    900: '#4c1d95',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Inter"', '"Segoe UI"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#07070b',
                primary: purple,
                cyan: purple,
                violet: purple,
                purple: purple,
                gray: gray,
                neutral: gray,
                blue: colors.blue,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderRadius: {
                DEFAULT: '0.5rem',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};

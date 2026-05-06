/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./assets/**/*.js",
        './assets/**/*.css',
        "./templates/web/**/*.html.twig",
    ],
    safelist: [
        'tooltip-visible'
    ],
    theme: {
        extend: {
            // Custom colors - matching Platform design system
            colors: {
                'primary': '#ff5113',
                'primary-gradient': '#FFA788',
                'primary-active': '#E64C15', // used for active + hover in side nav
                'primary-container': '#ffdcd0',
                'on-primary': '#ffffff', // white text on primary (orange) backgrounds

                /* Accent A - BLUE */
                'accent-a': '#3797dc',
                'accent-a-container': '#d7eaf8',

                /* Accent B - GREEN */
                'accent-b': '#1bc298',
                'accent-b-container': '#d1f3ea',

                /* Accent C - PURPLE */
                'accent-c': '#9a83db',
                'accent-c-container': '#ebe6f8',

                /* Accent D - YELLOW */
                'accent-d': '#fdcc09',
                'accent-d-container': '#fff5ce',
                'on-accent-d': '#2a2a2a', // dark text on yellow backgrounds

                /* Warning colors (amber) - for support mode, caution states */
                'warning': '#d97706',
                'warning-container': '#fef3c7',
                'on-warning': '#92400e',

                /* Error colors */
                'error': '#be2e2b',
                'error-container': '#ffd8d7',

                /* Neutral colors (warm tones for Tahoe aesthetic) */
                'neutral-light': '#fafaf8',  // background, list-cards, widgets, tables
                'neutral': '#f2f2f0',  // cards, navigation (side + top)
                'neutral-dark': '#e8e8e6',  // sub-menu + hover

                /* Text */
                'primary-text': '#2a2a2a', // body text, headlines ect
                'secondary-text': '#5a5a5a', // labels

            },
            // Custom spacing (padding, margin, etc.)
            spacing: {
                72: '18rem',         // 72 => 18rem
                84: '21rem',         // 84 => 21rem
                96: '24rem',         // 96 => 24rem
            },
            // Custom border radius (Tahoe aesthetic - semantic tokens)
            borderRadius: {
                'button': '9999px',    // Buttons - full pill shape (same as rounded-full)
                'container': '12px',   // Cards, Tables, Modals - rounded-2xl (12px)
                'element': '8px',      // Badges, Pagination, Nav links - rounded-lg (8px)
            },
            // Custom fonts
            fontFamily: {
                'inter': ['Inter', 'sans-serif'],
            },
            // Custom screens
            screens: {
                'sm': '640px',
                'md': '768px',
                'lg': '1024px',
                'xl': '1280px',
                '2xl': '1536px',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}

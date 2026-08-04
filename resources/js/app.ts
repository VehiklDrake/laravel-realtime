import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';

if (import.meta.env.SSR) {
    configureEcho({ broadcaster: 'null' });
} else {
    configureEcho({ broadcaster: 'reverb' });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
});

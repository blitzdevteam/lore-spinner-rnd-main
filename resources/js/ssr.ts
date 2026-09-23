import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { appNameFromPage } from './utils/appName';

const voiceLabPages = import.meta.glob<DefineComponent>('./voice-lab/pages/**/*.vue');
const xenRndPages = import.meta.glob<DefineComponent>('./xen-rnd/pages/**/*.vue');
const mainPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createServer(
    (page) => {
        const appName = appNameFromPage(page);

        return createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) => {
                if (name.startsWith('VoiceLab/')) {
                    const file = name.replace('VoiceLab/', '');
                    return resolvePageComponent(`./voice-lab/pages/${file}.vue`, voiceLabPages);
                }
                if (name.startsWith('XenRnd/')) {
                    const file = name.replace('XenRnd/', '');
                    return resolvePageComponent(`./xen-rnd/pages/${file}.vue`, xenRndPages);
                }
                return resolvePageComponent(`./pages/${name}.vue`, mainPages);
            },
            setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
        });
    },
    { cluster: true },
);

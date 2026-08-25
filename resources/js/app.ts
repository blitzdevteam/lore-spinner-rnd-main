// CSS
import '../css/app.css';
import '../css/fonts/abhaya-libre/abhaya-libre.css';
import '../css/fonts/gill-sans/gill-sans.css';
import '../css/fonts/source-sans-3/source-sans-3.css';

// Swiper CSS
import 'swiper/css';
import 'swiper/css/free-mode';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import AppLayout from './layouts/AppLayout.vue';

// Primevue
import PrimeVue from 'primevue/config';
import customPresetOptions from '../css/primevue-theme/aura/options';

// Primevue Component
import Checkbox from 'primevue/checkbox';
import Divider from 'primevue/divider';
import Drawer from 'primevue/drawer';
import IconField from 'primevue/iconfield';
import Inplace from 'primevue/inplace';
import InputIcon from 'primevue/inputicon';
import InputMask from 'primevue/inputmask';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Rating from 'primevue/rating';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const voiceLabPages = import.meta.glob<DefineComponent>('./voice-lab/pages/**/*.vue');
const xenRndPages = import.meta.glob<DefineComponent>('./xen-rnd/pages/**/*.vue');
const mainPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
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
        return resolvePageComponent(`./pages/${name}.vue`, mainPages).then((module: any) => {
            module.default.layout = module.default.layout || AppLayout;
            return module;
        });
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)

            // Primevue
            .use(PrimeVue, customPresetOptions)

            // Primevue Components
            .component('PrimeInputText', InputText)
            .component('PrimeInputMask', InputMask)
            .component('PrimeTextarea', Textarea)
            .component('PrimeCheckbox', Checkbox)
            .component('PrimeSelect', Select)
            .component('PrimeDivider', Divider)
            .component('PrimePassword', Password)
            .component('PrimeIconField', IconField)
            .component('PrimeInputIcon', InputIcon)
            .component('PrimeDrawer', Drawer)
            .component('PrimeRating', Rating)
            .component('PrimeInplace', Inplace)

            .mount(el);
    },
});

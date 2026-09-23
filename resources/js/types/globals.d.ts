import { UserInterface } from '@/types/index';

declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        flash: {
            error?: string[];
            success?: string[];
            warning?: string[];
            investor_welcome?: {
                plan_name: string;
                duration: string;
                expires_on: string;
            } | null;
        };
        auth: null | UserInterface;
    }
}

import type { EchoOptions } from 'laravel-echo';

export interface ReverbConnectionConfig {
    key?: string | null;
    host?: string | null;
    port?: number | string | null;
    scheme?: string | null;
}

export function reverbEchoOptions(
    runtime: ReverbConnectionConfig | null | undefined,
    build: ReverbConnectionConfig,
    hostname: string,
    cookies: () => string,
): EchoOptions<'reverb'> {
    const insideDiscord = hostname.endsWith('.discordsays.com');
    const port = runtime?.port || build.port;

    return {
        broadcaster: 'reverb',
        key: runtime?.key || build.key || '',
        wsHost: insideDiscord ? hostname : runtime?.host || build.host || hostname,
        wsPort: insideDiscord ? 443 : Number(port || 80),
        wssPort: insideDiscord ? 443 : Number(port || 443),
        forceTLS: insideDiscord || (runtime?.scheme || build.scheme || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        ...(insideDiscord ? { wsPath: '/reverb' } : {}),
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax',
            headersProvider: () => {
                const token = cookies()
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length);

                return { 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' };
            },
        },
    };
}

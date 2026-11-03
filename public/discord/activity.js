const statusNode = document.getElementById('status');
const playerNode = document.getElementById('player');
const pickerNode = document.getElementById('picker');
const openLobbyButton = document.getElementById('open-lobby');
const clientId = document.body.dataset.clientId;
const sessionUrl = document.body.dataset.sessionUrl;
const lobbyUrl = document.body.dataset.lobbyUrl;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const params = new URLSearchParams(window.location.search);
const insideDiscord = Boolean(params.get('frame_id') && params.get('instance_id'));
const LOBBY_POLL_MS = 2000;
let lobbyPoll = null;

function say(message, tone = 'info') {
    statusNode.textContent = message;
    statusNode.dataset.tone = tone;
}

function errorMessage(error) {
    if (error && typeof error.message === 'string' && error.message !== '') {
        return error.message;
    }

    return 'Discord did not connect. Close the activity and open it again.';
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers ?? {}),
        },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;

        throw new Error(firstError || body.message || 'Something went wrong. Try again.');
    }

    return body;
}

function enterLobby(url) {
    if (lobbyPoll) {
        window.clearInterval(lobbyPoll);
    }

    say('Taking you to the lobby…');
    window.location.assign(url);
}

function followLobby(body) {
    if (body.lobby_url) {
        enterLobby(body.lobby_url);

        return true;
    }

    if (body.message) {
        say(body.message, 'error');
    }

    return false;
}

function waitForLobby() {
    pickerNode.hidden = false;

    lobbyPoll = window.setInterval(async () => {
        try {
            followLobby(await request(lobbyUrl));
        } catch {
            // Keep polling; the next tick retries.
        }
    }, LOBBY_POLL_MS);
}

pickerNode.addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = new FormData(pickerNode);
    openLobbyButton.disabled = true;
    say('Opening the lobby…');

    try {
        followLobby(await request(lobbyUrl, {
            method: 'POST',
            body: JSON.stringify({
                story: form.get('story'),
                mode: form.get('mode'),
            }),
        }));
    } catch (error) {
        say(errorMessage(error), 'error');
        openLobbyButton.disabled = false;
    }
});

async function start() {
    if (!clientId) {
        say('This activity is missing its Discord application id.', 'error');

        return;
    }

    say('Connecting to Discord…');

    const { DiscordSDK } = await import('/discord/embedded-app-sdk.js');
    const discordSdk = new DiscordSDK(clientId);
    await discordSdk.ready();

    say('Signing you in as a guest…');

    const { code } = await discordSdk.commands.authorize({
        client_id: clientId,
        response_type: 'code',
        state: '',
        prompt: 'none',
        scope: ['identify'],
    });

    const session = await request(sessionUrl, {
        method: 'POST',
        body: JSON.stringify({
            code,
            instance_id: discordSdk.instanceId,
        }),
    });

    playerNode.textContent = session.player ? 'Playing as ' + session.player : '';

    if (followLobby(session) || session.message) {
        return;
    }

    say('You are the first one here.');
    waitForLobby();
}

if (insideDiscord) {
    start().catch((error) => say(errorMessage(error), 'error'));
}

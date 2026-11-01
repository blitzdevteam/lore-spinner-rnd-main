const scenes = JSON.parse(document.getElementById('activity-scenes').textContent);
const sceneNode = document.getElementById('scene');
const choicesNode = document.getElementById('choices');
const statusNode = document.getElementById('status');
const beginNode = document.getElementById('begin');
const beginResult = document.getElementById('begin-result');
const sceneTitleNode = document.getElementById('scene-title');
const locationNode = document.getElementById('location');
const objectiveNode = document.getElementById('objective');
const hotspotsNode = document.getElementById('hotspots');
let current = 'gate';

const scenePresentation = {
    gate: {
        title: 'A light in the dark',
        location: 'The quiet room',
        objective: 'Find a way out',
    },
    hall: {
        title: 'Beyond the door',
        location: 'The empty hall',
        objective: 'Investigate the bell',
    },
    lantern: {
        title: 'Warm to the touch',
        location: 'Beneath the lantern',
        objective: 'Inspect the note',
    },
    'bell-end': {
        title: 'A single chime',
        location: 'The empty hall',
        objective: 'The threshold remembers',
    },
    'note-end': {
        title: 'One step is enough',
        location: 'Beneath the lantern',
        objective: 'The message is complete',
    },
};

function createChoiceButton(choice) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.choice = choice.id;
    button.textContent = choice.label;

    return button;
}

function createHotspotButton(choice) {
    const button = document.createElement('button');
    const label = document.createElement('span');

    button.type = 'button';
    button.className = 'hotspot';
    button.dataset.choice = choice.id;
    button.setAttribute('aria-label', choice.label);
    label.setAttribute('aria-hidden', 'true');
    label.textContent = choice.label;
    button.appendChild(label);

    return button;
}

function paint(sceneId) {
    const scene = scenes[sceneId];
    const presentation = scenePresentation[sceneId];

    if (!scene) {
        return;
    }

    current = sceneId;
    document.body.dataset.scene = sceneId;
    sceneNode.textContent = scene.text;
    sceneTitleNode.textContent = presentation?.title ?? 'The story continues';
    locationNode.textContent = presentation?.location ?? 'Unknown';
    objectiveNode.textContent = presentation?.objective ?? 'Follow the story';
    choicesNode.replaceChildren();
    hotspotsNode.replaceChildren();

    for (const choice of scene.choices) {
        choicesNode.appendChild(createChoiceButton(choice));
        hotspotsNode.appendChild(createHotspotButton(choice));
    }
}

function choose(event) {
    const button = event.target.closest('[data-choice]');

    if (!button) {
        return;
    }

    const choice = scenes[current].choices.find((item) => item.id === button.dataset.choice);

    if (choice) {
        paint(choice.next);
    }
}

choicesNode.addEventListener('click', choose);
hotspotsNode.addEventListener('click', choose);

beginNode.addEventListener('click', async () => {
    const url = beginNode.dataset.url;

    if (!url) {
        beginResult.textContent = 'Open this Activity with ?game=YOUR_GAME_ID to call /user/games/{game}/begin.';

        return;
    }

    beginResult.textContent = 'Calling begin…';

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'text/html, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            const body = await response.json();

            if (body.text && body.scene) {
                paint(body.scene);
                beginResult.textContent = 'Test only. Scene returned without login.';

                return;
            }
        }

        if (response.redirected) {
            beginResult.textContent = 'Begin responded and moved to ' + new URL(response.url).pathname;

            return;
        }

        beginResult.textContent = 'Begin responded with ' + response.status + '.';
    } catch {
        beginResult.textContent = 'Begin could not be reached.';
    }
});

const roomStatusNode = document.getElementById('room-status');
const roomPlayersNode = document.getElementById('room-players');
const sessionStateNode = document.getElementById('session-state');
const roomUrl = document.body.dataset.roomUrl;
const params = new URLSearchParams(window.location.search);
const applicationId = document.body.dataset.applicationId;
const insideDiscord = params.get('frame_id') && params.get('instance_id') && params.get('platform');

function playerName(participant) {
    return participant.global_name || participant.username;
}

function normalizeParticipants(participants) {
    return participants.flatMap((participant) => {
        if (typeof participant?.id !== 'string' || typeof participant?.username !== 'string') {
            return [];
        }

        return [{
            id: participant.id,
            username: participant.username,
            global_name: typeof participant.global_name === 'string' ? participant.global_name : null,
        }];
    });
}

function paintPlayers(participants) {
    roomPlayersNode.replaceChildren();

    for (const participant of participants) {
        const item = document.createElement('li');
        item.dataset.discordId = participant.id;
        item.textContent = playerName(participant);
        roomPlayersNode.appendChild(item);
    }
}

function snowflake(value) {
    return typeof value === 'string' && /^[0-9]{1,32}$/.test(value) ? value : null;
}

async function shareRoom(discordSdk, participants) {
    const people = normalizeParticipants(participants);
    paintPlayers(people);
    sessionStateNode.textContent = 'Voice channel';

    if (!roomUrl) {
        roomStatusNode.textContent = 'Open this activity with ?test=1 to share a room.';

        return;
    }

    const instanceId = snowflake(discordSdk.instanceId);

    if (!instanceId) {
        roomStatusNode.textContent = 'Connected. The shared room could not be saved.';

        return;
    }

    try {
        const response = await fetch(roomUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                instance_id: instanceId,
                channel_id: snowflake(discordSdk.channelId),
                guild_id: snowflake(discordSdk.guildId),
                participants: people,
            }),
        });

        if (!response.ok) {
            roomStatusNode.textContent = 'Connected. The shared room could not be saved.';

            return;
        }

        const body = await response.json();
        paintPlayers(Array.isArray(body.participants) ? body.participants : people);
        roomStatusNode.textContent = 'Shared room ' + body.room_id;
    } catch {
        roomStatusNode.textContent = 'Connected. The shared room could not be saved.';
    }
}

if (insideDiscord && applicationId) {
    try {
        const { DiscordSDK } = await import('/discord/embedded-app-sdk.js');
        const discordSdk = new DiscordSDK(applicationId);
        statusNode.textContent = 'Connecting to Discord…';
        await discordSdk.ready();
        statusNode.textContent = 'Connected. This Activity is running in the voice channel.';

        let roomSync = Promise.resolve();
        const queueShare = (participants) => {
            roomSync = roomSync
                .then(() => shareRoom(discordSdk, participants))
                .catch(() => {});
        };

        const connected = await discordSdk.commands.getInstanceConnectedParticipants();
        queueShare(connected.participants ?? []);

        discordSdk.subscribe('ACTIVITY_INSTANCE_PARTICIPANTS_UPDATE', (update) => {
            queueShare(update.participants ?? []);
        }).catch(() => {});
    } catch (error) {
        statusNode.textContent = error instanceof Error ? error.message : 'Discord did not connect.';
    }
}

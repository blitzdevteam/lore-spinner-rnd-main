<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>LoreSpinner</title>
        <style>
            :root {
                color-scheme: dark;
                --ink: #080a0b;
                --panel: rgb(17 21 22 / 0.9);
                --line: rgb(193 216 211 / 0.14);
                --text: #e7ebe8;
                --muted: #89918e;
                --cyan: #82c4ca;
                --gold: #e5ad5d;
            }

            * {
                box-sizing: border-box;
            }

            html {
                min-width: 320px;
                background: var(--ink);
            }

            body {
                margin: 0;
                min-height: 100vh;
                overflow-x: hidden;
                background:
                    radial-gradient(circle at 16% 12%, rgb(54 97 94 / 0.16), transparent 32rem),
                    radial-gradient(circle at 92% 92%, rgb(154 92 42 / 0.08), transparent 26rem),
                    #080a0b;
                color: var(--text);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            button {
                font: inherit;
            }

            .game-shell {
                width: min(1180px, 100%);
                min-height: 100vh;
                margin: 0 auto;
                padding: clamp(1rem, 3vw, 2.5rem);
                display: grid;
                grid-template-rows: auto 1fr;
                gap: clamp(1rem, 2.5vw, 2rem);
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                min-width: 0;
            }

            .brand-mark {
                width: 2.35rem;
                height: 2.35rem;
                display: grid;
                place-items: center;
                border: 1px solid rgb(130 196 202 / 0.3);
                border-radius: 50%;
                background: rgb(130 196 202 / 0.07);
                color: var(--cyan);
                box-shadow: inset 0 0 1.2rem rgb(130 196 202 / 0.08);
            }

            .brand-mark::before {
                content: "";
                width: 0.55rem;
                height: 0.85rem;
                border: 1px solid currentColor;
                border-radius: 45% 45% 36% 36%;
                box-shadow: 0 0 0.8rem rgb(130 196 202 / 0.65);
            }

            .brand-copy {
                min-width: 0;
            }

            .brand-name,
            .brand-subtitle {
                margin: 0;
            }

            .brand-name {
                font-family: Georgia, "Iowan Old Style", Palatino, serif;
                font-size: 1rem;
                letter-spacing: 0.08em;
            }

            .brand-subtitle {
                margin-top: 0.15rem;
                color: var(--muted);
                font-size: 0.68rem;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .session-state {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--muted);
                font-size: 0.72rem;
                white-space: nowrap;
            }

            .session-state::before {
                content: "";
                width: 0.45rem;
                height: 0.45rem;
                flex: none;
                border-radius: 50%;
                background: var(--cyan);
                box-shadow: 0 0 0.7rem var(--cyan);
            }

            .game {
                min-height: 0;
                display: grid;
                grid-template-columns: minmax(0, 1.3fr) minmax(20rem, 0.7fr);
                border: 1px solid var(--line);
                border-radius: 1.5rem;
                overflow: hidden;
                background: rgb(12 15 16 / 0.88);
                box-shadow: 0 2rem 6rem rgb(0 0 0 / 0.38);
            }

            .scene-stage {
                position: relative;
                min-height: min(70vh, 720px);
                overflow: hidden;
                isolation: isolate;
                background:
                    linear-gradient(to bottom, rgb(8 12 13 / 0.1), rgb(5 7 8 / 0.68)),
                    radial-gradient(circle at 31% 36%, rgb(225 173 93 / 0.12), transparent 26%),
                    #0a0e0f;
            }

            .scene-stage::before {
                content: "";
                position: absolute;
                inset: 0;
                z-index: 5;
                pointer-events: none;
                background:
                    linear-gradient(90deg, rgb(0 0 0 / 0.42), transparent 22%, transparent 72%, rgb(0 0 0 / 0.46)),
                    linear-gradient(rgb(0 0 0 / 0.18), transparent 30%, rgb(0 0 0 / 0.3));
            }

            .room {
                position: absolute;
                inset: 0;
                transition: filter 700ms ease, transform 900ms ease;
            }

            .wall-lines,
            .dust {
                position: absolute;
                inset: 0;
            }

            .wall-lines {
                opacity: 0.34;
                background:
                    linear-gradient(91deg, transparent 49.8%, rgb(145 171 168 / 0.08) 50%, transparent 50.2%),
                    repeating-linear-gradient(0deg, transparent 0 84px, rgb(145 171 168 / 0.06) 85px 86px);
                transform: perspective(500px) rotateX(-2deg);
            }

            .floor {
                position: absolute;
                right: -8%;
                bottom: -12%;
                left: -8%;
                height: 38%;
                border-top: 1px solid rgb(163 187 181 / 0.12);
                background:
                    repeating-linear-gradient(94deg, transparent 0 82px, rgb(156 176 171 / 0.06) 84px 85px),
                    linear-gradient(#0a0d0e, #050607);
                transform: perspective(420px) rotateX(34deg);
                transform-origin: top;
            }

            .door-light {
                position: absolute;
                top: 8%;
                right: 8%;
                width: 48%;
                height: 88%;
                opacity: 0;
                clip-path: polygon(36% 0, 64% 0, 100% 100%, 0 100%);
                background: linear-gradient(rgb(146 211 213 / 0.22), rgb(146 211 213 / 0));
                filter: blur(10px);
                transition: opacity 800ms ease;
            }

            .door {
                position: absolute;
                right: 12%;
                bottom: 16%;
                width: clamp(9rem, 25%, 13rem);
                height: 58%;
                min-height: 18rem;
                border: 1px solid rgb(152 180 177 / 0.24);
                background:
                    linear-gradient(90deg, rgb(255 255 255 / 0.025), transparent 12% 88%, rgb(0 0 0 / 0.18)),
                    #111718;
                box-shadow:
                    0 0 0 0.5rem #090c0d,
                    0 0 0 0.58rem rgb(143 167 163 / 0.16),
                    -1.2rem 1.5rem 3rem rgb(0 0 0 / 0.48);
                transform-origin: left;
                transition: transform 900ms cubic-bezier(.2, .75, .2, 1), filter 600ms ease;
            }

            .door::before,
            .door::after {
                content: "";
                position: absolute;
                right: 13%;
                left: 13%;
                height: 34%;
                border: 1px solid rgb(159 186 182 / 0.13);
                box-shadow: inset 0 0 1rem rgb(0 0 0 / 0.35);
            }

            .door::before {
                top: 9%;
            }

            .door::after {
                bottom: 9%;
            }

            .door-knob {
                position: absolute;
                top: 53%;
                right: 9%;
                width: 0.65rem;
                height: 0.65rem;
                z-index: 2;
                border: 1px solid #d2b171;
                border-radius: 50%;
                background: #6d5936;
                box-shadow: 0 0 0.55rem rgb(229 173 93 / 0.25);
            }

            .lantern-wrap {
                position: absolute;
                top: 0;
                left: 24%;
                width: 8rem;
                height: 58%;
                transform-origin: top;
                animation: lantern-sway 5s ease-in-out infinite;
                transition: transform 700ms ease, opacity 500ms ease;
            }

            .chain {
                position: absolute;
                top: 0;
                left: 50%;
                width: 1px;
                height: 48%;
                background: repeating-linear-gradient(#6e7672 0 4px, transparent 4px 8px);
            }

            .lantern-glow {
                position: absolute;
                left: 50%;
                bottom: -5.5rem;
                width: 20rem;
                height: 20rem;
                border-radius: 50%;
                background: radial-gradient(circle, rgb(229 173 93 / 0.3), rgb(229 173 93 / 0.08) 40%, transparent 70%);
                transform: translateX(-50%);
                filter: blur(6px);
                animation: glow-breathe 3.2s ease-in-out infinite;
            }

            .lantern {
                position: absolute;
                bottom: 0;
                left: 50%;
                width: 3.4rem;
                height: 5rem;
                border: 2px solid #3f4744;
                border-radius: 0.45rem 0.45rem 0.75rem 0.75rem;
                background: linear-gradient(90deg, rgb(229 173 93 / 0.08), rgb(229 173 93 / 0.24), rgb(229 173 93 / 0.08));
                box-shadow: 0 0 1.5rem rgb(229 173 93 / 0.32);
                transform: translateX(-50%);
            }

            .lantern::before,
            .lantern::after {
                content: "";
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
            }

            .lantern::before {
                top: -0.7rem;
                width: 2.1rem;
                height: 0.75rem;
                border: 2px solid #3f4744;
                border-bottom: 0;
                border-radius: 1rem 1rem 0 0;
            }

            .lantern::after {
                top: 1rem;
                width: 0.9rem;
                height: 2.1rem;
                border-radius: 50% 50% 45% 45%;
                background: #f0bd68;
                box-shadow: 0 0 1rem #e5ad5d;
                animation: flame 1.7s ease-in-out infinite alternate;
            }

            .table {
                position: absolute;
                right: 19%;
                bottom: 17%;
                width: 9rem;
                height: 0.55rem;
                opacity: 0;
                background: #1d2423;
                box-shadow: 0 1rem 0.6rem -0.45rem rgb(0 0 0 / 0.45);
                transition: opacity 600ms ease;
            }

            .table::before,
            .table::after {
                content: "";
                position: absolute;
                top: 0.5rem;
                width: 0.45rem;
                height: 6rem;
                background: #141918;
            }

            .table::before {
                left: 1rem;
            }

            .table::after {
                right: 1rem;
            }

            .bell {
                position: absolute;
                right: 21.75%;
                bottom: calc(17% + 0.55rem);
                width: 2.5rem;
                height: 2.1rem;
                opacity: 0;
                border-radius: 1.4rem 1.4rem 0.3rem 0.3rem;
                background: linear-gradient(90deg, #675737, #b28f50, #51452e);
                box-shadow: 0 0 1.4rem rgb(229 173 93 / 0.14);
                transition: opacity 600ms ease;
            }

            .bell::before {
                content: "";
                position: absolute;
                top: -0.45rem;
                left: 50%;
                width: 0.65rem;
                height: 0.55rem;
                border-radius: 50%;
                background: #9c7d47;
                transform: translateX(-50%);
            }

            .note {
                position: absolute;
                top: 53%;
                left: 30%;
                width: 4.5rem;
                height: 3rem;
                opacity: 0;
                background: #b8aa82;
                box-shadow: 0 0.75rem 1.5rem rgb(0 0 0 / 0.36);
                transform: rotate(-7deg);
                transition: opacity 500ms ease;
            }

            .note::before,
            .note::after {
                content: "";
                position: absolute;
                right: 0.7rem;
                left: 0.7rem;
                height: 1px;
                background: rgb(45 39 31 / 0.4);
            }

            .note::before {
                top: 1rem;
            }

            .note::after {
                top: 1.55rem;
            }

            .scene-caption {
                position: absolute;
                z-index: 8;
                right: 1.5rem;
                bottom: 1.25rem;
                left: 1.5rem;
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                color: rgb(218 226 222 / 0.5);
                font-size: 0.66rem;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }

            .story-panel {
                position: relative;
                z-index: 10;
                padding: clamp(1.5rem, 4vw, 3.5rem);
                display: flex;
                flex-direction: column;
                justify-content: center;
                border-left: 1px solid var(--line);
                background:
                    linear-gradient(145deg, rgb(255 255 255 / 0.025), transparent 35%),
                    var(--panel);
            }

            .panel-topline {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: clamp(2rem, 5vh, 4rem);
            }

            .chapter {
                margin: 0;
                color: var(--cyan);
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.2em;
                text-transform: uppercase;
            }

            .test-only {
                padding: 0.36rem 0.55rem;
                border: 1px solid rgb(229 173 93 / 0.28);
                border-radius: 999px;
                background: rgb(229 173 93 / 0.07);
                color: #f1bf71;
                font-size: 0.62rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            #scene-title {
                margin: 0;
                max-width: 12ch;
                font-family: Georgia, "Iowan Old Style", Palatino, serif;
                font-size: clamp(2rem, 4vw, 3.6rem);
                font-weight: 400;
                line-height: 0.98;
                letter-spacing: -0.035em;
            }

            #scene {
                min-height: 6.75rem;
                margin: 1.5rem 0 0;
                color: #b9c0bc;
                font-family: Georgia, "Iowan Old Style", Palatino, serif;
                font-size: clamp(1rem, 1.8vw, 1.2rem);
                line-height: 1.7;
            }

            .choice-label {
                margin: 2rem 0 0.85rem;
                color: var(--muted);
                font-size: 0.66rem;
                font-weight: 700;
                letter-spacing: 0.18em;
                text-transform: uppercase;
            }

            #choices {
                display: grid;
                gap: 0.7rem;
            }

            [data-choice] {
                position: relative;
                width: 100%;
                padding: 1rem 3rem 1rem 1.1rem;
                overflow: hidden;
                border: 1px solid rgb(130 196 202 / 0.22);
                border-radius: 0.8rem;
                background: rgb(130 196 202 / 0.045);
                color: #d8e7e6;
                text-align: left;
                cursor: pointer;
                transition: border-color 180ms ease, background 180ms ease, transform 180ms ease;
            }

            [data-choice]::after {
                content: "→";
                position: absolute;
                top: 50%;
                right: 1.1rem;
                color: var(--cyan);
                transform: translateY(-52%);
                transition: transform 180ms ease;
            }

            [data-choice]:hover,
            [data-choice]:focus-visible {
                border-color: rgb(130 196 202 / 0.58);
                outline: none;
                background: rgb(130 196 202 / 0.11);
                transform: translateY(-1px);
            }

            [data-choice]:hover::after,
            [data-choice]:focus-visible::after {
                transform: translate(0.25rem, -52%);
            }

            .begin-area {
                margin-top: clamp(2rem, 5vh, 3.5rem);
                padding-top: 1.25rem;
                border-top: 1px solid var(--line);
            }

            #begin {
                padding: 0;
                border: 0;
                background: transparent;
                color: var(--cyan);
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                cursor: pointer;
            }

            #begin:hover,
            #begin:focus-visible {
                color: #c2edef;
                outline: none;
            }

            #begin-result {
                min-height: 1.2rem;
                margin: 0.65rem 0 0;
                color: #68716e;
                font-size: 0.72rem;
                line-height: 1.5;
            }

            #status {
                position: absolute;
                z-index: 8;
                top: 1.25rem;
                left: 1.5rem;
                margin: 0;
                padding: 0.45rem 0.65rem;
                border: 1px solid rgb(255 255 255 / 0.08);
                border-radius: 999px;
                background: rgb(7 10 11 / 0.58);
                color: rgb(218 226 222 / 0.58);
                font-size: 0.64rem;
                letter-spacing: 0.08em;
                backdrop-filter: blur(8px);
            }

            body[data-scene="hall"] .door {
                filter: brightness(1.2);
                transform: perspective(700px) rotateY(-62deg);
            }

            body[data-scene="hall"] .door-light,
            body[data-scene="hall"] .table,
            body[data-scene="hall"] .bell,
            body[data-scene="bell-end"] .door-light,
            body[data-scene="bell-end"] .table,
            body[data-scene="bell-end"] .bell {
                opacity: 1;
            }

            body[data-scene="lantern"] .lantern-wrap,
            body[data-scene="note-end"] .lantern-wrap {
                animation: none;
                transform: translate(18%, 23%) scale(1.35);
            }

            body[data-scene="lantern"] .note,
            body[data-scene="note-end"] .note {
                opacity: 1;
            }

            body[data-scene$="-end"] .room {
                filter: saturate(0.65) brightness(0.75);
                transform: scale(1.025);
            }

            @keyframes lantern-sway {
                0%, 100% { transform: rotate(-1.4deg); }
                50% { transform: rotate(1.4deg); }
            }

            @keyframes glow-breathe {
                0%, 100% { opacity: 0.78; transform: translateX(-50%) scale(0.94); }
                50% { opacity: 1; transform: translateX(-50%) scale(1.04); }
            }

            @keyframes flame {
                from { transform: translateX(-50%) scale(0.9, 1.03); }
                to { transform: translateX(-50%) scale(1.08, 0.92); }
            }

            @media (max-width: 760px) {
                .game-shell {
                    min-height: 100svh;
                    padding: 0.85rem;
                }

                .game {
                    grid-template-columns: 1fr;
                    border-radius: 1rem;
                }

                .scene-stage {
                    min-height: 38svh;
                }

                .story-panel {
                    padding: 1.5rem;
                    border-top: 1px solid var(--line);
                    border-left: 0;
                }

                .panel-topline {
                    margin-bottom: 1.5rem;
                }

                #scene-title {
                    font-size: clamp(2rem, 10vw, 3rem);
                }

                #scene {
                    min-height: 0;
                    margin-top: 1rem;
                }

                .choice-label {
                    margin-top: 1.5rem;
                }

                .begin-area {
                    margin-top: 1.75rem;
                }
            }

            @media (max-height: 700px) and (min-width: 761px) {
                .game-shell {
                    padding-top: 1rem;
                    padding-bottom: 1rem;
                }

                .game {
                    min-height: calc(100vh - 5rem);
                }

                .scene-stage {
                    min-height: 32rem;
                }

                .panel-topline {
                    margin-bottom: 1.5rem;
                }

                #scene {
                    min-height: 4.5rem;
                    margin-top: 1rem;
                }

                .choice-label {
                    margin-top: 1.25rem;
                }

                .begin-area {
                    margin-top: 1.5rem;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    scroll-behavior: auto !important;
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }

            /* Immersive point-and-click presentation */
            .game-shell {
                height: 100svh;
                min-height: 34rem;
                grid-template-rows: auto minmax(0, 1fr);
            }

            .game {
                position: relative;
                display: grid;
                grid-template-columns: minmax(0, 1.7fr) minmax(18rem, 0.75fr);
                min-height: 0;
                background: #080b0c;
            }

            .game::after {
                content: "";
                position: absolute;
                inset: 0;
                z-index: 7;
                pointer-events: none;
                background:
                    repeating-linear-gradient(0deg, rgb(255 255 255 / 0.012) 0 1px, transparent 1px 4px),
                    radial-gradient(circle at center, transparent 38%, rgb(0 0 0 / 0.38) 100%);
                mix-blend-mode: screen;
            }

            .scene-stage {
                width: 100%;
                height: 100%;
                min-height: 0;
            }

            .room {
                transform: scale(1.05);
            }

            .objective-hud {
                position: absolute;
                z-index: 12;
                top: 1.35rem;
                left: 1.5rem;
                display: grid;
                gap: 0.25rem;
                padding-left: 0.8rem;
                border-left: 2px solid var(--cyan);
                text-transform: uppercase;
                filter: drop-shadow(0 0.35rem 0.8rem rgb(0 0 0 / 0.7));
            }

            .objective-hud span {
                color: var(--cyan);
                font-size: 0.56rem;
                font-weight: 800;
                letter-spacing: 0.18em;
            }

            .objective-hud strong {
                color: #e3e9e6;
                font-size: 0.72rem;
                font-weight: 650;
                letter-spacing: 0.06em;
            }

            #status {
                top: 1.25rem;
                right: 1.5rem;
                left: auto;
            }

            #hotspots {
                position: absolute;
                inset: 0;
                z-index: 11;
                pointer-events: none;
            }

            .hotspot {
                position: absolute;
                width: 2.4rem;
                height: 2.4rem;
                padding: 0;
                border: 0;
                border-radius: 50%;
                background: transparent;
                color: #dce8e5;
                cursor: pointer;
                pointer-events: auto;
                transform: translate(-50%, -50%);
            }

            .hotspot::before {
                content: "";
                position: absolute;
                inset: 0.45rem;
                border: 1px solid var(--cyan);
                border-radius: 50%;
                background: rgb(130 196 202 / 0.12);
                box-shadow:
                    0 0 0 0 rgb(130 196 202 / 0.2),
                    0 0 1rem rgb(130 196 202 / 0.34);
                animation: hotspot-pulse 2.4s ease-out infinite;
            }

            .hotspot::after {
                content: "";
                position: absolute;
                inset: 0;
                border: 1px solid rgb(130 196 202 / 0.28);
                border-radius: 50%;
            }

            .hotspot span {
                position: absolute;
                top: 50%;
                left: calc(100% + 0.55rem);
                width: max-content;
                max-width: 10rem;
                padding: 0.42rem 0.58rem;
                border: 1px solid rgb(130 196 202 / 0.2);
                border-radius: 0.35rem;
                opacity: 0;
                background: rgb(7 10 11 / 0.88);
                color: #dce8e5;
                font-size: 0.62rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                transform: translate(0.35rem, -50%);
                transition: opacity 160ms ease, transform 160ms ease;
                backdrop-filter: blur(8px);
            }

            .hotspot:hover,
            .hotspot:focus-visible {
                outline: none;
                transform: translate(-50%, -50%) scale(1.08);
            }

            .hotspot:hover span,
            .hotspot:focus-visible span {
                opacity: 1;
                transform: translate(0, -50%);
            }

            .hotspot[data-choice="open"] {
                top: 48%;
                right: 19%;
            }

            .hotspot[data-choice="lantern"] {
                top: 49%;
                left: 24%;
            }

            .hotspot[data-choice="ring"] {
                right: 20%;
                bottom: 28%;
            }

            .hotspot[data-choice="back"] {
                top: 43%;
                right: 34%;
            }

            .hotspot[data-choice="read"] {
                top: 55%;
                left: 31%;
            }

            .hotspot[data-choice="again"] {
                top: 44%;
                left: 50%;
            }

            .story-panel {
                position: relative;
                z-index: 20;
                right: auto;
                bottom: auto;
                left: auto;
                width: 100%;
                height: 100%;
                padding: clamp(1.25rem, 3vw, 2.25rem);
                display: flex;
                flex-direction: column;
                justify-content: center;
                border: 0;
                border-left: 1px solid rgb(178 205 199 / 0.2);
                border-radius: 0;
                overflow: hidden;
                background:
                    linear-gradient(105deg, rgb(13 18 19 / 0.96), rgb(12 16 17 / 0.83)),
                    var(--panel);
                box-shadow: 0 1.2rem 3rem rgb(0 0 0 / 0.46);
                backdrop-filter: blur(18px);
            }

            .story-panel::before {
                content: "";
                position: absolute;
                top: 0;
                bottom: 0;
                left: 0;
                width: 2px;
                background: linear-gradient(transparent, var(--cyan), transparent);
            }

            .panel-topline {
                margin: 0 0 clamp(1rem, 3vh, 2rem);
            }

            #scene-title {
                max-width: none;
                font-size: clamp(1.75rem, 3.2vw, 2.8rem);
                line-height: 1;
            }

            #scene {
                min-height: 0;
                margin: 1rem 0 0;
                font-size: clamp(0.86rem, 1.5vw, 1rem);
                line-height: 1.62;
            }

            .choice-label {
                margin: clamp(1.25rem, 4vh, 2.5rem) 0 0.65rem;
            }

            #choices {
                grid-template-columns: 1fr;
            }

            #choices [data-choice] {
                padding: 0.82rem 2.6rem 0.82rem 0.9rem;
                border-radius: 0.55rem;
                background: rgb(130 196 202 / 0.07);
                font-size: 0.78rem;
                font-weight: 650;
            }

            .begin-area {
                display: block;
                margin-top: clamp(1.25rem, 4vh, 2.5rem);
                padding-top: 1rem;
            }

            #begin-result {
                margin: 0.65rem 0 0;
            }

            .scene-caption {
                bottom: auto;
                top: 4.5rem;
                justify-content: flex-end;
            }

            .scene-caption span:first-child {
                display: none;
            }

            @keyframes hotspot-pulse {
                0% { box-shadow: 0 0 0 0 rgb(130 196 202 / 0.28), 0 0 1rem rgb(130 196 202 / 0.32); }
                70%, 100% { box-shadow: 0 0 0 0.7rem rgb(130 196 202 / 0), 0 0 1rem rgb(130 196 202 / 0.2); }
            }

            @media (max-width: 760px) {
                .game-shell {
                    height: auto;
                    min-height: 100svh;
                }

                .game {
                    display: grid;
                    grid-template-columns: 1fr;
                }

                .scene-stage {
                    min-height: 48svh;
                }

                #hotspots {
                    display: none;
                }

                .story-panel {
                    position: relative;
                    right: auto;
                    bottom: auto;
                    left: auto;
                    width: 100%;
                    padding: 1.4rem;
                    display: grid;
                    grid-template-columns: 1fr;
                    grid-template-areas:
                        "top"
                        "title"
                        "story"
                        "choice-label"
                        "choices"
                        "begin";
                    border: 0;
                    border-top: 1px solid var(--line);
                    border-radius: 0;
                }

                .choice-label {
                    margin-top: 1.4rem;
                }

                #choices {
                    grid-template-columns: 1fr;
                }

                .begin-area {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .objective-hud {
                    top: 1rem;
                    left: 1rem;
                }

                #status {
                    display: none;
                }
            }

            @media (max-height: 620px) and (min-width: 761px) {
                .game-shell {
                    min-height: 28rem;
                    padding-top: 0.75rem;
                    padding-bottom: 0.75rem;
                    gap: 0.75rem;
                }

                .story-panel {
                    padding: 0.85rem 1rem;
                }

                .panel-topline {
                    margin-bottom: 0.55rem;
                }

                #scene {
                    min-height: 0;
                    margin-top: 0.35rem;
                    font-size: 0.8rem;
                }

                .begin-area {
                    margin-top: 0.65rem;
                    padding-top: 0.55rem;
                }
            }
        </style>
    </head>
    <body data-application-id="{{ $applicationId }}" data-scene="gate">
        <div class="game-shell">
            <header class="topbar">
                <div class="brand">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <div class="brand-copy">
                        <p class="brand-name">LoreSpinner</p>
                        <p class="brand-subtitle">A story shaped by choice</p>
                    </div>
                </div>
                <div class="session-state">Solo session</div>
            </header>

            <main class="game">
                <section class="scene-stage" aria-label="The lantern room">
                    <p id="status">Waiting for Discord connection</p>
                    <div class="objective-hud">
                        <span>Current objective</span>
                        <strong id="objective">Find a way out</strong>
                    </div>
                    <div class="room" aria-hidden="true">
                        <div class="wall-lines"></div>
                        <div class="door-light"></div>
                        <div class="floor"></div>
                        <div class="door">
                            <span class="door-knob"></span>
                        </div>
                        <div class="lantern-wrap">
                            <span class="chain"></span>
                            <span class="lantern-glow"></span>
                            <span class="lantern"></span>
                        </div>
                        <span class="table"></span>
                        <span class="bell"></span>
                        <span class="note"></span>
                    </div>
                    <div id="hotspots" aria-label="Interactive objects">
                        @foreach ($scenes['gate']['choices'] as $choice)
                            <button class="hotspot" type="button" data-choice="{{ $choice['id'] }}" aria-label="{{ $choice['label'] }}">
                                <span aria-hidden="true">{{ $choice['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="scene-caption">
                        <span id="location">The quiet room</span>
                        <span>Night · Unknown hour</span>
                    </div>
                </section>

                <section class="story-panel" aria-label="Story choices">
                    <div class="panel-topline">
                        <p class="chapter">Chapter I · The Threshold</p>
                        @if ($testMode)
                            <span class="test-only">Test only</span>
                        @endif
                    </div>

                    <h1 id="scene-title">A light in the dark</h1>
                    <p id="scene" aria-live="polite">{{ $scenes['gate']['text'] }}</p>

                    <p class="choice-label">What will you do?</p>
                    <div id="choices">
                        @foreach ($scenes['gate']['choices'] as $choice)
                            <button type="button" data-choice="{{ $choice['id'] }}">{{ $choice['label'] }}</button>
                        @endforeach
                    </div>

                    <div class="begin-area">
                        <button type="button" id="begin" @if ($beginUrl) data-url="{{ $beginUrl }}" @endif>
                            ↻ Begin narration
                        </button>
                        <p id="begin-result">
                            @if ($testMode)
                                Test only. Begin narration does not require a login.
                            @elseif ($beginUrl)
                                This calls {{ $beginUrl }}
                            @else
                                Add ?game= to call /user/games/{game}/begin.
                            @endif
                        </p>
                    </div>
                </section>
            </main>
        </div>
        <script type="application/json" id="activity-scenes">@json($scenes)</script>
        <script type="module" src="/discord/activity.js?v=3"></script>
    </body>
</html>

# LoreSpinner on Steam and Discord

Planning note, 12 October 2026. This describes how the current platform can be offered on Steam and Discord. It does not change the product. No code in this note is a task list that has been started.

Effort figures are for one engineer who already knows this codebase. They are ranges, not a quote. Store review sits on top of the engineering time and is not under our control.

---

## Short answer

Steam and Discord can both carry LoreSpinner. Neither one runs it.

The Laravel app, Postgres, Redis, queues, story pipeline, and live narration stay on our servers. Steam is a PC launcher and a store. Discord is either a link, a panel that embeds the play screen, or a chat bot. The game behind all three is the same website we already have.

The work is a new door for each platform: who the player is, whether they are allowed to play, and which parts of the site they are allowed to see.

Payments stay split by where the player is. The website keeps monthly Stripe plans. Steam sells the same plan as a 30-day pass through the Steam Wallet. A free Steam app that sends people to the website to pay does not meet Steam's rules. Discord, for now, is a link back to the website.

---

## What the platform is today

LoreSpinner is an always-online web platform. A player picks a story, and each turn is a request to Laravel. The server calls the model, writes the result, and sends narration and choices back. Saves live in Postgres. They are not files on the player's machine.

The pieces that matter for a port:

| Piece | Where it lives now | What a port does with it |
|---|---|---|
| Play screen | `resources/js/pages/User/Games/Show.vue`, route `user/games/{game}` | Reused. This is the game. |
| Login | Email and password, Laravel session cookie, guard `auth:user` | Kept for the website. Steam and Discord each add a second way in. |
| Paywall | `guard.subscription-is-active`, Stripe via Laravel Cashier. Each plan is a monthly price plus `action_limit` actions and `voice_minutes_limit` voice minutes | Kept for the website. Steam sells the same allowance as a 30-day pass. See [Payments](#payments). |
| Profile gate | `guard.profile-is-completed` | Must still pass, or the new client has to complete it without the website forms. |
| Multiplayer | Lobbies, room codes, heartbeat, chat, Reverb | Can stay. Discord can also key a lobby to the voice channel. |
| Writer Lab, Filament, pricing, marketing | The rest of the site | Stay on the website. They are not part of a Steam or Discord build. |

```
Website players                         Steam players                    Discord players
email + cookie                          Steam ID                         Discord user ID
        \                                   |                                  /
         \                                  |                                 /
          +---------------- LoreSpinner account --------------------------+
                                    |
                         Laravel, Postgres, AI narration
                                    |
                              one save, any client
```

---

## What does not change

These stay where they are, for both platforms:

- Story import, the Boiler, adaptation, and the narration agents
- The game row, prompts, choices, world state, and session advance
- Postgres, Redis, queues, media, and text-to-speech
- Writer Lab and the Filament manager
- The public site, Stripe checkout, trials, and cancellation, for people who play in a browser

Uploading this repository to Steam or Discord is not a release. There is no host on either platform for PHP, Postgres, or the model calls.

---

## Shared work, done once

Do this before building either client, so the two platforms do not fork the game.

1. **One account, many logins.** A user row can hold an email, a `steam_id`, and a Discord user id. Signing in with any of them opens the same saves. A player who starts on the web and later installs Steam should not get a second empty account.
2. **One "allowed to play" decision.** The subscription middleware asks a small service: website players are allowed while their Stripe plan is active; Steam players are allowed while a Steam pass is active, or while that same account already has an active Stripe plan; Discord players follow the website until Discord itself sells a pass. The play routes keep one gate. A player is never charged twice for the same period.
3. **A client flag.** The play screen needs to know it is inside Steam or Discord so it can hide pricing, Writer Lab, the marketing nav, and Stripe buttons. The narration UI stays.
4. **Rights.** Steam and Discord both require that we have the right to ship the stories. Public-domain titles are fine. A screenplay we do not control is a release blocker on every platform, including the website.

That shared slice is about **1–2 weeks**.

---

## Payments

The website packages are monthly Stripe prices. Each plan is a price plus an allowance for that billing period: a number of actions (`action_limit`) and a number of voice minutes (`voice_minutes_limit`). The allowance exists because every turn calls the model. A Steam purchase of "own the game forever" has no ceiling on that cost, so it does not match these plans.

### Decision

Keep automatic monthly Stripe billing on the website. On Steam, the app is free to install, and each website package is sold again as a one-time 30-day pass through the Steam Wallet. Discord stays a link to the website until Discord itself can sell that same pass.

| Player | How they pay | What they receive |
|---|---|---|
| Plays on the website | Stripe, renews every month | That plan's actions and voice minutes, then a new allowance when it renews |
| Plays from Steam | Steam Wallet, paid once | The same actions and voice minutes, for 30 days or until the actions run out, whichever comes first |
| Already pays on the website, then opens Steam | No second charge | Play in the Steam app while the Stripe plan is active |
| Bought a Steam pass, then opens the website | No second charge | Play in the browser until that pass ends |

When a Steam pass ends, the player buys it again. Steam does not renew it. The website plans, the action limits, and the voice-minute limits stay as they are. Writer Lab and the website pricing page stay on Stripe. The Steam app does not open a Stripe checkout.

Price the pass so that Steam's 30% and the model cost of that allowance still leave a margin. The cap is what makes the pass safe. An uncapped "buy once" price is not.

### A free Steam app plus a website subscription does not work

Steam's Free to Play page says content for a free or paid game is sold as downloadable content or as an in-game purchase, and both paths must use the Steam Wallet. The Steam client also cannot link to a checkout that does not take the Steam Wallet. A "Subscribe on our site" button inside the Steam version is that link.

Final Fantasy XIV is the usual real case. The Steam edition's monthly fee is paid through Steam. The website subscription is a separate license.

People who never install Steam keep paying Stripe in the browser. That path is unchanged. A person who finds the game on Steam has to buy the pass from Steam.

### Steam can bill every month, and we are not using that

Steam can start a recurring charge (`ISteamMicroTxn`, period Month, paid by card, PayPal, or the Steam Wallet). Valve's own note says this is not fully supported, that many products tried it and later switched to a single payment, that the recurring price cannot be raised later, and that a player can have only one active billing agreement per game. Several packages fit one-time items better than that single agreement. The 30-day pass is the supported shape. Recurring Steam billing stays unused unless Valve's support for it changes.

### Discord

A link in a server opens the website. Stripe already handles that. No payment work.

An activity that tells people to subscribe on the website is the same problem as the Steam app. If we later charge inside Discord, Discord's checkout sells the same 30-day allowance, at a price no higher than the website. Until that exists, Discord is a link, not a cashier.

---

## Steam

### How a player would use it

1. They install LoreSpinner from Steam. The app itself is free.
2. They launch it from the Steam library. Steam is already running and they are already logged in.
3. The app opens our real site in its window (`https://` our domain, not a copy of the site baked into the install).
4. The app asks Steam for an identity ticket. Our server checks that ticket, finds or creates the user, and starts a normal Laravel session.
5. They can play if this account has an active Stripe plan, or an active Steam pass. A pass is bought in the Steam app, with the Steam Wallet, and matches a website package for 30 days. They never see Stripe.
6. They use the library and the play screen. About, pricing, Writer Lab, and the manager are not in this window.
7. Narration, saves, and multiplayer still hit our servers. Closing the window does not delete the game.

The installed app is a launcher. A new build of the launcher is what we upload to Steam. A new build of Laravel is a normal server deploy, and Steam players get it the next time the window loads.

### What has to change

**New, outside this repo's PHP app**

- A Steamworks partner account and one app. The Steam Direct fee is $100 per app. Valve returns it after that app reaches $1,000 in adjusted gross revenue. It is not refundable before that.
- A desktop shell, typically Electron, whose window loads the production site and talks to the Steam client. Steamworks runs in the shell's main process, not in the Vue page.
- A Windows build uploaded as a Steam depot. macOS and Linux are separate builds and separate QA. The first release can be Windows only.
- Store page: capsule art, description, screenshots, trailer if we have one, age rating questionnaire, and the content survey.

**Inside the Laravel app**

- A `steam_id` on the user, unique, nullable.
- One endpoint that accepts a Steam auth ticket, verifies it with Steam, and logs that user in. Email registration stays for the website.
- The subscription gate learns a Steam path. An active website plan already allows play. Otherwise a Steam pass allows play until its actions are used or its 30 days end. The pass is checked with Steam on the server, not by a flag the client can invent.
- Checkout, free trial, and cancel-subscription are not shown when the client is the Steam shell. Buying or renewing a website plan stays on the website. Buying a pass uses the Steam Wallet.
- Links that leave the game (a stray pricing URL, an external help page) open in the system browser. Steam's rule for purchases inside a Steam build is that the player pays with the Steam Wallet. A Stripe Checkout page inside the shell violates that.

**On the play screen**

- Hide the marketing header, pricing, and anything that assumes a browser account.
- Keep scene, choices, text input, continue, reset, next session, and the existing multiplayer lobby if we want it.
- Optional later: map our existing achievements to Steam achievements. Cloud saves are unnecessary, because the save is already on our server.

**Store and policy, not code**

- Tell players the game requires an internet connection.
- In the Steam content survey, declare **live-generated** AI. Narration is produced while the game is running. Pre-generated marketing art is a separate declaration if we use AI to make it. The survey also asks what guardrails stop the model from producing illegal content. Valve reviews that the same way it reviews any other content.
- AI used only as a development tool does not have to be disclosed. Our runtime narrator does.

Steam's standard revenue share is 30% until a single app passes the published higher tiers ($10M, then $50M). That cut comes off each 30-day pass. The actions inside the pass still call our provider, so the pass price has to cover both. DREAMIO documents the same cost on its store page.

### Effort

| Slice | What | Time |
|---|---|---|
| Partner paperwork, tax interview, store questionnaire, AI disclosure | Non-code, blocks review | About 1 week of someone's time, then waiting |
| Desktop shell that loads the site and boots Steamworks | New project | 1–2 weeks |
| Ticket login, `steam_id`, Steam pass or an existing Stripe plan counts as access | Laravel | 1–2 weeks |
| Hide website chrome and Stripe inside the shell | Vue | About 1 week |
| Windows QA, overlay, always-online failure, a full story playthrough | Test | 1–2 weeks |
| **First Windows release** | | **About 4–7 weeks** |
| Achievements, Mac/Linux, Steam Deck, DLC chapters | Later | Each is its own slice, not part of the first release |

Valve's store review is extra calendar time after the build is uploaded. Budget days to a few weeks, and do not promise a date.

### What we would not do for Steam

- Rewrite the game in Unity, Unreal, or Godot
- Ship Postgres or the model inside the install
- Put Writer Lab or Filament in the Steam app
- Let the Steam build open Stripe
- Treat "owns the free app" as a paid plan
- Send a Steam player to the website to subscribe

---

## Discord

Discord is three different products. Only one of them is "the game runs inside Discord."

### Level 0 — a link

Post the website in a server. People click through and play in the browser.

Effort: none. This is available now. It does not make LoreSpinner a Discord game. Accounts, Stripe, and the full site stay exactly as they are.

### Level 1 — a Discord Activity

This is the real integration. Discord opens our play screen in a panel during a voice channel (and from the app launcher). The panel is an iframe. Discord proxies the page through its own host (`*.discordsays.com`) and the Embedded App SDK tells us who the user is and which channel they are in.

Colonist, a web board game, shipped this way. Their Discord build is play-only. Profile, history, and ranked mode stay on colonist.io. That split is the right model for us: Discord plays, the website keeps account, billing, Writer Lab, and history.

#### How a player would use it

1. They are in a Discord voice channel with friends.
2. They launch LoreSpinner from the activity launcher.
3. Discord asks them to authorize the app. Our server exchanges that for a LoreSpinner user linked to their Discord id.
4. They land in a play view sized for the panel: pick a story, or resume the lobby already tied to this channel.
5. Everyone in the channel who launches the activity joins that same lobby. Room codes still work as a fallback.
6. Turns go to our Laravel server, as they do on the web.
7. Until Discord sells a pass, they need an active website plan to play. The panel does not open Stripe. Charging inside the panel later means Discord's checkout sells the same 30-day allowance.

#### What has to change

**Discord developer portal**

- An application with a URL mapping that points at our deployed frontend.
- OAuth redirect set to that activity URL.
- Activities are reviewed before they are widely available. Terms and a privacy policy have to exist first.

**Login, the hard part**

The website depends on a session cookie for `auth:user`. Inside Discord that cookie is unreliable, because the panel is not a normal visit to our domain. The activity should:

- Call the Embedded App SDK `authorize` / `authenticate` flow.
- Send the resulting token to Laravel.
- Have Laravel verify it with Discord and issue our own token for that user.
- Send that token on each play request.

The play actions today are Inertia visits with a cookie and a CSRF token (`games.show`, `games.prompt`, reset, next session). Those visits assume a full browser. The activity needs those same controller actions to accept the token, or a thin play client that calls them that way. This is the largest code change on either platform. The narration code behind the controllers stays.

**The panel**

- A play-only layout. The activity viewport is shorter and narrower than our desktop game page, and full-page visits to pricing or the library header get stuck or look broken.
- Scene, choices, and input have to fit that frame. Mobile Discord is a second layout pass if we promise phones.
- No popups. Stripe Checkout, OAuth popups, and `window.open` do not belong here.

**Multiplayer**

- Create or find the lobby from Discord's channel id and activity instance id, instead of only a room code.
- The people already in the channel are the join list.
- Keep our heartbeat, proposals, and chat behind that. Reverb still has to be reachable from the panel; the activity's URL mapping and socket host have to allow it.
- Discord documents a server-side check (`activity-instances`) so we only serve the game to someone who is actually in that instance. Use it.

**Payment, only if we charge inside Discord**

- Discord sells SKUs: a user subscription, a server subscription, or a one-time purchase.
- Our server checks entitlements with Discord's HTTP API. The client is not trusted to say "I paid."
- Payouts go through Stripe, on Discord's side, after the app is verified and monetization is enabled.
- If a paid feature exists for the Discord app, Discord requires that same offer to be purchasable through Discord at a price no higher than elsewhere. A free activity that sends people to Stripe does not avoid that rule. The activity stays free of checkout, and payment stays on the website, only while we are not selling inside Discord. The current choice is the link in Level 0, not this panel.

#### Effort

| Slice | What | Time |
|---|---|---|
| Discord app, URL mapping, local tunnel, authorize flow | Portal + a spike | About 1 week |
| Token login that does not use the website cookie | Laravel auth | 2–3 weeks |
| Play-only layout that fits the panel | Vue | 2–3 weeks |
| Channel id to lobby, instance check, Reverb from the panel | Multiplayer | 1–2 weeks |
| **Playable activity, free** | | **About 6–10 weeks** |
| Discord SKUs and entitlement checks | Only if we charge in Discord | 1–2 more weeks |

Review and monetization approval are extra calendar time. Monetization also requires a verified app, a team, 2FA, and the owner to be 18 or older.

### Level 2 — a bot in a channel

A bot posts the scene and buttons. Players reply with a choice or a sentence. Laravel still resolves the turn.

This does not reuse `Show.vue`. It needs a small JSON API: start a game, submit a turn, return narration and choices. Discord components (buttons, modals) become the interface. Long prose has to be split to fit Discord's message length.

This is a different product. It is useful for a lightweight "play in chat" mode. It is a poor way to ship the cinematic play screen.

Effort: **about 3–5 weeks** for a text bot on top of the existing game actions, with no visual parity.

### What we would not do for Discord

- Expect the current Inertia site to drop into an iframe unchanged
- Put Stripe Checkout inside the activity
- Put Writer Lab in the activity
- Treat the bot and the activity as the same project

---

## Effort side by side

| Goal | Engineering | What the player gets |
|---|---|---|
| Link in a Discord server | None | The website, opened from chat |
| Steam, Windows, free app plus 30-day passes | 4–7 weeks, plus shared account work if not already done | A store page, a library entry, auto-login, passes through the Steam Wallet, no Stripe inside the app |
| Discord Activity, free, with channel lobbies | 6–10 weeks | Play inside a voice channel, on our servers |
| Discord bot | 3–5 weeks | Text scenes in a channel, not the Vue game |
| Both Steam and Discord, shared account | Add 1–2 weeks once, then the two rows above | One save across web, Steam, and Discord |

The narration pipeline is not in any of those ranges. If a platform release also needs new stories, content work is separate.

The Steam path is smaller because the shell can still be our website, and the cookie works once Steam has logged them in. Discord is larger because the panel cannot be that website.

---

## Games that already look like this

These are product comparisons, not codebases we would copy. Store details were checked in October 2026 and will go stale.

### On Steam, closest to LoreSpinner

**DREAMIO: AI-Powered Adventures** (Steam app 2795060, released 5 March 2024). A choose-your-own-adventure where a language model writes the next step, with generated illustrations and spoken narration. It is always online. The store page says the purchase covers a limited amount of cloud AI, then a token balance, with a free daily refill and an option to bring your own API key. When this note was written the store showed Very Positive from 172 reviews.

Why it matters: it is the closest shipped Steam game. Live story, live cost, and a store page that explains that cost to players. It is freer than LoreSpinner. Players invent a premise. We adapt a written story and hold a voice. Their token meter is the thing we would have to decide too, if a one-time Steam price cannot pay for long play.

**Playworlds** (Steam app 4911480, ElserAI, Early Access 9 August 2026). A single-player AI adventure. The player speaks or types, a game master answers, campaigns persist. The store page says an internet connection is required and that no separate account is required when you launch it from Steam. Responses are generated live, and the page warns that quality varies.

Why it matters: it is the account model we want. Steam is the login. Our email login remains for the website only.

**Whispers from the Star** (Steam app 3730100, Anuttacon, released 14 August 2025). You talk, by voice or text, to a stranded astronaut. The conversation is AI, fully voiced, with guardrails described on the store page. When this note was written the store showed Very Positive from about 1,662 reviews. It is one character, not a catalog of stories.

Why it matters: Valve accepts a live-generated narrative when the content survey discloses it and the page explains the safety limits. It is not a platform, and it is the best-known proof that this category can sell on Steam.

**Nearby, not the same**

- **StoryRealm: AI Interactive Story Games** (Steam app 5109340) is listed for October 2026 and was not released when this note was written. It is a platform: play AI dialogue stories, and write your own. Watch it as a competitor, not as a template.
- **Isentroth** (Steam app 4927450) is listed for November 2026. A solo saga with a "machine game master," dice under the prose, internet required. Closer to a single RPG than to our catalog.
- **AI Dungeon** (Latitude, 2019) is the genre ancestor: open text adventures on the web and mobile. It is not the model for "our Vue app inside Steam." Their Discord server is a community. Latitude's own bot privacy notice describes announcements and moderation, not playing the game in a channel.

### On Discord

**Colonist** (colonist.io, Discord Activity since December 2023). A web multiplayer board game. The same game runs on the website and inside Discord. Discord is ad-free and play-focused. Profile, game history, ranked mode, and leaderboards stay on the website.

Why it matters: this is the architecture, even though the genre is a board game. An existing web client, a reduced Discord build, the full product left on the site. That is Level 1 for us.

**Discord's own activities** (Chess, Poker Night, Gartic Phone, Putt Party, and others). Web apps in the same iframe, launched from a voice channel, with friends joining the same instance. They prove the shell. They are not narrative games.

**Adventure Bot** (github.com/postmemetic/adventure-bot). An open-source Discord Activity: a group in a voice channel, an LLM moderator, a Svelte page in the iframe, OpenAI for the text. It is a small project, not a shipped platform, and it does not have our story pipeline.

Why it matters: it is the closest public sketch of "multiplayer AI story inside a Discord call." It is a prototype to learn from, not a competitor with an audience.

**Chat bots are the other Discord shape.** Unofficial bots have run AI Dungeon-style play as channel commands (`/next`, `/undo`, `/restart`) since 2020. Newer bots narrate a tabletop scene, roll dice, and post pictures in the channel. That is Level 2. Players never see a page like `Show.vue`.

### What nobody in this list has done

None of these is a screenplay-to-playable-story platform with a website, a Steam launcher, and a Discord activity on one account. The pieces exist separately:

- DREAMIO and Playworlds show Steam will list an always-online AI story.
- Colonist shows a web game can grow a Discord activity without throwing away the site.
- Adventure Bot shows a voice-channel story is a known activity shape.

Our work is joining those, not inventing the category.

---

## A practical order

1. Finish the shared account and the "allowed to play" service on the website. Steam and Discord both sit on it.
2. Ship the Steam shell when the goal is a store page. The app is free. Each website package is a 30-day Steam Wallet pass. Playworlds already shows players will accept "Steam is my account."
3. Ship the Discord activity when the goal is people already in a call playing one story together. Keep payment on the website until Discord checkout sells the same pass. Do not put a subscribe link inside the panel.
4. Leave the bot until someone specifically wants play-by-chat. It does not get us the Steam page or the activity.

A Discord link can go out any day, and it does not replace step 3.

---

## Risks to decide before anyone builds

- **AI cost versus the pass price.** The payment shape is decided: monthly Stripe on the website, a capped 30-day Steam pass for Steam players. What is still open is the price of that pass. Steam's 30% comes off the pass, and the actions in it still call the model. DREAMIO hit the same cost and added a token balance after the purchase was used up. Our cap is the action limit. The pass price has to cover the limit.
- **The same offer on two stores.** A Steam player pays Steam for a Steam pass. A website player pays Stripe. The allowances match, and one account is not charged twice. A Discord player, if we later charge inside Discord, must be able to buy that same pass through Discord at a price no higher than the website.
- **Content bounds.** Live models will eventually say something we would not ship in a script. Steam's survey and Discord's monetization rules both expect guardrails. We should be able to describe them in a paragraph before review.
- **Story rights.** The platform cannot list a title on either store unless we can ship that title.
- **Always online.** If our server or the model provider is down, Steam and Discord players are stuck. The store page has to say that, and the client needs a clear failure state. The play screen already tells the player when a turn fails to save. That message has to remain visible inside the shell and the panel.

---

## Sources

Checked when this note was written. Policies change; read the live page before a submission.

- Steam Direct fee: https://partner.steamgames.com/doc/gettingstarted/appfee
- Steam content survey, including live-generated AI: https://partner.steamgames.com/doc/gettingstarted/contentsurvey
- Steam microtransactions must use the Steam Wallet: https://partner.steamgames.com/doc/features/microtransactions
- Steam, selling content in a free or paid game: https://partner.steamgames.com/doc/store/freetoplay
- Steam recurring billing, and Valve's warning that it is poorly supported: https://partner.steamgames.com/doc/store/pricing/subscriptions and https://partner.steamgames.com/doc/features/microtransactions/recurring_billing
- Discord, building an activity: https://docs.discord.com/developers/activities/building-an-activity
- Discord, multiplayer instances: https://docs.discord.com/developers/activities/development-guides/multiplayer-experience
- Discord, in-app purchases for activities: https://docs.discord.com/developers/monetization/implementing-iap-for-activities
- Discord, Premium Apps price rule: https://support-dev.discord.com/hc/en-us/articles/23810643331735-Premium-Apps-Required-Support-for-Monetizing-Apps
- DREAMIO: https://store.steampowered.com/app/2795060/DREAMIO_AIPowered_Adventures/
- Playworlds: https://store.steampowered.com/app/4911480/Playworlds/
- Whispers from the Star: https://store.steampowered.com/app/3730100/Whispers_from_the_Star/
- Colonist on Discord: https://blog.colonist.io/play-colonist-on-discord/

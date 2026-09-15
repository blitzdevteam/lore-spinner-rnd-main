# Narration audio: 504 on generation, then overlapping playback

**Dates:** 31 Jul 2026 (audio never played) → 2 Aug 2026 (playback control)
**Area:** user gameplay TTS — `/user/games/{game}/tts/{prompt}`
**Commits:** `75f0b47`, `78834cd`, `d184dc7`, `66877a9`
**Patch series for other forks:** `patches/narration-audio/` (base `9d0dfc1`)

---

## Part 1 — Text played, narration did not (504)

### Symptom

After picking a choice the narration text appeared but no voice. Console:

```
[TTS] fetching  <game>:<prompt>
[TTS] waiting (buffering)
[TTS] stalled
GET /user/games/<game>/tts/<prompt> 504 (Gateway Timeout)
[TTS] error MediaError {code: 4, message: 'MEDIA_ELEMENT_ERROR: Format error'}
[TTS] play() rejected NotSupportedError
```

### Root cause

`TextToSpeechController` called ElevenLabs **inside the request**, with the
browser's `<audio>` element holding the socket open. A full turn takes the
provider 30–90s with `eleven_v3`, longer than the nginx / Cloudflare budget, so
the gateway killed the request. The audio element then received an HTML error
page as its media source — hence `MediaError 4` and `NotSupportedError`. The
text was unaffected because it comes from a different endpoint.

This is the same class of bug that had just been fixed for the LLM turn itself
(`19d0b72`, deferred narration). Only the text half had been moved off the
request path; the audio half was still blocking.

### Fix

**Never call a slow provider from a request a browser is waiting on.**

1. `app/Services/NarrationAudioService.php` owns rendering and caching
   (`tts/{provider}/{promptId}.mp3` on the `local` disk — path unchanged, so
   previously generated audio still serves). A cache lock per prompt prevents a
   turn being billed twice; pending / failure state lives in the cache.
2. `TextToSpeechController` never blocks. File on disk → `BinaryFileResponse`
   with byte-range support. Not yet → **`202`** immediately plus a background
   render via `afterResponse()`. Provider failure → `503` with a message.
3. `ProcessGameTurnAction` and `ProcessGameOpeningAction` pre-render the audio
   straight after the narration row commits — **after** the `turn_pending` cache
   flag is cleared, so the voice provider never holds the text UI back.
4. `useTextToSpeech.ts` polls the endpoint (2s interval, 4min ceiling) instead
   of pointing an audio element at a slow URL, then plays from an object URL.
   Non-audio content types are rejected, so a session expiry redirecting to the
   login page cannot reach the element as HTML.
5. `GameplayChatCard.vue` prefetches when the typewriter *starts* rather than
   when it finishes, so the download overlaps with reading time.

### Why it works

The render happens where nothing is waiting on it, and the endpoint becomes a
fast status check. Latency drops because generation now overlaps the typewriter
instead of starting after it.

---

## Part 2 — Overlapping narrations, pause not working

### Symptoms

Previous turn kept playing under the new one; the media player's pause button
did nothing, or stopped one voice and *restarted* a narration.

### Root cause A — playback identity tracked by the wrong variable

`pause()` / `stop()` only touched `currentAudio`, but every turn keeps its own
element. The "is this the current turn?" check read `activeKey`, which
`queuePendingAutoplay()` moves to the new turn **without** touching
`currentAudio` or stopping playback. So a queued turn could resume the
*previous* turn's element, leaving audio running that nothing tracked — and the
pause button then paused the wrong element.

### Root cause B — stale autoplay queue replayed by unrelated taps

`primeAudio()` is called from **every** user gesture (choice, continue, listen,
autoplay toggle, resume) and always calls `flushPendingAutoplay()`. But
`pendingAutoplay` was only cleared on flush or dismiss — never when playback
started another way, when the user paused, or when another turn took over. A
turn queued by a blocked autoplay therefore fired on the next unrelated tap.
The pause button is the worst case: when it believes nothing is playing it
primes audio before resuming, flushing that stale queue into playback.

### Fix

- `currentKey` now tracks which narration `currentAudio` belongs to, and moves
  with it. `activeKey` stays a pure UI signal (which card is highlighted) and is
  no longer used for playback decisions.
- `silenceOthers(keep)` pauses and rewinds every other cached element. Called
  from `play`, `resume`, `pause`, `stop`, the queued-autoplay branch, and the
  moment a download lands.
- Per-element listeners ignore events unless their key is current, so an element
  being silenced cannot stomp the new turn's state. A stale element that somehow
  fires `playing` pauses itself.
- `stop()` and `pause()` clear `pendingAutoplay`.
- A `playToken` is bumped by anything that silences audio; the download's
  `.then()` compares it, so pausing mid-download cancels the auto-start rather
  than being overridden when the mp3 lands.
- `togglePause()` cancels while loading instead of priming (which would flush).

### Behaviour chosen

When a new narration is ready it **takes over** — the previous one is cut off.
Queueing it to play after the current one was rejected: skipping ahead early in
a 90s narration would strand the player listening to a turn they have left.

---

## Invariants to preserve

1. No provider call (ElevenLabs, Deepgram, Anthropic) inside a request a browser
   waits on. Use `afterResponse()` or a queued job; the endpoint returns `202`.
2. Only ever one audible element. Anything that starts playback must silence the
   others first — pausing `currentAudio` alone is not sufficient.
3. Element identity is `currentKey`, never `activeKey`.
4. Anything that stops audio must also clear `pendingAutoplay`, or the next tap
   will replay it.
5. iOS Safari ties playback permission to the element instance activated during
   a gesture. Claim `primedAudio` synchronously and assign its `src` **once**;
   extra `load()` calls on that element are a risk.

## Diagnosing next time

- `storage/logs/laravel.log` → `tts.generate_failed` carries the provider's real
  status code and response body. Before this work a 504 hid all of it.
- Endpoint contract: `200` audio/mpeg · `202` still rendering (`Retry-After: 2`)
  · `503` render failed · `404` unknown prompt.
- Cache keys: `tts-pending:{promptId}`, `tts-error:{promptId}`, lock
  `tts-generate:{promptId}`. A stuck "generating" self-heals in ≤240s.

## Known remaining

- `ChaosTtsController` still generates inline and will 504 the same way under
  Chaos Mode. Same fix applies.
- Turn work runs via `afterResponse()`, so one PHP-FPM worker is held for the
  LLM call plus the voice render (~60–120s). Under real concurrency this should
  move to a queued job on the existing `database` queue connection.
- Frontend changes require an asset rebuild; backend-only deploys leave the old
  bundle pointing an audio element at an endpoint that now answers `202`.

# XEN Hand UI — R&D Sandbox

Isolated frontend experiment for the **XEN hand-gesture mascot**: engraved cyan hands emerging from a fixed white portal ellipse, like pulling a drawer / matchbox open.

Same pattern as `/bg-rnd` and `/design-rnd`: **no database, no models, no migrations, no auth**. Safe to delete in one pass.

---

## Quick access

| What | Where |
|------|--------|
| **Live URL** | `/xen-rnd` |
| **Route name** | `xen-rnd` |
| **Inertia component** | `XenRnd/Index` |
| **Frontend root** | `resources/js/xen-rnd/` |
| **R&D folder (art + this doc)** | `RnD Ideas/XEN/` |
| **This doc** | `RnD Ideas/XEN/XEN-HAND-UI-RND-SANDBOX.md` |

Deployed the same way as other Inertia pages. Laravel Cloud needs the Vite path mapping in `app.blade.php` (see [Laravel integration](#laravel-integration-minimal-touch)).

---

## What we built

1. **Sandbox page** — gesture picker, replay button, dark stage.
2. **`XenPortalHand` component** — hand slides out of a **fixed white ellipse**; baked-in portal in PNG is masked out so our rim stays put during animation.
3. **Gesture registry** — `gestures.ts` defines 7 poses with per-gesture portal side, travel distance, and axis.

### Gesture inventory

| ID | Label | Portal | Motion |
|----|-------|--------|--------|
| `point` | Point Up | bottom | slides up |
| `chef-kiss` | Chef Kiss | left | slides right |
| `match` | Lit Match | right | slides left (matchbox; no baked portal in art) |
| `surprise` | Surprise Me | bottom | slides up |
| `your-move` | Your Move | bottom | slides up |
| `bold` | Bold Tray | bottom | slides up |
| `yes-master` | Yes, Master | right | slides left |

Runtime pose PNGs live in `resources/js/xen-rnd/assets/poses/`. Source/reference copies in `RnD Ideas/XEN/`.

---

## File layout

```
RnD Ideas/XEN/
├── XEN-HAND-UI-RND-SANDBOX.md        ← this file
├── XEN.png … XEN-06.png              ← reference art (not imported by app)

resources/js/xen-rnd/
├── pages/
│   └── Index.vue                      ← /xen-rnd page (layout: null)
├── components/
│   └── XenPortalHand.vue              ← portal + emerge animation
├── data/
│   └── gestures.ts                    ← gesture config + image imports
└── assets/
    ├── poses/                         ← PNGs used by Vite (required at runtime)
    │   └── XEN.png, XEN-1.png, XEN-02.png … XEN-06.png
    └── frames/                        ← reserved for frame sequences (.gitkeep)
```

---

## How the portal animation works

**Pixel-split printer model** (synced to the originals):

1. At runtime, `splitXenLayers.ts` punches the largest white portal blob out of the PNG → **hand layer** + **portal layer**.
2. Portal layer stays fixed. Hand layer slides through a clip at the portal mouth.
3. Rest frame = hand @ `translate(0)` + portal = **exact original composite** (unstretched).
4. Match (no baked portal) gets a synthetic rim at the wrist edge.

Tune only `emergeFrom` / `emergeDistance` in `gestures.ts` — mouth comes from the image.

---

## Laravel integration (minimal touch)

Three small hooks outside `resources/js/xen-rnd/`. Same idea as Voice Lab’s isolated folder.

### 1. Route — `routes/web.php`

```php
Route::get('xen-rnd', fn () => inertia('XenRnd/Index'))->name('xen-rnd');
```

### 2. Client page resolver — `resources/js/app.ts`

- `xenRndPages` glob: `./xen-rnd/pages/**/*.vue`
- If `name.startsWith('XenRnd/')`, resolve to `./xen-rnd/pages/{file}.vue`

### 3. Vite manifest + SSR — required for deploy

**`resources/views/app.blade.php`** — `@vite` must point at the real file path (otherwise Cloud error: *Unable to locate file in Vite manifest: resources/js/pages/XenRnd/Index.vue*):

```php
str_starts_with($pageComponent, 'XenRnd/') =>
    'resources/js/xen-rnd/pages/' . substr($pageComponent, strlen('XenRnd/')) . '.vue',
```

**`resources/js/ssr.ts`** — same `XenRnd/` branch in the Inertia `resolve` callback.

No controllers, config files, migrations, or PHP classes.

---

## Adding a new gesture

1. Drop PNG in `resources/js/xen-rnd/assets/poses/`.
2. Import it in `resources/js/xen-rnd/data/gestures.ts`.
3. Append a `XenGesture` object (pick `portalSide`, `axis`, `travel`; start with defaults from a similar pose).
4. Reload `/xen-rnd` and use **Replay** to tune.

Optional: keep a reference copy in `RnD Ideas/XEN/`.

---

## What this is **not** (do not delete with the sandbox)

These are **production / other R&D** XEN-related pieces — unrelated to `/xen-rnd`:

| Path | Purpose |
|------|---------|
| `resources/js/voice-lab/` | Voice Lab orb UI (XEN voice companion) |
| `resources/js/components/XenBanner.vue` | Marketing banner |
| `resources/js/assets/story/xen.png` | Story asset |
| `resources/views/voice-lab/agents/voice-chat/system-prompt.blade.php` | XEN voice persona prompt |

---

## How to remove the sandbox completely

Checklist — delete or revert each item, then deploy:

### Delete entire folders

- [ ] `resources/js/xen-rnd/` (all code + pose assets)
- [ ] `RnD Ideas/XEN/` (reference art + this doc)

### Revert code edits

- [ ] **`routes/web.php`** — remove the `xen-rnd` route block (comment mentions XenRnd).

- [ ] **`resources/js/app.ts`** — remove:
  - `const xenRndPages = import.meta.glob(...)`
  - the `if (name.startsWith('XenRnd/')) { ... }` block

- [ ] **`resources/views/app.blade.php`** — remove the `XenRnd/` arm from the `$pageEntry` `match` expression.

- [ ] **`resources/js/ssr.ts`** — remove:
  - `const xenRndPages = import.meta.glob(...)`
  - the `if (name.startsWith('XenRnd/')) { ... }` block

### Verify

```bash
# No remaining references
rg -i 'xen-rnd|XenRnd|xen-rnd/' --glob '!RnD Ideas/XEN/**'

# Build still passes
npm run build
```

No database rollback. No env vars to unset.

---

## Git history (for reference)

| Commit | Summary |
|--------|---------|
| `05124cd` | Add XEN hand gesture R&D sandbox at `/xen-rnd` |
| `38ccacb` | Fix XenRnd Vite manifest path for Laravel Cloud (`app.blade.php`, `ssr.ts`) |

---

## Related R&D pages (same removal pattern)

| URL | Page component | Location |
|-----|----------------|----------|
| `/bg-rnd` | `BgRnd` | `resources/js/pages/BgRnd.vue` |
| `/design-rnd` | `DesignRnd` | `resources/js/pages/DesignRnd.vue` |
| `/chaos-mode` | `ChaosMode` | heavier (controllers + DB) |
| `/voice-lab` | `VoiceLab/Index` | `resources/js/voice-lab/` + PHP module |

XEN hand R&D follows the **Voice Lab folder isolation** pattern, not the flat `pages/` pattern used by bg-rnd.

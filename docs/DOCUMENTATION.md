# LoreSpinner

An AI-powered interactive fiction platform that transforms screenplay scripts into playable, branching narrative experiences with dynamic narration and player-driven choices.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Technology Stack](#technology-stack)
- [Local Development Setup](#local-development-setup)
- [Environment Configuration](#environment-configuration)
- [Database](#database)
- [AI Agent System](#ai-agent-system)
- [Story Processing Pipeline](#story-processing-pipeline)
- [The Boiler](#the-boiler)
- [Gameplay Loop](#gameplay-loop)
- [Artisan Commands](#artisan-commands)
- [Admin Panel](#admin-panel)
- [Frontend](#frontend)
- [CI/CD Workflows](#cicd-workflows)
- [Deployment](#deployment)
- [API Routes Reference](#api-routes-reference)
- [Domain Model](#domain-model)
- [Troubleshooting](#troubleshooting)

Distribution planning for Steam and Discord lives in [STEAM-AND-DISCORD.md](STEAM-AND-DISCORD.md).

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        BROWSER (Vue 3 SPA)                      │
│  Inertia.js v2  ·  PrimeVue  ·  Tailwind CSS 4  ·  Swiper     │
└────────────────────────────┬────────────────────────────────────┘
                             │ Inertia Protocol
┌────────────────────────────▼────────────────────────────────────┐
│                     LARAVEL 12 (PHP 8.4)                        │
│                                                                  │
│  Controllers ──► Actions ──► Models ──► Database                │
│       │                                                          │
│       ├── AI Agents (Laravel AI + OpenAI GPT-5.2)               │
│       ├── Queue Jobs (story processing pipeline)                │
│       └── Filament v4 Admin Panel                               │
└───────┬──────────┬───────────┬──────────┬───────────────────────┘
        │          │           │          │
   ┌────▼───┐ ┌───▼────┐ ┌───▼───┐ ┌───▼────┐
   │ PgSQL  │ │ Redis  │ │ MinIO │ │Mailpit │
   │  17    │ │ Cache/ │ │  S3   │ │ Email  │
   │        │ │ Queue  │ │Storage│ │  Dev   │
   └────────┘ └────────┘ └───────┘ └────────┘
```

---

## Technology Stack

| Layer       | Technology                                    |
|-------------|-----------------------------------------------|
| Language    | PHP 8.4                                       |
| Framework   | Laravel 12                                    |
| Frontend    | Vue 3 + Inertia.js v2 + Tailwind CSS 4       |
| UI Library  | PrimeVue 4                                    |
| Admin       | Filament v4                                   |
| AI          | Laravel AI (`laravel/ai`) + OpenAI GPT-5.2    |
| Database    | PostgreSQL 17                                 |
| Cache/Queue | Redis                                         |
| Storage     | MinIO (S3-compatible) / local                 |
| Media       | Spatie Media Library v11                      |
| Build       | Vite 7                                        |
| Testing     | Pest v4                                       |
| Containers  | Laravel Sail (Docker)                         |

---

## Local Development Setup

### Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- Node.js 22+
- Composer 2 (only needed for initial `vendor/` bootstrap)

### First-Time Setup

```bash
# 1. Clone the repository
git clone <repo-url> lore-spinner && cd lore-spinner

# 2. Install PHP dependencies (uses a temporary Docker container if PHP isn't local)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 3. Copy environment file and generate app key
cp .env.example .env
./vendor/bin/sail artisan key:generate

# 4. Start all services
./vendor/bin/sail up -d

# 5. Run database migrations
./vendor/bin/sail artisan migrate

# 6. Seed the database (requires OPENAI_API_KEY — calls AI for extraction)
./vendor/bin/sail artisan db:seed

# 7. Install frontend dependencies and start dev server
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

The application will be available at `http://localhost` (or the port set in `APP_PORT`).

### Service Ports

| Service         | Default Port | Override Env Var                  |
|-----------------|--------------|-----------------------------------|
| Application     | `80`         | `APP_PORT`                        |
| Vite Dev Server | `5173`       | `VITE_PORT`                       |
| PostgreSQL      | `5432`       | `FORWARD_DB_PORT`                 |
| Redis           | `6379`       | `FORWARD_REDIS_PORT`              |
| MinIO API       | `9000`       | `FORWARD_MINIO_PORT`              |
| MinIO Console   | `8900`       | `FORWARD_MINIO_CONSOLE_PORT`      |
| Mailpit SMTP    | `1025`       | `FORWARD_MAILPIT_PORT`            |
| Mailpit UI      | `8025`       | `FORWARD_MAILPIT_DASHBOARD_PORT`  |

### Common Sail Commands

```bash
# Start / stop
./vendor/bin/sail up -d
./vendor/bin/sail down

# Artisan
./vendor/bin/sail artisan <command>

# Run tests
./vendor/bin/sail test

# Tinker
./vendor/bin/sail tinker

# Queue worker
./vendor/bin/sail artisan queue:work

# Fresh migration + seed
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## Environment Configuration

### Required API Keys

| Variable           | Purpose                           | Required For         |
|--------------------|-----------------------------------|----------------------|
| `OPENAI_API_KEY`   | GPT-5.2 narration & extraction    | Core gameplay + seed |
| `GEMINI_API_KEY`   | Image generation (covers/avatars) | Image generation     |
| `ELEVENLABS_API_KEY` | Text-to-speech narration        | TTS feature          |

### Optional AI Provider Keys

The platform supports multiple AI providers via `config/ai.php`. Configure any of these for extended capabilities:

`ANTHROPIC_API_KEY`, `COHERE_API_KEY`, `DEEPSEEK_API_KEY`, `GROQ_API_KEY`, `JINA_API_KEY`, `MISTRAL_API_KEY`, `OPENROUTER_API_KEY`, `VOYAGEAI_API_KEY`, `XAI_API_KEY`

### Database Configuration

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql          # "pgsql" when using Sail, "localhost" otherwise
DB_PORT=5432
DB_DATABASE=lore_spinner
DB_USERNAME=sail
DB_PASSWORD=password
```

### Storage Configuration

MinIO acts as an S3-compatible object store for media (covers, avatars, scripts):

```env
AWS_ACCESS_KEY_ID=sail
AWS_SECRET_ACCESS_KEY=password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=local
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

The default filesystem disk is `private` (local). The `s3` disk is available for production use with MinIO or any S3-compatible service.

---

## Database

### Schema Overview

```
managers ─────────────── (admin users for Filament panel)
users ────────────────── (players)
creators ─────────────── (story authors)
categories ───────────── (story genres/tags)

stories ──┬── chapters ──┬── events
          │              └── (chapter covers via media)
          ├── (story covers via media)
          ├── (script files via media)
          └── system_prompt (JSON: character_name, world_rules, tone_and_style)

games ────┬── prompts (ULID keys)
          │   ├── response (AI narration HTML)
          │   ├── choices (JSON array)
          │   └── prompt (player input)
          ├── story_id
          ├── user_id
          └── current_event_id
```

### Key Tables

| Table        | Primary Key | Notable Columns                                                  |
|-------------|-------------|------------------------------------------------------------------|
| `stories`    | `id`        | `slug`, `teaser`, `opening`, `system_prompt` (JSON), `status`   |
| `chapters`   | `id`        | `position`, `title`, `content`, `status`                        |
| `events`     | `id`        | `position`, `title`, `content`, `objectives`, `attributes` (JSON)|
| `games`      | ULID        | `story_id`, `user_id`, `current_event_id`                       |
| `prompts`    | ULID        | `game_id`, `event_id`, `response`, `choices` (JSON), `prompt`   |

### Story Status Lifecycle

```
DRAFT → AWAITING_EXTRACTING_CHAPTERS_REQUEST → EXTRACTING_CHAPTERS → PUBLISHED
```

### Chapter Status Lifecycle

```
AWAITING_CREATOR_REVIEW → AWAITING_EXTRACTING_EVENTS_REQUEST → EXTRACTING_EVENTS
    → WAITING_FOR_EVENT_PREPARATION → READY_TO_PLAY
    → REJECTED (terminal)
```

### Seeding

The `DatabaseSeeder` runs in this order:

1. **UserSeeder** — 1 test user + 9 random users
2. **CreatorSeeder** — 10 random creators + "Thomas Wittmer"
3. **CategorySeeder** — 15 predefined story categories
4. **StorySeeder** — 7 screenplay stories with full AI extraction pipeline (chapters, events, system prompts, openings). This calls OpenAI and takes several minutes.
5. **CommentSeeder** — AI-generated comments on stories

The `StorySeeder` forces `queue.default = sync` so all AI jobs run inline. It includes retry logic (3 attempts, exponential backoff) for transient API failures.

### Restoring from Dump

A PostgreSQL dump is available at `database/dump.sql` for fast setup without running the AI-heavy seeder:

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail exec pgsql psql -U sail -d lore_spinner < database/dump.sql
```

---

## AI Agent System

All agents live in `app/Ai/Agents/` and use the Laravel AI package with PHP attributes for configuration.

### Agent Inventory

| Agent                                  | Model    | Temp | Timeout | Purpose                                       |
|----------------------------------------|----------|------|---------|-----------------------------------------------|
| `NarrationAgent`                       | GPT-5.2  | 0.85 | 60s     | Real-time gameplay narration + 3 choices      |
| `OpeningNarrationAgent`                | GPT-5.2  | 0.9  | 120s    | Cinematic story opening generation             |
| `SystemPromptGeneratorAgent`           | GPT-5.2  | 0.4  | 180s    | Extract character, rules, tone from scripts   |
| `ChapterExtractorAgent`               | GPT-5.2  | —    | 120s    | Split scripts into chapters                    |
| `EventExtractorAgent`                  | GPT-5.2  | —    | 120s    | Extract playable events from chapters          |
| `EventObjectiveAndAttributesExtractor` | GPT-5.2  | —    | 120s    | Extract objectives/attributes per event        |

### How Agents Work

Each agent implements `Agent` and `HasStructuredOutput`. The `schema()` method defines the JSON structure the AI must return:

```php
#[Model('gpt-5.2')]
#[Temperature(0.85)]
#[Timeout(60)]
class NarrationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'response' => $schema->string()->required(),  // HTML narration
            'choices'  => $schema->array()->required(),    // 3 actionable choices
        ];
    }
}
```

System prompts are Blade templates rendered at runtime with story context. They live in `resources/views/ai/agents/`.

### AI Provider Configuration

Default routing in `config/ai.php`:

| Task           | Provider |
|----------------|----------|
| Text/Chat      | OpenAI   |
| Image Gen      | Gemini   |
| Audio/TTS      | OpenAI   |
| Transcription  | OpenAI   |
| Embeddings     | OpenAI   |
| Reranking      | Cohere   |

---

## Story Processing Pipeline

When a new screenplay script is uploaded, it flows through a multi-stage AI extraction pipeline. Each stage is a queued job:

```
PDF Script
    │
    ▼
┌─────────────────────────┐
│ ConvertScriptPdfsCommand│   artisan stories:convert-scripts
│ PDF → .txt cleanup      │
└───────────┬─────────────┘
            ▼
┌─────────────────────────┐
│ ChapterExtractorJob     │   Splits script into chapters
│ → ChapterExtractorAgent │   Output: position, title, teaser, line ranges
└───────────┬─────────────┘
            ▼ (per chapter)
┌─────────────────────────┐
│ EventExtractorJob       │   Extracts playable events from each chapter
│ → EventExtractorAgent   │   Output: position, title, content, line coords
└───────────┬─────────────┘
            ▼ (per event)
┌──────────────────────────────────┐
│ EventObjectiveAndAttributeExtractor │  Adds objectives + attributes
└───────────┬──────────────────────┘
            ▼
┌─────────────────────────┐
│ SystemPromptGeneratorJob│   Extracts story metadata
│ → SystemPromptGenerator │   Output: character_name, world_rules, tone
└───────────┬─────────────┘
            ▼
┌─────────────────────────┐
│ StoryOpeningGeneratorJob│   Generates cinematic opening
│ → OpeningNarrationAgent │   Output: HTML opening narration
└───────────┬─────────────┘
            ▼
┌─────────────────────────┐
│ Image Generation Jobs   │   Covers for stories, chapters, avatars
│ StoryCoverGeneratorJob  │   Uses Gemini Imagen
│ ChapterCoverGeneratorJob│
│ CreatorAvatarGeneratorJob│
└─────────────────────────┘
```

### Running the Pipeline Manually

```bash
# Convert all PDFs in database/stories/ to .txt
./vendor/bin/sail artisan stories:convert-scripts

# Seed runs the full pipeline synchronously:
./vendor/bin/sail artisan db:seed --class=StorySeeder

# Generate missing cover images
./vendor/bin/sail artisan images:generate-missing --stories --chapters --creators

# Generate story openings (or regenerate with --force)
./vendor/bin/sail artisan stories:generate-openings
./vendor/bin/sail artisan stories:generate-openings --story=5 --force --sync
```

---

## The Boiler

Boiler is LoreSpinner’s **playable-cut compiler**: an adaptation-time pacing layer that turns source events into ordered playable moments (rooms), then makes Chaos perform only the active room at runtime. It does not replace Chaos, Choice Design, or A/B/C branching.

**Team documentation (product, writers, engineering, ops):**

- [`docs/Boiler/BOILER_DOCUMENTATION_V1.md`](Boiler/BOILER_DOCUMENTATION_V1.md) — start here
- [`docs/Boiler/README.md`](Boiler/README.md) — index of Boiler docs

Enable at adaptation time only:

```bash
php artisan stories:run-adaptation {story-id-or-slug} --force --boiler
```

---

## Gameplay Loop

### Session Flow

```
Player selects a Story
    │
    ▼
POST /user/games              → CreateGameAction creates Game + sets first Event
    │
    ▼
POST /user/games/{game}/begin → NarrationAgent generates opening narration
    │                            Creates first Prompt (response + 3 choices)
    ▼
┌──────────────────────────────────────────┐
│  GAME LOOP                               │
│                                          │
│  Player sees narration + 3 choices       │
│      │                                   │
│      ▼                                   │
│  Player picks a choice or types freely   │
│      │                                   │
│      ▼                                   │
│  POST /user/games/{game}/prompt          │
│      │                                   │
│      ├─ Advance current_event_id         │
│      ├─ Build conversation history       │
│      ├─ Render system prompt (Blade)     │
│      ├─ NarrationAgent generates next    │
│      │  narration + 3 new choices        │
│      └─ Create new Prompt record         │
│      │                                   │
│      ▼                                   │
│  (loop until events exhausted)           │
└──────────────────────────────────────────┘
```

### Event Progression

Events are traversed in `position` order within a chapter. When a chapter's events are exhausted, the system advances to the next chapter's first event. The conversation history (last 6 prompts) provides the AI with continuity context.

### Text-to-Speech

```
GET /user/games/{game}/tts/{prompt}
```

Returns audio narration for a specific prompt response via the configured TTS provider (ElevenLabs / OpenAI).

---

## Artisan Commands

| Command                          | Signature                       | Description                                        |
|----------------------------------|---------------------------------|----------------------------------------------------|
| Convert screenplay PDFs          | `stories:convert-scripts`       | Parses PDFs in `database/stories/` to `.txt` files |
| Generate missing images          | `images:generate-missing`       | Dispatches AI image jobs for stories/chapters/creators |
| Generate story openings          | `stories:generate-openings`     | Creates cinematic opening narrations               |

### Command Options

**`images:generate-missing`**
- `--stories` — Generate missing story cover images
- `--chapters` — Generate missing chapter cover images
- `--creators` — Generate missing creator avatar images

**`stories:generate-openings`**
- `--force` — Regenerate even if an opening already exists
- `--sync` — Run synchronously instead of dispatching to queue
- `--story=ID` — Target a specific story by ID

---

## Admin Panel

Filament v4 provides the admin interface at `/admin`. It manages:

- **Stories** — CRUD, status management, script uploads
- **Chapters** — Review extracted chapters, trigger event extraction
- **Events** — Review extracted events, edit objectives/attributes
- **Creators** — Manage creator profiles and avatars
- **Categories** — Genre/tag management
- **Users** — Player management
- **Comments** — Moderation

Admin users are stored in the `managers` table (separate from players). Create one with:

```bash
./vendor/bin/sail artisan make:filament-user
```

Or seed them:

```bash
./vendor/bin/sail artisan db:seed --class=ManagerSeeder
```

---

## Frontend

### Build System

Vite 7 handles asset compilation with these plugins:

- `@vitejs/plugin-vue` — Vue 3 SFC support
- `@tailwindcss/vite` — Tailwind CSS 4 integration
- `laravel-wayfinder/vite` — Type-safe route generation from Laravel routes

### Development

```bash
./vendor/bin/sail npm run dev    # Start Vite dev server with HMR
./vendor/bin/sail npm run build  # Production build
```

### Page Structure

```
resources/js/
├── app.ts                    # Inertia app bootstrap + PrimeVue setup
├── ssr.ts                    # Server-side rendering entry
├── pages/
│   ├── Index.vue             # Homepage (stories carousel, creators)
│   ├── Stories/
│   │   ├── Index.vue         # Story listing
│   │   └── Show.vue          # Story detail page
│   ├── Creators/
│   │   ├── Index.vue         # Creator listing
│   │   └── Show.vue          # Creator profile
│   └── User/
│       ├── Authentication/   # Register, Login, Verify, CompleteProfile
│       ├── Dashboard.vue     # Player dashboard
│       └── Games/
│           └── Show.vue      # Active game session (narration + choices)
├── wayfinder/                # Auto-generated typed route helpers
└── components/               # Shared Vue components
```

### Styling

- **Tailwind CSS 4** with custom configuration
- **PrimeVue Aura theme** (customized in `resources/css/primevue-theme/`)
- **Custom fonts**: Gill Sans, Source Sans 3

---

## CI/CD Workflows

Two GitHub Actions workflows run on pushes and PRs to `develop` and `main`.

### Test Workflow (`.github/workflows/tests.yml`)

Runs the full test suite on every push/PR:

```
Trigger: push/PR to develop or main

Steps:
  1. Checkout code
  2. Setup PHP 8.4 with Composer v2 + Xdebug coverage
  3. Setup Node.js 22
  4. npm ci
  5. composer install --optimize-autoloader
  6. Copy .env.example → .env
  7. Generate application key
  8. npm run build
  9. Run Pest test suite
```

### Lint Workflow (`.github/workflows/lint.yml`)

Enforces code style on every push/PR:

```
Trigger: push/PR to develop or main

Steps:
  1. Checkout code
  2. Setup PHP 8.4
  3. Install PHP + Node dependencies
  4. Run Laravel Pint (PHP formatting)
  5. Run npm run format (Prettier)
  6. Run npm run lint (ESLint)
```

An auto-commit step is available but currently commented out. Uncomment the `stefanzweifel/git-auto-commit-action@v5` block to auto-fix formatting on push.

### Branch Strategy

```
feature/* ──► develop ──► main
              (CI runs)   (CI runs, deploy trigger)
```

---

## Deployment

### Deployment Data Archive

The file `deploy-data.tar.gz` (~150MB) contains pre-processed deployment data (seeded database exports, generated media assets). This avoids running the expensive AI extraction pipeline in production.

### Production Environment Checklist

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=lore_spinner
DB_USERNAME=your-user
DB_PASSWORD=your-secure-password

# Required API keys
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AI...
ELEVENLABS_API_KEY=...

# Cache & Queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Storage (S3 or S3-compatible)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=lore-spinner
AWS_URL=https://your-cdn.com
```

### Build & Deploy Steps

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci

# 2. Build frontend assets
npm run build

# 3. Run migrations
php artisan migrate --force

# 4. Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Link storage
php artisan storage:link

# 6. Restart queue workers
php artisan queue:restart
```

### Queue Workers

The application relies heavily on queued jobs for AI processing. In production, run a persistent queue worker:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

For process management, use Supervisor or a similar tool:

```ini
[program:lore-spinner-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
stopwaitsecs=3600
```

### Production Database Seeding

For initial deployment, either:

**Option A** — Restore from dump (fast, no API calls):
```bash
psql -U $DB_USERNAME -d $DB_DATABASE < database/dump.sql
```

**Option B** — Extract from `deploy-data.tar.gz`:
```bash
tar -xzf deploy-data.tar.gz
# Follow included instructions for data restoration
```

**Option C** — Run full seeder (slow, requires OpenAI API, ~$5-10 in API costs):
```bash
php artisan migrate:fresh --seed
```

### Nginx Configuration (Reference)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/lore-spinner/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## API Routes Reference

### Public Routes

| Method | URI                         | Controller                | Name             |
|--------|-----------------------------|---------------------------|------------------|
| GET    | `/`                         | `IndexController`         | `index`          |
| GET    | `/creators`                 | `CreatorController@index` | `creators.index` |
| GET    | `/creators/{username}`      | `CreatorController@show`  | `creators.show`  |
| GET    | `/stories`                  | `StoryController@index`   | `stories.index`  |
| GET    | `/stories/{slug}`           | `StoryController@show`    | `stories.show`   |

### Authentication Routes (prefix: `/user/authentication`)

| Method | URI                                    | Name                                |
|--------|----------------------------------------|-------------------------------------|
| GET    | `/user/authentication/register`        | `user.authentication.register.create` |
| POST   | `/user/authentication/register`        | `user.authentication.register.store`  |
| GET    | `/user/authentication/login`           | `user.authentication.login.create`    |
| POST   | `/user/authentication/login`           | `user.authentication.login.store`     |
| GET    | `/user/authentication/verify`          | `user.authentication.verify.index`    |
| POST   | `/user/authentication/verify/resend`   | `user.authentication.verify.resend`   |
| GET    | `/user/authentication/verify/confirm/{id}/{hash}` | `user.authentication.verify.confirm` |
| DELETE | `/user/authentication/logout`          | `user.logout`                         |

### Player Routes (prefix: `/user`, requires auth + verified + completed profile)

| Method | URI                                  | Name                  |
|--------|--------------------------------------|-----------------------|
| GET    | `/user/dashboard`                    | `user.dashboard.index`|
| GET    | `/user/games`                        | `user.games.index`    |
| GET    | `/user/games/{game}`                 | `user.games.show`     |
| POST   | `/user/games`                        | `user.games.store`    |
| POST   | `/user/games/{game}/begin`           | `user.games.begin`    |
| POST   | `/user/games/{game}/prompt`          | `user.games.prompt.store` |
| GET    | `/user/games/{game}/tts/{prompt}`    | `user.games.tts`      |

---

## Domain Model

### Core Entities

**Story** — A complete interactive fiction piece, derived from a screenplay script.
- Has many Chapters (ordered by position)
- Has a `system_prompt` JSON column storing extracted metadata (`character_name`, `world_rules`, `tone_and_style`)
- Media: script file, cover image
- Belongs to a Creator and Category

**Chapter** — A logical division within a story.
- Has many Events (ordered by position)
- Contains the raw `content` text from the screenplay
- Media: cover image

**Event** — A single playable scene or beat within a chapter.
- Contains `content` (script text), `objectives`, and `attributes` (JSON)
- The atomic unit that drives the gameplay loop

**Game** — A player's session with a specific story.
- Tracks `current_event_id` as the player progresses
- Has many Prompts (the conversation log)
- One game per user per story

**Prompt** — A single turn in the conversation.
- `response` — AI-generated HTML narration
- `choices` — JSON array of 3 player options
- `prompt` — The player's chosen action (or `__continue__`)

### Relationships

```
Creator 1──N Story N──1 Category
Story   1──N Chapter
Chapter 1──N Event
User    1──N Game
Story   1──N Game
Game    1──N Prompt
Event   1──N Prompt
```

---

## Troubleshooting

### Seeding fails with AI API errors

The `StorySeeder` makes heavy use of OpenAI. If it fails:
- Verify `OPENAI_API_KEY` is set and has sufficient credits
- The seeder retries 3 times with exponential backoff (10s, 20s, 30s)
- Use `database/dump.sql` to skip AI calls entirely

### Queue jobs not processing

```bash
# Check failed jobs
./vendor/bin/sail artisan queue:failed

# Retry all failed jobs
./vendor/bin/sail artisan queue:retry all

# Clear failed jobs
./vendor/bin/sail artisan queue:flush
```

### MinIO bucket not found

Access the MinIO console at `http://localhost:8900` (user: `sail`, password: `password`) and create the bucket matching your `AWS_BUCKET` env value.

### Frontend HMR not working

Ensure `VITE_PORT` in `.env` matches the port exposed in `compose.yaml` (default: 5173). If running behind a reverse proxy, set `ASSET_URL` accordingly.

### Filament admin login

Admin users are in the `managers` table, separate from players:

```bash
./vendor/bin/sail artisan make:filament-user
```

### Database connection refused

When using Sail, `DB_HOST` must be `pgsql` (the Docker service name), not `localhost` or `127.0.0.1`.

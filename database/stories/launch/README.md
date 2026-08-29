# Launch title library

**18 titles** — 6 worlds + 2 standalones. Registry: [`manifest.php`](./manifest.php).

## Per-title folder layout

Each title folder uses **the story's actual filenames** (not `source.pdf` / `script.txt`):

```
launch/01-baker-street/the-adventure-of-the-speckled-band/
├── THE ADVENTURE OF THE SPECKLED BAND.pdf
└── THE ADVENTURE OF THE SPECKLED BAND_script.txt
```

Same filenames are mirrored at `database/stories/{script}` for the seeder.

## Worlds

`manifest.php` also owns the six public worlds (Baker Street, Oz, Olympus, Journey to the West, Jane Austen, LoreSpinner Originals). Standalones stay off the world catalog.

```bash
php artisan db:seed --class=WorldSeeder --force
```

That upserts world rows and attaches any already-seeded launch titles. Seeding a title with `AddSingleStorySeeder` attaches it to its world automatically. World background images are still uploaded in Filament.

## Seed all pending titles (one command)

Runs every launch title that is **not** already in the DB and **has a TXT on disk**. Skips the 6 already-seeded/boiled titles automatically.

```bash
SEED_LAUNCH=pending php artisan db:seed --class=AddSingleStorySeeder --force
```

To walk the full manifest (skips any slug already in DB):

```bash
SEED_LAUNCH=all php artisan db:seed --class=AddSingleStorySeeder --force
```

## Seed one title

```bash
SEED_STORY=blue-carbuncle php artisan db:seed --class=AddSingleStorySeeder --force
```

## Title files

| Title | PDF | TXT |
|-------|-----|-----|
| The Adventure of the Speckled Band | `THE ADVENTURE OF THE SPECKLED BAND.pdf` | `THE ADVENTURE OF THE SPECKLED BAND_script.txt` |
| The Adventure of the Blue Carbuncle | `THE ADVENTURE OF THE BLUE CARBUNCLE.pdf` | `THE ADVENTURE OF THE BLUE CARBUNCLE_script.txt` |
| A Scandal in Bohemia | `A SCANDAL IN BOHEMIA.pdf` | `A SCANDAL IN BOHEMIA_script.txt` |
| The Wonderful Wizard of Oz | `THE WONDERFUL WIZARD OF OZ.pdf` *(needs PDF)* | `THE WONDERFUL WIZARD OF OZ_script.txt` ✓ |
| The Marvelous Land of Oz | `THE MARVELOUS LAND OF OZ.pdf` | `THE MARVELOUS LAND OF OZ_script.txt` |
| Theseus and the Minotaur | `Theseus and the Minotaur.pdf` | `Theseus and the Minotaur_script.txt` |
| Orpheus and Eurydice | `Orpheus and Eurydice.pdf` | `Orpheus and Eurydice_script.txt` |
| Havoc in Heaven | `Havoc in Heaven.pdf` *(needs PDF)* | `Havoc in Heaven_script.txt` *(needs PDF)* |
| The White Bone Demon | `The White Bone Demon.pdf` *(needs PDF)* | `The White Bone Demon_script.txt` *(needs PDF)* |
| Pride and Prejudice | `PRIDE AND PREJUDICE.pdf` | `PRIDE AND PREJUDICE_script.txt` |
| Emma | `EMMA.pdf` | `EMMA_script.txt` |
| Anima Machina | `ANIMA_MACHINA_FINAL_MASTER_SCRIPT_JUNE 16th.pdf` | `ANIMA_MACHINA_FINAL_MASTER_SCRIPT_JUNE_16th_script.txt` |
| Wasteland | `WASTELAND.pdf` | `WASTELAND_script.txt` |
| Driftheart | `DRIFTHEART_INTERACTIVE_TEASE_v3.pdf` | `DRIFTHEART_INTERACTIVE_TEASE_v3_script.txt` |
| Dream Police | `DREAM POLICE.pdf` | `DREAM POLICE_script.txt` |
| The Unwritten Ones | `THE UNWRITTEN ONES - Season One.pdf` | `THE UNWRITTEN ONES - Season One_script.txt` |
| Momotaro: The Peach Boy | `Momotaro_The_Peach_Boy.pdf` | `Momotaro_The_Peach_Boy_script.txt` |
| The Tell-Tale Heart | `TellTaleHeart_Premium.pdf` | `TellTaleHeart_Premium_script.txt` |

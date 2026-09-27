<?php

declare(strict_types=1);

/**
 * Launch library — playable allowlist and marketing catalog.
 *
 * `launch_playable_slugs` gates reviewer playability (with published + boiler-complete).
 * `boiled_playable_slugs` keeps already-boiled RnD titles playable alongside the launch roster.
 * `launch_catalog` is the canonical world, marketing title, and playable protagonist roster.
 */
return [
    'launch_playable_slugs' => [
        'the-wonderful-wizard-of-oz',
        'peter-pan',
        'the-adventure-of-the-speckled-band',
        'a-scandal-in-bohemia',
        'the-adventure-of-the-blue-carbuncle',
        'dracula',
        'frankenstein',
        'dr-jekyll-and-mr-hyde',
        'the-tell-tale-heart',
        'pride-and-prejudice',
        'emma',
        'around-the-world-in-eighty-days',
        'journey-to-the-center-of-the-earth',
        'the-three-musketeers',
        'robin-hood',
        'the-invisible-man',
        'sir-gawain-and-the-green-knight',
        'treasure-island',
    ],

    /** Already boiled on Cloud — playable but excluded from SEED_LAUNCH batch seeding. */
    'boiled_playable_slugs' => [
        'dr-jekyll-and-mr-hyde',
        'the-strange-case-of-dr-jekyll-and-mr-hyde',
    ],

    /** Published stories omitted from library index and homepage story grids (mirrors TS hidden list). */
    'library_hidden_slugs' => [
        'driftheart',
        'the-snow-queen',
        'momotaro-the-peach-boy',
        'the-matrix',
        'i-love-lucy-job-switching',
        'dream-police',
        'the-unwritten-ones',
    ],

    /** Legacy / seed DB slugs → canonical launch slug. */
    'playable_slug_aliases' => [
        'the-strange-case-of-dr-jekyll-and-mr-hyde' => 'dr-jekyll-and-mr-hyde',
        'jekyll-and-hyde' => 'dr-jekyll-and-mr-hyde',
    ],

    /** Homepage "Step into Worlds" when all of these worlds exist. */
    'homepage_world_slugs' => [
        'baker-street',
        'jane-austen',
        'gothic-horrors',
    ],

    'launch_catalog' => [
        'worlds' => [
            ['slug' => 'world-of-oz', 'folder' => '02-world-of-oz', 'title' => 'World of Oz', 'legacy_slugs' => ['land-of-oz']],
            ['slug' => 'baker-street', 'folder' => '01-baker-street', 'title' => 'Baker Street'],
            ['slug' => 'jane-austen', 'folder' => '05-jane-austen', 'title' => 'Jane Austen', 'legacy_slugs' => ['regency-england']],
            [
                'slug' => 'gothic-horrors',
                'folder' => 'gothic-horrors',
                'title' => 'Gothic Horrors',
                'legacy_slugs' => ['draculas-england', 'frankensteins-world', 'hydes-london', 'poes-gothic'],
            ],
            [
                'slug' => 'high-adventure',
                'folder' => 'high-adventure',
                'title' => 'High Adventure',
                'legacy_slugs' => ['treasure-island', 'musketeer-france', 'sherwood-forest'],
            ],
            [
                'slug' => 'extraordinary-voyages',
                'folder' => 'extraordinary-voyages',
                'title' => 'Extraordinary Voyages',
                'legacy_slugs' => [],
            ],
            [
                'slug' => 'camelot',
                'folder' => 'camelot',
                'title' => 'Camelot',
                'legacy_slugs' => ['arthurian-britain'],
            ],
        ],

        'titles' => [
            'the-wonderful-wizard-of-oz' => [
                'title' => 'The Wonderful Wizard of Oz: The Road Home',
                'protagonist' => 'Dorothy Gale',
                'world_slug' => 'world-of-oz',
                'world' => 'World of Oz',
            ],
            'peter-pan' => [
                'title' => "Peter Pan: Wendy's Neverland",
                'protagonist' => 'Wendy Darling',
            ],
            'the-adventure-of-the-speckled-band' => [
                'title' => 'Sherlock Holmes: The Speckled Band',
                'protagonist' => 'Dr. John Watson',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'a-scandal-in-bohemia' => [
                'title' => 'Sherlock Holmes: A Scandal in Bohemia',
                'protagonist' => 'Dr. John Watson',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'the-adventure-of-the-blue-carbuncle' => [
                'title' => 'Sherlock Holmes: The Blue Carbuncle',
                'protagonist' => 'Dr. John Watson',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'dracula' => [
                'title' => 'Dracula: The First Invitation',
                'protagonist' => 'Mina Murray',
                'world_slug' => 'gothic-horrors',
                'world' => 'Gothic Horrors',
            ],
            'frankenstein' => [
                'title' => 'Frankenstein: The Creature Awakens',
                'protagonist' => 'The Creature',
                'world_slug' => 'gothic-horrors',
                'world' => 'Gothic Horrors',
            ],
            'dr-jekyll-and-mr-hyde' => [
                'title' => 'Dr. Jekyll and Mr. Hyde: The Other Self',
                'protagonist' => 'Dr. Henry Jekyll',
                'world_slug' => 'gothic-horrors',
                'world' => 'Gothic Horrors',
            ],
            'the-tell-tale-heart' => [
                'title' => 'The Tell-Tale Heart: Beneath the Floor',
                'protagonist' => 'The Narrator',
                'world_slug' => 'gothic-horrors',
                'world' => 'Gothic Horrors',
            ],
            'pride-and-prejudice' => [
                'title' => 'Pride and Prejudice: First Impressions',
                'protagonist' => 'Elizabeth Bennet',
                'world_slug' => 'jane-austen',
                'world' => 'Jane Austen',
            ],
            'emma' => [
                'title' => 'Emma: Hearts Misread',
                'protagonist' => 'Emma Woodhouse',
                'world_slug' => 'jane-austen',
                'world' => 'Jane Austen',
            ],
            'treasure-island' => [
                'title' => 'Treasure Island: The Map and the Mutiny',
                'protagonist' => 'Jim Hawkins',
                'world_slug' => 'high-adventure',
                'world' => 'High Adventure',
            ],
            'around-the-world-in-eighty-days' => [
                'title' => 'Around the World in Eighty Days: Race the Clock',
                'protagonist' => 'Jean Passepartout',
                'world_slug' => 'extraordinary-voyages',
                'world' => 'Extraordinary Voyages',
            ],
            'journey-to-the-center-of-the-earth' => [
                'title' => 'Journey to the Center of the Earth: Descent',
                'protagonist' => 'Axel',
                'world_slug' => 'extraordinary-voyages',
                'world' => 'Extraordinary Voyages',
            ],
            'the-three-musketeers' => [
                'title' => 'The Three Musketeers: One for All',
                'protagonist' => "D'Artagnan",
                'world_slug' => 'high-adventure',
                'world' => 'High Adventure',
            ],
            'robin-hood' => [
                'title' => 'Robin Hood: Outlaw of Sherwood',
                'protagonist' => 'Robin Hood',
                'world_slug' => 'high-adventure',
                'world' => 'High Adventure',
            ],
            'the-invisible-man' => [
                'title' => 'The Invisible Man: Unseen',
                'protagonist' => 'Griffin',
            ],
            'sir-gawain-and-the-green-knight' => [
                'title' => 'Sir Gawain and the Green Knight: The Year of Honor',
                'protagonist' => 'Sir Gawain',
                'world_slug' => 'camelot',
                'world' => 'Camelot',
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Launch library — playable allowlist and marketing catalog.
 *
 * `launch_playable_slugs` gates reviewer playability (with published + boiler-complete).
 * `boiled_playable_slugs` keeps already-boiled RnD titles playable alongside the launch roster.
 * `launch_catalog` is the canonical world + marketing title roster.
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
        'treasure-island',
        'around-the-world-in-eighty-days',
        'journey-to-the-center-of-the-earth',
        'the-three-musketeers',
        'robin-hood',
        'the-invisible-man',
        'sir-gawain-and-the-green-knight',
    ],

    /** Already boiled on Cloud — stay playable even though not on the 18-title launch roster. */
    'boiled_playable_slugs' => [
        'anima-machina',
        'wasteland',
        'driftheart',
        'momotaro-the-peach-boy',
        'the-unwritten-ones',
    ],

    'launch_catalog' => [
        'worlds' => [
            ['slug' => 'land-of-oz', 'folder' => '02-world-of-oz', 'title' => 'Land of Oz'],
            ['slug' => 'neverland', 'folder' => 'neverland', 'title' => 'Neverland'],
            ['slug' => 'baker-street', 'folder' => '01-baker-street', 'title' => 'Baker Street'],
            ['slug' => 'draculas-england', 'folder' => 'draculas-england', 'title' => "Dracula's England"],
            ['slug' => 'frankensteins-world', 'folder' => 'frankensteins-world', 'title' => "Frankenstein's World"],
            ['slug' => 'hydes-london', 'folder' => 'hydes-london', 'title' => "Hyde's London"],
            ['slug' => 'poes-gothic', 'folder' => 'poes-gothic', 'title' => "Poe's Gothic"],
            ['slug' => 'regency-england', 'folder' => '05-jane-austen', 'title' => 'Regency England'],
            ['slug' => 'treasure-island', 'folder' => 'treasure-island', 'title' => 'Treasure Island'],
            ['slug' => 'extraordinary-voyages', 'folder' => 'extraordinary-voyages', 'title' => 'Extraordinary Voyages'],
            ['slug' => 'musketeer-france', 'folder' => 'musketeer-france', 'title' => 'Musketeer France'],
            ['slug' => 'sherwood-forest', 'folder' => 'sherwood-forest', 'title' => 'Sherwood Forest'],
            ['slug' => 'the-unseen-world', 'folder' => 'the-unseen-world', 'title' => 'The Unseen World'],
            ['slug' => 'arthurian-britain', 'folder' => 'arthurian-britain', 'title' => 'Arthurian Britain'],
        ],

        'titles' => [
            'the-wonderful-wizard-of-oz' => [
                'title' => 'The Wonderful Wizard of Oz: The Road Home',
                'world_slug' => 'land-of-oz',
                'world' => 'Land of Oz',
            ],
            'peter-pan' => [
                'title' => "Peter Pan: Wendy's Neverland",
                'world_slug' => 'neverland',
                'world' => 'Neverland',
            ],
            'the-adventure-of-the-speckled-band' => [
                'title' => 'Sherlock Holmes: The Speckled Band',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'a-scandal-in-bohemia' => [
                'title' => 'Sherlock Holmes: A Scandal in Bohemia',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'the-adventure-of-the-blue-carbuncle' => [
                'title' => 'Sherlock Holmes: The Blue Carbuncle',
                'world_slug' => 'baker-street',
                'world' => 'Baker Street',
            ],
            'dracula' => [
                'title' => 'Dracula: The First Invitation',
                'world_slug' => 'draculas-england',
                'world' => "Dracula's England",
            ],
            'frankenstein' => [
                'title' => 'Frankenstein: The Creature Awakens',
                'world_slug' => 'frankensteins-world',
                'world' => "Frankenstein's World",
            ],
            'dr-jekyll-and-mr-hyde' => [
                'title' => 'Dr. Jekyll and Mr. Hyde: The Other Self',
                'world_slug' => 'hydes-london',
                'world' => "Hyde's London",
            ],
            'the-tell-tale-heart' => [
                'title' => 'The Tell-Tale Heart: Beneath the Floor',
                'world_slug' => 'poes-gothic',
                'world' => "Poe's Gothic",
            ],
            'pride-and-prejudice' => [
                'title' => 'Pride and Prejudice: First Impressions',
                'world_slug' => 'regency-england',
                'world' => 'Regency England',
            ],
            'emma' => [
                'title' => 'Emma: Hearts Misread',
                'world_slug' => 'regency-england',
                'world' => 'Regency England',
            ],
            'treasure-island' => [
                'title' => 'Treasure Island: The Map and the Mutiny',
                'world_slug' => 'treasure-island',
                'world' => 'Treasure Island',
            ],
            'around-the-world-in-eighty-days' => [
                'title' => 'Around the World in Eighty Days: Race the Clock',
                'world_slug' => 'extraordinary-voyages',
                'world' => 'Extraordinary Voyages',
            ],
            'journey-to-the-center-of-the-earth' => [
                'title' => 'Journey to the Center of the Earth: Descent',
                'world_slug' => 'extraordinary-voyages',
                'world' => 'Extraordinary Voyages',
            ],
            'the-three-musketeers' => [
                'title' => 'The Three Musketeers: One for All',
                'world_slug' => 'musketeer-france',
                'world' => 'Musketeer France',
            ],
            'robin-hood' => [
                'title' => 'Robin Hood: Outlaw of Sherwood',
                'world_slug' => 'sherwood-forest',
                'world' => 'Sherwood Forest',
            ],
            'the-invisible-man' => [
                'title' => 'The Invisible Man: Unseen',
                'world_slug' => 'the-unseen-world',
                'world' => 'The Unseen World',
            ],
            'sir-gawain-and-the-green-knight' => [
                'title' => 'Sir Gawain and the Green Knight: The Year of Honor',
                'world_slug' => 'arthurian-britain',
                'world' => 'Arthurian Britain',
            ],
        ],
    ],
];

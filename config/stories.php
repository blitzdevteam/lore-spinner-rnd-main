<?php

declare(strict_types=1);

/**
 * Review / launch playability — "The Unwritten Ones".
 *
 * A story is playable only when its slug is listed here AND adaptation_status is
 * completed. Boiling an approved title unlocks it automatically; all other
 * published stories show as Coming Soon in the UI.
 */
return [
    'review_playable_slugs' => [
        // Baker Street / Sherlock Holmes
        'the-adventure-of-the-speckled-band',
        'the-adventure-of-the-blue-carbuncle',
        'a-scandal-in-bohemia',

        // Jane Austen
        'pride-and-prejudice',

        // Classics
        'treasure-island',
        'dr-jekyll-and-mr-hyde',
        'the-tell-tale-heart',

        // LoreSpinner Originals
        'the-unwritten-ones',
    ],
];

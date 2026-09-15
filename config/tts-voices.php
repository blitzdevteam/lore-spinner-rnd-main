<?php

declare(strict_types=1);

/**
 * ElevenLabs voices available for player selection in gameplay settings.
 *
 * Story defaults are resolved by TtsVoiceCatalog (slug first, then launch
 * world, then fallback). Only IDs listed in `voices` may be requested
 * from the client.
 */
$jofra = 'NuRyEq0OdD9mMOyd51UZ';
$davidAmerican = 'v9LgF91V36LGgbLX3iHW';
$jonathan = '4u5cJuSmHP9d6YRolsOu';
$ellis = 'QxpsWUTZAxznFqyH1goJ';
$jake = 'ev2kMR9ZJZZsemuogS5u';
$henry = 'pVnrL6sighQX7hVz89cp';
$ianCartwell = 'e5WNhrdI30aXpS2RSGm1';
$davidDeep = 'BNgbHR0DNeZixGQVzloa';
$declanSage = 'kqVT88a5QfII1HNAEPTJ';
$autumnVeil = 'KoVIHoyLDrQyd4pGalbs';
$amelia = 'pGAwIQNN9UjOkKxjAyGQ';
$priyanka = 'BpjGufoPiobT79j2vtj4';
$ivanna = 'tQ4MEZFJOzsahSEEZtHK';
$imogen = 'jv41DhCf464zw0TI7I1w';
$emmaTaylor = 'S9EGwlCtMF7VXtENq79v';

return [
    'fallback' => $jofra,

    'voices' => [
        [
            'id' => $jofra,
            'label' => 'Jofra',
            'description' => 'Expressive & Neutral Narrator',
            'gender' => 'male',
        ],
        [
            'id' => $davidAmerican,
            'label' => 'David (American)',
            'description' => 'American Narrator',
            'gender' => 'male',
        ],
        [
            'id' => $jonathan,
            'label' => 'Jonathan',
            'description' => 'Sophisticated, Calm Narrator',
            'gender' => 'male',
        ],
        [
            'id' => $ellis,
            'label' => 'Ellis',
            'description' => 'Engaging British Storyteller',
            'gender' => 'male',
        ],
        [
            'id' => $jake,
            'label' => 'Jake',
            'description' => 'Deep, Smooth, Dramatic',
            'gender' => 'male',
        ],
        [
            'id' => $henry,
            'label' => 'Henry',
            'description' => 'Deep, Professional, and Soothing',
            'gender' => 'male',
        ],
        [
            'id' => $ianCartwell,
            'label' => 'Ian Cartwell',
            'description' => 'Suspense and Mystery',
            'gender' => 'male',
        ],
        [
            'id' => $davidDeep,
            'label' => 'David (Deep)',
            'description' => 'Deep and Engaging Storyteller',
            'gender' => 'male',
        ],
        [
            'id' => $declanSage,
            'label' => 'Declan Sage',
            'description' => 'Wise and Captivating',
            'gender' => 'male',
        ],
        [
            'id' => $autumnVeil,
            'label' => 'Autumn Veil',
            'description' => 'Warm and Reflective',
            'gender' => 'female',
        ],
        [
            'id' => $amelia,
            'label' => 'Amelia',
            'description' => 'Gentle and Emotive',
            'gender' => 'female',
        ],
        [
            'id' => $priyanka,
            'label' => 'Priyanka',
            'description' => 'Calm, Neutral and Relaxed',
            'gender' => 'female',
        ],
        [
            'id' => $ivanna,
            'label' => 'Ivanna',
            'description' => 'Seductive & Intimate',
            'gender' => 'female',
        ],
        [
            'id' => $imogen,
            'label' => 'Imogen',
            'description' => 'Warm & Professional British Storyteller',
            'gender' => 'female',
        ],
        [
            'id' => $emmaTaylor,
            'label' => 'Emma Taylor',
            'description' => 'Gentle Thoughtful Narrator',
            'gender' => 'female',
        ],
    ],

    /**
     * Launch world slug → recommended narrator. Players can override in
     * gameplay settings; switching titles restores that title's recommended voice.
     *
     * Launch sheet: Oz/Austen → Imogen, Baker Street → Ellis,
     * Gothic Horrors → Jonathan, High Adventure / Extraordinary Voyages → David (Deep).
     * Camelot is TBD and stays Declan Sage until a voice is chosen.
     * Standalone titles are set per story below, not here.
     */
    'world_defaults' => [
        'baker-street' => $ellis,
        '01-baker-street' => $ellis,
        'jane-austen' => $imogen,
        '05-jane-austen' => $imogen,
        'world-of-oz' => $imogen,
        '02-world-of-oz' => $imogen,
        'gothic-horrors' => $jonathan,
        'poes-gothic' => $jonathan,
        'hydes-london' => $jonathan,
        'draculas-england' => $jonathan,
        'frankensteins-world' => $jonathan,
        'high-adventure' => $davidDeep,
        'treasure-island' => $davidDeep,
        'musketeer-france' => $davidDeep,
        'sherwood-forest' => $davidDeep,
        'camelot' => $declanSage,
        'arthurian-britain' => $declanSage,
        'extraordinary-voyages' => $davidDeep,
        'neverland' => $emmaTaylor,
        'the-unseen-world' => $jonathan,
        'legends-of-mt-olympus' => $declanSage,
        '03-legends-of-mt-olympus' => $declanSage,
        'journey-to-the-west' => $jofra,
        '04-journey-to-the-west' => $jofra,
    ],

    /**
     * Story slug → voice. Used for standalones, titles without a launch world,
     * and explicit Baker Street locks. Jane Austen titles inherit Imogen from
     * the world (Emma Taylor is Peter and Wendy only).
     */
    'story_defaults' => [
        'the-adventure-of-the-speckled-band' => $ellis,
        'the-adventure-of-the-blue-carbuncle' => $ellis,
        'a-scandal-in-bohemia' => $ellis,
        'peter-pan' => $emmaTaylor,
        'peter-and-wendy' => $emmaTaylor,
        'the-invisible-man' => $jonathan,
        'the-masque-of-the-red-death' => $jonathan,
        'the-murders-in-the-rue-morgue' => $jonathan,
        'twenty-thousand-leagues-under-the-seas' => $davidDeep,
        'the-island-of-doctor-moreau' => $davidDeep,
        'the-marvelous-land-of-oz' => $imogen,
        'anima-machina' => $amelia,
        'nocturne' => $autumnVeil,
        'driftheart' => $declanSage,
        'the-matrix' => $declanSage,
        'the-unwritten-ones' => $declanSage,
        'dream-police' => $ianCartwell,
        'wasteland' => $jake,
        'pjs' => $davidDeep,
        'momotaro-the-peach-boy' => $jofra,
        'alices-adventures-in-wonderland' => $amelia,
        'the-snow-queen' => $autumnVeil,
        'theseus-and-the-minotaur' => $declanSage,
        'orpheus-and-eurydice' => $henry,
        'havoc-in-heaven' => $jofra,
        'the-white-bone-demon' => $ivanna,
    ],
];

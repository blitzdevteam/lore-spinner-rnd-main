<?php

declare(strict_types=1);

/**
 * ElevenLabs voices available for player selection in gameplay settings.
 *
 * Story-specific recommended voices are resolved separately via
 * ChaosStoryConfig::ttsVoiceId(). Only IDs listed here may be requested
 * from the client.
 */
return [
    'voices' => [
        [
            'id' => 'pNInz6obpgDQGcFmaJgB',
            'label' => 'Adam',
            'description' => 'Classic narrator',
        ],
        [
            'id' => 'j3QcmAr55TvFW5CDB0Q3',
            'label' => 'Warm female',
            'description' => 'Soft and expressive',
        ],
        [
            'id' => 'kqVT88a5QfII1HNAEPTJ',
            'label' => 'Declan Sage',
            'description' => 'Deep and cinematic',
        ],
        [
            'id' => 'Lci8YeL6PAFHJjNKvwXq',
            'label' => 'Yusuke',
            'description' => 'Dynamic storytelling',
        ],
    ],
];

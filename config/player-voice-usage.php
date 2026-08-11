<?php

declare(strict_types=1);

/**
 * Public pay-as-you-go TTS prices in USD per one thousand provider billing
 * units. Every usage row snapshots its active period, so future price or model
 * changes never rewrite historical estimates.
 *
 * ElevenLabs reports the characters used for billing in `character-cost`.
 * Deepgram reports input characters in `dg-char-count`. A custom ElevenLabs
 * voice multiplier is therefore captured through the provider-reported
 * billable characters rather than inferred only from the input text length.
 */
return [
    'currency' => 'USD',

    'providers' => [
        'elevenlabs' => [
            'label' => 'ElevenLabs',
            'models' => [
                'eleven_v3' => [
                    'label' => 'Eleven v3',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.100000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
                'eleven_multilingual_v2' => [
                    'label' => 'Multilingual v2',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.100000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
                'eleven_flash_v2_5' => [
                    'label' => 'Flash v2.5',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.050000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
                'eleven_turbo_v2_5' => [
                    'label' => 'Turbo v2.5',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.050000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
                'eleven_flash_v2' => [
                    'label' => 'Flash v2',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.050000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
                'eleven_turbo_v2' => [
                    'label' => 'Turbo v2',
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'elevenapi-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.050000',
                        'source' => 'https://elevenlabs.io/pricing/api?price.platform=api',
                    ]],
                ],
            ],
        ],

        'deepgram' => [
            'label' => 'Deepgram',
            'models' => [
                'aura-2' => [
                    'label' => 'Aura-2',
                    'prefixes' => ['aura-2-'],
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'deepgram-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.030000',
                        'source' => 'https://deepgram.com/pricing',
                    ]],
                ],
                'aura-1' => [
                    'label' => 'Aura-1',
                    'prefixes' => ['aura-'],
                    'billing_unit' => 'characters',
                    'periods' => [[
                        'version' => 'deepgram-payg-2026-08-11',
                        'effective_from' => null,
                        'effective_until' => null,
                        'rate_per_thousand' => '0.015000',
                        'source' => 'https://deepgram.com/pricing',
                    ]],
                ],
            ],
        ],
    ],
];

<?php

declare(strict_types=1);

/**
 * Standard, first-party API token prices in USD per one million tokens.
 *
 * These rates are intentionally separate from the gameplay model selection.
 * Every recorded usage row snapshots the selected period and rates, so editing
 * this file changes future calculations without rewriting historical costs.
 *
 * Anthropic gameplay prompts use a one-hour cache breakpoint. Consequently,
 * cache-write rates below use Anthropic's 2x one-hour write multiplier; cache
 * reads use the 0.1x multiplier. OpenAI cache writes are billed as ordinary
 * input if the provider ever reports them separately.
 */
return [
    'currency' => 'USD',

    'models' => [
        'gpt-5.5' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2026-04-23',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '5.000000',
                'output' => '30.000000',
                'cache_write' => '5.000000',
                'cache_read' => '0.500000',
                'source' => 'https://developers.openai.com/api/docs/models/gpt-5.5',
            ]],
        ],
        'gpt-5.4' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2026-03-05',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '2.500000',
                'output' => '15.000000',
                'cache_write' => '2.500000',
                'cache_read' => '0.250000',
                'source' => 'https://developers.openai.com/api/docs/models/gpt-5.4',
            ]],
        ],
        'gpt-5.4-mini' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2026-03-17',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '0.750000',
                'output' => '4.500000',
                'cache_write' => '0.750000',
                'cache_read' => '0.075000',
                'source' => 'https://developers.openai.com/api/docs/models/gpt-5.4-mini',
            ]],
        ],
        'gpt-5.2' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2025-12-11',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '1.750000',
                'output' => '14.000000',
                'cache_write' => '1.750000',
                'cache_read' => '0.175000',
                'source' => 'https://developers.openai.com/api/docs/models/gpt-5.2',
            ]],
        ],
        'gpt-4.1' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2025-04-14',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '2.000000',
                'output' => '8.000000',
                'cache_write' => '2.000000',
                'cache_read' => '0.500000',
                'source' => 'https://developers.openai.com/api/docs/models/gpt-4.1',
            ]],
        ],
        'chat-latest' => [
            'provider' => 'openai',
            'periods' => [[
                'version' => 'openai-standard-2026-08-10',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '5.000000',
                'output' => '30.000000',
                'cache_write' => '5.000000',
                'cache_read' => '0.500000',
                'source' => 'https://developers.openai.com/api/docs/models/chat-latest',
            ]],
        ],
        'claude-opus-4-8' => [
            'provider' => 'anthropic',
            'periods' => [[
                'version' => 'anthropic-standard-2026-08-10',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '5.000000',
                'output' => '25.000000',
                'cache_write' => '10.000000',
                'cache_read' => '0.500000',
                'source' => 'https://platform.claude.com/docs/en/about-claude/pricing',
            ]],
        ],
        'claude-opus-4-7' => [
            'provider' => 'anthropic',
            'periods' => [[
                'version' => 'anthropic-standard-2026-08-10',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '5.000000',
                'output' => '25.000000',
                'cache_write' => '10.000000',
                'cache_read' => '0.500000',
                'source' => 'https://platform.claude.com/docs/en/about-claude/pricing',
            ]],
        ],
        'claude-sonnet-5' => [
            'provider' => 'anthropic',
            'periods' => [
                [
                    'version' => 'anthropic-intro-2026-06-30',
                    'effective_from' => '2026-06-30T00:00:00Z',
                    'effective_until' => '2026-09-01T00:00:00Z',
                    'input' => '2.000000',
                    'output' => '10.000000',
                    'cache_write' => '4.000000',
                    'cache_read' => '0.200000',
                    'source' => 'https://platform.claude.com/docs/en/about-claude/models/whats-new-sonnet-5',
                ],
                [
                    'version' => 'anthropic-standard-2026-09-01',
                    'effective_from' => '2026-09-01T00:00:00Z',
                    'effective_until' => null,
                    'input' => '3.000000',
                    'output' => '15.000000',
                    'cache_write' => '6.000000',
                    'cache_read' => '0.300000',
                    'source' => 'https://platform.claude.com/docs/en/about-claude/models/whats-new-sonnet-5',
                ],
            ],
        ],
        'claude-sonnet-4-6' => [
            'provider' => 'anthropic',
            'periods' => [[
                'version' => 'anthropic-standard-2026-08-10',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '3.000000',
                'output' => '15.000000',
                'cache_write' => '6.000000',
                'cache_read' => '0.300000',
                'source' => 'https://platform.claude.com/docs/en/about-claude/pricing',
            ]],
        ],
        'claude-haiku-4-5' => [
            'provider' => 'anthropic',
            'periods' => [[
                'version' => 'anthropic-standard-2026-08-10',
                'effective_from' => null,
                'effective_until' => null,
                'input' => '1.000000',
                'output' => '5.000000',
                'cache_write' => '2.000000',
                'cache_read' => '0.100000',
                'source' => 'https://platform.claude.com/docs/en/about-claude/pricing',
            ]],
        ],
    ],
];

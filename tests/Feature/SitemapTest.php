<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Creator;
use App\Models\Story;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

it('publishes an xml sitemap of public marketing pages and live content', function () {
    Story::factory()->create([
        'slug' => 'pride-and-prejudice',
        'status' => StoryStatusEnum::PUBLISHED,
        'updated_at' => '2026-01-15 08:30:00',
    ]);

    Story::factory()->create([
        'slug' => 'unpublished-draft',
        'status' => StoryStatusEnum::DRAFT,
    ]);

    Story::factory()->create([
        'slug' => 'driftheart',
        'status' => StoryStatusEnum::PUBLISHED,
    ]);

    World::factory()->create([
        'slug' => 'jane-austen',
        'updated_at' => '2026-02-02 10:00:00',
    ]);

    World::factory()->inactive()->create([
        'slug' => 'hidden-world',
    ]);

    Creator::factory()->create([
        'username' => 'austen',
        'updated_at' => '2026-03-03 11:00:00',
    ]);

    $response = $this->get(route('sitemap'));

    $response
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse();

    expect($xml)
        ->toContain('<loc>https://lorespinner.com</loc>')
        ->toContain('<loc>https://lorespinner.com/about-us</loc>')
        ->toContain('<loc>https://lorespinner.com/pricing</loc>')
        ->toContain('<loc>https://lorespinner.com/stories</loc>')
        ->toContain('<loc>https://lorespinner.com/worlds</loc>')
        ->toContain('<loc>https://lorespinner.com/creators</loc>')
        ->toContain('<loc>https://lorespinner.com/stories/pride-and-prejudice</loc>')
        ->toContain('<lastmod>2026-01-15</lastmod>')
        ->toContain('<loc>https://lorespinner.com/worlds/jane-austen</loc>')
        ->toContain('<loc>https://lorespinner.com/creators/austen</loc>')
        ->not->toContain('localhost')
        ->not->toContain('unpublished-draft')
        ->not->toContain('driftheart')
        ->not->toContain('hidden-world')
        ->not->toContain('/chaos-mode')
        ->not->toContain('/user/');
});

it('tells crawlers where the sitemap is and which private paths to skip', function () {
    $response = $this->get(route('robots'));

    $response
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Disallow: /user/', false)
        ->assertSee('Disallow: /manager/', false)
        ->assertSee('Disallow: /expansion-status', false)
        ->assertSee('Sitemap: https://lorespinner.com/sitemap.xml', false)
        ->assertDontSee('Disallow: /stories', false);

    expect($response->getContent())
        ->toContain("Allow: /\n")
        ->not->toContain("Disallow: /\n");
});

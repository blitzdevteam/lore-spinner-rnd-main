<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Creator;
use App\Models\Story;
use App\Models\World;
use Illuminate\Support\Carbon;

final class Sitemap
{
    /**
     * Named routes for public pages that do not depend on a database record.
     *
     * @var list<string>
     */
    private const STATIC_ROUTES = [
        'index',
        'about-us',
        'pricing',
        'stories.index',
        'worlds.index',
        'creators.index',
    ];

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public function urls(): array
    {
        return [
            ...$this->staticUrls(),
            ...$this->storyUrls(),
            ...$this->worldUrls(),
            ...$this->creatorUrls(),
        ];
    }

    public function toXml(): string
    {
        $entries = '';

        foreach ($this->urls() as $url) {
            $entries .= "  <url>\n";
            $entries .= '    <loc>'.$this->escape($url['loc'])."</loc>\n";

            if ($url['lastmod'] !== null) {
                $entries .= '    <lastmod>'.$this->escape($url['lastmod'])."</lastmod>\n";
            }

            $entries .= "  </url>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$entries}</urlset>

XML;
    }

    /**
     * @return list<array{loc: string, lastmod: null}>
     */
    private function staticUrls(): array
    {
        return array_map(
            fn (string $name): array => [
                'loc' => $this->url($name),
                'lastmod' => null,
            ],
            self::STATIC_ROUTES,
        );
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function storyUrls(): array
    {
        return Story::query()
            ->published()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->reject(fn (Story $story): bool => StoryLibraryVisibility::isHidden((string) $story->slug))
            ->map(fn (Story $story): array => [
                'loc' => $this->url('stories.show', ['story' => $story->slug]),
                'lastmod' => $this->lastModified($story->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function worldUrls(): array
    {
        return World::query()
            ->active()
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->map(fn (World $world): array => [
                'loc' => $this->url('worlds.show', ['world' => $world->slug]),
                'lastmod' => $this->lastModified($world->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function creatorUrls(): array
    {
        return Creator::query()
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->orderBy('username')
            ->get(['username', 'updated_at'])
            ->map(fn (Creator $creator): array => [
                'loc' => $this->url('creators.show', ['creator' => $creator->username]),
                'lastmod' => $this->lastModified($creator->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        $path = route($name, $parameters, absolute: false);
        $origin = rtrim((string) config('app.canonical_url'), '/');

        if ($path === '' || $path === '/') {
            return $origin;
        }

        return $origin.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    private function lastModified(mixed $updatedAt): ?string
    {
        if (! $updatedAt instanceof Carbon) {
            return null;
        }

        return $updatedAt->toDateString();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

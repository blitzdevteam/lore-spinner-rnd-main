<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\StripeClient;

final class StripePriceCatalog
{
    private const string ACTIVE_CACHE_KEY = 'stripe.active-price-product-names';

    private ?StripeClient $client = null;

    public function usingClient(StripeClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function activeProductNames(): array
    {
        if (blank(config('cashier.secret'))) {
            return [];
        }

        $cached = Cache::get(self::ACTIVE_CACHE_KEY);

        if (is_array($cached)) {
            return $this->stringMap($cached);
        }

        try {
            $names = $this->fetchActiveProductNames();
        } catch (ApiErrorException $exception) {
            report($exception);

            return [];
        }

        Cache::put(self::ACTIVE_CACHE_KEY, $names, now()->addMinutes(10));

        return $names;
    }

    public function productName(string $priceId): ?string
    {
        if ($priceId === '' || blank(config('cashier.secret'))) {
            return null;
        }

        $activeNames = $this->activeProductNames();

        if (array_key_exists($priceId, $activeNames)) {
            return $activeNames[$priceId];
        }

        $cacheKey = 'stripe.price-product-name.'.$priceId;
        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        try {
            $name = $this->retrieveProductName($priceId);
        } catch (ApiErrorException $exception) {
            report($exception);

            return null;
        }

        Cache::put($cacheKey, $name ?? '', now()->addMinutes(10));

        return $name;
    }

    /**
     * @return array<string, string>
     */
    private function fetchActiveProductNames(): array
    {
        $names = [];
        $startingAfter = null;

        do {
            $parameters = [
                'active' => true,
                'limit' => 100,
                'expand' => ['data.product'],
            ];

            if (is_string($startingAfter)) {
                $parameters['starting_after'] = $startingAfter;
            }

            $page = $this->stripe()->prices->all($parameters);
            $startingAfter = null;

            foreach ($page->data as $price) {
                if (! $price instanceof Price || ! is_string($price->id) || $price->id === '') {
                    continue;
                }

                $name = $this->nameFromPrice($price);

                if ($name !== null) {
                    $names[$price->id] = $name;
                }

                $startingAfter = $price->id;
            }
        } while ($page->has_more === true && is_string($startingAfter));

        return $names;
    }

    private function retrieveProductName(string $priceId): ?string
    {
        $price = $this->stripe()->prices->retrieve($priceId, [
            'expand' => ['product'],
        ]);

        return $price instanceof Price ? $this->nameFromPrice($price) : null;
    }

    private function nameFromPrice(Price $price): ?string
    {
        $product = $price->product;
        $productName = null;

        if (is_object($product)) {
            $productName = $product->name ?? null;
        } elseif (is_array($product)) {
            $productName = $product['name'] ?? null;
        }

        if (is_string($productName) && $productName !== '') {
            return $productName;
        }

        $nickname = $price->nickname;

        return is_string($nickname) && $nickname !== '' ? $nickname : null;
    }

    private function stripe(): StripeClient
    {
        return $this->client ?? Cashier::stripe();
    }

    /**
     * @param  array<mixed, mixed>  $names
     * @return array<string, string>
     */
    private function stringMap(array $names): array
    {
        $mapped = [];

        foreach ($names as $priceId => $name) {
            if (! is_string($priceId) || $priceId === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $mapped[$priceId] = $name;
        }

        return $mapped;
    }
}

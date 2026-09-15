<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

final class SameOriginMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if ($this->media === null || ! PublicMedia::isPublic($this->media)) {
            return parent::getUrl();
        }

        $conversion = $this->conversion?->getName();

        return PublicMedia::url($this->media, filled($conversion) ? $conversion : null);
    }
}

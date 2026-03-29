<?php

declare(strict_types=1);

namespace App\Service\Reader;

final readonly class ReadDocument
{
    public function __construct(
        public string $url,
        public string $canonicalUrl,
        public string $title,
        public string $markdown,
    ) {
    }
}

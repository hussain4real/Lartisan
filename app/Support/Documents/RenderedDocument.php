<?php

namespace App\Support\Documents;

final readonly class RenderedDocument
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $path,
        public string $view,
        public array $data,
    ) {}
}

<?php

namespace App\Contracts\Documents;

use App\Support\Documents\RenderedDocument;

interface DocumentRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $path): RenderedDocument;
}

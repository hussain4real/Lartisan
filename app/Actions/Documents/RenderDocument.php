<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\DocumentRenderer;
use App\Support\Documents\RenderedDocument;

class RenderDocument
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly DocumentRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $view, array $data, string $path): RenderedDocument
    {
        return $this->renderer->render($view, $data, $path);
    }
}

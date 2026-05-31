<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentRenderer;
use App\Support\Documents\RenderedDocument;
use Spatie\LaravelPdf\Facades\Pdf;

class LaravelPdfDocumentRenderer implements DocumentRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $path): RenderedDocument
    {
        Pdf::view($view, $data)->save($path);

        return new RenderedDocument($path, $view, $data);
    }
}

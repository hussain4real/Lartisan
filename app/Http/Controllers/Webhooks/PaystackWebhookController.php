<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\ProcessPaystackWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPaystackWebhook $processPaystackWebhook): JsonResponse
    {
        $processPaystackWebhook->handle(
            payload: $request->getContent(),
            signature: $request->header('x-paystack-signature'),
        );

        return response()->json(['status' => 'ok']);
    }
}

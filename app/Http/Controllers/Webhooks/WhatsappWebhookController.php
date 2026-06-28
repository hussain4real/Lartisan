<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Notifications\RecordWhatsappDeliveryCallback;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function __invoke(Request $request, RecordWhatsappDeliveryCallback $recordWhatsappDeliveryCallback): JsonResponse
    {
        $payload = $request->getContent();
        $secretValue = config('lartisan.notifications.channels.whatsapp.webhook_secret', '');
        $signatureValue = $request->header('X-Lartisan-Whatsapp-Signature', '');
        $secret = is_scalar($secretValue) ? (string) $secretValue : '';
        $signature = $signatureValue;

        abort_if($secret === '' || $signature === '', 403);
        abort_unless(hash_equals(hash_hmac('sha256', $payload, $secret), $signature), 403);

        $data = $request->json()->all();
        $recordWhatsappDeliveryCallback->handle($data);

        return response()->json(['ok' => true]);
    }
}

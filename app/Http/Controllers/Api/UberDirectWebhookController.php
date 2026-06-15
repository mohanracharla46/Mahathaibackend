<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UberDirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UberDirectWebhookController extends Controller
{
    public function __invoke(Request $request, UberDirectService $uberDirect): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $uberDirect->handleWebhook($request->all());

        if (! $order) {
            Log::info('Uber Direct webhook did not match an order.', ['payload' => $request->all()]);
        }

        return response()->json(['received' => true]);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.uber_direct.webhook_secret');

        if (blank($secret)) {
            Log::error('Uber Direct webhook rejected because no webhook secret is configured.');

            return false;
        }

        $signature = $request->header('X-Uber-Signature') ?: $request->header('X-Uber-Signature-256');

        if (blank($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $normalized = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals($expected, $normalized);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\Chapa\HandleChapaWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapaWebhookController extends Controller
{
    public function __construct(
        private HandleChapaWebhookAction $handleWebhookAction
    ) {
    }

    /**
     * Handle Chapa webhook (authoritative path)
     * Note: Webhook signature verification should be added per Chapa documentation
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Chapa-Signature'); // Adjust header name per Chapa docs
        $this->handleWebhookAction->execute($request->all(), $signature);

        return response()->json([
            'message' => 'Webhook received',
        ]);
    }
}


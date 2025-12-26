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
     * Handle Chapa webhook
     * Note: Add webhook signature verification based on Chapa documentation
     */
    public function handle(Request $request): JsonResponse
    {
        $this->handleWebhookAction->execute($request->all());

        return response()->json([
            'message' => 'Webhook received',
        ]);
    }
}


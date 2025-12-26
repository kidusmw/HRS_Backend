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
        // Chapa may send one or more signature headers; accept any valid signature.
        $signatures = array_values(array_filter([
            $request->header('Chapa-Signature'),
            $request->header('x-chapa-signature'),
            $request->header('X-Chapa-Signature'),
        ]));

        // Verify signature against the RAW body (do not re-encode JSON)
        $rawBody = $request->getContent();

        $this->handleWebhookAction->execute($request->all(), $signatures, $rawBody);

        return response()->json([
            'message' => 'Webhook received',
        ]);
    }
}


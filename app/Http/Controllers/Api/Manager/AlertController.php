<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $perPage = (int) $request->input('per_page', 10);
        $status = $request->input('status');
        $severity = $request->input('severity');

        $query = Alert::query()->where('hotel_id', $hotelId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($severity) {
            $query->where('severity', $severity);
        }

        $alerts = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($alerts);
    }
}


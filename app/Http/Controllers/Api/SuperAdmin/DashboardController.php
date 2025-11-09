<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\MetricService;

class DashboardController extends Controller
{
    public function __construct(
        private MetricService $metricService
    ) {}

    public function metrics(Request $request)
    {
        $metrics = $this->metricService->getDashboardMetrics();
        return response()->json($metrics);
    }
}



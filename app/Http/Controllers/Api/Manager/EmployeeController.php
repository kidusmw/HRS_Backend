<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');

        $query = User::query()
            ->where('role', 'receptionist')
            ->where('hotel_id', $hotelId)
            ->with('supervisor');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('supervisor', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $paginated = $query->paginate($perPage);

        // Transform the data to include supervision information
        $transformedData = $paginated->getCollection()->map(function ($employee) use ($manager) {
            $underSupervision = $employee->supervisor_id === $manager->id;
            $managerName = null;
            
            if (!$underSupervision && $employee->supervisor) {
                $managerName = $employee->supervisor->name;
            }

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone_number,
                'shift' => $employee->shift ?? 'morning', // Default if not set
                'underSupervision' => $underSupervision,
                'status' => $employee->active ? 'active' : 'inactive',
                'managerName' => $managerName,
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}


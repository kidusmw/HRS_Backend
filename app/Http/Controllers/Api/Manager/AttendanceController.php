<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $perPage = (int) $request->input('per_page', 10);
        $date = $request->input('date');
        $userId = $request->input('user_id');

        $query = AttendanceRecord::query()
            ->with('user')
            ->where('hotel_id', $hotelId);

        if ($date) {
            $query->whereDate('date', $date);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $records = $query->orderByDesc('date')->paginate($perPage);

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $manager = $request->user();
        $hotelId = $manager->hotel_id;

        $data = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) use ($hotelId) {
                    $query->where('hotel_id', $hotelId)->where('role', 'receptionist');
                }),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['present', 'late', 'absent', 'on_leave'])],
            'note' => ['nullable', 'string'],
        ]);

        $record = AttendanceRecord::create([
            'user_id' => $data['user_id'],
            'hotel_id' => $hotelId,
            'date' => Carbon::parse($data['date'])->toDateString(),
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $record], 201);
    }
}


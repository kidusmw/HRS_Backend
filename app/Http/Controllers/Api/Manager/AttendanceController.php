<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\AuditLog;
use App\Enums\UserRole;
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
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
        $userId = $request->input('user_id');

        // Get all receptionists in the hotel (under this manager's supervision or not)
        // The frontend will filter by supervision if needed
        $receptionistsQuery = User::where('hotel_id', $hotelId)
            ->where('role', UserRole::RECEPTIONIST->value);
        
        // Optionally filter by supervisor_id if provided, but show all by default
        // This allows managers to see all receptionists in their hotel

        if ($userId) {
            $receptionistsQuery->where('id', $userId);
        }

        $receptionists = $receptionistsQuery->get();

        $attendanceRecords = [];

        foreach ($receptionists as $receptionist) {
            // Check if there's an existing attendance record for this date
            $existingRecord = AttendanceRecord::where('user_id', $receptionist->id)
                ->whereDate('date', $date->toDateString())
                ->first();

            if ($existingRecord) {
                // Use existing record
                $attendanceRecords[] = $existingRecord;
            } else {
                // Calculate attendance based on login time from audit logs
                $loginLog = AuditLog::where('user_id', $receptionist->id)
                    ->where('action', 'user.login')
                    ->whereDate('timestamp', $date->toDateString())
                    ->orderBy('timestamp', 'asc')
                    ->first();

                $status = 'absent';
                $loginTime = null;

                if ($loginLog) {
                    $loginTime = Carbon::parse($loginLog->timestamp);
                    $loginHour = $loginTime->hour;
                    $loginMinute = $loginTime->minute;

                    // Working hours: 9 AM to 5 PM
                    // If login is before 9 AM (or exactly at 9:00), mark as present
                    // If login is after 9 AM but before 5 PM, mark as late
                    // If login is after 5 PM, still mark as late (they came but late)
                    if ($loginHour < 9 || ($loginHour === 9 && $loginMinute === 0)) {
                        $status = 'present';
                    } elseif ($loginHour < 17) { // Before 5 PM
                        $status = 'late';
                    } else {
                        // After 5 PM - still mark as late (they logged in but very late)
                        $status = 'late';
                    }
                }

                // Create or get attendance record
                $record = AttendanceRecord::firstOrCreate(
                    [
                        'user_id' => $receptionist->id,
                        'hotel_id' => $hotelId,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'note' => $loginTime ? "Auto-calculated from login time: " . $loginTime->format('H:i') : 'No login recorded',
                    ]
                );

                $attendanceRecords[] = $record;
            }
        }

        // Load user relationships and transform to match frontend expectations
        $transformedRecords = [];
        foreach ($attendanceRecords as $record) {
            $record->load('user');
            $transformedRecords[] = [
                'id' => $record->id,
                'employeeId' => $record->user_id, // Frontend expects employeeId
                'user_id' => $record->user_id, // Keep for backward compatibility
                'date' => $record->date->format('Y-m-d'),
                'status' => $record->status,
                'note' => $record->note,
                'user' => $record->user ? [
                    'id' => $record->user->id,
                    'name' => $record->user->name,
                    'email' => $record->user->email,
                ] : null,
            ];
        }

        // Paginate manually
        $total = count($transformedRecords);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $paginatedRecords = array_slice($transformedRecords, $offset, $perPage);

        return response()->json([
            'data' => $paginatedRecords,
            'meta' => [
                'current_page' => $page,
                'from' => $total > 0 ? $offset + 1 : null,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                'total' => $total,
            ],
        ]);
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


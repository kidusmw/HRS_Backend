<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'status',
        'is_walk_in',
        'total_amount',
        'payment_status',
        'guests',
        'special_requests',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'is_walk_in' => 'boolean',
            'guests' => 'integer',
            'total_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the attributes that should be included in the model's array/JSON representation.
     */
    protected function visible(): array
    {
        return [
            'id',
            'room_id',
            'user_id',
            'guest_name',
            'guest_email',
            'guest_phone',
            'check_in',
            'check_out',
            'status',
            'is_walk_in',
            'total_amount',
            'payment_status',
            'guests',
            'special_requests',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the room for this reservation
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user who made this reservation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Sums paid/completed payments and updates payment_status based on total_amount.
     *
     * pending:   paid <= 0
     * paid:      paid >= total
     * failed:    payment failed (set explicitly)
     * refunded: payment refunded (set explicitly)
     */
    public function calculatePaymentStatus(bool $persist = true): PaymentStatus
    {
        $total = (float) ($this->total_amount ?? 0);

        $paid = (float) $this->payments()
            ->whereIn('status', [
                PaymentTransactionStatus::PAID->value,
                PaymentTransactionStatus::COMPLETED->value,
            ])
            ->sum('amount');

        // Compare in cents to avoid floating point edge cases
        $totalCents = (int) round($total * 100);
        $paidCents = (int) round($paid * 100);

        if ($paidCents <= 0) {
            $status = PaymentStatus::PENDING;
        } elseif ($paidCents >= $totalCents) {
            $status = PaymentStatus::PAID;
        } else {
            // Partial payment - still pending
            $status = PaymentStatus::PENDING;
        }

        if ($persist) {
            $this->payment_status = $status;
            $this->save();
        }

        return $status;
    }
}


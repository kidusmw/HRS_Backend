<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'amount',
        'currency',
        'method',
        'status',
        'transaction_reference',
        'collected_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentTransactionStatus::class,
            'meta' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * User who collected cash / verified transfer (nullable)
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}

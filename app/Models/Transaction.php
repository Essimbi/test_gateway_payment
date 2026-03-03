<?php

namespace App\Models;

use App\PaymentStatus;
use App\GatewayType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'gateway_type',
        'gateway_payment_id',
        'return_url',
        'notify_url',
        'metadata',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PaymentStatus::class,
        'gateway_type' => GatewayType::class,
        'metadata' => 'array',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * Scope a query to only include accepted transactions.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::ACCEPTED);
    }

    /**
     * Scope a query to only include refused transactions.
     */
    public function scopeRefused(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::REFUSED);
    }

    /**
     * Scope a query to filter transactions by gateway type.
     * 
     * @param Builder $query
     * @param GatewayType $gatewayType
     * @return Builder
     */
    public function scopeByGateway(Builder $query, GatewayType $gatewayType): Builder
    {
        return $query->where('gateway_type', $gatewayType);
    }
}

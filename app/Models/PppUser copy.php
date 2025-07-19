<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PppUser extends Model
{
    protected $fillable = [
        'username', 'password', 'service', 'profile',
        'local_address', 'remote_address', 'phone',
        'email', 'address', 'activated_at', 'expired_at',
        'due_date', 'grace_period_days', 'suspended_at',
        'balance', 'payment_history', 'status', 'package_id'
    ];

    protected $casts = [
        'payment_history' => 'array',
        'due_date' => 'date',
        'suspended_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'balance' => 'decimal:2',
    ];

    /**
     * Relationship to Package
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Relationship to Payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Process a payment and update user account
     */
    public function addPayment(float $amount, string $method, string $reference, int $durationDays): array
    {
        DB::beginTransaction();

        try {
            // Create payment record
            $payment = $this->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'date' => now(),
            ]);

            // Update user account
            $this->update([
                'balance' => $this->balance + $amount,
                'due_date' => now()->addDays($durationDays),
                'expired_at' => now()->addDays($durationDays),
                'status' => 'active',
            ]);

            DB::commit();

            return [
                'success' => true,
                'payment' => $payment,
                'user' => $this->fresh()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user should be suspended
     */
    public function shouldBeSuspended(): bool
    {
        if (in_array($this->status, ['suspended', 'expired'])) {
            return false;
        }

        if ($this->due_date && Carbon::now()->startOfDay()->lte($this->due_date->addDays($this->grace_period_days)->endOfDay())) {
            return false;
        }

        return $this->balance > 0;
    }
}
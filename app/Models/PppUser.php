<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'suspended_at' => 'date',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'balance' => 'decimal:2',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function shouldBeSuspended(): bool
    {
        if (in_array($this->status, ['suspended', 'expired'])) {
            return false;
        }

        if ($this->due_date && Carbon::now()->lte($this->due_date->addDays($this->grace_period_days))) {
            return false;
        }

        return $this->balance > 0;
    }

    public function addPayment(float $amount, string $method, string $reference): void
    {
        $payment = [
            'date' => now()->toDateString(),
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
        ];

        $history = $this->payment_history ?? [];
        $history[] = $payment;

        $this->update([
            'balance' => max(0, $this->balance - $amount),
            'payment_history' => $history,
            'status' => 'active',
            'suspended_at' => null,
        ]);

        if ($this->due_date && now()->gt($this->due_date)) {
            $this->update(['due_date' => $this->calculateNextDueDate()]);
        }
    }

    protected function calculateNextDueDate(): ?Carbon
    {
        if (!$this->package) {
            return null;
        }
        return now()->addDays($this->package->duration_days);
    }
}
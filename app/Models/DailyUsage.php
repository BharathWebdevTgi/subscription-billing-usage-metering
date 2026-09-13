<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    protected $table = 'daily_usage';

    protected $fillable = [
        'customer_id',
        'subscription_id',
        'usage_date',
        'units',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'units' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
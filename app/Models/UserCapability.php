<?php

namespace App\Models;

use App\Enums\CapabilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCapability extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'capability',
        'otorgado_por',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'capability' => CapabilityType::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function otorgadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgado_por');
    }
}

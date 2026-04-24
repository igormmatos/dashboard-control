<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_number',
    ];

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }
}

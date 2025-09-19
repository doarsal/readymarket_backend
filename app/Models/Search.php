<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Search extends Model
{
    protected $fillable = [
        'search_term',
        'total_results',
        'user_id',
        'store_id',
        'session_id',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'total_results' => 'integer',
        'user_id' => 'integer',
        'store_id' => 'integer',
    ];

    /**
     * Get the user that performed the search
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

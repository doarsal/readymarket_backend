<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'language_id',
        'translatable_type',
        'translatable_id',
        'field',
        'value'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function translatable()
    {
        return $this->morphTo();
    }
}

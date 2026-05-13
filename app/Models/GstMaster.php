<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cgst',
        'sgst',
        'igst',
        'total_gst',
        'is_active',
    ];

    protected $casts = [
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'igst' => 'decimal:2',
        'total_gst' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function getActiveOptions()
    {
        return self::where('is_active', true)->get();
    }
}

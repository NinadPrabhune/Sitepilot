<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'site_id',
        'created_by',
        'workspace_id',
        'is_active',
        'status',
    ];


    public $timestamps = true;

    
    protected static function booted()
    {
        static::addGlobalScope('orderByName', function (Builder $builder) {
            $builder->orderBy('name', 'asc');
        });
    }
}

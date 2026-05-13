<?php

namespace Workdo\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];
    
    protected static function newFactory()
    {
        return \Workdo\Hrm\Database\factories\BranchFactory::new();
    }
}

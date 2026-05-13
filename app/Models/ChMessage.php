<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChMessage extends Model
{
    protected $fillable = ['id', 'type', 'from_id', 'to_id', 'project_id', 'body', 'attachment', 'seen'];

    // Disable auto-increment since we're providing custom IDs
    public $incrementing = false;
    protected $keyType = 'int';
}

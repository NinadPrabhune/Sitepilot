<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name','symbol', 'site_id', 'created_by', 'workspace_id'];

}

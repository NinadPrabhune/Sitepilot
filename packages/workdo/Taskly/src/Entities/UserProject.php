<?php

namespace Workdo\Taskly\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'user_id', 'project_id', 'is_active',
    ];

    protected static function newFactory()
    {
        return \Workdo\Taskly\Database\factories\UserProjectFactory::new();
    }

    /**
     * Get the project that this user assignment belongs to
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Get the user that this project assignment belongs to
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityCompleted extends Model
{
    use HasFactory;

    // Specify the table name
    protected $table = 'activities_completed';

    protected $fillable = ['activity_id', 'completed_quantity', 'completed_date', 'created_by', 'completed_reference_file'];

    /**
     * Get the user who created this completion entry.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the activity that owns this completion entry.
     */
    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * Get all manpower records associated with this completion.
     */
    public function manpowers()
    {
        return $this->hasMany(ManPowerMaster::class, 'activity_completed_id');
    }

    /**
     * Get all daily progress reports associated with this completion.
     */
    public function dailyProgressReports()
    {
        return $this->hasMany(DailyProgressReport::class, 'activity_completed_id')
                    ->orderBy('date', 'desc');
    }

    /**
     * Get all daily consumption records associated with this completion.
     */
    public function dailyConsumptions()
    {
        return $this->hasMany(DailyConsumptionMaster::class, 'activity_completed_id')
                    ->orderBy('consumption_date', 'desc');
    }

    public function allConsumptions()
    {
        return $this->hasMany(DailyConsumptionMaster::class, 'activity_completed_id')
                    ->where('consumption_type', 'all')
                    ->orderBy('consumption_date', 'desc');
    }

    public function fuelConsumptions()
    {
        return $this->hasMany(DailyConsumptionMaster::class, 'activity_completed_id')
                    ->where('consumption_type', 'fuel')
                    ->orderBy('consumption_date', 'desc');
    }
}

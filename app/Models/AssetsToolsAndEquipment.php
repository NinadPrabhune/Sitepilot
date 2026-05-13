<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;


class AssetsToolsAndEquipment extends Model
{
    use HasFactory;

    protected $table = 'assets_tools_and_equipment';

    protected $fillable = [
        'material_id',
        'quantity',
        'operational_status',
        'site_id',
        'created_by',
        'workspace_id',
    ];

    /**
     * Relationships
     */

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function site()
    {
        return $this->belongsTo(Project::class);
    }

    public function WorkSpace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

    public function generalTransfers()
    {
        return $this->hasMany(GeneralTransfer::class, 'tools_and_equipment_id');
    }

    /**
     * Scopes (optional)
     */

    public function scopeActive($query)
    {
        return $query->where('operational_status', 'active');
    }

    public function scopeBreakdown($query)
    {
        return $query->where('operational_status', 'breakdown');
    }

    public function scopeScrap($query)
    {
        return $query->where('operational_status', 'scrap');
    }
}

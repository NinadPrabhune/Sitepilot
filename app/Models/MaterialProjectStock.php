<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialProjectStock extends Model
{
    use HasFactory;

    protected $table = 'material_project_stock';

    protected $fillable = [
        'project_id',
        'material_id',
        'current_stock',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'project_id' => 'integer',
        'material_id' => 'integer',
    ];

    /**
     * Get the project (site/warehouse) for this stock.
     */
    public function project()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'project_id');
    }

    /**
     * Get the material for this stock.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Scope to filter by project.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter by material.
     */
    public function scopeForMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    /**
     * Scope for items with stock.
     */
    public function scopeWithStock($query)
    {
        return $query->where('current_stock', '>', 0);
    }

    /**
     * Check if stock is available.
     */
    public function hasStock($quantity = 0)
    {
        return $this->current_stock >= $quantity;
    }

    /**
     * Add stock to current balance.
     */
    public function addStock($quantity)
    {
        $this->current_stock += $quantity;
        $this->save();
    }

    /**
     * Deduct stock from current balance.
     */
    public function deductStock($quantity)
    {
        if ($this->current_stock >= $quantity) {
            $this->current_stock -= $quantity;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Get current stock or 0 if not exists.
     */
    public static function getCurrentStock($projectId, $materialId)
    {
        $stock = self::where('project_id', $projectId)
            ->where('material_id', $materialId)
            ->first();
        
        return $stock ? $stock->current_stock : 0;
    }

    /**
     * Get or create stock record.
     */
    public static function getOrCreate($projectId, $materialId)
    {
        return self::firstOrCreate(
            ['project_id' => $projectId, 'material_id' => $materialId],
            ['current_stock' => 0]
        );
    }
}

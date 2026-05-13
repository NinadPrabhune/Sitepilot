<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;
use App\Models\WorkSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class Machinery extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'model_number',
        'manufacturer',
        'purchase_date',
        'capacity',
        'maintenance_schedule',
        'remarks',
        'description',
        'vehicle_number',
        'owned_by',
        'rate',
        'supplier_id',
        'operational_status',
        'site_id',
        'created_by',
        'workspace_id',
        'status',
        'machine_id',
        'rate_type',
        'minimum_billing_hours',
        'diesel_by_company',
        'operator_by_supplier',
        'number_of_operators',
        'rental_agreement_file',
        'purchase_value',
        'insurance_due_date',
        'puc_due_date',
        'fitness_due_date',
        'last_service_date',
        'ownership_documents_file',
    ];

    protected $casts = [
        'diesel_by_company' => 'boolean',
        'operator_by_supplier' => 'boolean',
    ];

    public static function rules()
    {
        return [
            'machine_id' => 'nullable|regex:/^MCH-\d{3,4}$/|unique:machineries,machine_id',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Auto-generate machine_id with lockForUpdate to prevent race conditions
        static::creating(function ($machinery) {
            if (empty($machinery->machine_id)) {
                try {
                    DB::transaction(function () use ($machinery) {
                        $lastMachine = Machinery::lockForUpdate()
                            ->whereNotNull('machine_id')
                            ->orderBy('machine_id', 'desc')
                            ->first();

                        $lastNumber = 0;
                        if ($lastMachine) {
                            // Extract number from MCH-XXX format
                            preg_match('/MCH-(\d+)$/', $lastMachine->machine_id, $matches);
                            $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                        }

                        $nextNumber = $lastNumber + 1;
                        $machinery->machine_id = 'MCH-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

                        Log::info('Machine ID generated', [
                            'machine_id' => $machinery->machine_id,
                            'machinery_name' => $machinery->name,
                        ]);
                    });
                } catch (\Exception $e) {
                    Log::error('Machine ID generation failed', [
                        'error' => $e->getMessage(),
                        'machinery_name' => $machinery->name,
                    ]);
                    throw $e;
                }
            }
        });

        // Delete files when machinery is deleted
        static::deleting(function ($machinery) {
            try {
                if ($machinery->rental_agreement_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->rental_agreement_file);
                }
                if ($machinery->ownership_documents_file) {
                    Storage::disk('public')->delete('machinery_documents/' . $machinery->ownership_documents_file);
                }
            } catch (\Exception $e) {
                Log::error('File deletion failed during machinery delete', [
                    'error' => $e->getMessage(),
                    'machinery_id' => $machinery->id,
                    'machine_id' => $machinery->machine_id,
                ]);
            }
        });
    }

    // 🔗 Relationship: Machinery belongs to a Category
    public function category()
    {
        return $this->belongsTo(MachineryCategory::class, 'category_id');
    }

    // 🔗 Relationship: Machinery may belong to a Supplier (if rented)
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function site()
    {
        return $this->belongsTo(Project::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }

}

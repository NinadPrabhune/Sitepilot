<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\Spent;
use Illuminate\Support\Facades\Log;

class SpentExport implements FromQuery, WithHeadings, WithMapping
{
    protected array $ids;
    protected bool $exportAll;
    protected ?array $columns;
    protected ?array $columnLabels;

    public function __construct(
        ?array $columns = null,
        ?array $columnLabels = null,
        array $ids = [],
        bool $exportAll = false
    ) {
        $this->columns = $columns;
        $this->columnLabels = $columnLabels;
        $this->ids = $ids;
        $this->exportAll = $exportAll;

        Log::info('SpentExport __construct', [
            'columns' => $columns,
            'columnLabels' => $columnLabels,
            'ids' => $ids,
            'exportAll' => $exportAll,
            'ids_count' => count($ids)
        ]);
    }

    public function query()
    {
        // Load relationships with withTrashed to handle soft deletes
        $query = Spent::with(['spentLedger', 'project', 'createdBy'])
            ->with(['project' => function($q) {
                $q->withTrashed();
            }])
            ->with(['createdBy' => function($q) {
                $q->withTrashed();
            }]);

        if ($this->exportAll) {
            // When exportAll is true, apply workspace/project filters
            $query->where('workspace_id', getActiveWorkSpace())
                ->when(getActiveProject(), function ($q) {
                    $q->where('project_id', getActiveProject());
                });

            Log::info('SpentExport query: exportAll mode with filters');
        } elseif (!empty($this->ids)) {
            // When exportAll is false, filter by IDs only (checkbox export)
            $query->whereIn('id', $this->ids);
            Log::info('SpentExport query: checkbox mode with IDs', ['ids_count' => count($this->ids)]);
        } else {
            Log::warning('SpentExport query: no filters applied', [
                'exportAll' => $this->exportAll,
                'ids_count' => count($this->ids)
            ]);
        }

        Log::info('SpentExport query built', [
            'exportAll' => $this->exportAll,
            'ids' => $this->ids,
            'workspace_id' => getActiveWorkSpace(),
            'project_id' => getActiveProject()
        ]);

        return $query;
    }

    public function headings(): array
    {
        Log::info('SpentExport headings', [
            'columns' => $this->columns,
            'columnLabels' => $this->columnLabels,
            'has_columns' => !empty($this->columns),
            'has_labels' => !empty($this->columnLabels)
        ]);

        if ($this->columnLabels && $this->columns) {
            return array_map(fn($col) => $this->columnLabels[$col] ?? ucwords(str_replace('_', ' ', $col)), $this->columns);
        }

        if ($this->columns) {
            return array_map(fn($col) => ucwords(str_replace('_', ' ', $col)), $this->columns);
        }

        return ['ID', 'Name', 'Ledger Name', 'Amount', 'Project', 'Created By', 'Created At'];
    }

    public function map($spent): array
    {
        $data = [];

        if ($this->columns && !empty($this->columns)) {
            foreach ($this->columns as $column) {
                $data[] = $this->getColumnValue($spent, $column);
            }
        } else {
            $data = [
                $spent->id,
                $spent->name,
                optional($spent->spentLedger)->name ?? '',
                $spent->amount,
                optional($spent->project)->name ?? '',
                optional($spent->createdBy)->name ?? '',
                $spent->created_at->format('d-m-Y H:i:s'),
            ];
        }

        Log::info('SpentExport map for record', [
            'spent_id' => $spent->id,
            'spent_ledger_id' => $spent->spent_ledger_id,
            'spentLedger' => $spent->spentLedger ? $spent->spentLedger->toArray() : null,
            'data' => $data
        ]);

        return $data;
    }

    protected function getColumnValue(Spent $spent, string $column)
    {
        $value = '';

        switch ($column) {
            case 'id':
                $value = $spent->id;
                break;
            case 'name':
                $value = $spent->name;
                break;
            case 'ledger_name':
                $value = optional($spent->spentLedger)->name ?? '';
                Log::info('SpentExport getColumnValue ledger_name', [
                    'spent_ledger_id' => $spent->spent_ledger_id,
                    'spentLedger' => $spent->spentLedger ? $spent->spentLedger->toArray() : null,
                    'value' => $value
                ]);
                break;
            case 'spent_ledger_id':
                $value = $spent->spent_ledger_id;
                break;
            case 'amount':
                $value = $spent->amount;
                break;
            case 'project_id':
                $value = optional($spent->project)->name ?? '';
                break;
            case 'project_name':
                $value = optional($spent->project)->name ?? '';
                Log::info('SpentExport getColumnValue project_name', [
                    'project_id' => $spent->project_id,
                    'project' => $spent->project ? $spent->project->toArray() : null,
                    'value' => $value
                ]);
                break;
            case 'created_by':
                $value = optional($spent->createdBy)->name ?? '';
                break;
            case 'created_by_name':
                $value = optional($spent->createdBy)->name ?? '';
                Log::info('SpentExport getColumnValue created_by_name', [
                    'created_by' => $spent->created_by,
                    'createdBy' => $spent->createdBy ? $spent->createdBy->toArray() : null,
                    'value' => $value
                ]);
                break;
            case 'created_at':
                $value = $spent->created_at->format('d-m-Y H:i:s');
                break;
            default:
                $value = '';
        }

        return $value;
    }
}

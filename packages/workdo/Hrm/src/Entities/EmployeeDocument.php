<?php

namespace Workdo\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeDocument extends Model {

    use HasFactory;

    protected $fillable = [
        'employee_id',
        'document_id',
        'document_value',
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function documentType() {
        return $this->belongsTo(DocumentType::class, 'document_id');
    }

    protected static function newFactory() {
        return \Workdo\Hrm\Database\factories\EmployeeDocumentFactory::new();
    }
}

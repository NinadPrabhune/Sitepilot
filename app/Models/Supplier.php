<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'type',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'gst_number',
        'pan_number',
        'registration_number',
        'bank_name',
        'account_number',
        'ifsc_code',
        'payment_terms',
        'upi_screenshot_1',
        'upi_screenshot_2',
        'created_by',
        'is_active',
        'status',
        'site_id',
    ];

    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, 'category_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

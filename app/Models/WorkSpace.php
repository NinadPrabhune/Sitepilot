<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Project;

class WorkSpace extends Model
{
    use HasFactory;

    protected $table = 'work_spaces';

    protected $fillable = [
        // custom domain code
        'name', 'status', 'slug', 'enable_domain', 'domain_type', 'domain', 'subdomain', 'is_disable',
        // business information
        'contact_person', 'phone', 'email', 'address', 'city', 'state', 'pincode', 'country',
        'gst_number', 'pan_number', 'bank_name', 'account_number', 'ifsc_code',
        // additional business details
        'website', 'cin_no', 'logo', 'terms_and_conditions','created_by'
    ];

    protected $casts = [
        'enable_domain' => 'boolean',
        'is_disable' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($workspace) {
            $workspace->slug = $workspace->createSlug($workspace->name);
        });
    }

    private function createSlug(string $name): string
    {
        $baseSlug = \Str::slug($name);
        $slug = $baseSlug;
        $count = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    // Relationship with projects
    public function projects()
    {
        return $this->hasMany(Project::class, 'workspace', 'id')->with('task');
    }
}
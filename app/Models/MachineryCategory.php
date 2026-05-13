<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineryCategory extends Model
{
        protected $fillable = [
            'name',
            'description',
            'site_id',
            'created_by',
            'workspace_id',
            'is_active',
            'status',
        ];


        public $timestamps = true;
   
        public function machineries()
        {
            return $this->hasMany(Machinery::class, 'category_id');
        }

}

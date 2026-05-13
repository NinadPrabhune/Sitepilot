<?php

namespace App\Traits;

trait HasAssignTo
{
    /**
     * Mutator: Convert array to comma-separated string on assignment
     *
     * @param mixed $value
     * @return void
     */
    public function setAssignToAttribute($value)
    {
        $this->attributes['assign_to'] = (is_array($value) && !empty($value))
            ? implode(',', $value)
            : (is_string($value) ? $value : null);
    }

    /**
     * Accessor: Convert comma-separated string to array
     *
     * @return array
     */
    public function getAssignToArrayAttribute()
    {
        return $this->assign_to ? explode(',', $this->assign_to) : [];
    }
}

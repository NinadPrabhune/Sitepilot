<?php

namespace Tests\Feature;

use App\Models\Role;
use Tests\TestCase;

class RbacPermissionEqualityTest extends TestCase
{
    public function test_admin_and_company_have_same_permissions()
    {
        $company = Role::where('name', 'company')->first();
        $admin = Role::where('name', 'admin')->first();

        $this->assertEquals(
            $company->permissions->pluck('name')->sort()->values(),
            $admin->permissions->pluck('name')->sort()->values(),
            'Admin should have exactly the same permissions as Company'
        );
    }
}

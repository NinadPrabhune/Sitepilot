<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Machinery;
use App\Domain\Machinery\Services\DieselResponsibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DieselResponsibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_true_when_company_pays_diesel()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => true,
        ]);

        $this->assertTrue(DieselResponsibilityService::companyPaysDiesel($machinery));
        $this->assertTrue(DieselResponsibilityService::shouldDeductDieselFromPayment($machinery));
    }

    /** @test */
    public function it_returns_false_when_supplier_pays_diesel()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => false,
        ]);

        $this->assertFalse(DieselResponsibilityService::companyPaysDiesel($machinery));
        $this->assertFalse(DieselResponsibilityService::shouldDeductDieselFromPayment($machinery));
    }

    /** @test */
    public function it_returns_full_diesel_cost_when_company_pays()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => true,
        ]);

        $dieselCost = 5000.00;
        $result = DieselResponsibilityService::getDeductibleDieselAmount($machinery, $dieselCost);

        $this->assertEquals(5000.00, $result);
    }

    /** @test */
    public function it_returns_zero_when_supplier_pays()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => false,
        ]);

        $dieselCost = 5000.00;
        $result = DieselResponsibilityService::getDeductibleDieselAmount($machinery, $dieselCost);

        $this->assertEquals(0.00, $result);
    }

    /** @test */
    public function it_handles_zero_diesel_cost()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => true,
        ]);

        $dieselCost = 0.00;
        $result = DieselResponsibilityService::getDeductibleDieselAmount($machinery, $dieselCost);

        $this->assertEquals(0.00, $result);
    }

    /** @test */
    public function it_handles_null_diesel_by_company_as_false()
    {
        $machinery = Machinery::factory()->make([
            'diesel_by_company' => null,
        ]);

        // Null should be treated as false (supplier pays by default)
        $this->assertFalse(DieselResponsibilityService::companyPaysDiesel($machinery));
        
        $dieselCost = 5000.00;
        $result = DieselResponsibilityService::getDeductibleDieselAmount($machinery, $dieselCost);
        $this->assertEquals(0.00, $result);
    }
}

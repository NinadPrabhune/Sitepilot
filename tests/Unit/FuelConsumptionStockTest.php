<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Material;
use App\Models\MaterialProjectStock;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Services\StockService;
use Workdo\Taskly\Entities\Project;
use Illuminate\Support\Facades\DB;

class FuelConsumptionStockTest extends TestCase
{
    use RefreshDatabase;

    private $stockService;
    private $testSite;
    private $fuelMaterial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService();
        
        // Create test site
        $this->testSite = Project::create([
            'name' => 'Test Site',
            'workspace' => 1,
        ]);

        // Create fuel material (category_id = 2)
        $this->fuelMaterial = Material::create([
            'name' => 'Diesel Fuel',
            'category_id' => 2, // Fuel category
            'unit_id' => 1,
            'price' => 85.50,
            'reorder_level' => 100,
        ]);

        // Initialize stock for the fuel material
        $this->stockService->addOpeningStock(
            $this->testSite->id,
            $this->fuelMaterial->id,
            1000, // 1000 liters
            85.50,
            'Initial stock for testing'
        );
    }

    /**
     * Test that fuel consumption properly deducts from stock
     */
    public function test_fuel_consumption_deducts_from_stock()
    {
        // Get initial stock
        $initialStock = $this->stockService->getCurrentStock($this->testSite->id, $this->fuelMaterial->id);
        $this->assertEquals(1000, $initialStock);

        // Create fuel consumption
        $consumptionData = [
            'consumption_date' => now()->format('Y-m-d'),
            'site_id' => $this->testSite->id,
            'consumption_type' => 'fuel',
            'machinery_id' => null,
            'items' => [
                [
                    'material_id' => $this->fuelMaterial->id,
                    'quantity' => 50, // 50 liters
                    'unit' => 'Liters',
                    'remarks' => 'Test consumption'
                ]
            ]
        ];

        // Simulate DailyConsumptionController@store logic
        $master = DailyConsumptionMaster::create([
            'consumption_number' => 'TEST-001',
            'consumption_date' => $consumptionData['consumption_date'],
            'site_id' => $consumptionData['site_id'],
            'consumption_type' => $consumptionData['consumption_type'],
            'created_by' => 1,
            'workspace_id' => 1,
        ]);

        // Create consumption details and stock transaction
        foreach ($consumptionData['items'] as $item) {
            // Check stock availability
            $availableStock = $this->stockService->getCurrentStock($this->testSite->id, $item['material_id']);
            $this->assertGreaterThanOrEqual($item['quantity'], $availableStock);

            // Create consumption detail
            DailyConsumptionDetails::create([
                'daily_consumption_master_id' => $master->id,
                'material_id' => $item['material_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'remarks' => $item['remarks'] ?? null,
            ]);

            // Create stock transaction
            $this->stockService->issueMaterial(
                $this->testSite->id,
                $item['material_id'],
                $item['quantity'],
                "Test fuel consumption - {$master->consumption_number}",
                'DailyConsumptionMaster',
                $master->id
            );
        }

        // Verify stock was deducted
        $finalStock = $this->stockService->getCurrentStock($this->testSite->id, $this->fuelMaterial->id);
        $this->assertEquals(950, $finalStock); // 1000 - 50 = 950

        // Verify consistency between calculation method and MaterialProjectStock
        $calculatedStock = getCurrentStockBySiteId($this->testSite->id, null, null, null, null, $this->fuelMaterial->id, false)
            ->firstWhere('material_id', $this->fuelMaterial->id);
        $actualStock = getCurrentStockBySiteId($this->testSite->id, null, null, null, null, $this->fuelMaterial->id, true)
            ->firstWhere('material_id', $this->fuelMaterial->id);

        $this->assertEquals($finalStock, $calculatedStock->total_qty);
        $this->assertEquals($finalStock, $actualStock->total_qty);
    }

    /**
     * Test that insufficient stock prevents consumption
     */
    public function test_insufficient_stock_prevents_consumption()
    {
        // Try to consume more than available
        $excessQuantity = 1500; // More than 1000 available

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        // Check stock availability should fail
        $availableStock = $this->stockService->getCurrentStock($this->testSite->id, $this->fuelMaterial->id);
        $this->assertLessThan($excessQuantity, $availableStock);

        // This should throw an exception
        $this->stockService->issueMaterial(
            $this->testSite->id,
            $this->fuelMaterial->id,
            $excessQuantity,
            'Excess consumption test',
            'Test',
            null
        );
    }

    /**
     * Test stock consistency validation
     */
    public function test_stock_consistency_validation()
    {
        // Get initial consistency report
        $discrepancies = validateStockConsistency($this->testSite->id, $this->fuelMaterial->id);
        $this->assertEmpty($discrepancies, 'Stock should be consistent initially');

        // Create some consumption
        $this->stockService->issueMaterial(
            $this->testSite->id,
            $this->fuelMaterial->id,
            100,
            'Test consumption for consistency check',
            'Test',
            null
        );

        // Check consistency again
        $discrepancies = validateStockConsistency($this->testSite->id, $this->fuelMaterial->id);
        $this->assertEmpty($discrepancies, 'Stock should remain consistent after consumption');
    }

    /**
     * Test fuel stock report generation
     */
    public function test_fuel_stock_report_generation()
    {
        // Generate fuel stock report
        $report = getFuelStockReport($this->testSite->id);

        $this->assertEquals($this->testSite->id, $report['site_id']);
        $this->assertGreaterThan(0, $report['fuel_materials_count']);
        $this->assertEquals(0, $report['discrepancies_count']); // Should be consistent initially

        // Find our test fuel material in the report
        $testMaterial = collect($report['materials'])->firstWhere('material_id', $this->fuelMaterial->id);
        $this->assertNotNull($testMaterial);
        $this->assertEquals(1000, $testMaterial['calculated_stock']);
        $this->assertEquals(1000, $testMaterial['actual_stock']);
        $this->assertTrue($testMaterial['is_consistent']);
    }
}

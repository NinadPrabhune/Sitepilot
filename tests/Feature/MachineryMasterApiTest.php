<?php

namespace Tests\Feature;

use App\Models\Machinery;
use App\Models\MachineryCategory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MachineryMasterApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected MachineryCategory $category;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['machinery manage', 'machinery create', 'machinery edit', 'machinery delete', 'machinery show']);
        
        $this->category = MachineryCategory::factory()->create();
        $this->supplier = Supplier::factory()->create();
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_list_machineries_with_new_fields()
    {
        // Create both owned and rental machinery
        Machinery::factory()->create([
            'owned_by' => 'owned',
            'rate_type' => null,
            'minimum_billing_hours' => null,
        ]);
        
        Machinery::factory()->create([
            'owned_by' => 'rental',
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
        ]);

        $response = $this->getJson('/api/machineries');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'machine_id',
                            'name',
                            'category' => ['id', 'name'],
                            'model_number',
                            'manufacturer',
                            'purchase_date',
                            'capacity',
                            'maintenance_schedule',
                            'remarks',
                            'description',
                            'vehicle_number',
                            'owned_by',
                            'supplier',
                            'rate',
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
                            'operational_status',
                            'site',
                            'workspace',
                            'status',
                            'created_by',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_create_owned_machinery_with_all_fields()
    {
        Storage::fake('public');

        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'model_number' => 'EXC-2000',
            'manufacturer' => 'JCB',
            'purchase_date' => '2024-01-15',
            'capacity' => '10 tons',
            'maintenance_schedule' => '2024-06-15',
            'remarks' => 'Regular maintenance required',
            'description' => 'Heavy duty excavator',
            'vehicle_number' => 'MH-01-AB-1234',
            'owned_by' => 'owned',
            'rate' => 1500.00,
            'purchase_value' => 2500000.00,
            'insurance_due_date' => '2024-12-31',
            'puc_due_date' => '2024-11-30',
            'fitness_due_date' => '2024-10-31',
            'last_service_date' => '2024-05-01',
            'operational_status' => 'active',
            'site_id' => 1,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
            'ownership_documents_file' => UploadedFile::fake()->create('ownership.pdf', 100, 'application/pdf'),
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'machine_id',
                        'name',
                        'owned_by',
                        'rate',
                        'purchase_value',
                        'ownership_documents_file' => [
                            'filename',
                            'url'
                        ]
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('machineries', [
            'name' => $data['name'],
            'owned_by' => 'owned',
            'supplier_id' => null,
            'rate' => 1500.00,
            'purchase_value' => 2500000.00,
        ]);
    }

    /** @test */
    public function it_can_create_rental_machinery_with_all_fields()
    {
        Storage::fake('public');

        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'model_number' => 'CRN-3000',
            'manufacturer' => 'Caterpillar',
            'purchase_date' => '2024-02-15',
            'capacity' => '20 tons',
            'vehicle_number' => 'MH-02-CD-5678',
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate' => 2000.00,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
            'diesel_by_company' => false,
            'operator_by_supplier' => true,
            'number_of_operators' => 2,
            'operational_status' => 'active',
            'site_id' => 1,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
            'rental_agreement_file' => UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf'),
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'machine_id',
                        'name',
                        'owned_by',
                        'supplier' => ['id', 'name'],
                        'rate',
                        'rate_type',
                        'minimum_billing_hours',
                        'diesel_by_company',
                        'operator_by_supplier',
                        'number_of_operators',
                        'rental_agreement_file' => [
                            'filename',
                            'url'
                        ]
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('machineries', [
            'name' => $data['name'],
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate' => 2000.00,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
            'diesel_by_company' => false,
            'operator_by_supplier' => true,
            'number_of_operators' => 2,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_machinery_creation()
    {
        $response = $this->postJson('/api/machineries', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'name',
                    'category_id',
                    'vehicle_number',
                    'owned_by',
                    'rate',
                    'created_by',
                    'workspace_id'
                ]);
    }

    /** @test */
    public function it_validates_conditional_fields_for_rental_machinery()
    {
        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'vehicle_number' => 'MH-01-AB-1234',
            'owned_by' => 'rental',
            'rate' => 1500.00,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'supplier_id',
                    'rate_type',
                    'minimum_billing_hours'
                ]);
    }

    /** @test */
    public function it_validates_conditional_fields_for_owned_machinery()
    {
        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'vehicle_number' => 'MH-01-AB-1234',
            'owned_by' => 'owned',
            'rate' => 1500.00,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'purchase_value',
                    'insurance_due_date',
                    'puc_due_date',
                    'fitness_due_date',
                    'last_service_date'
                ]);
    }

    /** @test */
    public function it_prevents_supplier_id_for_owned_machinery()
    {
        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'vehicle_number' => 'MH-01-AB-1234',
            'owned_by' => 'owned',
            'supplier_id' => $this->supplier->id,
            'rate' => 1500.00,
            'purchase_value' => 2500000.00,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['supplier_id']);
    }

    /** @test */
    public function it_can_update_machinery_and_change_ownership_type()
    {
        $machinery = Machinery::factory()->create([
            'owned_by' => 'owned',
            'purchase_value' => 2500000.00,
        ]);

        $updateData = [
            'name' => 'Updated Machinery Name',
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate' => 2000.00,
            'rate_type' => 'daily',
            'minimum_billing_hours' => 8,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
        ];

        $response = $this->putJson("/api/machineries/{$machinery->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('machineries', [
            'id' => $machinery->id,
            'name' => 'Updated Machinery Name',
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate' => 2000.00,
            'rate_type' => 'daily',
        ]);
    }

    /** @test */
    public function it_can_show_single_machinery_with_all_fields()
    {
        $machinery = Machinery::factory()->create([
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
        ]);

        $response = $this->getJson("/api/machineries/{$machinery->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'machine_id',
                        'name',
                        'owned_by',
                        'supplier' => ['id', 'name'],
                        'rate',
                        'rate_type',
                        'minimum_billing_hours',
                        'diesel_by_company',
                        'operator_by_supplier',
                        'number_of_operators',
                        'operational_status',
                        'site',
                        'workspace',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_can_delete_machinery()
    {
        $machinery = Machinery::factory()->create();

        $response = $this->deleteJson("/api/machineries/{$machinery->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 1,
                    'message' => 'Machinery deleted successfully'
                ]);

        $this->assertSoftDeleted('machineries', ['id' => $machinery->id]);
    }

    /** @test */
    public function it_handles_file_uploads_correctly()
    {
        Storage::fake('public');

        $machinery = Machinery::factory()->create(['owned_by' => 'rental']);

        $file = UploadedFile::fake()->create('agreement.pdf', 100, 'application/pdf');
        
        $response = $this->putJson("/api/machineries/{$machinery->id}", [
            'name' => $machinery->name,
            'owned_by' => 'rental',
            'rate' => $machinery->rate,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
            'rental_agreement_file' => $file,
        ]);

        $response->assertStatus(200);
        
        // Check file was stored
        Storage::disk('public')->assertExists('machinery_documents/' . $machinery->fresh()->rental_agreement_file);
    }

    /** @test */
    public function it_validates_file_types_and_sizes()
    {
        $data = [
            'name' => $this->faker->company,
            'category_id' => $this->category->id,
            'vehicle_number' => 'MH-01-AB-1234',
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'rate' => 1500.00,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
            'created_by' => $this->user->id,
            'workspace_id' => $this->user->workspace_id,
            'rental_agreement_file' => UploadedFile::fake()->create('agreement.exe', 100, 'application/x-executable'),
        ];

        $response = $this->postJson('/api/machineries', $data);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['rental_agreement_file']);
    }
}

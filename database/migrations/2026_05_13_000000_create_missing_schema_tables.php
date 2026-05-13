<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('item_categories')) {
            Schema::create('item_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('site_id')->nullable()->default(null);
                $table->integer('created_by')->default(0);
                $table->integer('workspace_id')->default(0);
                $table->string('status')->default('0');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku')->unique();
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('unit_id');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('reorder_level')->default(10);
                $table->string('status')->default('active');
                $table->string('image')->nullable();
                $table->integer('site_id')->nullable()->default(null);
                $table->integer('created_by')->default(0);
                $table->integer('workspace_id')->default(0);
                $table->timestamps();

                if (Schema::hasTable('item_categories')) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('item_categories')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('units')) {
                    $table->foreign('unit_id')
                        ->references('id')
                        ->on('units')
                        ->onDelete('cascade');
                }
            });
        }

        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('calculation_versions')) {
            Schema::create('calculation_versions', function (Blueprint $table) {
                $table->id();
                $table->string('version', 20)->unique();
                $table->string('type', 50);
                $table->text('description');
                $table->json('rules')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('effective_from')->useCurrent();
                $table->timestamp('effective_to')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['type', 'is_active']);
                $table->index('effective_from');

                if (Schema::hasTable('users')) {
                    $table->foreign('created_by')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                }
            });
        }

        if (!Schema::hasTable('monthly_closures')) {
            Schema::create('monthly_closures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedBigInteger('closed_by');
                $table->timestamp('closed_at')->useCurrent();
                $table->text('remarks')->nullable();

                $table->unique(['workspace_id', 'site_id', 'year', 'month']);
                $table->index(['workspace_id', 'year', 'month']);
                $table->index(['site_id', 'year', 'month']);
                $table->index('closed_at');

                if (Schema::hasTable('workspaces')) {
                    $table->foreign('workspace_id')
                        ->references('id')
                        ->on('workspaces')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('sites')) {
                    $table->foreign('site_id')
                        ->references('id')
                        ->on('sites')
                        ->onDelete('cascade');
                }

                if (Schema::hasTable('users')) {
                    $table->foreign('closed_by')
                        ->references('id')
                        ->on('users')
                        ->onDelete('restrict');
                }
            });
        }

        if (!Schema::hasTable('assets_tools_and_equipment_transfer')) {
            Schema::create('assets_tools_and_equipment_transfer', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->unsignedBigInteger('from_site_id')->nullable();
                $table->unsignedBigInteger('to_site_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->unsignedBigInteger('transferred_by')->nullable();
                $table->timestamp('transferred_at')->useCurrent();
                $table->timestamps();

                if (Schema::hasTable('assets_tools_and_equipment')) {
                    $table->foreign('asset_id')
                        ->references('id')
                        ->on('assets_tools_and_equipment')
                        ->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets_tools_and_equipment_transfer');
        Schema::dropIfExists('monthly_closures');
        Schema::dropIfExists('calculation_versions');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }
};

<?php
// database/migrations/xxxx_xx_xx_create_device_tokens_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->nullable();     // 'android', 'ios', 'web'
            $table->string('device_name')->nullable();  // optional: model or label
            $table->string('app_version')->nullable();  // optional
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('device_tokens');
    }
};

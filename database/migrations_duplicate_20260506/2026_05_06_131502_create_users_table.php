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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('email', 255)->unique('email');
            $table->enum('escalation_level', ['none', 'supervisor', 'manager', 'restricted'])->default('none')->index('idx_user_escalation_level');
            $table->string('trust_level', 20)->default('standard')->index('idx_user_trust_level');
            $table->date('trust_review_date')->nullable();
            $table->timestamp('escalation_locked_until')->nullable();
            $table->unsignedBigInteger('escalation_locked_by')->nullable()->index('users_escalation_locked_by_foreign');
            $table->string('mobile_no', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->nullable();
            $table->string('password_text', 255)->nullable();
            $table->rememberToken();
            $table->string('type', 255)->default('company');
            $table->boolean('active_status')->default(false);
            $table->integer('active_workspace')->default(0);
            $table->integer('active_project')->default(0);
            $table->string('avatar', 255)->default('uploads/users-avatar/avatar.png');
            $table->integer('requested_plan')->default(0);
            $table->boolean('dark_mode')->default(false);
            $table->string('lang')->default('en');
            $table->string('messenger_color', 255)->default('#2180f3');
            $table->integer('active_plan')->default(0);
            $table->longText('active_module')->nullable();
            $table->date('plan_expire_date')->nullable();
            $table->string('billing_type', 255)->nullable();
            $table->integer('total_user')->default(-1);
            $table->integer('seeder_run')->default(0);
            $table->integer('is_enable_login')->default(1);
            $table->string('default_pipeline', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->integer('is_disable')->default(1);
            $table->string('trial_expire_date', 255)->nullable();
            $table->string('is_trial_done', 255)->default('0');
            $table->string('total_workspace', 255)->default('-1');
            $table->integer('referral_code')->default(0);
            $table->integer('used_referral_code')->default(0);
            $table->integer('commission_amount')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->integer('site_id')->nullable()->default(0);
            $table->integer('created_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

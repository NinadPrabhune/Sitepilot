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
        Schema::create('project_files_new', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->index('project_files_new_user_id_foreign');
            $table->string('name')->index()->comment('File or folder name');
            $table->string('file_path')->nullable()->comment('Full storage path');
            $table->string('folder_path')->nullable()->index()->comment('Parent folder path');
            $table->boolean('is_folder')->default(false)->index()->comment('Is this a directory?');
            $table->string('mime_type')->nullable()->comment('MIME type of file');
            $table->bigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->string('original_name')->nullable()->comment('Original uploaded filename');
            $table->string('disk')->default('local')->comment('Storage disk name');
            $table->text('description')->nullable()->comment('File/folder description');
            $table->string('tags')->nullable()->comment('Comma-separated tags');
            $table->timestamp('downloaded_at')->nullable()->comment('Last download time');
            $table->integer('download_count')->default(0)->comment('Total downloads');
            $table->boolean('is_public')->default(false)->comment('Public access flag');
            $table->boolean('is_archived')->default(false)->index()->comment('Archived files');
            $table->softDeletes();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();

            $table->fullText(['name', 'description']);
            $table->index(['project_id', 'folder_path', 'is_folder']);
            $table->index(['project_id', 'is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_files_new');
    }
};

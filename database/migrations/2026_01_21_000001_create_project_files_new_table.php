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
            $table->id();
            $table->foreignId('project_id')
                  ->constrained('projects')
                  ->onDelete('cascade')
                  ->comment('Project ID');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('set null')
                  ->nullable()
                  ->comment('User who uploaded/created the file');
            
            // File information
            $table->string('name')->index()->comment('File or folder name');
            $table->string('file_path')->nullable()->comment('Full storage path');
            $table->string('folder_path')->nullable()->index()->comment('Parent folder path');
            $table->boolean('is_folder')->default(false)->index()->comment('Is this a directory?');
            
            // File metadata (null for folders)
            $table->string('mime_type')->nullable()->comment('MIME type of file');
            $table->bigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->string('original_name')->nullable()->comment('Original uploaded filename');
            
            // Storage information
            $table->string('disk')->default('local')->comment('Storage disk name');
            
            // Description and tags
            $table->text('description')->nullable()->comment('File/folder description');
            $table->string('tags')->nullable()->comment('Comma-separated tags');
            
            // Audit trail
            $table->timestamp('downloaded_at')->nullable()->comment('Last download time');
            $table->integer('download_count')->default(0)->comment('Total downloads');
            
            // Status and visibility
            $table->boolean('is_public')->default(false)->comment('Public access flag');
            $table->boolean('is_archived')->default(false)->index()->comment('Archived files');
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index(['project_id', 'folder_path', 'is_folder']);
            $table->index(['project_id', 'is_archived']);
            $table->index('created_at');
        });

        // Create index for faster queries
        Schema::table('project_files_new', function (Blueprint $table) {
            $table->fullText(['name', 'description'])->comment('Full text search index');
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

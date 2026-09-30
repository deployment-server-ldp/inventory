<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->enum('inventory_type', ['cnc', 'imported']);
            $table->string('sku', 60)->unique();
            $table->string('name', 191);
            $table->foreignId('category_id')->constrained('part_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('specification', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('final_operation_id')->nullable()->constrained('operations')->restrictOnDelete();
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->decimal('opening_stock', 14, 3)->default(0);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_type', 'is_active']);
            $table->index('name');
            $table->index('part_number');
        });

        Schema::create('spare_part_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('original_name')->nullable();
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['spare_part_id', 'is_primary']);
        });

        Schema::create('spare_part_machinery_model', function (Blueprint $table) {
            $table->foreignId('spare_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_model_id')->constrained()->cascadeOnDelete();
            $table->primary(['spare_part_id', 'machinery_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_machinery_model');
        Schema::dropIfExists('spare_part_images');
        Schema::dropIfExists('spare_parts');
    }
};

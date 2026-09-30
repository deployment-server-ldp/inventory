<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->string('name', 191);
            $table->foreignId('machinery_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer', 150)->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned')->index();
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('machine_assembly_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_assembly_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 14, 3);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
            $table->unique(['machine_assembly_id', 'spare_part_id']);
        });

        Schema::create('imported_inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->date('transaction_date');
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('part_name', 191);
            $table->string('part_sku', 60);
            $table->string('category_name', 100)->nullable();
            $table->string('unit_name', 50)->nullable();
            $table->string('specification', 255)->nullable();
            $table->enum('type', ['opening', 'in', 'out', 'adjustment_in', 'adjustment_out', 'reversal']);
            $table->decimal('quantity_in', 14, 3)->default(0);
            $table->decimal('quantity_out', 14, 3)->default(0);
            $table->decimal('balance_after', 14, 3);
            // IN fields
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 150)->nullable();
            $table->string('document_reference', 100)->nullable();
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            // OUT fields
            $table->foreignId('machinery_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose', 255)->nullable();
            $table->string('collected_by', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->foreignId('machine_assembly_id')->nullable()->constrained()->restrictOnDelete();
            // links
            $table->foreignId('stock_adjustment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('imported_inventory_transactions')->restrictOnDelete();
            $table->boolean('is_reversed')->default(false);
            $table->text('remarks')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['spare_part_id', 'transaction_date'], 'iit_part_date_idx');
            $table->index(['type', 'transaction_date'], 'iit_type_date_idx');
            $table->index(['machine_assembly_id', 'type'], 'iit_assembly_type_idx');
            $table->index(['machinery_model_id', 'type'], 'iit_model_type_idx');
            $table->index('document_reference', 'iit_docref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_inventory_transactions');
        Schema::dropIfExists('machine_assembly_items');
        Schema::dropIfExists('machine_assemblies');
    }
};

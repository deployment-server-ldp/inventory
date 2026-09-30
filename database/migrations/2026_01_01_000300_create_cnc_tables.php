<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnc_production_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->date('production_date');
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('part_name', 191);
            $table->string('part_sku', 60);
            $table->foreignId('machinery_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('operation_id')->constrained()->restrictOnDelete();
            $table->string('operation_name', 50);
            $table->unsignedSmallInteger('operation_sequence');
            $table->boolean('is_final_operation')->default(false);
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->foreignId('operator_id')->constrained()->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->enum('status', ['running', 'completed', 'cancelled'])->default('completed');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['production_date', 'machine_id']);
            $table->index(['machine_id', 'status']);
            $table->index(['spare_part_id', 'operation_id', 'status']);
            $table->index(['operator_id', 'production_date']);
            $table->index(['status', 'production_date']);
        });

        Schema::create('cnc_production_completions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->date('completion_date');
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('part_name', 191);
            $table->string('part_sku', 60);
            $table->decimal('quantity_inspected', 14, 3);
            $table->decimal('quantity_accepted', 14, 3);
            $table->decimal('quantity_rejected', 14, 3)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'reversed'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('decision_notes', 500)->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 500)->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['spare_part_id', 'status']);
            $table->index(['status', 'completion_date']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->enum('inventory_type', ['cnc', 'imported']);
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->date('adjustment_date');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 14, 3);
            $table->string('reason', 500);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_type', 'adjustment_date']);
        });

        Schema::create('cnc_inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->date('transaction_date');
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('part_name', 191);
            $table->string('part_sku', 60);
            $table->string('unit_name', 50)->nullable();
            $table->enum('type', ['opening', 'production_receipt', 'issue', 'adjustment_in', 'adjustment_out', 'reversal']);
            $table->decimal('quantity_in', 14, 3)->default(0);
            $table->decimal('quantity_out', 14, 3)->default(0);
            $table->decimal('balance_after', 14, 3);
            $table->foreignId('completion_id')->nullable()->constrained('cnc_production_completions')->restrictOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('cnc_inventory_transactions')->restrictOnDelete();
            $table->boolean('is_reversed')->default(false);
            $table->string('issued_to', 150)->nullable();
            $table->foreignId('machinery_model_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['spare_part_id', 'transaction_date']);
            $table->index(['type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnc_inventory_transactions');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('cnc_production_completions');
        Schema::dropIfExists('cnc_production_records');
    }
};

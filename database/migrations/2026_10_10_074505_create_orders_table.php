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
        Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique();
        $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
        $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
        $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
        $table->string('status')->default('draft')->index();
        $table->string('priority')->default('normal')->index();
        $table->date('order_date');
        $table->decimal('subtotal',12,2)->default(0);
        $table->decimal('discount',12,2)->default(0);
        $table->decimal('tax',12,2)->default(0);
        $table->decimal('total',12,2)->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->index(['assigned_to', 'status']);
        $table->index(['status', 'order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['assigned_to']);
        });
        Schema::dropIfExists('orders');
    }
};

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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('period_start');

            $table->date('period_end');

            $table->decimal('base_amount', 12, 2);

            $table->unsignedBigInteger('included_units');

            $table->unsignedBigInteger('used_units');

            $table->unsignedBigInteger('overage_units');

            $table->decimal('overage_amount', 12, 2);

            $table->decimal('total_amount', 12, 2);

            $table->string('status')->default('pending');

            $table->timestamp('issued_at')->nullable();

            $table->timestamps();

            $table->unique([
                'subscription_id',
                'period_start',
                'period_end'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

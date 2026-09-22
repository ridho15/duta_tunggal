<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_transaction_archives', function (Blueprint $table) {
            $table->id();
            $table->string('source_name', 50);
            $table->string('table_name', 100);
            $table->string('row_kind', 50);
            $table->unsignedBigInteger('legacy_id');
            $table->string('transaction_type', 50)->nullable();
            $table->string('parent_table_name', 100)->nullable();
            $table->unsignedBigInteger('parent_legacy_id')->nullable();
            $table->string('document_number', 150)->nullable();
            $table->string('reference_number', 150)->nullable();
            $table->dateTime('document_date')->nullable();
            $table->string('status', 100)->nullable();
            $table->string('payment_status', 100)->nullable();
            $table->string('delivery_status', 100)->nullable();
            $table->string('receive_status', 100)->nullable();
            $table->string('party_type', 30)->nullable();
            $table->unsignedBigInteger('party_legacy_id')->nullable();
            $table->string('party_code', 100)->nullable();
            $table->string('party_name', 255)->nullable();
            $table->unsignedBigInteger('product_legacy_id')->nullable();
            $table->string('product_code', 150)->nullable();
            $table->string('location_type', 50)->nullable();
            $table->unsignedBigInteger('location_legacy_id')->nullable();
            $table->string('location_name', 255)->nullable();
            $table->string('origin_type', 50)->nullable();
            $table->unsignedBigInteger('origin_legacy_id')->nullable();
            $table->string('origin_name', 255)->nullable();
            $table->string('dest_type', 50)->nullable();
            $table->unsignedBigInteger('dest_legacy_id')->nullable();
            $table->string('dest_name', 255)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->decimal('quantity', 18, 2)->nullable();
            $table->decimal('processed_quantity', 18, 2)->nullable();
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->nullable();
            $table->decimal('cost_amount', 18, 2)->nullable();
            $table->longText('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['source_name', 'table_name', 'legacy_id'], 'legacy_tx_archive_unique');
            $table->index(['source_name', 'transaction_type'], 'legacy_tx_archive_source_type_idx');
            $table->index(['table_name', 'row_kind'], 'legacy_tx_archive_table_kind_idx');
            $table->index(['parent_table_name', 'parent_legacy_id'], 'legacy_tx_archive_parent_idx');
            $table->index('document_number', 'legacy_tx_archive_document_idx');
            $table->index('party_code', 'legacy_tx_archive_party_code_idx');
            $table->index('product_code', 'legacy_tx_archive_product_code_idx');
            $table->index('document_date', 'legacy_tx_archive_document_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_transaction_archives');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_orders', function (Blueprint $row) {
            $row->string('fulfillment_order_no')->nullable();
            $row->string('shop_name')->nullable();
            $row->dateTime('created_time')->nullable();
            $row->dateTime('estimated_inbound_time')->nullable();
            $row->dateTime('inbounded_time')->nullable();
            $row->string('fulfillment_sku')->nullable();
            $row->string('seller_sku')->nullable();
            $row->string('product_name')->nullable();
            $row->string('inbound_warehouse')->nullable();
            $row->string('reservation_order')->nullable();
            $row->string('sku_status')->nullable();
            $row->string('io_status')->nullable();
            $row->string('cainiao_consolidation_service')->nullable();
            $row->integer('items_requested')->default(0);
            $row->integer('items_inbounded_good')->default(0);
            $row->integer('lgf_quantity')->default(0);
            $row->string('lgf_status')->nullable();
            $row->dateTime('lgf_date')->nullable();
            $row->string('rep_planning_order_id')->nullable();
            $row->integer('rep_order_quantity')->default(0);
            $row->dateTime('rep_planning_order_date')->nullable();
            $row->string('alert')->nullable();
            $row->text('alert_detail')->nullable();
            $row->integer('items_inbounded_damaged')->default(0);
            $row->integer('items_inbounded_expired')->default(0);
            $row->decimal('cogs', 15, 2)->nullable();
            $row->string('cogs_currency', 10)->nullable();
            $row->text('seller_comment')->nullable();
            $row->text('seller_address_details')->nullable();
            $row->string('vas_needed')->nullable();
            $row->text('vas_instruction')->nullable();
            $row->string('vas_order')->nullable();
            $row->string('ontime')->nullable();
            $row->string('lmo_seller')->nullable();
            $row->string('exception')->nullable();
            $row->string('temperature')->nullable();
            $row->string('product_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inbound_orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_order_no', 'shop_name', 'created_time', 'estimated_inbound_time',
                'inbounded_time', 'fulfillment_sku', 'seller_sku', 'product_name',
                'inbound_warehouse', 'reservation_order', 'sku_status',
                'io_status', 'cainiao_consolidation_service', 'items_requested',
                'items_inbounded_good', 'lgf_quantity', 'lgf_status', 'lgf_date',
                'rep_planning_order_id', 'rep_order_quantity', 'rep_planning_order_date',
                'alert', 'alert_detail', 'items_inbounded_damaged', 'items_inbounded_expired',
                'cogs', 'cogs_currency', 'seller_comment', 'seller_address_details',
                'vas_needed', 'vas_instruction', 'vas_order', 'ontime', 'lmo_seller',
                'exception', 'temperature', 'product_type'
            ]);
        });
    }
};

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
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('prescription')->nullable()->after('variant');
            $table->foreignId('parent_item_id')->nullable()->after('prescription')->constrained('order_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropColumn(['prescription', 'parent_item_id']);
        });
    }
};

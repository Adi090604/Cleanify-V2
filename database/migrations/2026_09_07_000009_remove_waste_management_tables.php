<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop child tables first so the Waste Management foreign keys remain valid.
        Schema::dropIfExists('showcases');
        Schema::dropIfExists('recycled_products');
        Schema::dropIfExists('recyclable_materials');
        Schema::dropIfExists('waste_segregations');
        Schema::dropIfExists('collection_records');
    }

    public function down(): void
    {
        throw new \LogicException('Removing Waste Management tables is intentionally irreversible because it deletes feature-specific data.');
    }
};

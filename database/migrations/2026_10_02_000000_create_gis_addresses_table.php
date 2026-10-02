<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('house_number')->nullable()->index();
            $table->text('pre_directional')->nullable();
            $table->text('street_name')->nullable();
            $table->text('suffix')->nullable();
            $table->text('secondary_number')->nullable();
            $table->text('full_address')->nullable();
            $table->string('street_key')->nullable()->index();
            $table->string('street_key_loose')->nullable()->index();
            $table->string('street_name_key')->nullable()->index();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->text('location_id')->nullable();
            $table->text('is_built')->nullable();
            $table->text('address_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_addresses');
    }
};

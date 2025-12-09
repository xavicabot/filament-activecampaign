<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activecampaign_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ac_id')->unique();
            $table->string('name');
            $table->string('type')->nullable();       // text, textarea, etc.
            $table->string('field_type')->nullable(); // text, dropdown, etc.
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activecampaign_fields');
    }
};

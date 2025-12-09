<?php
// database/migrations/2025_01_01_000400_create_activecampaign_automations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activecampaign_automations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('event'); // libre: user.registered, wallet.first_deposit, etc.
            $table->boolean('is_active')->default(true);

            $table->string('list_ac_id')->nullable();  // lista opcional
            $table->json('tag_ac_ids')->nullable();    // tags opcionales
            $table->json('fields')->nullable();        // [{field_ac_id, value_template}]
            $table->json('system_fields')->nullable();  // system fields

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activecampaign_automations');
    }
};

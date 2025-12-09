<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activecampaign_automation_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('automation_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('event');
            $table->boolean('success')->default(true);

            $table->json('context')->nullable();
            $table->json('payload')->nullable(); // plan de ejecución (listas, tags, fields, system_fields)

            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activecampaign_automation_logs');
    }
};

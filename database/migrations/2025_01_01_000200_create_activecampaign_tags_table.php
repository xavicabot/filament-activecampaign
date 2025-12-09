<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activecampaign_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ac_id')->unique();
            $table->string('name');
            $table->string('tag_type')->nullable(); // contact, template, etc.
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activecampaign_tags');
    }
};

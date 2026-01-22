<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'activecampaign_contact_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('activecampaign_contact_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'activecampaign_contact_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activecampaign_contact_id');
        });
    }
};

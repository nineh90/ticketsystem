<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wann jemand das Dashboard zuletzt angesehen hat.
 *
 * Damit bekommt der Ereignisstrom eine Wasserlinie: alles darüber ist neu
 * seit dem letzten Besuch und wird markiert. Ohne diese Marke müsste man
 * jeden Morgen selbst herausfinden, wo man gestern aufgehört hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dashboard_gesehen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_gesehen_at');
        });
    }
};

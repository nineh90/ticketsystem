<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Die laufende Fassung, die der Kunde sich ansehen kann. Bewusst
            // am Projekt und nicht am Kunden: ein Kunde hat mehrere Projekte
            // und jedes seine eigene Adresse.
            $table->string('demo_url')->nullable()->after('beschreibung');

            // Was der Kunde in seinem Bereich über den Stand liest. Getrennt
            // von "beschreibung", weil die intern ist und dort auch Notizen
            // stehen, die den Kunden nichts angehen.
            $table->text('kunden_info')->nullable()->after('demo_url');

            // Sichtbar, sofern nicht ausdrücklich anders gesagt: ein Projekt
            // gehört dem Kunden, dessen Namen es trägt. Der Schalter ist für
            // die Ausnahme da — etwa ein Angebot, das noch nicht besprochen
            // ist, oder ein Umbau, der erst zum Termin gezeigt werden soll.
            $table->boolean('kunden_sichtbar')->default(true)->after('kunden_info');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['demo_url', 'kunden_info', 'kunden_sichtbar']);
        });
    }
};

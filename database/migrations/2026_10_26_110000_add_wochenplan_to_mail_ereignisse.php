<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Meine Woche" für alle internen Zugänge anhaken, die schon eine Auswahl
 * gespeichert haben.
 *
 * Anders als bei den Planer-Themen zuvor (README, "Die neuen Mail-Themen"):
 * die Wochenmail soll ausdrücklich jeder aus der Crew bekommen. Ohne diese
 * Zeile bekäme sie nur, wer seine Auswahl nie angefasst hat (null = alles),
 * und das ist heute niemand. Der Hauptschalter bleibt unberührt — wer keine
 * Mails will, bekommt auch diese nicht. Abwählen geht wie immer unter
 * "Mein Zugang".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('rolle', '!=', 'kunde')
            ->whereNotNull('mail_ereignisse')
            ->get(['id', 'mail_ereignisse'])
            ->each(function (object $nutzer) {
                $auswahl = json_decode($nutzer->mail_ereignisse, true) ?? [];

                if (in_array('wochenplan', $auswahl, true)) {
                    return;
                }

                DB::table('users')->where('id', $nutzer->id)->update([
                    'mail_ereignisse' => json_encode([...$auswahl, 'wochenplan']),
                ]);
            });
    }

    public function down(): void
    {
        // Nichts zurückzunehmen: wer das Thema danach behalten oder
        // abgewählt hat, hat das selbst entschieden.
    }
};

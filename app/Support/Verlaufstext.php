<?php

namespace App\Support;

use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Übersetzt einen Protokolleintrag in etwas, das ein Mensch lesen kann.
 *
 * Ohne das stünde überall "ticket_status_id: 2 → 5" — technisch korrekt und
 * im Alltag wertlos. Die Übersetzung lag ursprünglich im Verlaufs-Relation-
 * Manager des Tickets; seit derselbe Text auch im Dashboard erscheint, steht
 * sie hier. Zwei Stellen, die dieselbe Änderung unterschiedlich benennen,
 * wären genau die Art von Unterschied, über die man später stolpert.
 */
class Verlaufstext
{
    /**
     * Eine Zeile je geändertem Feld.
     *
     * @return array<int, string>
     */
    public static function zeilen(Activity $eintrag): array
    {
        if ($eintrag->event === 'created') {
            return ['Ticket angelegt'];
        }

        // In activitylog v5 stehen die Feldänderungen in attribute_changes,
        // nicht mehr in properties — properties bleibt hier leer. Nachgesehen,
        // nicht angenommen: mit dem alten Zugriff zeigte der Verlauf für jede
        // Änderung nur einen Strich an.
        $aenderungen = $eintrag->attribute_changes ?? [];

        $alt = $aenderungen['old'] ?? [];
        $neu = $aenderungen['attributes'] ?? [];

        $zeilen = [];

        foreach ($neu as $feld => $wert) {
            $vorher = $alt[$feld] ?? null;

            if ($vorher === $wert) {
                continue;
            }

            $zeilen[] = self::feldname($feld).': '
                .self::wert($feld, $vorher).' → '.self::wert($feld, $wert);
        }

        return $zeilen ?: ['—'];
    }

    /**
     * Eine einzelne Zeile als Kurzfassung — für den Ereignisstrom, wo je
     * Eintrag nur eine Zeile Platz hat.
     */
    public static function kurz(Activity $eintrag): string
    {
        $zeilen = self::zeilen($eintrag);

        if (count($zeilen) <= 1) {
            return $zeilen[0] ?? '—';
        }

        return $zeilen[0].' (+'.(count($zeilen) - 1).' weitere)';
    }

    public static function feldname(string $feld): string
    {
        return match ($feld) {
            'titel' => 'Titel',
            'ticket_status_id' => 'Status',
            'prioritaet' => 'Priorität',
            'assigned_to' => 'Zuständig',
            'faellig_am' => 'Fällig',
            default => $feld,
        };
    }

    public static function wert(string $feld, mixed $wert): string
    {
        if ($wert === null || $wert === '') {
            return '—';
        }

        return match ($feld) {
            'ticket_status_id' => self::stadium((int) $wert),
            'assigned_to' => self::nutzer((int) $wert),
            'faellig_am' => Carbon::parse($wert)->format('d.m.Y'),
            default => (string) $wert,
        };
    }

    /**
     * Stadien und Nutzer werden gemerkt: im Dashboard stehen zwanzig
     * Ereignisse untereinander, die überwiegend dieselbe Handvoll Stadien und
     * Personen nennen. Ohne den Merker wäre das je Zeile eine eigene Abfrage.
     *
     * @var array<int, string>
     */
    private static array $stadien = [];

    /** @var array<int, string> */
    private static array $nutzer = [];

    private static function stadium(int $id): string
    {
        return self::$stadien[$id] ??= TicketStatus::find($id)?->name ?? (string) $id;
    }

    private static function nutzer(int $id): string
    {
        return self::$nutzer[$id] ??= User::find($id)?->name ?? (string) $id;
    }
}

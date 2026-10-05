<?php

namespace App\Filament\Concerns;

use App\Enums\Quelle;
use App\Models\Project;
use App\Models\TicketStatus;
use Illuminate\Validation\ValidationException;

/**
 * Was ein Anliegen eines Kunden außer seinen vier Feldern noch braucht.
 *
 * Das ist der eigentliche Schutz beim Melden: der Kunde füllt vier Felder
 * aus, und die übrigen — Kunde, Herkunft, Stadium, Urheber — ergeben sich,
 * statt aus dem Browser zu kommen. Käme etwa project_id ungeprüft aus dem
 * Formular, ließe sich mit einer geänderten Anfrage ein Anliegen in einem
 * fremden Projekt anlegen.
 *
 * Als Trait, seit es zwei Formulare gibt: die Seite "Neues Anliegen" und die
 * Karte auf der Übersicht (NID-23). Stünde die Prüfung zweimal da, wäre eine
 * der beiden irgendwann die schwächere — und genau die fände jemand.
 */
trait LegtAnliegenAn
{
    /**
     * @param  array<string, mixed>  $data  die Formulardaten, ohne "dateien"
     * @return array<string, mixed>
     */
    protected function anliegenDaten(array $data): array
    {
        $nutzer = auth()->user();

        // Gehört das Projekt wirklich diesem Kunden? Die Auswahlliste zeigt
        // nur passende, aber eine Auswahlliste ist keine Prüfung.
        $projekt = Project::query()
            ->sichtbarFuer($nutzer)
            ->whereKey($data['project_id'] ?? null)
            ->first();

        if ($projekt === null) {
            throw ValidationException::withMessages([
                'data.project_id' => 'Bitte wählen Sie eines Ihrer Projekte aus.',
            ]);
        }

        $stadium = TicketStatus::standard();

        if ($stadium === null) {
            throw ValidationException::withMessages([
                'data.titel' => 'Das System nimmt gerade keine Anliegen an. Bitte melden Sie sich direkt bei uns.',
            ]);
        }

        return [
            ...$data,
            'project_id' => $projekt->getKey(),
            'customer_id' => $projekt->customer_id,
            // Das erste Stadium der Reihenfolge — bei uns "Backlog". Neue
            // Anliegen kommen bewusst dort an und nicht in "Offen": erst
            // sehen wir sie an, dann werden sie eingeplant.
            'ticket_status_id' => $stadium->getKey(),
            'quelle' => Quelle::Kunde,
            'created_by' => $nutzer->getKey(),
            // Priorität bleibt auf dem Standardwert des Models. Wer sie
            // festlegt, sind wir.
        ];
    }
}

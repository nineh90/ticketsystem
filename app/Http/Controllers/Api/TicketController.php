<?php

namespace App\Http\Controllers\Api;

use App\Enums\Prioritaet;
use App\Enums\Quelle;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ApiToken;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Schnittstelle für n8n und die Website.
 *
 * Beide laufen auf derselben Maschine und im selben Docker-Netz
 * (n8n_default), erreichen das Ticketsystem also intern unter
 * http://ticketsystem/api/v1/… ohne Umweg über das öffentliche Internet.
 */
class TicketController extends Controller
{
    /**
     * Projekte auflisten, damit n8n einen Slug auf ein Projekt abbilden kann.
     */
    public function projekte(): JsonResponse
    {
        $projekte = Project::query()
            ->with('customer:id,name,kuerzel')
            ->where('status', '!=', 'abgeschlossen')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'kunde' => $p->customer->name,
                'kuerzel' => $p->customer->kuerzel,
            ]);

        return response()->json(['projekte' => $projekte]);
    }

    /**
     * Ticket anlegen.
     *
     * Idempotent über external_ref: derselbe Fremdschlüssel liefert das
     * bestehende Ticket mit 200 zurück, statt ein zweites anzulegen. Ohne das
     * erzeugt jeder Wiederholungslauf von n8n — und n8n wiederholt bei jedem
     * Zeitüberschreiten — ein Duplikat.
     */
    public function anlegen(Request $request): JsonResponse
    {
        $daten = $request->validate([
            // Projekt als Slug ODER als ID: n8n hat je nach Workflow das eine
            // oder das andere zur Hand.
            'projekt' => ['required'],
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'prioritaet' => ['nullable', Rule::enum(Prioritaet::class)],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'absender_email' => ['nullable', 'email', 'max:255'],
            'absender_name' => ['nullable', 'string', 'max:255'],
            'faellig_am' => ['nullable', 'date'],
        ]);

        if (! empty($daten['external_ref'])) {
            $vorhanden = Ticket::query()
                ->where('external_ref', $daten['external_ref'])
                ->first();

            if ($vorhanden !== null) {
                return response()->json([
                    'ticket' => $this->darstellen($vorhanden),
                    'neu' => false,
                ], 200);
            }
        }

        if ($this->istSpamVonDerWebsite($request, $daten)) {
            // Keine stille Erfolgsmeldung wie auf der Website: dort sitzt ein
            // Roboter, der nichts erfahren soll, hier nur unsere eigene
            // Warteschlange. Sie soll die Anfrage als nicht übergeben stehen
            // lassen, statt eine Kennung zu speichern, die es nie gab.
            Log::notice('Schnittstelle: Anfrage der Website als Spam verworfen.', [
                'external_ref' => $daten['external_ref'] ?? null,
                'absender_email' => $daten['absender_email'] ?? null,
            ]);

            return response()->json([
                'fehler' => 'Als Spam verworfen.',
            ], 422);
        }

        $projekt = $this->projektFinden($daten['projekt']);

        if ($projekt === null) {
            return response()->json([
                'fehler' => 'Projekt nicht gefunden.',
                'hinweis' => 'Verfügbare Projekte über GET /api/v1/projects abrufen.',
            ], 422);
        }

        $stadium = TicketStatus::standard();

        if ($stadium === null) {
            // Ohne Stadien lässt sich kein Ticket anlegen. Eine klare Antwort
            // ist hier mehr wert als ein Datenbankfehler im n8n-Protokoll.
            return response()->json([
                'fehler' => 'Es ist kein Ticket-Stadium eingerichtet.',
            ], 503);
        }

        $beschreibung = $daten['beschreibung'] ?? null;

        $absender = $this->absenderzeile($daten['absender_name'] ?? null, $daten['absender_email'] ?? null);

        if ($absender !== null) {
            // Der Absender gehört sichtbar ins Ticket — sonst weiß niemand,
            // wem er antworten soll. Die Felder darunter sind für die
            // Maschine, diese Zeile ist für den, der das Ticket liest.
            $beschreibung = trim('Absender: '.$absender."\n\n".$beschreibung);
        }

        $ticket = Ticket::create([
            'project_id' => $projekt->id,
            'titel' => $daten['titel'],
            'beschreibung' => $beschreibung,
            'ticket_status_id' => $stadium->id,
            'prioritaet' => $daten['prioritaet'] ?? Prioritaet::Normal->value,
            'faellig_am' => $daten['faellig_am'] ?? null,
            // Die Quelle folgt dem Token, nicht dem Aufruf: was die
            // Middleware erkannt hat, gilt.
            'quelle' => $request->attributes->get(ApiToken::QUELLE, Quelle::Api),
            'external_ref' => $daten['external_ref'] ?? null,
            'absender_name' => $daten['absender_name'] ?? null,
            'absender_email' => $daten['absender_email'] ?? null,
            // created_by bleibt leer: hinter dem Aufruf steht kein Mensch.
        ]);

        return response()->json([
            'ticket' => $this->darstellen($ticket),
            'neu' => true,
        ], 201);
    }

    /**
     * Das zweite Netz hinter dem Spamschutz der Website.
     *
     * Im Oktober 2026 kamen zweimal Dutzende Anfragen durch, alle auf
     * Russisch, mit einem Betrugslink. Die Website fängt kyrillischen Text
     * inzwischen selbst ab (App\Support\Spamschutz dort); hier steht dieselbe
     * Regel noch einmal, damit eine Lücke drüben nicht wieder siebzig Tickets
     * bedeutet, die ein Mitarbeiter öffnet und deren Link er anklickt.
     *
     * Nur für die Website: über n8n kommt Post von bestehenden Kunden, und
     * die darf schreiben, wie sie will.
     *
     * @param  array<string, mixed>  $daten
     */
    private function istSpamVonDerWebsite(Request $request, array $daten): bool
    {
        if ($request->attributes->get(ApiToken::QUELLE) !== Quelle::Website) {
            return false;
        }

        $text = implode("\n", array_filter([
            $daten['titel'] ?? null,
            $daten['beschreibung'] ?? null,
            $daten['absender_name'] ?? null,
        ]));

        return preg_match('~\p{Cyrillic}~u', $text) === 1
            || preg_match('~https?:|://|www\.~i', (string) ($daten['absender_name'] ?? '')) === 1;
    }

    /** "Erika Muster <erika@example.de>", oder was davon vorhanden ist. */
    private function absenderzeile(?string $name, ?string $email): ?string
    {
        $name = trim((string) $name);
        $email = trim((string) $email);

        return match (true) {
            $name !== '' && $email !== '' => "{$name} <{$email}>",
            $name !== '' => $name,
            $email !== '' => $email,
            default => null,
        };
    }

    private function projektFinden(mixed $kennung): ?Project
    {
        if (is_numeric($kennung)) {
            return Project::find((int) $kennung);
        }

        return Project::where('slug', (string) $kennung)->first();
    }

    /** @return array<string, mixed> */
    private function darstellen(Ticket $ticket): array
    {
        $ticket->loadMissing(['customer', 'project', 'status']);

        return [
            'id' => $ticket->id,
            'kennung' => $ticket->kennung(),
            'titel' => $ticket->titel,
            'status' => $ticket->status->name,
            'prioritaet' => $ticket->prioritaet->value,
            'kunde' => $ticket->customer->name,
            'projekt' => $ticket->project->name,
            // Die sprechende Adresse, nicht die ID: diese Zeile wandert in
            // n8n weiter und landet am Ende in einer Mail oder einer
            // Chatnachricht, wo man sie liest, bevor man sie anklickt.
            'url' => $ticket->oeffentlicheAdresse(),
        ];
    }
}

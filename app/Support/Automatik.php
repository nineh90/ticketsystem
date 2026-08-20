<?php

namespace App\Support;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Enums\Prioritaet;
use App\Enums\Quelle;
use App\Enums\TicketArt;
use App\Models\Dokument;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;

/**
 * Was das System von selbst tut, ohne dass jemand klickt.
 *
 * Alle Regeln dieser Art stehen hier zusammen, und das ist der eigentliche
 * Zweck der Klasse: eine Automatik, die man nicht findet, ist eine, der man
 * nicht traut. Wer wissen will, warum ein Ticket gewandert ist, ohne dass er
 * es angefasst hat, soll genau eine Datei aufschlagen müssen.
 *
 * Ausgelöst werden die Regeln in den Observern (Tatsache → Regel), nicht in
 * den Formularen. Was an der Tatsache hängt, gilt für jeden Weg dorthin —
 * auch für den, den es noch nicht gibt.
 *
 * Jede Regel hier hält sich an dieselben zwei Grundsätze:
 *
 *  - **Sie schreibt nur, was ohnehin gälte.** Wer die Uhr startet, arbeitet
 *    daran; wer antwortet, wartet nicht mehr. Die Automatik nimmt niemandem
 *    eine Entscheidung ab, sie spart den zweiten Handgriff.
 *  - **Sie tut nichts, wenn die Voraussetzungen fehlen.** Kein Stadium, keine
 *    eindeutige Zuständigkeit, kein Projekt — dann bleibt alles, wie es ist.
 *    Raten wäre schlimmer als nichts tun.
 */
class Automatik
{
    /**
     * Ticket auf "In Arbeit" — wenn jemand die Uhr startet.
     *
     * Vorher waren das zwei Handgriffe, die dasselbe sagen: Uhr starten und
     * die Karte auf dem Deck eine Spalte weiterziehen. Den zweiten vergisst
     * man, und ein Brett, das nicht mehr stimmt, sieht sich niemand mehr an.
     */
    public static function inArbeit(Ticket $ticket): void
    {
        $inArbeit = TicketStatus::inArbeit();

        // Kein Stadium, kein Umzug. Und kein Fehler: wer "In Arbeit"
        // gelöscht hat, arbeitet mit anderen Spalten.
        if ($inArbeit === null || $ticket->ticket_status_id === $inArbeit->getKey()) {
            return;
        }

        // update() und nicht saveQuietly(): der Wechsel soll im Verlauf des
        // Tickets stehen wie jeder andere auch. Nach außen bleibt er still —
        // "In Arbeit" ist weder Abschluss noch Rückfrage, und der
        // TicketObserver meldet nur diese beiden.
        $ticket->update(['ticket_status_id' => $inArbeit->getKey()]);
    }

    /**
     * Aus der Wartestellung zurück zu uns — wenn der Kunde geantwortet hat.
     *
     * "Warten auf Kunde" ist das einzige Stadium, das eine Bedingung nennt,
     * die von außen endet. Bis hierher endete sie trotzdem nur, wenn jemand
     * von uns die Spalte durchging: der Kunde schrieb, die Meldung kam an der
     * Glocke an, das Ticket blieb stehen. Wer die Glocke gerade nicht las,
     * fand es erst Tage später wieder.
     *
     * Wohin es zurückgeht, entscheidet die Zuständigkeit: hat das Ticket
     * jemanden, liegt es bei ihm ("In Arbeit"), sonst im Stapel ("Offen").
     * Das ist die ehrlichere Antwort als ein pauschales "In Arbeit" — an
     * einem Ticket, für das niemand eingetragen ist, arbeitet auch niemand.
     */
    public static function ausDerWartestellung(Ticket $ticket): void
    {
        $ticket->loadMissing('status');

        if (! $ticket->status?->wartet_auf_kunde) {
            return;
        }

        $ziel = $ticket->assigned_to === null
            ? TicketStatus::offen()
            : TicketStatus::inArbeit();

        if ($ziel === null || $ticket->ticket_status_id === $ziel->getKey()) {
            return;
        }

        $ticket->update(['ticket_status_id' => $ziel->getKey()]);
    }

    /**
     * Wer sich um das kümmern soll, was von außen hereinkommt.
     *
     * Nur bei Eindeutigkeit: genau ein Mitarbeiter am Projekt, sonst genau
     * einer am Kunden. Sind es mehrere, wäre jede Wahl geraten — und ein
     * falsch zugeteiltes Ticket ist schlimmer als ein unzugeteiltes, weil
     * sich danach niemand mehr zuständig fühlt.
     *
     * Administratoren zählen bewusst nicht mit: sie sehen ohnehin alles und
     * stehen in keiner Zuordnung. Wäre der Chef automatisch der Zuständige,
     * hätte jedes Ticket einen — und die Frage "wer macht das" wäre nur noch
     * scheinbar beantwortet.
     */
    public static function zustaendigerFuer(Ticket $ticket): ?User
    {
        $ausProjekt = $ticket->project?->mitarbeiter()->where('aktiv', true)->get() ?? collect();

        if ($ausProjekt->count() === 1) {
            return $ausProjekt->first();
        }

        // Kein Rückfall auf den Kunden, wenn am Projekt mehrere stehen: die
        // engere Zuordnung ist die genauere Aussage. Erst wenn es am Projekt
        // gar keine gibt, zählt die des Kunden.
        if ($ausProjekt->isNotEmpty()) {
            return null;
        }

        $ausKunde = $ticket->project?->customer?->mitarbeiter()->where('aktiv', true)->get() ?? collect();

        return $ausKunde->count() === 1 ? $ausKunde->first() : null;
    }

    /**
     * Aus einem angenommenen Angebot wird Arbeit.
     *
     * Sagt der Kunde Ja, fing es bis hierher damit an, dass jemand von Hand
     * ein Ticket anlegte und den Betreff aus dem Angebot abtippte — und
     * zwischen Zusage und Ticket lagen die Tage, in denen man dachte, der
     * andere hätte es schon gemacht.
     *
     * Drei Bedingungen, und jede hat ihren Grund:
     *
     *  - **Nur Angebote.** Eine bezahlte Rechnung ist ein Abschluss, kein
     *    Anfang.
     *  - **Nur mit Projekt.** Ein Ticket ohne Projekt gibt es nicht (die
     *    Nummer hängt am Kunden des Projekts), und raten wäre hier besonders
     *    teuer: das falsche Projekt sieht der Kunde in seinem Reiseplan.
     *  - **Nur einmal.** Die Spalte folgeticket_id ist die Sperre; wer den
     *    Stand hin- und herschaltet, bekommt kein zweites Ticket.
     *
     * Zugeteilt wird über dieselbe Regel wie bei allem, was hereinkommt.
     */
    public static function folgeticket(Dokument $dokument): ?Ticket
    {
        if ($dokument->art !== DokumentArt::Angebot
            || $dokument->stand !== DokumentStand::Angenommen
            || $dokument->folgeticket_id !== null
            || $dokument->project_id === null) {
            return null;
        }

        $ticket = Ticket::create([
            'project_id' => $dokument->project_id,
            'titel' => 'Auftrag: '.$dokument->titel,
            'beschreibung' => self::auftragstext($dokument),
            'art' => TicketArt::Aufgabe,
            'prioritaet' => Prioritaet::Normal,
            // Die Quelle sagt, woher es kam — und sorgt nebenbei dafür, dass
            // die Zuteilung greift: von Hand angelegte Tickets lässt sie in
            // Ruhe (TicketObserver::creating).
            'quelle' => Quelle::Api,
            'ticket_status_id' => TicketStatus::standard()?->getKey(),
        ]);

        $dokument->forceFill(['folgeticket_id' => $ticket->getKey()])->saveQuietly();

        return $ticket;
    }

    /** Was in dem Ticket steht, das aus einem Angebot entsteht. */
    private static function auftragstext(Dokument $dokument): string
    {
        $zeilen = array_filter([
            'Der Kunde hat das Angebot angenommen.',
            'Angebot: '.$dokument->titel
                .($dokument->nummer ? ' ('.$dokument->nummer.')' : ''),
            $dokument->betragLesbar() ? 'Betrag: '.$dokument->betragLesbar() : null,
            $dokument->beantwortet_at ? 'Zugesagt am '.$dokument->beantwortet_at->format('d.m.Y') : null,
            'Dieses Ticket ist automatisch entstanden — die Schritte dazu trägt jemand von uns nach.',
        ]);

        return implode("\n", $zeilen);
    }
}

<?php

namespace App\Observers;

use App\Enums\MailEreignis;
use App\Enums\Quelle;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\Automatik;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Notifications\Notification;

/**
 * Was beim Anlegen und Ändern eines Tickets nach außen bzw. innen gemeldet
 * wird.
 *
 * Ein Observer und keine Zeile im Formular: ein Ticket entsteht an vier
 * Stellen — im internen Panel, im Kundenbereich, über die n8n-Schnittstelle
 * und im Kanban per Ziehen. Hinge die Benachrichtigung an der Oberfläche,
 * müsste jede dieser Stellen sie einzeln auslösen, und die vierte vergisst
 * es. Am Model hängt sie an der Tatsache selbst.
 *
 * Drei Dinge passieren hier: das Zuteilen beim Anlegen (creating), die
 * Meldung an den Kunden bzw. an uns (created/updated) und die Meldung an den,
 * der ein Ticket bekommt. Die Regel hinter dem Zuteilen steht in
 * Support\Automatik, wo alles steht, was das System von selbst tut.
 */
class TicketObserver
{
    /**
     * Was von außen hereinkommt, bekommt gleich seinen Zuständigen — wenn es
     * genau einen gibt (Support\Automatik::zustaendigerFuer).
     *
     * Nur was von außen kommt: ein Ticket, das jemand von uns von Hand
     * anlegt und bewusst ohne Zuständigen lässt, soll ohne bleiben. Bei einem
     * gemeldeten Anliegen ist das anders — dort ist "niemand" kein Wille,
     * sondern nur der Zustand direkt nach dem Absenden.
     *
     * In creating und nicht in created: so entsteht das Ticket bereits
     * zugeteilt. Nachträglich zugewiesen wäre es eine Änderung, über die die
     * Zuweisungsmeldung unten zusätzlich informieren würde — der Zuständige
     * bekäme zwei Meldungen zu derselben Sache, die im selben Moment
     * entstanden sind.
     */
    public function creating(Ticket $ticket): void
    {
        if ($ticket->assigned_to !== null || $ticket->quelle === Quelle::Manuell) {
            return;
        }

        $ticket->assigned_to = Automatik::zustaendigerFuer($ticket)?->getKey();
    }

    public function created(Ticket $ticket): void
    {
        if (! $ticket->istVomKunden()) {
            return;
        }

        $ticket->loadMissing(['customer', 'project']);

        Benachrichtigung::nachInnen(
            $ticket,
            Notification::make()
                ->title($ticket->art->getLabel().' von '.$ticket->customer->name)
                ->body($ticket->kennung().' · '.$ticket->project->name.' — '.$ticket->titel)
                ->icon($ticket->art->getIcon())
                ->color($ticket->art->getColor())
                ->actions([
                    Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlIntern($ticket)),
                ]),
            MailEreignis::Anliegen,
        );
    }

    public function updated(Ticket $ticket): void
    {
        $this->zuweisungMelden($ticket);

        // Nur der Stadienwechsel ist eine Nachricht wert. Eine geänderte
        // Beschreibung oder eine verschobene Position im Kanban interessiert
        // den Kunden nicht — und was ihn nicht interessiert, bringt ihm bei,
        // die Glocke zu ignorieren.
        if (! $ticket->wasChanged('ticket_status_id')) {
            return;
        }

        $stadium = TicketStatus::find($ticket->ticket_status_id);

        if ($stadium === null) {
            return;
        }

        $ticket->loadMissing('project');

        if ($stadium->wartet_auf_kunde) {
            Benachrichtigung::nachAussen(
                $ticket,
                Notification::make()
                    ->title('Wir brauchen etwas von Ihnen')
                    ->body($ticket->kennung().' — '.$ticket->titel)
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->actions([
                        Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlKunde($ticket)),
                    ]),
                MailEreignis::StandAnKunde,
            );

            return;
        }

        if ($stadium->ist_abschluss) {
            Benachrichtigung::nachAussen(
                $ticket,
                Notification::make()
                    ->title($stadium->name.': '.$ticket->titel)
                    ->body($ticket->kennung().' · '.$ticket->project->name)
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->actions([
                        Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlKunde($ticket)),
                    ]),
                MailEreignis::StandAnKunde,
            );
        }

        // Alle übrigen Stadien laufen bewusst still: dass etwas von "Offen"
        // nach "In Arbeit" gewandert ist, steht in seiner Liste, und dafür
        // muss ihm niemand auf die Schulter tippen.
    }

    /**
     * Wer ein Ticket bekommt, erfährt es auch.
     *
     * Vorher nicht: assigned_to löste gar nichts aus. Wer zugeteilt wurde,
     * merkte es, wenn er zufällig auf seine Wache sah — und wer zuteilte,
     * schrieb sicherheitshalber noch eine Nachricht hinterher. Genau die
     * spart diese Meldung.
     *
     * Nicht an den, der gerade selbst zuteilt: wer sich ein Ticket nimmt,
     * braucht darüber keine Meldung. Dieselbe Regel wie beim Eintragen in
     * eine Treffen-Crew.
     */
    private function zuweisungMelden(Ticket $ticket): void
    {
        if (! $ticket->wasChanged('assigned_to') || $ticket->assigned_to === null) {
            return;
        }

        $zustaendig = User::query()->where('aktiv', true)->find($ticket->assigned_to);

        if ($zustaendig === null || $zustaendig->is(auth()->user())) {
            return;
        }

        $ticket->loadMissing(['customer', 'project']);

        Benachrichtigung::an(
            collect([$zustaendig]),
            Notification::make()
                ->title('Für dich: '.$ticket->kennung())
                ->body($ticket->titel.' · '.$ticket->customer?->name)
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->actions([
                    Benachrichtigung::knopf('Ansehen', Benachrichtigung::urlIntern($ticket)),
                ]),
            Herkunft::ticket($ticket),
            $ticket->customer,
            MailEreignis::Zuweisung,
        );
    }
}

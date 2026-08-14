<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Support\Benachrichtigung;
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
 */
class TicketObserver
{
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
        );
    }

    public function updated(Ticket $ticket): void
    {
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
            );
        }

        // Alle übrigen Stadien laufen bewusst still: dass etwas von "Offen"
        // nach "In Arbeit" gewandert ist, steht in seiner Liste, und dafür
        // muss ihm niemand auf die Schulter tippen.
    }
}

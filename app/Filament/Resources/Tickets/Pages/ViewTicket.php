<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Support\Benachrichtigung;
use App\Support\Herkunft;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

/**
 * Die Arbeitsfläche für ein einzelnes Ticket: Beschreibung oben, darunter
 * Kommentare, Zeiten und Verlauf als Reiter.
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Wer das Ticket öffnet, hat die Meldungen dazu gesehen.
     *
     * Der Knopf in der Glocke führt genau hierher — wer ihn benutzt, hat sie
     * damit gelesen, und wer den Weg über die Ticketliste nimmt, ebenso. Ohne
     * das trüge man die Zahl an der Glocke auch dann weiter vor sich her,
     * wenn man die Antwort längst kennt, und nach der dritten Woche sagt eine
     * Zahl, die immer da ist, gar nichts mehr.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        Benachrichtigung::gesehen(auth()->user(), Herkunft::ticket($this->record));
    }

    public function getTitle(): string
    {
        return $this->record->kennung().' — '.$this->record->titel;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Bearbeiten'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Ganz oben, wenn draußen jemand wartet. Das steht zwar auch als
            // Herkunft weiter unten in den Eckdaten, aber dort ist es eine
            // Angabe unter neun anderen — hier ist es das Erste, was man
            // liest, und es ändert, wie dringend das Ticket ist.
            Section::make('Vom Kunden gemeldet')
                ->icon('heroicon-o-inbox-arrow-down')
                ->description(fn () => $this->record->customer->name
                    .' wartet auf eine Antwort. Kommentare erreichen den Kunden nur, wenn "Interne Notiz" ausgeschaltet ist.')
                ->schema([])
                ->visible(fn () => $this->record->istVomKunden()),

            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('status.name')
                        ->label('Status')
                        ->badge()
                        ->color(fn () => Color::hex($this->record->status->farbe)),

                    TextEntry::make('art')
                        ->label('Art')
                        ->badge(),

                    TextEntry::make('prioritaet')
                        ->label('Priorität')
                        ->badge(),

                    TextEntry::make('zustaendig.name')
                        ->label('Zuständig')
                        ->placeholder('Niemand'),

                    TextEntry::make('faellig_am')
                        ->label('Fällig')
                        ->date('d.m.Y')
                        ->placeholder('—')
                        ->color(fn () => $this->record->faellig_am
                            && $this->record->faellig_am->isPast()
                            && ! $this->record->erledigt_at
                                ? 'danger'
                                : null),

                    TextEntry::make('project.name')
                        ->label('Projekt')
                        ->url(fn () => route('filament.admin.resources.projects.edit', $this->record->project)),

                    TextEntry::make('customer.name')
                        ->label('Kunde'),

                    TextEntry::make('erfasste_zeit')
                        ->label('Erfasste Zeit')
                        ->state(function () {
                            $minuten = $this->record->erfassteMinuten();

                            return intdiv($minuten, 60).':'
                                .str_pad((string) ($minuten % 60), 2, '0', STR_PAD_LEFT).' h';
                        }),

                    TextEntry::make('quelle')
                        ->label('Herkunft')
                        ->badge(),
                ]),

            // Bilder direkt unter den Eckdaten. Wer ein Ticket öffnet, soll
            // den Screenshot sehen, ohne erst einen Reiter zu suchen — das
            // ist der ganze Zweck der Anhänge bei Fehlerberichten.
            Section::make('Bilder')
                ->schema([
                    ViewEntry::make('bilder')
                        ->hiddenLabel()
                        ->view('filament.ticket-bilder'),
                ])
                ->visible(fn () => $this->record->bilder()->exists()),

            Section::make('Beschreibung')
                ->schema([
                    TextEntry::make('beschreibung')
                        ->hiddenLabel()
                        ->placeholder('Keine Beschreibung hinterlegt.')
                        ->prose(),
                ])
                ->collapsible(),
        ]);
    }
}

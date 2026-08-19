<?php

namespace App\Filament\Kunde\Resources\Dokumente\Pages;

use App\Enums\DokumentStand;
use App\Enums\MailEreignis;
use App\Filament\Kunde\Resources\Dokumente\DokumentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Dokument;
use App\Support\Benachrichtigung;
use App\Support\Dauer;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Gate;

/**
 * Ein einzelnes Dokument im Kundenbereich.
 *
 * Die Seite hat drei Aufgaben: das PDF herausgeben, die Eckdaten nennen, und
 * bei einem Angebot die Frage stellen, um die es geht. Der letzte Punkt ist
 * der eigentliche Zweck — bis hierher lief eine Zusage über Telefon oder
 * Mail und war danach nirgends festgehalten.
 */
class ViewDokument extends ViewRecord
{
    protected static string $resource = DokumentResource::class;

    public function getTitle(): string
    {
        return $this->record->titel;
    }

    public function getSubheading(): ?string
    {
        /** @var Dokument $dokument */
        $dokument = $this->record;

        return implode(' · ', array_filter([
            $dokument->art->getLabel(),
            $dokument->nummer,
            $dokument->datum->format('d.m.Y'),
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('herunterladen')
                ->label('PDF herunterladen')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => $this->record->url())
                ->openUrlInNewTab(),

            $this->antwort(
                'annehmen',
                'Angebot annehmen',
                'heroicon-o-check-circle',
                'success',
                DokumentStand::Angenommen,
                'Möchten Sie dieses Angebot verbindlich annehmen? Wir werden benachrichtigt und melden uns bei Ihnen.',
                'Angebot angenommen',
            ),

            $this->antwort(
                'ablehnen',
                'Ablehnen',
                'heroicon-o-x-circle',
                'gray',
                DokumentStand::Abgelehnt,
                'Möchten Sie dieses Angebot ablehnen? Sie können sich danach jederzeit wieder bei uns melden.',
                'Angebot abgelehnt',
            ),
        ];
    }

    /**
     * Einer der beiden Antwortknöpfe.
     *
     * Beide tun dasselbe und unterscheiden sich nur in Beschriftung, Farbe
     * und Zielstand — zweimal ausgeschrieben wären sie die Stelle, an der
     * eines Tages nur der eine benachrichtigt.
     *
     * Die Rückfrage ist Absicht: eine Zusage ist verbindlich gemeint, und ein
     * Knopf, der beim ersten Klick zusagt, wird versehentlich gedrückt.
     */
    private function antwort(
        string $name,
        string $beschriftung,
        string $symbol,
        string $farbe,
        DokumentStand $stand,
        string $rueckfrage,
        string $bestaetigung,
    ): Action {
        return Action::make($name)
            ->label($beschriftung)
            ->icon($symbol)
            ->color($farbe)
            ->requiresConfirmation()
            ->modalHeading($beschriftung)
            ->modalDescription($rueckfrage)
            ->modalWidth(Width::Medium)
            // Sichtbar nur, solange die Frage offen ist — und über dieselbe
            // Prüfung, die auch der Direktaufruf durchläuft. Ein Knopf, der
            // verschwindet, aber weiter ausgelöst werden kann, wäre keine
            // Absicherung.
            ->visible(fn () => Gate::allows('beantworten', $this->record))
            ->action(function () use ($stand, $bestaetigung) {
                /** @var Dokument $dokument */
                $dokument = $this->record;

                // Zweite Prüfung im Moment des Ausführens: zwischen dem
                // Aufbau der Seite und dem Klick kann jemand intern den Stand
                // geändert haben.
                Gate::authorize('beantworten', $dokument);

                $dokument->vomKundenBeantworten($stand, auth()->user());

                $this->meldenAnUns($dokument, $stand);

                Notification::make()
                    ->title($bestaetigung)
                    ->body('Vielen Dank — wir haben Ihre Rückmeldung erhalten.')
                    ->success()
                    ->send();

                $this->refreshFormData([]);
            });
    }

    /**
     * Uns Bescheid geben, dass der Kunde geantwortet hat.
     *
     * An die Zuständigen des Kunden, nicht an alle: derselbe Kreis, der auch
     * eine geänderte Anschrift erfährt. Eine Angebotszusage, die in der
     * Glocke von jemandem landet, der mit dem Kunden nichts zu tun hat, ist
     * eine Meldung mehr, die man wegklickt.
     */
    private function meldenAnUns(Dokument $dokument, DokumentStand $stand): void
    {
        $angenommen = $stand === DokumentStand::Angenommen;

        $meldung = Notification::make()
            ->title($angenommen ? 'Angebot angenommen' : 'Angebot abgelehnt')
            ->body(
                $dokument->customer->name.' — '.$dokument->titel
                .($dokument->betragLesbar() ? ' ('.$dokument->betragLesbar().')' : ''),
            )
            ->actions([
                Benachrichtigung::knopf(
                    'Kunde öffnen',
                    // Panel ausdrücklich "admin": ausgelöst wird das hier im
                    // Kundenpanel, und ohne die Angabe entstünde für uns ein
                    // Link nach /kunde (siehe Benachrichtigung::urlIntern).
                    CustomerResource::getUrl(
                        'view',
                        ['record' => $dokument->customer_id],
                        panel: 'admin',
                    ),
                ),
            ]);

        // Farbe und Symbol setzt Filament über success()/warning() gemeinsam;
        // ein eigenes icon() davor würde dabei überschrieben.
        $angenommen ? $meldung->success() : $meldung->warning();

        Benachrichtigung::anZustaendige($dokument->customer_id, $meldung, MailEreignis::Angebot);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Eckdaten')
                ->columns(3)
                // Volle Breite: Filament stellt Abschnitte einer Ansicht
                // sonst in zwei Spalten, und ein einzelner Abschnitt steht
                // dann mit der halben Seite Leerraum daneben.
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('art')
                        ->label('Art')
                        ->badge(),

                    TextEntry::make('nummer')
                        ->label('Nummer')
                        ->placeholder('—'),

                    TextEntry::make('datum')
                        ->label('Datum')
                        ->date('d.m.Y'),

                    TextEntry::make('betrag')
                        ->label('Betrag')
                        ->state(fn (Dokument $record) => $record->betragLesbar())
                        ->placeholder('—'),

                    TextEntry::make('faellig_am')
                        ->label(fn (Dokument $record) => $record->art->datumsBeschriftung())
                        ->date('d.m.Y')
                        ->placeholder('—'),

                    TextEntry::make('stand')
                        ->label('Stand')
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('project.name')
                        ->label('Projekt')
                        ->placeholder('—'),

                    // Wie viel Arbeitszeit in dieser Rechnung steckt — als
                    // Summe und nicht aufgeschlüsselt. Sie beantwortet die
                    // Frage "wofür", ohne eine Diskussion über einzelne
                    // Posten zu eröffnen; und die Tätigkeitstexte der
                    // Buchungen bleiben ganz draußen, die sind für interne
                    // Augen geschrieben.
                    //
                    // Nur wenn wirklich etwas zugeordnet ist: eine Zeile
                    // "0:00 h" an einer Rechnung über 420 € wirft mehr Fragen
                    // auf, als sie beantwortet.
                    TextEntry::make('arbeitszeit')
                        ->label('Enthaltene Arbeitszeit')
                        ->state(fn (Dokument $record) => Dauer::alsStunden($record->zugeordneteMinuten()))
                        ->visible(fn (Dokument $record) => $record->zugeordneteMinuten() > 0),

                    // Nur wenn der Kunde selbst geantwortet hat. Haben wir den
                    // Stand eingetragen, steht hier nichts — die Zeile wäre
                    // sonst eine Behauptung über ihn.
                    TextEntry::make('beantwortet_at')
                        ->label('Ihre Rückmeldung')
                        ->dateTime('d.m.Y H:i')
                        ->visible(fn (Dokument $record) => $record->beantwortet_at !== null),
                ]),
        ]);
    }
}

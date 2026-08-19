<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Nachricht;
use App\Models\Unterhaltung;
use App\Models\User;
use App\Support\Unterhaltungen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Der Chat — links die Fäden, rechts der offene.
 *
 * Selbst gebaut und keine Resource, aus demselben Grund wie beim Kanban: eine
 * Ressourcenliste mit Anlegen-Formular und Detailseite ist die richtige Form
 * für Datensätze, die man verwaltet. Eine Unterhaltung verwaltet man nicht,
 * man liest sie und schreibt hinein — und dafür braucht es genau zwei
 * Flächen nebeneinander und ein Eingabefeld unten.
 *
 * Zwei Gruppen in der Liste, Kunden und Intern, und die Trennung ist keine
 * Sortierung: an einem Kundenfaden hängt ein anderer Empfängerkreis. Wer
 * beides untereinander sähe, schriebe irgendwann in den falschen — und das
 * ist der eine Fehler, den man hier nicht zurücknehmen kann.
 */
class Nachrichten extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Funk';

    protected static ?string $title = 'Nachrichten';

    /** Vor die Tickets: was jemand geschrieben hat, wartet auf Antwort. */
    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.nachrichten';

    /** Der gerade geöffnete Faden. Steht in der Adresszeile, damit ein Link aus der Glocke hier ankommt. */
    public ?int $unterhaltung = null;

    /** Was im Eingabefeld steht. */
    public string $entwurf = '';

    public function mount(): void
    {
        $ausAdresse = request()->integer('unterhaltung') ?: null;

        // Ohne Vorgabe der oberste Faden — eine leere rechte Hälfte neben
        // einer gefüllten Liste sieht aus, als wäre etwas nicht geladen.
        $this->oeffnen($ausAdresse ?? $this->unterhaltungen()->first()?->getKey());
    }

    public static function getNavigationBadge(): ?string
    {
        $offen = Unterhaltungen::ungelesen();

        return $offen > 0 ? (string) $offen : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * Alle sichtbaren Fäden, neueste zuerst.
     *
     * Ohne Merker: nach jedem Absenden zeichnet Livewire in derselben Anfrage
     * neu, und ein gemerkter Wert wäre die Liste von vor der Nachricht — der
     * eben geschriebene Faden stünde dann noch an alter Stelle.
     *
     * @return Collection<int, Unterhaltung>
     */
    public function unterhaltungen(): Collection
    {
        return Unterhaltungen::fuer(auth()->user());
    }

    /** @return Collection<int, Unterhaltung> */
    public function getKundenfaeden(): Collection
    {
        return $this->unterhaltungen()->reject->istIntern()->values();
    }

    /** @return Collection<int, Unterhaltung> */
    public function getInternenFaeden(): Collection
    {
        return $this->unterhaltungen()->filter->istIntern()->values();
    }

    public function getAktuelle(): ?Unterhaltung
    {
        if ($this->unterhaltung === null) {
            return null;
        }

        $unterhaltung = Unterhaltung::query()
            ->with(['customer', 'teilnehmer', 'nachrichten.absender'])
            ->find($this->unterhaltung);

        // Verschwunden oder nie erlaubt: beides endet hier gleich, nämlich
        // ohne Fehlermeldung. Eine Nummer in der Adresszeile ist kein
        // Angriff, sondern meistens ein veralteter Link aus der Glocke.
        if ($unterhaltung === null || auth()->user()?->cannot('view', $unterhaltung)) {
            return null;
        }

        return $unterhaltung;
    }

    /** Einen Faden öffnen — und alles darin als gelesen vermerken. */
    public function oeffnen(?int $id): void
    {
        $this->unterhaltung = $id;
        $this->entwurf = '';

        $aktuelle = $this->getAktuelle();

        if ($aktuelle === null) {
            $this->unterhaltung = null;

            return;
        }

        $aktuelle->alsGelesenMarkieren(auth()->user());
    }

    public function senden(): void
    {
        $unterhaltung = $this->getAktuelle();

        if ($unterhaltung === null) {
            return;
        }

        // Nicht bloß die Oberfläche fragen: senden() ist eine Livewire-
        // Methode und damit von außen aufrufbar, auch mit einer fremden
        // Nummer in $unterhaltung.
        if (auth()->user()?->cannot('schreiben', $unterhaltung)) {
            throw ValidationException::withMessages([
                'entwurf' => 'In diese Unterhaltung darfst du nicht schreiben.',
            ]);
        }

        $text = trim($this->entwurf);

        if ($text === '') {
            return;
        }

        Nachricht::create([
            'unterhaltung_id' => $unterhaltung->getKey(),
            'user_id' => auth()->id(),
            'text' => $text,
        ]);

        $this->entwurf = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mitKunde')
                ->label('An einen Kunden')
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('create', Unterhaltung::class) ?? false)
                ->schema([
                    Select::make('customer_id')
                        ->label('Kunde')
                        ->options(fn () => Customer::query()
                            ->sichtbarFuer(auth()->user())
                            ->aktiv()
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Es gibt je Kunde genau einen Verlauf — ein bestehender wird geöffnet, kein zweiter angelegt.'),

                    Textarea::make('text')
                        ->label('Nachricht')
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $kunde = Customer::query()->sichtbarFuer(auth()->user())->findOrFail($data['customer_id']);

                    $this->schreibenAn(Unterhaltungen::fuerKunden($kunde), $data['text']);
                }),

            Action::make('intern')
                ->label('An einen Kollegen')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('create', Unterhaltung::class) ?? false)
                ->schema([
                    Select::make('user_id')
                        ->label('Wer')
                        ->options(fn () => User::query()
                            ->intern()
                            ->whereKeyNot(auth()->id())
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Textarea::make('text')
                        ->label('Nachricht')
                        ->rows(4)
                        ->required()
                        ->helperText('Nur ihr beide lest mit — auch kein Administrator.'),
                ])
                ->action(function (array $data): void {
                    $kollege = User::query()->intern()->findOrFail($data['user_id']);

                    $this->schreibenAn(Unterhaltungen::zwischen(auth()->user(), $kollege), $data['text']);
                }),
        ];
    }

    /** Nachricht anlegen und den Faden gleich öffnen — sonst schriebe man ins Leere. */
    private function schreibenAn(Unterhaltung $unterhaltung, string $text): void
    {
        Nachricht::create([
            'unterhaltung_id' => $unterhaltung->getKey(),
            'user_id' => auth()->id(),
            'text' => trim($text),
        ]);

        $this->oeffnen($unterhaltung->getKey());

        Notification::make()
            ->title('Nachricht gesendet')
            ->success()
            ->send();
    }
}

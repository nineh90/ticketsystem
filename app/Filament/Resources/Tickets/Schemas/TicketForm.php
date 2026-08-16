<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Enums\Prioritaet;
use App\Enums\TicketArt;
use App\Filament\Formulare\Anhangfeld;
use App\Models\Project;
use App\Models\TicketStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('titel')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('beschreibung')
                            ->label('Beschreibung')
                            ->rows(8)
                            ->columnSpanFull(),

                        // Gleich beim Anlegen und nicht erst am fertigen
                        // Ticket. Beides geht, aber der Screenshot liegt in
                        // genau diesem Moment auf dem Bildschirm — eine Seite
                        // später ist er vergessen.
                        //
                        // Nur beim Anlegen: am bestehenden Ticket macht das
                        // der Reiter "Anhänge", der auch löschen und öffnen
                        // kann. Zwei Wege zum selben Ziel auf einer Seite
                        // wären eine Erklärung mehr, nicht eine weniger.
                        // visibleOn('create') sorgt außerdem dafür, dass
                        // "dateien" beim Bearbeiten gar nicht erst in den
                        // Formulardaten auftaucht — es ist keine Spalte.
                        Anhangfeld::machen()
                            ->visibleOn('create')
                            ->helperText('Bilder (PNG, JPG, GIF, WebP) und PDF, je bis 16 MB. Später gibt es dafür den Reiter „Anhänge".'),
                    ]),

                Section::make('Zuordnung')
                    ->columns(2)
                    ->schema([
                        Select::make('project_id')
                            ->label('Projekt')
                            ->required()
                            ->searchable()
                            ->preload()
                            // Die Auswahl ist bereits auf sichtbare Projekte
                            // begrenzt; ein Mitarbeiter kann also gar kein
                            // Ticket in einem fremden Projekt anlegen.
                            ->options(fn () => Project::query()
                                ->sichtbarFuer(auth()->user())
                                ->with('customer')
                                ->get()
                                ->sortBy(fn ($p) => $p->customer->name.$p->name)
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => "{$p->customer->kuerzel} — {$p->name}",
                                ]))
                            // Beim Bearbeiten gesperrt: ein Projektwechsel
                            // würde den Kunden und damit den Nummernkreis
                            // wechseln, die vergebene Nummer aber nicht. Die
                            // Kennung zeigte danach auf den falschen Kunden.
                            ->disabled(fn (string $operation) => $operation === 'edit')
                            ->dehydrated(fn (string $operation) => $operation === 'create')
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Nicht änderbar — die Ticketnummer gehört zum Kunden dieses Projekts.'
                                : null),

                        Select::make('ticket_status_id')
                            ->label('Status')
                            ->relationship('status', 'name', fn ($query) => $query->sortiert())
                            ->default(fn () => TicketStatus::standard()?->id)
                            ->required()
                            ->preload(),

                        Select::make('art')
                            ->label('Art')
                            ->options(TicketArt::class)
                            ->default(TicketArt::Aufgabe->value)
                            ->required()
                            ->helperText('Was es ist. Meldet ein Kunde etwas, steht die Art schon drin.'),

                        Select::make('prioritaet')
                            ->label('Priorität')
                            ->options(Prioritaet::class)
                            ->default(Prioritaet::Normal->value)
                            ->required(),

                        Select::make('assigned_to')
                            ->label('Zuständig')
                            ->relationship('zustaendig', 'name', fn ($query) => $query->intern()->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Niemand'),

                        DatePicker::make('faellig_am')
                            ->label('Fällig am')
                            ->displayFormat('d.m.Y')
                            ->native(false),
                    ]),
            ]);
    }
}

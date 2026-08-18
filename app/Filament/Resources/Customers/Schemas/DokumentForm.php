<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\DokumentArt;
use App\Enums\DokumentStand;
use App\Models\Customer;
use App\Models\Dokument;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Das Formular für ein Kundendokument.
 *
 * Die Art steht ganz oben und ist live: sie entscheidet, welche Felder
 * darunter überhaupt erscheinen. Ein Vertrag hat keinen Zahlungsstand, ein
 * "Sonstiges" keinen Betrag — sie trotzdem anzuzeigen hieße, bei jedem
 * Hochladen drei Felder zu überspringen, und übersprungene Felder werden
 * irgendwann versehentlich ausgefüllt.
 *
 * Der Freigabeschalter steht bewusst als Letztes und mit deutlichem Text:
 * er ist die einzige Stelle, an der entschieden wird, ob der Kunde die Datei
 * bekommt.
 */
class DokumentForm
{
    /** Der Kunde, an dem das Dokument hängt — für die Projektauswahl. */
    public static function configure(Schema $schema, Customer $kunde): Schema
    {
        return $schema
            ->components([
                Section::make('Was ist das')
                    ->columns(2)
                    ->schema([
                        Select::make('art')
                            ->label('Art')
                            ->options(DokumentArt::class)
                            ->default(DokumentArt::Rechnung->value)
                            ->required()
                            // live, weil Betrag, Stand und die Beschriftung
                            // des Datums daran hängen.
                            ->live()
                            // Beim Wechsel der Art einen Stand setzen, der zu
                            // ihr passt. Ohne das bliebe "bezahlt" an einem
                            // Angebot stehen — ein Wert, den die Auswahl
                            // darunter gar nicht mehr anbietet und den man
                            // deshalb auch nicht mehr sieht.
                            ->afterStateUpdated(function ($state, $set) {
                                $art = $state instanceof DokumentArt
                                    ? $state
                                    : DokumentArt::tryFrom((string) $state);

                                $set('stand', $art?->staende() === []
                                    ? null
                                    : DokumentStand::Offen->value);
                            }),

                        TextInput::make('titel')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Relaunch Startseite')
                            ->helperText('Das, was in der Liste steht — beim Kunden genauso wie bei uns.'),

                        TextInput::make('nummer')
                            ->label('Nummer')
                            ->maxLength(50)
                            ->placeholder('R-2026-014')
                            ->helperText('Die Nummer aus sevDesk. Hier wird keine vergeben.'),

                        Select::make('project_id')
                            ->label('Projekt')
                            ->options(fn () => Project::query()
                                ->where('customer_id', $kunde->getKey())
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Kein bestimmtes Projekt')
                            ->helperText('Optional. Die Jahresrechnung für die Betreuung gehört zu keinem.'),
                    ]),

                Section::make('Zahlen und Fristen')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('datum')
                            ->label('Datum')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->default(now())
                            ->required()
                            ->helperText('Das Datum auf dem Dokument.'),

                        DatePicker::make('faellig_am')
                            // Eine Spalte, zwei Bedeutungen — die
                            // Beschriftung sagt, welche gerade gilt.
                            ->label(fn (Get $get) => self::art($get)?->datumsBeschriftung() ?? 'Frist')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->helperText('Leer = ohne Frist. Ohne Frist wird nie etwas als überfällig gezeigt.'),

                        TextInput::make('betrag')
                            ->label('Betrag')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('€')
                            ->visible(fn (Get $get) => self::art($get)?->hatBetrag() ?? false)
                            ->helperText('Brutto, wie auf dem Dokument.'),

                        Select::make('stand')
                            ->label('Stand')
                            ->options(fn (Get $get) => collect(self::art($get)?->staende() ?? [])
                                ->mapWithKeys(fn (DokumentStand $s) => [$s->value => $s->getLabel()])
                                ->all())
                            ->visible(fn (Get $get) => (self::art($get)?->staende() ?? []) !== [])
                            ->default(DokumentStand::Offen->value)
                            ->helperText('Sagt der Kunde selbst zu, wird das hier automatisch gesetzt.'),
                    ]),

                Section::make('Datei')
                    ->schema([
                        FileUpload::make('pfad')
                            ->label('PDF')
                            ->disk(Dokument::PLATTE)
                            ->directory('dokumente/'.$kunde->getKey())
                            ->visibility('private')
                            // Derselbe Namensaufbau wie bei den Anhängen:
                            // Zufallsvorsatz gegen Überschreiben und gegen
                            // erratbare Adressen, echter Name hinter "__".
                            // Bei Rechnungen wiegt das schwerer — Namen aus
                            // einem Buchhaltungsprogramm sind fortlaufend.
                            // Der Parameter MUSS $file heißen, siehe
                            // AnhaengeRelationManager.
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::random(24)
                                    .'__'
                                    .Str::of($file->getClientOriginalName())
                                        ->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')
                                        ->limit(80, ''),
                            )
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(16 * 1024)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Nur PDF, bis 16 MB. Die Datei wird geschützt abgelegt und nur über eine geprüfte Adresse ausgeliefert.'),
                    ]),

                Section::make('Freigabe')
                    ->schema([
                        Toggle::make('kunden_sichtbar')
                            ->label('Für den Kunden sichtbar')
                            ->default(false)
                            ->helperText('Aus, solange es ein Entwurf ist. Sobald der Haken gesetzt ist, steht das Dokument in seinem Bereich — und der Bereich taucht bei ihm überhaupt erst auf, wenn dort etwas steht.'),

                        Textarea::make('notiz')
                            ->label('Interne Notiz')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Sieht nur das Team.'),
                    ]),
            ]);
    }

    /** Die gerade gewählte Art, egal ob als Enum oder als Zeichenkette. */
    private static function art(Get $get): ?DokumentArt
    {
        $wert = $get('art');

        return $wert instanceof DokumentArt ? $wert : DokumentArt::tryFrom((string) $wert);
    }
}

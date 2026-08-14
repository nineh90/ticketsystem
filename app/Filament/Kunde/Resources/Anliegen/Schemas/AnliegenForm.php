<?php

namespace App\Filament\Kunde\Resources\Anliegen\Schemas;

use App\Enums\TicketArt;
use App\Models\Attachment;
use App\Models\Project;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Das Formular, mit dem ein Kunde etwas meldet.
 *
 * Vier Felder, mehr nicht. Jedes weitere ist eine Hürde vor einer Meldung,
 * die wir haben wollen — wer beim Melden eines Fehlers erst Priorität,
 * Fälligkeit und Kategorie einordnen soll, ruft stattdessen an oder schreibt
 * eine Mail, und dann steht es wieder nirgends.
 *
 * Was hier fehlt, ist Absicht: Priorität, Zuständigkeit, Termin und Status
 * setzen wir. Ein Kunde, der seine eigene Meldung auf "dringend" stellen
 * kann, stellt jede darauf, und die Angabe wird wertlos.
 */
class AnliegenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Worum geht es?')
                    ->schema([
                        Radio::make('art')
                            ->hiddenLabel()
                            ->options(collect(TicketArt::fuerKunden())
                                ->mapWithKeys(fn (TicketArt $art) => [$art->value => $art->getLabel()])
                                ->all())
                            ->descriptions(collect(TicketArt::fuerKunden())
                                ->mapWithKeys(fn (TicketArt $art) => [$art->value => $art->erklaerung()])
                                ->all())
                            ->default(TicketArt::Fehler->value)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Ihr Anliegen')
                    ->schema([
                        Select::make('project_id')
                            ->label('Projekt')
                            ->options(fn () => Project::query()
                                ->sichtbarFuer(auth()->user())
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->required()
                            // Bei genau einem Projekt gibt es nichts zu
                            // wählen — dann steht die Auswahl vorbelegt da,
                            // statt eine Entscheidung zu verlangen, die keine
                            // ist.
                            ->default(fn () => Project::query()
                                ->sichtbarFuer(auth()->user())
                                ->count() === 1
                                    ? Project::query()->sichtbarFuer(auth()->user())->value('id')
                                    : null)
                            ->native(false)
                            ->searchable()
                            ->helperText('Zu welchem Projekt gehört es?'),

                        TextInput::make('titel')
                            ->label('Kurz gesagt')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Das Kontaktformular verschickt nichts')
                            ->helperText('Ein Satz genügt.'),

                        Textarea::make('beschreibung')
                            ->label('Ausführlich')
                            ->rows(8)
                            ->columnSpanFull()
                            ->placeholder(
                                "Was haben Sie gemacht?\n".
                                "Was ist passiert?\n".
                                'Was hätte stattdessen passieren sollen?',
                            ),

                        // Gleich hier und nicht erst hinterher am angelegten
                        // Anliegen. Beides geht, aber wer einen Fehler meldet,
                        // hat den Screenshot in genau diesem Moment auf dem
                        // Bildschirm — eine Seite später ist er vergessen, und
                        // dann kostet die Rückfrage "können Sie ein Bild
                        // schicken?" beide Seiten einen halben Tag.
                        FileUpload::make('dateien')
                            ->label('Screenshots')
                            ->multiple()
                            ->disk(Attachment::PLATTE)
                            // Zwischenlager: die Ticketnummer gibt es beim
                            // Hochladen noch nicht. Nach dem Absenden werden
                            // die Dateien in den Ordner des Anliegens
                            // verschoben (siehe CreateAnliegen).
                            ->directory('anhaenge/eingang')
                            ->visibility('private')
                            // Der Parameter MUSS $file heißen — Filament
                            // reicht ihn über den Namen durch, nicht über den
                            // Typ. Ausführlich im internen
                            // AnhaengeRelationManager.
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::random(24)
                                    .'__'
                                    .Str::of($file->getClientOriginalName())
                                        ->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')
                                        ->limit(80, ''),
                            )
                            // Kein ->image(): das setzt die erlaubten Typen auf
                            // image/* und schlösse PDF aus. Ein Kunde schickt
                            // aber durchaus mal einen ausgedruckten Fehler.
                            ->imagePreviewHeight('120')
                            ->openable()
                            ->acceptedFileTypes([
                                'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf',
                            ])
                            // 16 MB, passend zu upload_max_filesize in
                            // deploy/php.ini. Ein höherer Wert führte nur
                            // dazu, dass PHP die Datei stillschweigend
                            // verwirft.
                            ->maxSize(16 * 1024)
                            ->maxFiles(10)
                            ->columnSpanFull()
                            ->helperText('Bilder oder PDF, je bis 16 MB. Ein Screenshot spart oft drei Absätze Beschreibung.'),
                    ]),
            ]);
    }
}

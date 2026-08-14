<?php

namespace App\Filament\Kunde\Resources\Anliegen\RelationManagers;

use App\Models\Attachment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Dateien am Anliegen — vor allem Screenshots.
 *
 * Bewusst derselbe Ablauf wie intern (AnhaengeRelationManager), bis hin zum
 * zufälligen Namensvorsatz: ein Kunde lädt genau die Dateien hoch, die wir
 * anschließend im Ticket sehen, und für die Ablage gelten dieselben Regeln.
 * Der Unterschied ist, was er hinterher damit darf — löschen nur die eigenen,
 * und das erledigt die AttachmentPolicy.
 */
class DateienRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Dateien';

    protected static ?string $modelLabel = 'Datei';

    protected static ?string $pluralModelLabel = 'Dateien';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-paper-clip';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('pfad')
                ->label('Dateien')
                ->multiple()
                ->disk(Attachment::PLATTE)
                ->directory('anhaenge/'.$this->getOwnerRecord()->getKey())
                ->visibility('private')
                // Der Parameter MUSS $file heißen — Filament reicht ihn über
                // den Namen durch, nicht über den Typ. Bei jedem anderen
                // Namen scheitert der Upload mit einer Meldung, die nicht auf
                // die Ursache zeigt. Ausführlich im internen
                // AnhaengeRelationManager.
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $file): string => Str::random(24)
                        .'__'
                        .Str::of($file->getClientOriginalName())
                            ->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')
                            ->limit(80, ''),
                )
                ->imagePreviewHeight('120')
                ->acceptedFileTypes([
                    'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf',
                ])
                ->maxSize(16 * 1024)
                ->required()
                ->columnSpanFull()
                ->helperText('Bilder (PNG, JPG, GIF, WebP) und PDF, je bis 16 MB.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('vorschau')
                    ->label('')
                    ->state(fn (Attachment $record) => $record->istBild() ? $record->url() : null)
                    ->height(56)
                    ->extraImgAttributes(['class' => 'rounded object-cover']),

                TextColumn::make('dateiname')
                    ->label('Datei')
                    ->weight('medium')
                    ->description(fn (Attachment $record) => $record->groesseLesbar()),

                TextColumn::make('hochgeladenVon.name')
                    ->label('Von')
                    ->placeholder('Nils-Digital'),

                TextColumn::make('created_at')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->label('Datei hochladen')
                    ->icon('heroicon-o-arrow-up-tray')
                    // Siehe intern: ->multiple() liefert ein Array von
                    // Pfaden, daraus wird je Datei ein eigener Datensatz.
                    ->using(function (array $data) {
                        $letzter = null;

                        foreach ((array) ($data['pfad'] ?? []) as $pfad) {
                            $platte = Storage::disk(Attachment::PLATTE);

                            $basis = basename($pfad);
                            $anzeigename = str_contains($basis, '__')
                                ? Str::after($basis, '__')
                                : $basis;

                            $letzter = $this->getOwnerRecord()->attachments()->create([
                                'user_id' => auth()->id(),
                                'pfad' => $pfad,
                                'dateiname' => $anzeigename,
                                'mime' => $platte->mimeType($pfad) ?: null,
                                'groesse' => $platte->size($pfad),
                            ]);
                        }

                        return $letzter;
                    }),
            ])
            ->recordActions([
                Action::make('oeffnen')
                    ->label('Öffnen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Attachment $record) => $record->url())
                    ->openUrlInNewTab(),

                // Die Policy erlaubt das Löschen nur am eigenen Upload; für
                // unsere Dateien erscheint der Knopf beim Kunden gar nicht.
                DeleteAction::make()->label('Löschen'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('Keine Dateien')
            ->emptyStateDescription('Ein Screenshot spart bei einem Fehlerbericht oft drei Absätze Beschreibung.');
    }
}

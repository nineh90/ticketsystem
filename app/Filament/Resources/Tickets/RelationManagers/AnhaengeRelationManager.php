<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

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

class AnhaengeRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Anhänge';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-paper-clip';

    /** Siehe CommentsRelationManager: sonst fehlen alle Knöpfe. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('pfad')
                ->label('Dateien')
                // Mehrere auf einmal, auch per Ziehen und Ablegen.
                ->multiple()
                ->disk(Attachment::PLATTE)
                ->directory('anhaenge/'.$this->getOwnerRecord()->getKey())
                ->visibility('private')
                // Der Name auf der Platte bekommt einen zufälligen Vorsatz:
                // zwei "screenshot.png" würden sich sonst überschreiben, und
                // über einen ratbaren Namen käme man an fremde Dateien. Der
                // ursprüngliche Name hängt hinter dem Trenner "__" und wird
                // beim Anlegen daraus gelesen, damit die Liste nicht lauter
                // Zufallsketten zeigt.
                ->getUploadedFileNameForStorageUsing(
                    fn (TemporaryUploadedFile $datei): string => Str::random(24)
                        .'__'
                        // Nur harmlose Zeichen im Namen: Schrägstriche oder
                        // ".." dürfen niemals in einem Pfad landen.
                        .Str::of($datei->getClientOriginalName())
                            ->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')
                            ->limit(80, ''),
                )
                ->imagePreviewHeight('120')
                ->acceptedFileTypes([
                    'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf',
                ])
                // 16 MB, passend zu upload_max_filesize in deploy/php.ini.
                // Ein höherer Wert hier führte nur dazu, dass PHP die Datei
                // stillschweigend verwirft.
                ->maxSize(16 * 1024)
                ->required()
                ->columnSpanFull()
                ->helperText('Bilder (PNG, JPG, GIF, WebP) und PDF, je bis 16 MB. Mehrere Dateien gleichzeitig möglich.'),
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
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Attachment $record) => $record->groesseLesbar()),

                TextColumn::make('hochgeladenVon.name')
                    ->label('Von')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Dateien hochladen')
                    ->icon('heroicon-o-arrow-up-tray')
                    // FileUpload mit ->multiple() liefert ein Array von
                    // Pfaden. Daraus wird je Datei ein eigener Datensatz —
                    // deshalb übernimmt using() das Anlegen selbst.
                    ->using(function (array $data, string $model) {
                        $pfade = (array) ($data['pfad'] ?? []);
                        $letzter = null;

                        foreach ($pfade as $pfad) {
                            $platte = Storage::disk(Attachment::PLATTE);

                            // Alles hinter "__" ist der ursprüngliche Name.
                            // Fehlt der Trenner (etwa bei einer von Hand
                            // abgelegten Datei), bleibt der ganze Basisname.
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

                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Keine Anhänge')
            ->emptyStateDescription('Ein Screenshot spart bei einem Fehlerbericht oft drei Absätze Beschreibung.')
            ->emptyStateIcon('heroicon-o-photo');
    }
}

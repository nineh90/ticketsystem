<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Kommentare';

    /**
     * Filament setzt Relation-Manager auf einer Ansehen-Seite automatisch auf
     * schreibgeschützt, sobald die Ressource eine Bearbeiten-Seite hat — dann
     * verschwinden sämtliche Knöpfe wortlos. Hier ist das Gegenteil gewollt:
     * die Detailseite ist die Arbeitsfläche, auf der kommentiert wird.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Kommentar')
                ->required()
                ->rows(5)
                ->columnSpanFull(),

            Toggle::make('ist_intern')
                ->label('Interne Notiz')
                ->default(true)
                ->helperText('Interne Notizen bleiben im Team. Wird der Kundenbereich später freigeschaltet, sehen Kunden nur Kommentare, bei denen dieser Schalter aus ist.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('autor.name')
                    ->label('Von')
                    ->placeholder('—'),

                TextColumn::make('body')
                    ->label('Kommentar')
                    ->wrap()
                    // Ohne Begrenzung sprengt ein langer Kommentar die
                    // Zeilenhöhe der ganzen Tabelle.
                    ->limit(300),

                IconColumn::make('ist_intern')
                    ->label('Intern')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('gray')
                    ->falseColor('info'),

                TextColumn::make('created_at')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Kommentar schreiben')
                    // Der Urheber wird gesetzt, nicht ausgewählt — sonst
                    // könnte man einen Kommentar unter fremdem Namen anlegen.
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Noch keine Kommentare')
            ->emptyStateDescription('Halte hier fest, was zum Ticket besprochen wurde.');
    }
}

<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\Comment;
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

    /**
     * Der Gesprächsfaden am Ticket — unsere Seite davon.
     *
     * Anders als im Kundenbereich stehen hier auch die internen Notizen. Zwei
     * Dinge sind deshalb wichtig und beide waren einmal falsch: Kommentare
     * werden vollständig angezeigt (ein abgeschnittener Kommentar ist ein
     * verlorener), und was der Kunde geschrieben hat, ändert bei uns niemand.
     */
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
                // Der Text ist wichtiger, als er aussieht: seit der
                // Kundenbereich läuft, entscheidet dieser Schalter darüber,
                // ob der Kunde den Kommentar liest UND eine Benachrichtigung
                // darüber bekommt. Ein vergessener Schalter ist hier kein
                // Schönheitsfehler.
                ->helperText('An: bleibt im Team. Aus: der Kunde sieht den Kommentar in seinem Bereich als Antwort und wird darüber benachrichtigt.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('autor.name')
                    ->label('Von')
                    ->placeholder('—'),

                // Vollständig und ohne "…". Vorher stand hier limit(300),
                // mit der Begründung, ein langer Kommentar sprenge die
                // Zeilenhöhe — was stimmt, aber den falschen Preis hat: es
                // gab keinen Weg, den Rest zu lesen. Ein abgeschnittener
                // Kommentar ist kein kürzerer Kommentar, sondern ein
                // verlorener, und ausgerechnet der lange enthält das, worauf
                // es ankommt.
                TextColumn::make('body')
                    ->label('Kommentar')
                    ->wrap(),

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
                // Beide fragen die CommentPolicy: bearbeiten darf man nur den
                // eigenen Beitrag, löschen zusätzlich der Administrator. Was
                // ein Kunde geschrieben hat, ändert hier niemand — dort steht
                // seine Aussage, nicht unsere.
                EditAction::make()
                    ->label('Bearbeiten')
                    ->visible(fn (Comment $record) => auth()->user()?->can('update', $record) ?? false),

                DeleteAction::make()
                    ->label('Löschen')
                    ->visible(fn (Comment $record) => auth()->user()?->can('delete', $record) ?? false),
            ])
            ->emptyStateHeading('Noch keine Kommentare')
            ->emptyStateDescription('Halte hier fest, was zum Ticket besprochen wurde.');
    }
}

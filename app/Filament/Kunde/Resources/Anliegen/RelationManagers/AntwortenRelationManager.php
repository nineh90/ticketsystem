<?php

namespace App\Filament\Kunde\Resources\Anliegen\RelationManagers;

use App\Models\Comment;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Der Gesprächsfaden zu einem Anliegen.
 *
 * Hier liegt die heikelste Stelle des ganzen Kundenbereichs: dieselbe Tabelle
 * enthält interne Notizen. Sie wird an zwei Stellen abgesichert — beim Lesen
 * über den Scope fuerKunden(), beim Schreiben über ist_intern = false. Beides
 * ist nötig: der Scope allein ließe einen Kunden eine Antwort schreiben, die
 * er anschließend selbst nicht mehr sieht (weil ist_intern am Model auf true
 * vorbelegt ist), und die feste Belegung allein ließe ihn interne Notizen
 * lesen.
 */
class AntwortenRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Antworten';

    protected static ?string $modelLabel = 'Antwort';

    protected static ?string $pluralModelLabel = 'Antworten';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    /**
     * Filament schaltet Relation Manager auf Ansehen-Seiten stumm, sobald es
     * eine Bearbeiten-Seite gibt — hier gibt es keine, aber der Standard
     * greift trotzdem vorsichtshalber. Ohne diese Zeile verschwindet der
     * Knopf zum Antworten wortlos, und der Kundenbereich wäre eine
     * Einbahnstraße.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('Ihre Antwort')
                ->required()
                ->rows(6)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Die Sperre beim Lesen. fuerKunden() ist ein Scope am Model und
            // keine Bedingung, die man hier hinschreibt — damit dieselbe
            // Regel gilt, wo immer Kommentare an Kunden gehen.
            ->modifyQueryUsing(fn ($query) => $query->fuerKunden()->with('autor'))
            ->columns([
                TextColumn::make('autor.name')
                    ->label('Von')
                    ->placeholder('Nils-Digital')
                    ->weight('medium')
                    ->description(fn (Comment $record) => $record->created_at?->format('d.m.Y H:i')),

                TextColumn::make('body')
                    ->label('Nachricht')
                    ->wrap()
                    ->limit(600),
            ])
            // Älteste oben: ein Gespräch liest man von vorn.
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->headerActions([
                CreateAction::make()
                    ->label('Antworten')
                    ->icon('heroicon-o-paper-airplane')
                    ->modalHeading('Antwort schreiben')
                    ->modalSubmitActionLabel('Absenden')
                    // Die Sperre beim Schreiben. Beides wird gesetzt und
                    // nicht gewählt: der Urheber, damit niemand unter fremdem
                    // Namen schreibt, und ist_intern, weil es am Model auf
                    // true vorbelegt ist — eine Antwort des Kunden, die als
                    // interne Notiz landet, wäre für ihn im selben Moment
                    // wieder unsichtbar.
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['ist_intern'] = false;

                        return $data;
                    }),
            ])
            // Kein Bearbeiten, kein Löschen. Ein Gesprächsverlauf, in dem
            // nachträglich etwas verschwindet, ist als Beleg wertlos — für
            // beide Seiten.
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('Noch keine Antworten')
            ->emptyStateDescription('Alles, was wir zu diesem Anliegen schreiben, erscheint hier — und Ihre Antworten ebenso.');
    }
}

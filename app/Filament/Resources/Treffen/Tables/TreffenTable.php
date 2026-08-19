<?php

namespace App\Filament\Resources\Treffen\Tables;

use App\Models\Treffen;
use App\Support\Messe;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * Die Liste aller Treffen.
 *
 * Vorgabe ist "was noch kommt", aufsteigend — ein Kalender, der mit dem
 * ältesten Termin aufmacht, beantwortet die falsche Frage. Vergangenes ist
 * über den Filter erreichbar und nicht gelöscht: woran man im Mai gesessen
 * hat, ist im August die Antwort auf "haben wir darüber schon gesprochen".
 */
class TreffenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('beginnt_am')
            ->columns([
                TextColumn::make('beginnt_am')
                    ->label('Wann')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->description(fn (Treffen $record) => $record->istAbgesagt()
                        ? 'abgesagt'
                        : $record->beginnt_am->diffForHumans())
                    ->color(fn (Treffen $record) => match (true) {
                        $record->istAbgesagt() => 'gray',
                        $record->laeuft() => 'success',
                        $record->istVorbei() => 'gray',
                        default => null,
                    }),

                TextColumn::make('titel')
                    ->label('Worum')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (Treffen $record) => $record->project?->name)
                    ->wrap(),

                // Der Platzhalter ist hier die Information: kein Kunde heißt
                // Team-Besprechung, nicht "fehlt noch".
                TextColumn::make('customer.name')
                    ->label('Mit')
                    ->placeholder('nur wir')
                    ->badge()
                    ->color(fn (Treffen $record) => $record->istIntern() ? 'gray' : 'primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('crew.name')
                    ->label('Dabei')
                    ->badge()
                    ->separator(',')
                    ->placeholder('niemand'),

                TextColumn::make('dauer_minuten')
                    ->label('Dauer')
                    ->suffix(' Min.')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('kunden_sichtbar')
                    ->label('Eingeladen')
                    ->boolean()
                    // Ohne Kunden gibt es niemanden einzuladen — ein Kreuz
                    // dort läse sich wie ein Versäumnis.
                    ->placeholder('—')
                    ->state(fn (Treffen $record) => $record->istIntern() ? null : $record->kunden_sichtbar),
            ])
            ->filters([
                Filter::make('bevorstehend')
                    ->label('Nur was noch kommt')
                    ->default()
                    ->query(fn (Builder $query) => $query->bevorstehend()),

                TernaryFilter::make('customer_id')
                    ->label('Art')
                    ->placeholder('Alle')
                    ->trueLabel('Mit Kunden')
                    ->falseLabel('Nur intern')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('customer_id'),
                        false: fn (Builder $q) => $q->whereNull('customer_id'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                Action::make('anBord')
                    ->label('An Bord')
                    ->icon('heroicon-o-video-camera')
                    ->color('success')
                    ->url(fn (Treffen $record) => $record->url)
                    ->openUrlInNewTab()
                    ->visible(fn (Treffen $record) => filled($record->url) && ! $record->istAbgesagt()),

                Action::make('kalender')
                    ->label('Kalender')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->url(fn (Treffen $record) => route('treffen.kalender', $record)),

                EditAction::make()
                    ->label('Ändern')
                    ->mutateRecordDataUsing(function (array $data, Treffen $record): array {
                        $data['crew_ids'] = $record->crew()->pluck('users.id')->all();

                        return $data;
                    })
                    ->using(function (Treffen $record, array $data): Treffen {
                        $crew = Arr::pull($data, 'crew_ids', []);

                        $record->update($data);

                        Messe::crewSetzen($record, $crew);

                        return $record;
                    }),

                /*
                 * Absagen statt löschen — bei einem Kundentermin verschwindet
                 * er sonst wortlos aus dessen Bereich, und der Kunde sitzt um
                 * zwei Uhr trotzdem davor.
                 */
                Action::make('absagen')
                    ->label('Absagen')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Treffen absagen')
                    ->modalDescription('Alle Beteiligten bekommen eine Meldung. Der Termin bleibt stehen, durchgestrichen.')
                    ->modalSubmitActionLabel('Absagen')
                    ->visible(fn (Treffen $record) => ! $record->istAbgesagt() && ! $record->istVorbei())
                    ->action(function (Treffen $record): void {
                        $record->update(['abgesagt_at' => now()]);

                        Notification::make()->title('Treffen abgesagt')->success()->send();
                    }),

                Action::make('wiederAnsetzen')
                    ->label('Doch wieder')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Treffen $record) => $record->istAbgesagt())
                    ->action(fn (Treffen $record) => $record->update(['abgesagt_at' => null])),

                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Nichts angesetzt')
            ->emptyStateDescription('Setze ein Treffen an — mit einem Kunden oder nur für uns.')
            ->emptyStateIcon('heroicon-o-video-camera');
    }
}

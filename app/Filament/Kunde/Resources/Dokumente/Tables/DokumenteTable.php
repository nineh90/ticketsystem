<?php

namespace App\Filament\Kunde\Resources\Dokumente\Tables;

use App\Enums\DokumentArt;
use App\Models\Dokument;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Die Dokumentenliste, wie der Kunde sie sieht.
 *
 * Deutlich weniger Spalten als innen — und vor allem keine, die ihn nichts
 * angeht: kein Dateiname, keine Größe, keine interne Notiz, kein Hinweis
 * darauf, dass es weitere Dokumente gibt, die nicht freigegeben sind.
 *
 * Ganz rechts steht bei einem offenen Angebot der Hinweis, dass wir auf ihn
 * warten. Er ist der Grund, warum die Liste überhaupt eine Statusspalte hat:
 * "Offen" allein sagt einem Kunden nichts, "Wartet auf Ihre Antwort" schon.
 */
class DokumenteTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('art')
                    ->label('Art')
                    ->badge()
                    ->sortable(),

                TextColumn::make('titel')
                    ->label('Titel')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (Dokument $record) => collect([
                        $record->nummer,
                        $record->project?->name,
                    ])->filter()->implode(' · ') ?: null),

                TextColumn::make('datum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('betrag')
                    ->label('Betrag')
                    ->state(fn (Dokument $record) => $record->betragLesbar())
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable(),

                // Der Stand in seinen Worten, nicht in unseren. "Offen" heißt
                // bei einem Angebot etwas anderes als bei einer Rechnung, und
                // beide Male ist die Frage an ihn eine andere.
                TextColumn::make('stand')
                    ->label('Stand')
                    ->badge()
                    ->state(fn (Dokument $record) => self::standText($record))
                    ->color(fn (Dokument $record) => $record->wartetAufAntwort()
                        ? 'warning'
                        : ($record->istUeberfaellig() ? 'danger' : $record->stand?->getColor()))
                    ->placeholder('—'),
            ])
            ->defaultSort('datum', 'desc')
            ->filters([
                SelectFilter::make('art')
                    ->label('Art')
                    ->options(DokumentArt::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Ansehen'),

                Action::make('herunterladen')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Dokument $record) => $record->url())
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Keine Dokumente')
            ->emptyStateDescription('Hier erscheinen Angebote, Rechnungen und Verträge, sobald wir Ihnen welche bereitstellen.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /**
     * Der Stand in Worten, die für den Empfänger gelten.
     *
     * Eine Rechnung ist für uns "offen", für ihn "unbezahlt" — und ein
     * Angebot, das offen ist, ist für ihn eine Frage, die noch aussteht.
     * Dieselbe Zeichenkette für beides wäre kürzer und würde ihn zweimal
     * ratlos lassen.
     */
    private static function standText(Dokument $record): ?string
    {
        if ($record->wartetAufAntwort()) {
            return 'Wartet auf Ihre Antwort';
        }

        if ($record->istUeberfaellig()) {
            return 'Offen seit '.$record->faellig_am->format('d.m.Y');
        }

        return $record->stand?->getLabel();
    }
}

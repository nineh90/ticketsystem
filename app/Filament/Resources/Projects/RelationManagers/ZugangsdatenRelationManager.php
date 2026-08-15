<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Customers\Schemas\ZugangsdatenForm;
use App\Models\Project;
use App\Models\Zugangsdaten;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Zugangsdaten zu genau diesem Projekt — der Login zur Vorschau, das
 * WordPress der neuen Seite.
 *
 * Dieselben Einträge wie im Tresor des Kunden, nur auf dieses Projekt
 * eingeschränkt. Sie hier zu haben ist der Unterschied zwischen "beim Kunden
 * nachsehen, welcher der vier Logins zu dieser Seite gehört" und "steht
 * daneben".
 */
class ZugangsdatenRelationManager extends RelationManager
{
    protected static string $relationship = 'zugangsdaten';

    protected static ?string $title = 'Zugangsdaten';

    protected static ?string $modelLabel = 'Zugang';

    protected static ?string $pluralModelLabel = 'Zugangsdaten';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-lock-closed';

    public function form(Schema $schema): Schema
    {
        // Ohne Projektauswahl: das Projekt ergibt sich daraus, wo man steht.
        return ZugangsdatenForm::configure($schema, mitProjektauswahl: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Nur die Zugänge zu diesem Projekt. Hoster- und Serverzugänge des Kunden stehen beim Kunden.')
            ->columns([
                TextColumn::make('bezeichnung')
                    ->label('Wofür')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('benutzername')
                    ->label('Benutzer')
                    ->copyable()
                    ->copyMessage('Kopiert')
                    ->placeholder('—'),

                TextColumn::make('passwort')
                    ->label('Passwort')
                    ->formatStateUsing(fn () => '••••••••')
                    ->copyable()
                    ->copyableState(fn (Zugangsdaten $record) => $record->passwort)
                    ->copyMessage('Passwort kopiert')
                    ->tooltip('Klicken kopiert das Passwort')
                    ->placeholder('—'),

                IconColumn::make('kunden_sichtbar')
                    ->label('Kunde sieht es')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->inReihenfolge())
            ->headerActions([
                CreateAction::make()
                    ->label('Zugang hinterlegen')
                    // Der Kunde ergibt sich aus dem Projekt. Ihn hier zur
                    // Auswahl zu stellen hieße, einen Eintrag beim falschen
                    // Kunden ablegen zu können — und damit im falschen
                    // Kundenbereich.
                    ->mutateDataUsing(function (array $daten): array {
                        /** @var Project $projekt */
                        $projekt = $this->getOwnerRecord();
                        $daten['customer_id'] = $projekt->customer_id;

                        return $daten;
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-lock-closed')
            ->emptyStateHeading('Noch keine Zugangsdaten')
            ->emptyStateDescription('Der Login zur Vorschau gehört hierhin — mit dem Schalter „der Kunde darf das sehen" steht er in seinem Bereich.');
    }
}

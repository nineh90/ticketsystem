<?php

namespace App\Filament\Formulare;

use App\Enums\Rolle;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Die Felder eines Treffens — einmal geschrieben, an zwei Stellen benutzt.
 *
 * Es gibt zwei Wege zu einem Termin: den Reiter *Messe* an der Kundenakte
 * (dort steht der Kunde schon fest) und die Seite *Messe* im Menü (dort ist
 * er eine Auswahl und darf leer bleiben — Team-Besprechung). Zwei Formulare
 * dafür wären zwei Fassungen, und die zweite ist die, die eine spätere
 * Änderung nicht mitbekommt.
 *
 * Der Unterschied steckt allein in $mitKundenwahl.
 */
class Treffenformular
{
    /**
     * @param  bool  $mitKundenwahl  Auf der Messe-Seite ja, am Kunden nein —
     *                               dort steht er über die Beziehung fest.
     * @return array<int, mixed>
     */
    public static function felder(bool $mitKundenwahl, ?int $customerId = null): array
    {
        return array_values(array_filter([
            TextInput::make('titel')
                ->label('Worum geht es?')
                ->required()
                ->maxLength(255)
                ->columnSpanFull()
                ->placeholder('Wochenplanung')
                ->helperText('Steht so beim Kunden, wenn einer dabei ist.'),

            $mitKundenwahl ? Select::make('customer_id')
                ->label('Mit welchem Kunden?')
                ->options(fn () => Customer::query()
                    ->sichtbarFuer(auth()->user())
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->live()
                // Der Kern der Seite: leer heißt Team-Besprechung. Deshalb
                // steht das im Platzhalter und nicht nur im Hilfetext — wer
                // eine Auswahl sieht, sucht sonst nach dem richtigen Eintrag,
                // statt sie leer zu lassen.
                ->placeholder('Niemand — nur wir (Team-Besprechung)')
                ->afterStateUpdated(fn (Set $set) => $set('project_id', null))
                ->helperText('Leer lassen für alles, was nur uns betrifft: Planung, Retro, ein Gespräch zu zweit.')
                : null,

            DateTimePicker::make('beginnt_am')
                ->label('Wann')
                ->required()
                ->native(false)
                ->seconds(false)
                ->displayFormat('d.m.Y H:i')
                ->minutesStep(15),

            TextInput::make('dauer_minuten')
                ->label('Dauer')
                ->numeric()
                ->required()
                ->default(30)
                ->minValue(5)
                ->maxValue(480)
                ->suffix('Minuten'),

            TextInput::make('url')
                ->label('Wo')
                ->url()
                ->maxLength(255)
                ->columnSpanFull()
                ->placeholder('https://meet.google.com/…')
                // Ausdrücklich irgendein Link und nicht "Google Meet": was
                // hinter dem Knopf liegt, soll austauschbar sein, ohne dass
                // hier ein Feld umbenannt werden muss.
                ->helperText('Der Link zur Besprechung. Dorthin führt der Knopf "An Bord gehen".'),

            Select::make('project_id')
                ->label('Projekt')
                ->options(function (Get $get) use ($customerId) {
                    $kunde = $customerId ?? $get('customer_id');

                    return $kunde === null
                        ? []
                        : Project::query()
                            ->where('customer_id', $kunde)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                })
                ->searchable()
                ->placeholder('Kein bestimmtes')
                // Ohne Kunde gibt es kein Projekt. Ein leeres Auswahlfeld
                // daneben sähe aus, als wären die Projekte nicht geladen.
                ->visible(fn (Get $get) => ($customerId ?? $get('customer_id')) !== null)
                ->helperText('Optional. Das Quartalsgespräch gehört zum Kunden, die Abnahme zum Projekt.'),

            Select::make('crew_ids')
                ->label('Wer von uns dabei ist')
                ->multiple()
                ->options(fn () => User::query()
                    ->where('aktiv', true)
                    ->where('rolle', '!=', Rolle::Kunde->value)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->default([auth()->id()])
                ->columnSpanFull()
                // Wer dazukommt, bekommt eine Meldung — und Mail, falls er
                // "Meine Treffen" angehakt hat. Wer sich selbst einträgt,
                // nicht: er füllt gerade das Formular aus.
                ->helperText('Sie bekommen den Termin an die Glocke und stehen unter "Meine Wache".'),

            Toggle::make('kunden_sichtbar')
                ->label('Kunden einladen')
                ->inline(false)
                // Der Schalter IST die Einladung: springt er an, geht die
                // Meldung hinaus (TreffenObserver). Ohne Kunden hat er
                // nichts zu tun und wäre nur eine Frage ohne Adressaten.
                ->visible(fn (Get $get) => ($customerId ?? $get('customer_id')) !== null)
                ->helperText('Erst damit steht das Treffen beim Kunden — und er bekommt eine Meldung.'),

            Textarea::make('notiz')
                ->label('Tagesordnung')
                ->rows(3)
                ->columnSpanFull()
                ->helperText('Optional. Sieht der Kunde ebenfalls, wenn einer dabei ist — also nichts Internes hier hinein.'),
        ]));
    }
}

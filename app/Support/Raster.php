<?php

namespace App\Support;

/**
 * Die Breiten, in denen Dashboard-Widgets stehen.
 *
 * An einer Stelle, weil vier Widgets sich dieselbe Aufteilung teilen und ein
 * halb umgestelltes Raster schlimmer aussieht als gar keines.
 */
class Raster
{
    /**
     * Halbe Breite — aber erst ab xl.
     *
     * Filaments Dashboard teilt schon ab lg (1024 px) in zwei Spalten. Für
     * eine Ticketliste mit Titel, Kunde und Projekt ist die Hälfte davon zu
     * schmal: die Titel brechen dann nach jedem zweiten Wort um. Deshalb
     * darunter volle Breite und untereinander, was auch dem entspricht, was
     * ein Laptopbildschirm oder ein hoch eingestellter Zoom hergibt.
     *
     * @var array<string, string|int>
     */
    public const HALB = ['default' => 'full', 'xl' => 1];
}

<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatare aus Initialen, lokal erzeugt.
 *
 * Filament liefert von Haus aus UiAvatarsProvider aus, der für jeden Namen
 * eine Grafik von ui-avatars.com holt. Das heißt: bei jedem Seitenaufruf
 * wandern die Klarnamen der Mitarbeiter an einen Drittanbieter — und weil
 * unsere CSP img-src auf 'self' begrenzt, kommt ohnehin nur ein kaputtes Bild
 * an. Beides erledigt sich mit einem SVG als data:-URI.
 *
 * Kein Netzwerk, kein Cache-Problem, funktioniert unter der CSP.
 */
class InitialenAvatar implements AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));

        $svg = $this->svg($this->initialen($name), $this->farbe($name));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Erste Buchstaben von Vor- und Nachname, maximal zwei. */
    private function initialen(string $name): string
    {
        $teile = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($teile === []) {
            return '?';
        }

        $erste = Str::upper(Str::substr($teile[0], 0, 1));

        if (count($teile) === 1) {
            return $erste;
        }

        return $erste.Str::upper(Str::substr(end($teile), 0, 1));
    }

    /**
     * Farbe aus dem Namen ableiten, damit dieselbe Person immer dieselbe
     * bekommt. Die Palette bleibt im Farbraum der Marke (Cyan-Familie plus
     * ein paar verträgliche Nachbarn), damit die Oberfläche nicht bunt wird.
     */
    private function farbe(string $name): string
    {
        $palette = ['#00bcd4', '#0891b2', '#0ea5e9', '#14b8a6', '#6366f1', '#8b5cf6'];

        return $palette[crc32($name) % count($palette)];
    }

    private function svg(string $initialen, string $farbe): string
    {
        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
                <rect width="100" height="100" fill="{$farbe}"/>
                <text x="50" y="50" fill="#0d1117" font-family="sans-serif" font-size="42"
                      font-weight="600" text-anchor="middle" dominant-baseline="central">{$initialen}</text>
            </svg>
            SVG;
    }
}

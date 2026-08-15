<?php

namespace App\Filament\AvatarProviders;

use App\Models\User;
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
 *
 * Kundenzugänge bekommen statt der Initialen das Logo ihres Kunden, sofern
 * eines hinterlegt ist. Damit sieht man in einer Ticketliste am Bild, aus
 * welchem Haus jemand schreibt, statt zwei Buchstaben zu entziffern.
 */
class InitialenAvatar implements AvatarProvider
{
    /**
     * Gelesene Kundenlogos, für die Dauer einer Anfrage gemerkt.
     *
     * Der Avatar wird je Zeile einmal geholt; eine Ticketliste mit zwanzig
     * Kommentaren derselben Kundin fragte sonst zwanzigmal nach demselben
     * Logo. Der Merker lebt nur innerhalb der Anfrage — ein Logo, das gerade
     * getauscht wird, ist beim nächsten Aufruf da.
     *
     * @var array<int, string|null>
     */
    private static array $logos = [];

    public function get(Model|Authenticatable $record): string
    {
        if ($logo = $this->kundenlogo($record)) {
            return $logo;
        }

        $name = trim((string) Filament::getNameForDefaultAvatar($record));

        $svg = $this->svg($this->initialen($name), $this->farbe($name));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Das Logo des Kunden, zu dem dieser Zugang gehört.
     *
     * Nur für Kundenzugänge. Ein Mitarbeiter behält seine Initialen — er
     * gehört zu keinem Kunden, und das Logo soll genau die Aussage tragen
     * "hier schreibt jemand von dort". Steht kein Logo bereit, fällt es auf
     * die Initialen zurück; ein leerer Kreis wäre schlechter als ein Kürzel.
     */
    private function kundenlogo(Model|Authenticatable $record): ?string
    {
        if (! $record instanceof User || ! $record->istKunde() || $record->customer_id === null) {
            return null;
        }

        return self::$logos[$record->customer_id] ??= $record->customer?->logoUrl();
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

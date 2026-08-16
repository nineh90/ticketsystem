<?php

namespace App\Filament\Formulare;

use App\Models\Attachment;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Das Dateifeld für Formulare, in denen es das Ticket noch gar nicht gibt.
 *
 * Beim Anlegen fehlt die Ticketnummer, und damit fehlt der Ordner, in den die
 * Datei gehört. Deshalb landet sie zuerst im Zwischenlager und wird nach dem
 * Anlegen umgehängt — siehe NimmtDateienEntgegen, das die zweite Hälfte
 * erledigt. Beide Seiten benutzen dieselben Regeln: dieselben Dateitypen,
 * dieselbe Höchstgröße, derselbe Namensaufbau. Zwei Fassungen, die sich um
 * ein akzeptiertes Format unterscheiden, fallen erst dem Kunden auf.
 */
class Anhangfeld
{
    /** Wohin Dateien gehen, solange es noch kein Ticket gibt. */
    public const ZWISCHENLAGER = 'anhaenge/eingang';

    /**
     * 16 MB, passend zu upload_max_filesize in deploy/php.ini. Ein höherer
     * Wert hier führte nur dazu, dass PHP die Datei stillschweigend verwirft.
     */
    public const MAX_KB = 16 * 1024;

    /** @var list<string> */
    public const TYPEN = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    public static function machen(string $name = 'dateien'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Dateien')
            // Mehrere auf einmal, auch per Ziehen und Ablegen.
            ->multiple()
            ->disk(Attachment::PLATTE)
            ->directory(self::ZWISCHENLAGER)
            ->visibility('private')
            // Der Parameter MUSS $file heißen: Filament reicht Werte an solche
            // Closures über den Parameternamen durch, nicht über den Typ
            // (BaseFileUpload::getUploadedFileNameForStorage, 'file' => $file).
            // Bei jedem anderen Namen versucht der Container,
            // TemporaryUploadedFile selbst zu bauen, und der Upload scheitert
            // mit "Unresolvable dependency resolving [$path]" — ohne dass die
            // Meldung auf die Ursache zeigt.
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => self::ablagename($file->getClientOriginalName()),
            )
            // Kein ->image(): das setzt die erlaubten Typen auf image/* und
            // schlösse PDF aus. Ein ausgedruckter Fehler kommt durchaus vor.
            ->imagePreviewHeight('120')
            ->openable()
            ->acceptedFileTypes(self::TYPEN)
            ->maxSize(self::MAX_KB)
            ->maxFiles(10)
            ->columnSpanFull();
    }

    /**
     * Der Name auf der Platte: Zufallsvorsatz, "__", entschärfter Klarname.
     *
     * Der Vorsatz hält zwei "screenshot.png" auseinander und verhindert, dass
     * man über einen geratenen Namen an eine fremde Datei kommt. Der Klarname
     * dahinter ist das, was später in der Liste steht — ohne ihn sähe man
     * lauter Zufallsketten.
     */
    public static function ablagename(string $klarname): string
    {
        return Str::random(24)
            .'__'
            // Nur harmlose Zeichen: Schrägstriche oder ".." dürfen niemals in
            // einem Pfad landen.
            .Str::of($klarname)
                ->replaceMatches('/[^\p{L}\p{N}._-]+/u', '-')
                ->limit(80, '');
    }

    /**
     * Aus dem Ablagenamen den Klarnamen zurückholen.
     *
     * Fehlt der Trenner — etwa bei einer von Hand abgelegten Datei —, bleibt
     * der ganze Basisname stehen.
     */
    public static function anzeigename(string $pfad): string
    {
        $basis = basename($pfad);

        return str_contains($basis, '__') ? Str::after($basis, '__') : $basis;
    }
}

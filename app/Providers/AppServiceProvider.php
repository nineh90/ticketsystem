<?php

namespace App\Providers;

use App\Notifications\PasswortZuruecksetzen;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Die Reset-Mail ohne Warteschlange verschicken.
         *
         * Filament erzeugt seine Meldung über den Container
         * (RequestPasswordReset: app(ResetPassword::class, ['token' => …])),
         * und weil sie ein ShouldQueue ist, bliebe sie in der jobs-Tabelle
         * liegen — es läuft kein Worker, weder lokal noch auf dem Server.
         * Diese Zeile ist die einzige Stelle, an der sich das abfangen lässt,
         * ohne Filaments Seite nachzubauen. Begründung ausführlich in der
         * ersetzenden Klasse.
         */
        $this->app->bind(FilamentResetPassword::class, PasswortZuruecksetzen::class);
    }

    public function boot(): void
    {
        /*
         * Produktiv jede erzeugte Adresse mit https aufbauen.
         *
         * TLS endet bei Traefik, im Container kommt die Anfrage als http an.
         * trustProxies in bootstrap/app.php behebt das für alles, was WÄHREND
         * einer Anfrage entsteht — Provider laufen aber vorher. Genau daran
         * hing das Favicon: es wurde im Panel-Provider mit asset() gebaut,
         * kam mit http:// heraus und ließ den Browser die ganze Seite als
         * nicht sicher melden, obwohl das Zertifikat einwandfrei war.
         *
         * Diese Zeile ist die allgemeine Absicherung dagegen. Lokal bleibt
         * sie aus, sonst wäre die Entwicklung über http nicht mehr benutzbar.
         */
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

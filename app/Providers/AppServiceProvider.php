<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

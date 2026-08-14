<?php

use App\Http\Middleware\SicherheitsHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS endet bei Traefik; hier kommt die Anfrage als http an. Ohne
        // dieses Vertrauen hält Laravel die Verbindung für unverschlüsselt
        // und baut Weiterleitungen und Asset-Adressen mit http:// — der
        // Browser wird dann von Traefik ein zweites Mal umgeleitet. Außerdem
        // bliebe der HSTS-Header aus, weil SicherheitsHeader ihn an
        // $request->secure() knüpft.
        //
        // Alle Proxys zu vertrauen ist hier vertretbar: der Container
        // veröffentlicht keinen Port, Traefik ist der einzige Weg hinein.
        $middleware->trustProxies(at: '*');

        // Laravels auth-Middleware leitet Gäste auf eine Route namens
        // "login". Die gibt es hier nicht — Filaments Anmeldung heißt
        // filament.admin.auth.login. Ohne diese Zeile endet jeder
        // unangemeldete Aufruf einer eigenen Route (etwa eines Anhangs) in
        // einem 500er statt auf der Anmeldeseite.
        //
        // Seit es zwei Panels gibt, entscheidet der Pfad, auf welche der
        // beiden Anmeldungen es geht. Das ist kein Schönheitsfehler: ein
        // Kunde kommt an der internen Anmeldung nicht vorbei
        // (User::canAccessPanel), er stünde dort also vor einem Formular,
        // das seine richtigen Zugangsdaten abweist — und würde annehmen,
        // sein Zugang sei kaputt.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('kunde', 'kunde/*')
            ? route('filament.kunde.auth.login')
            : route('filament.admin.auth.login'));

        // Gilt für jede Web-Antwort, also auch für die Filament-Oberfläche.
        // Die Middleware fasst nur HTML an, Downloads und JSON bleiben unberührt.
        $middleware->web(append: [
            SicherheitsHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

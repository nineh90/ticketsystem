<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Anmeldung;
use App\Filament\AvatarProviders\InitialenAvatar;
use App\Filament\Kunde\Pages\Uebersicht;
use App\Filament\Kunde\Widgets\MeineProjekte;
use App\Filament\Kunde\Widgets\StandDerDinge;
use App\Http\Middleware\SicherheitsHeader;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Der Kundenbereich unter /kunde.
 *
 * Ein zweites Panel und nicht bloß eine Rolle mit weniger Menüpunkten. Der
 * Unterschied ist nicht kosmetisch: das interne Panel hat rund zwanzig
 * Ressourcen, Relation Manager und Widgets, und jedes davon müsste sonst
 * einzeln wissen, dass es sich vor einem Kunden anders zu verhalten hat.
 * Eines, das es vergisst, zeigt einem Kunden Zeitbuchungen oder interne
 * Notizen. Hier gilt die Umkehrung: was der Kunde sehen darf, steht unter
 * app/Filament/Kunde und ist einzeln aufgeschrieben — und alles, was dort
 * nicht steht, existiert für ihn nicht.
 *
 * Die zweite Verteidigungslinie sitzt in User::canAccessPanel(): dort ist
 * festgelegt, dass in dieses Panel ausschließlich die Rolle "kunde" kommt,
 * und in das interne ausschließlich alle anderen.
 */
class KundePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('kunde')
            ->path('kunde')
            // Eigener Guard, damit die Anmeldung hier neben der internen
            // besteht statt sie zu verdrängen. Begründung ausführlich in
            // config/auth.php.
            ->authGuard('kunde')
            // Dasselbe Theme wie innen. Der Kunde soll dieselbe Marke sehen,
            // die er von nils-digital.de kennt — ein zweites, eigenes Theme
            // wäre eine zweite Datei, die bei jeder Änderung an der Marke
            // mitgepflegt werden müsste und es nach der zweiten Änderung
            // nicht mehr wird. Der Ordnername sagt "admin", gemeint ist
            // "unser Aussehen".
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Dieselbe Anmeldeseite wie innen — sie weist einen internen
            // Zugang hier ebenso freundlich zurück wie umgekehrt.
            ->login(Anmeldung::class)
            // Jeder Kunde ändert sein Startpasswort selbst. Das ist der Grund,
            // warum ein Admin überhaupt eines vergeben darf: es ist von Anfang
            // an als vorläufig gedacht.
            ->profile(isSimple: false)
            // ->passwordReset() bleibt aus, solange MAIL_MAILER auf "log"
            // steht — genau wie im internen Panel. Vergisst ein Kunde sein
            // Passwort, setzt es ein Admin unter Kunden → Zugänge neu.
            ->brandName('Nils-Digital')
            ->brandLogo(fn () => view('filament.marke'))
            ->favicon('/favicon-32x32.png')
            ->colors([
                'primary' => Color::hex('#00bcd4'),
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->defaultAvatarProvider(InitialenAvatar::class)
            // Waagerechte Navigation statt Seitenleiste. Der Kundenbereich hat
            // vier Punkte; eine Seitenleiste dafür sieht aus wie ein Werkzeug,
            // in dem man sich zurechtfinden muss, und das ist genau der
            // Eindruck, den er nicht machen soll.
            ->topNavigation()
            ->maxContentWidth('7xl')
            // Die Glocke: hier landet, was wir dem Kunden mitzuteilen haben —
            // eine Antwort auf sein Anliegen, ein Statuswechsel. Ohne sie
            // müsste er von sich aus nachsehen, ob sich etwas getan hat.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->discoverResources(in: app_path('Filament/Kunde/Resources'), for: 'App\Filament\Kunde\Resources')
            ->discoverPages(in: app_path('Filament/Kunde/Pages'), for: 'App\Filament\Kunde\Pages')
            ->pages([
                Uebersicht::class,
            ])
            ->widgets([
                StandDerDinge::class,
                MeineProjekte::class,
            ])
            // Keine globale Suche: sie durchsucht alles, was als Ressource
            // registriert ist, und ist damit genau die Art von Abkürzung, an
            // der eine Sichtbarkeitsregel unbemerkt vorbeiläuft. Bei vier
            // Menüpunkten fehlt sie ohnehin niemandem.
            ->globalSearch(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Muss auch hier stehen: Filament baut jedem Panel seinen
                // eigenen Middleware-Stack und durchläuft die "web"-Gruppe
                // nicht. Siehe denselben Hinweis im AdminPanelProvider.
                SicherheitsHeader::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

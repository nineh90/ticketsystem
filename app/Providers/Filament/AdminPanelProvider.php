<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Anmeldung;
use App\Filament\AvatarProviders\InitialenAvatar;
use App\Filament\Widgets\Geschehen;
use App\Filament\Widgets\MeineTickets;
use App\Filament\Widgets\MeinUeberblick;
use App\Filament\Widgets\TeamUeberblick;
use App\Filament\Widgets\TicketsVerteilung;
use App\Filament\Widgets\VonKunden;
use App\Http\Middleware\PasswortWechseln;
use App\Http\Middleware\SicherheitsHeader;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // Panel liegt auf der Wurzel: intern.nils-digital.de führt direkt
            // ins Dashboard bzw. auf /login. Ein späterer Kundenbereich wird
            // ein zweites Panel mit ->id('kunde')->path('kunde').
            ->path('')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Eigene Anmeldeseite: sie erkennt einen Kundenzugang und schickt
            // ihn weiter, statt ihn wie ein falsches Passwort abzuweisen.
            ->login(Anmeldung::class)
            // Eigene Profilseite: jeder ändert Name und Passwort selbst.
            // Ohne sie müsste ein Admin jedes Passwort vergeben — es ginge
            // also durch fremde Hände und wäre nie nur dem Nutzer bekannt.
            ->profile(isSimple: false)
            // ->passwordReset() bleibt aus, solange MAIL_MAILER auf "log"
            // steht: der Knopf verschickt eine Mail, die nirgends ankommt,
            // und der Nutzer wartet auf etwas, das nie kommt. Sobald der
            // Strato-SMTP hinterlegt ist, hier einkommentieren. Bis dahin
            // setzt ein Admin ein vergessenes Passwort in der
            // Nutzerverwaltung neu.
            ->brandName('Nils-Digital')
            // Die Glocke. Sie kam mit dem Kundenbereich: bis dahin entstand
            // jede Änderung durch jemanden, der ohnehin gerade davorsaß.
            // Ein Kundenanliegen entsteht dagegen, während niemand hinschaut,
            // und darf nicht erst beim nächsten Blick in die Liste auffallen.
            ->databaseNotifications()
            // Takt aus config/benachrichtigungen.php — beide Panels teilen
            // ihn, damit sie nicht auseinanderlaufen.
            ->databaseNotificationsPolling(config('benachrichtigungen.glocke_takt'))
            // Logo UND Schriftzug: Filament zeigt sonst nur eines von beiden.
            ->brandLogo(fn () => view('filament.marke'))
            // Bewusst ein wurzelrelativer Pfad statt asset(): Provider
            // laufen VOR der Middleware, trustProxies hat dort also noch
            // nicht gegriffen, und asset() baute die Adresse mit http://
            // zusammen. Ergebnis war gemischter Inhalt — der Browser meldete
            // die Seite als nicht sicher, obwohl das Zertifikat einwandfrei
            // ist. Ein relativer Pfad übernimmt schlicht das Schema der Seite.
            ->favicon('/favicon-32x32.png')
            // Markenfarbe der Website (css/main.css: --accent: #00bcd4).
            ->colors([
                'primary' => Color::hex('#00bcd4'),
            ])
            // Die Website ist dark-only, das Ticketsystem bleibt es auch.
            ->defaultThemeMode(ThemeMode::Dark)
            // Statt Filaments Voreinstellung, die für jeden Namen ein Bild von
            // ui-avatars.com holt und dabei die Klarnamen der Mitarbeiter nach
            // außen gibt.
            ->defaultAvatarProvider(InitialenAvatar::class)
            // Der Weg in den Kundenbereich, im Benutzermenü oben rechts.
            // Ohne ihn ist /kunde eine Adresse, die man kennen muss — und
            // genau daran ist die erste Anmeldung eines Kundenzugangs
            // gescheitert. Dank getrennter Guards führt der Klick nicht aus
            // der eigenen Sitzung heraus: man landet auf der Kundenanmeldung
            // und bleibt intern angemeldet.
            ->userMenuItems([
                Action::make('kundenbereich')
                    ->label('Kundenbereich ansehen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => route('filament.kunde.auth.login'))
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()?->istAdmin() ?? false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // AccountWidget und FilamentInfoWidget sind bewusst raus: das eine
            // wiederholt nur den Namen aus der Kopfleiste, das andere wirbt für
            // Filament. Beide kosten die beste Stelle des Dashboards.
            // Reihenfolge auf dem Dashboard: erst die eigenen Zahlen, daneben
            // die des Betriebs bzw. der eigenen Projekte, darunter die eigene
            // Arbeitsliste neben dem Geschehen, zuletzt die Verteilung.
            ->widgets([
                // Ganz oben und nur dann da, wenn ein Kunde tatsächlich
                // wartet — siehe VonKunden::canView().
                VonKunden::class,
                MeinUeberblick::class,
                TeamUeberblick::class,
                Geschehen::class,
                MeineTickets::class,
                TicketsVerteilung::class,
            ])
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
                // Muss hier stehen und nicht nur in bootstrap/app.php: Filament
                // baut sich seinen Middleware-Stack selbst und durchläuft die
                // "web"-Gruppe nicht. Ohne diese Zeile liefert das Panel
                // stillschweigend gar keine Sicherheits-Header aus.
                SicherheitsHeader::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Gilt auch hier: ein Mitarbeiter, dem ein Administrator ein
                // Passwort gesetzt hat, wechselt es beim ersten Anmelden.
                // Dieselbe Begründung wie im Kundenpanel — nur der Weg, auf
                // dem das Passwort ankommt, ist derselbe.
                PasswortWechseln::class,
            ]);
    }
}

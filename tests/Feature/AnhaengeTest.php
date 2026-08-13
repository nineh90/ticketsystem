<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\AnhaengeRelationManager;
use App\Models\Attachment;
use Filament\Actions\Testing\TestAction;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anhänge an Tickets.
 *
 * Der Schwerpunkt liegt auf der Zugriffsschranke: die Dateien liegen
 * außerhalb von public/, damit ein Screenshot aus einem Kundenprojekt nicht
 * für jeden abrufbar ist, der die Adresse kennt. Wenn diese Tests fallen,
 * ist genau das wieder der Fall.
 */
class AnhaengeTest extends TestCase
{
    use RefreshDatabase;

    private function anhangAn(Ticket $ticket, ?User $von = null): Attachment
    {
        Storage::fake(Attachment::PLATTE);

        $pfad = 'anhaenge/'.$ticket->id.'/abc123__screenshot.png';
        Storage::disk(Attachment::PLATTE)->put($pfad, 'nicht-wirklich-ein-bild');

        return $ticket->attachments()->create([
            'user_id' => $von?->id,
            'pfad' => $pfad,
            'dateiname' => 'screenshot.png',
            'mime' => 'image/png',
            'groesse' => 23,
        ]);
    }

    public function test_ein_ticket_traegt_beliebig_viele_anhaenge(): void
    {
        Storage::fake(Attachment::PLATTE);
        $ticket = Ticket::factory()->create();

        foreach (range(1, 5) as $i) {
            $ticket->attachments()->create([
                'pfad' => "anhaenge/{$ticket->id}/datei-{$i}.png",
                'dateiname' => "bild-{$i}.png",
                'mime' => 'image/png',
                'groesse' => 100,
            ]);
        }

        $this->assertSame(5, $ticket->attachments()->count());
        $this->assertSame(5, $ticket->bilder()->count());
    }

    public function test_bestehendes_ticket_kann_nachtraeglich_anhaenge_bekommen(): void
    {
        // Genau der Fall aus der Praxis: das Ticket ist längst da, der
        // Screenshot kommt später.
        $ticket = Ticket::factory()->create(['created_at' => now()->subMonths(2)]);

        $this->assertSame(0, $ticket->attachments()->count());

        $this->anhangAn($ticket);

        $this->assertSame(1, $ticket->fresh()->attachments()->count());
    }

    public function test_ohne_anmeldung_kein_zugriff(): void
    {
        $anhang = $this->anhangAn(Ticket::factory()->create());

        $this->get($anhang->url())->assertRedirect('/login');
    }

    public function test_fremder_mitarbeiter_kommt_nicht_an_die_datei(): void
    {
        // Der wichtigste Test hier: Anhänge erben die Rechte ihres Tickets.
        $fremder = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $anhang = $this->anhangAn(Ticket::factory()->create());

        $this->actingAs($fremder)->get($anhang->url())->assertForbidden();
    }

    public function test_zustaendiger_mitarbeiter_bekommt_die_datei(): void
    {
        $nutzer = User::factory()->create([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ]);

        $projekt = Project::factory()->create();
        $projekt->mitarbeiter()->attach($nutzer);

        $ticket = Ticket::factory()->for($projekt, 'project')->create();
        $anhang = $this->anhangAn($ticket);

        $this->actingAs($nutzer)
            ->get($anhang->url())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_admin_bekommt_jede_datei(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $anhang = $this->anhangAn(Ticket::factory()->create());

        $this->actingAs($admin)->get($anhang->url())->assertOk();
    }

    public function test_fehlende_datei_meldet_404_statt_abzustuerzen(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $anhang = $this->anhangAn(Ticket::factory()->create());
        Storage::disk(Attachment::PLATTE)->delete($anhang->pfad);

        $this->actingAs($admin)->get($anhang->url())->assertNotFound();
    }

    public function test_geloeschter_anhang_nimmt_die_datei_mit(): void
    {
        // Sonst blieben verwaiste Dateien liegen — mit genau den Daten, die
        // eigentlich weg sollten.
        $anhang = $this->anhangAn(Ticket::factory()->create());
        $pfad = $anhang->pfad;

        $this->assertTrue(Storage::disk(Attachment::PLATTE)->exists($pfad));

        $anhang->delete();

        $this->assertFalse(Storage::disk(Attachment::PLATTE)->exists($pfad));
    }

    public function test_geloeschtes_ticket_nimmt_die_anhaenge_mit(): void
    {
        $ticket = Ticket::factory()->create();
        $anhang = $this->anhangAn($ticket);

        $ticket->delete();

        $this->assertSame(0, Attachment::where('id', $anhang->id)->count());
    }

    public function test_nur_der_hochladende_oder_ein_admin_darf_loeschen(): void
    {
        $hochlader = User::factory()->create(['panel_zugang' => true]);
        $anderer = User::factory()->create(['panel_zugang' => true]);
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        $anhang = $this->anhangAn(Ticket::factory()->create(), $hochlader);

        $this->assertTrue($hochlader->can('delete', $anhang));
        $this->assertFalse($anderer->can('delete', $anhang));
        $this->assertTrue($admin->can('delete', $anhang));
    }

    public function test_bilder_erscheinen_auf_der_ticketseite(): void
    {
        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);

        TicketStatus::factory()->create();
        $ticket = Ticket::factory()->create();
        $anhang = $this->anhangAn($ticket);

        $this->actingAs($admin)
            ->get('/tickets/'.$ticket->id)
            ->assertOk()
            ->assertSee('Bilder')
            ->assertSee($anhang->url(), escape: false);
    }

    public function test_upload_ueber_die_oberflaeche_legt_einen_anhang_an(): void
    {
        // Der Test, der gefehlt hat. Der erste Upload live scheiterte mit
        // "Unresolvable dependency resolving [$path]", weil der Parameter der
        // Closure in getUploadedFileNameForStorageUsing nicht $file hieß —
        // Filament reicht dort über den Parameternamen durch, nicht über den
        // Typ. Kein Unit-Test hätte das gefunden; nur ein Durchlauf durch die
        // Oberfläche.
        Storage::fake(Attachment::PLATTE);

        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
        $ticket = Ticket::factory()->create();

        Livewire::actingAs($admin)
            ->test(AnhaengeRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            // ->table(): "create" ist eine Kopfaktion der TABELLE, keine
            // Aktion der Komponente. Ohne den Kontext findet der Test sie
            // nicht — genau wie im Browser, wo der Knopf
            // mountAction('create', {}, {table: true}) aufruft.
            ->callAction(TestAction::make('create')->table(), data: [
                'pfad' => [
                    UploadedFile::fake()->image('fehler-im-formular.png', 400, 300),
                ],
            ])
            ->assertHasNoActionErrors();

        $anhang = $ticket->fresh()->attachments()->first();

        $this->assertNotNull($anhang, 'Es wurde kein Anhang angelegt.');
        // Der ursprüngliche Dateiname muss erhalten bleiben, obwohl die Datei
        // auf der Platte einen Zufallsnamen trägt.
        $this->assertSame('fehler-im-formular.png', $anhang->dateiname);
        $this->assertSame($admin->id, $anhang->user_id);
        $this->assertTrue($anhang->istBild());
        $this->assertTrue(Storage::disk(Attachment::PLATTE)->exists($anhang->pfad));
        $this->assertStringContainsString('__', $anhang->pfad);
    }

    public function test_mehrere_dateien_in_einem_durchgang(): void
    {
        Storage::fake(Attachment::PLATTE);

        $admin = User::factory()->create([
            'rolle' => Rolle::Admin,
            'panel_zugang' => true,
        ]);
        $ticket = Ticket::factory()->create();

        Livewire::actingAs($admin)
            ->test(AnhaengeRelationManager::class, [
                'ownerRecord' => $ticket,
                'pageClass' => ViewTicket::class,
            ])
            ->callAction(TestAction::make('create')->table(), data: [
                'pfad' => [
                    UploadedFile::fake()->image('eins.png'),
                    UploadedFile::fake()->image('zwei.png'),
                    UploadedFile::fake()->image('drei.png'),
                ],
            ])
            ->assertHasNoActionErrors();

        // Aus einem Upload werden drei Datensätze — dafür übernimmt using()
        // das Anlegen selbst.
        $this->assertSame(3, $ticket->fresh()->attachments()->count());
    }

    public function test_groesse_wird_lesbar_dargestellt(): void
    {
        $anhang = new Attachment(['groesse' => 1_572_864]);

        $this->assertSame('1,5 MB', $anhang->groesseLesbar());
    }
}

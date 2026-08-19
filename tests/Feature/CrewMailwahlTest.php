<?php

namespace Tests\Feature;

use App\Enums\MailEreignis;
use App\Enums\Rolle;
use App\Filament\Pages\Profil;
use App\Filament\Widgets\MailEinrichten;
use App\Models\Customer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Die Crew wählt ihre Mail-Themen selbst.
 *
 * Vorher stand das ausschließlich unter Maschinenraum → Crew, also an der
 * Stelle, an der einer für einen anderen entscheidet, was der zu lesen
 * bekommt. Wer seine Mails nicht selbst gewählt hat, schaltet sie beim
 * ersten Ärger ganz ab — und danach erreicht ihn auch das nicht mehr, was
 * ihn wirklich angeht.
 *
 * Der Schwerpunkt liegt auf der Einmaligkeit der Frage: sie muss nach der
 * Antwort verschwinden, und zwar auch bei "nein".
 */
class CrewMailwahlTest extends TestCase
{
    use RefreshDatabase;

    private function mitarbeiter(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'rolle' => Rolle::Mitarbeiter,
            'panel_zugang' => true,
        ], $extra));
    }

    // ----------------------------------------------------------- Die Frage

    public function test_die_frage_steht_da_solange_sie_offen_ist(): void
    {
        $this->actingAs($this->mitarbeiter(['benachrichtigungen_gefragt_at' => null]));
        Filament::setCurrentPanel('admin');

        $this->assertTrue(MailEinrichten::canView());
    }

    public function test_die_frage_verschwindet_nach_der_antwort(): void
    {
        $this->actingAs($this->mitarbeiter(['benachrichtigungen_gefragt_at' => now()]));
        Filament::setCurrentPanel('admin');

        $this->assertFalse(MailEinrichten::canView());
    }

    /**
     * Ein Hinweis, der nach der Entscheidung stehen bleibt, ist eine
     * Aufforderung — auch dann, wenn die Entscheidung "nein" war.
     */
    public function test_auch_nein_beantwortet_die_frage(): void
    {
        $kevin = $this->mitarbeiter(['benachrichtigungen_gefragt_at' => null]);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(MailEinrichten::class)->call('nein');

        $kevin->refresh();

        $this->assertNotNull($kevin->benachrichtigungen_gefragt_at);
        $this->assertFalse((bool) $kevin->mail_benachrichtigungen);
        $this->assertFalse(MailEinrichten::canView());
    }

    public function test_ja_schaltet_ein_und_setzt_die_vorgabe(): void
    {
        $kevin = $this->mitarbeiter([
            'benachrichtigungen_gefragt_at' => null,
            'mail_benachrichtigungen' => false,
            'mail_ereignisse' => null,
        ]);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(MailEinrichten::class)->call('ja');

        $kevin->refresh();

        $this->assertTrue((bool) $kevin->mail_benachrichtigungen);
        $this->assertNotNull($kevin->benachrichtigungen_gefragt_at);

        // Alles, was hereinkommt — und nichts von dem, was hinausgeht.
        $this->assertSame(MailEreignis::vorgabeIntern(), $kevin->mail_ereignisse);
        $this->assertFalse($kevin->willMailZu(MailEreignis::AntwortAnKunde));
    }

    /**
     * Hat ein Admin die Themen beim Anlegen schon gesetzt, wäre das
     * Überschreiben genau die stille Änderung, die niemandem auffällt.
     */
    public function test_ja_ueberschreibt_eine_vorhandene_auswahl_nicht(): void
    {
        $kevin = $this->mitarbeiter([
            'benachrichtigungen_gefragt_at' => null,
            'mail_ereignisse' => [MailEreignis::Anliegen->value],
        ]);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(MailEinrichten::class)->call('ja');

        $this->assertSame([MailEreignis::Anliegen->value], $kevin->fresh()->mail_ereignisse);
    }

    /** Kunden haben ihre eigene Karte — mit Adresse und Bestätigung. */
    public function test_kunden_bekommen_diese_karte_nicht(): void
    {
        $kunde = User::factory()->create([
            'rolle' => Rolle::Kunde,
            'panel_zugang' => true,
            'customer_id' => Customer::factory()->create()->getKey(),
            'benachrichtigungen_gefragt_at' => null,
        ]);

        $this->actingAs($kunde, 'kunde');
        Filament::setCurrentPanel('kunde');

        $this->assertFalse(MailEinrichten::canView());
    }

    // -------------------------------------------------------- Mein Zugang

    public function test_jeder_waehlt_seine_themen_im_eigenen_profil(): void
    {
        $kevin = $this->mitarbeiter([
            'mail_benachrichtigungen' => false,
            'mail_ereignisse' => null,
        ]);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Profil::class)
            ->fillForm([
                'mail_benachrichtigungen' => true,
                'mail_ereignisse' => [MailEreignis::Anliegen->value, MailEreignis::Nachricht->value],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $kevin->refresh();

        $this->assertTrue((bool) $kevin->mail_benachrichtigungen);
        $this->assertTrue($kevin->willMailZu(MailEreignis::Anliegen));
        $this->assertFalse($kevin->willMailZu(MailEreignis::Stammdaten));
    }

    /**
     * Wer gespeichert hat, hatte die Frage vor sich — auch wenn er den
     * Schalter nicht angefasst hat.
     */
    public function test_speichern_im_profil_beantwortet_die_frage(): void
    {
        $kevin = $this->mitarbeiter(['benachrichtigungen_gefragt_at' => null]);

        $this->actingAs($kevin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Profil::class)
            ->fillForm(['name' => 'Kevin Neu'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($kevin->fresh()->benachrichtigungen_gefragt_at);
    }

    /**
     * Die Auswahl im eigenen Profil zeigt nur, was hereinkommt. Was wir
     * selbst nach außen schicken, ist keine Nachricht an uns.
     */
    public function test_das_profil_bietet_nur_die_internen_ereignisse_an(): void
    {
        $this->actingAs($this->mitarbeiter(['mail_benachrichtigungen' => true]));
        Filament::setCurrentPanel('admin');

        // Erst den Schalter, dann die Auswahl: sie ist bewusst nur sichtbar,
        // wenn überhaupt Mail hinausgeht — sonst stünde eine Themenliste da,
        // die nichts bewirkt.
        Livewire::test(Profil::class)
            ->fillForm(['mail_benachrichtigungen' => true])
            ->assertOk()
            ->assertSee(MailEreignis::Anliegen->getLabel())
            ->assertDontSee(MailEreignis::AntwortAnKunde->getLabel());
    }

    public function test_die_auswahl_steuert_den_versand_tatsaechlich(): void
    {
        $kevin = $this->mitarbeiter([
            'mail_benachrichtigungen' => true,
            'mail_ereignisse' => [MailEreignis::Anliegen->value],
        ]);

        $this->assertTrue($kevin->bekommtMailMeldungen(MailEreignis::Anliegen));
        $this->assertFalse($kevin->bekommtMailMeldungen(MailEreignis::Nachricht));
    }
}

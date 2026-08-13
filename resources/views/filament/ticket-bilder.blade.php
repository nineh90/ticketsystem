{{--
    Bildvorschau auf der Ticket-Detailseite.

    Der eigentliche Sinn der Anhänge: wer ein Ticket öffnet, soll den
    Screenshot sofort sehen und nicht erst einen Reiter suchen. Nicht-Bilder
    (PDF) bleiben dem Reiter "Anhänge" vorbehalten — sie taugen nicht als
    Vorschaubild.

    Die Adressen zeigen auf die geschützte Route, nicht auf public/.
--}}
<div class="flex flex-wrap gap-3">
    @foreach ($bilder as $bild)
        <a
            href="{{ $bild->url() }}"
            target="_blank"
            rel="noopener"
            title="{{ $bild->dateiname }} ({{ $bild->groesseLesbar() }})"
            class="block overflow-hidden rounded-lg ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:ring-primary-500/40"
        >
            <img
                src="{{ $bild->url() }}"
                alt="{{ $bild->dateiname }}"
                loading="lazy"
                class="h-32 w-auto max-w-[16rem] object-cover"
            >
        </a>
    @endforeach
</div>

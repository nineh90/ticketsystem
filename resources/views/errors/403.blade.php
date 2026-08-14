{{--
    403.

    Entstanden aus einem konkreten Ärgernis: als beide Panels sich noch einen
    Guard teilten, bekam jeder, der intern angemeldet war und /kunde aufrief,
    einen nackten 403 mit zwei Wörtern darauf. Man sah nicht, woran es liegt,
    und schon gar nicht, dass die Lösung "abmelden" hieß — der erste Verdacht
    war ein kaputtes System.

    Die Ursache ist inzwischen weg (getrennte Guards, siehe config/auth.php),
    der Fall selbst kann aber weiterhin auftreten: etwa mit einer Sitzung, die
    noch von vorher stammt, oder wenn jemand die Rolle eines angemeldeten
    Zugangs ändert. Dann sagt diese Seite wenigstens, als wer man angemeldet
    ist und wo dieser Zugang hingehört.
--}}
@php
    $nutzer = auth()->user();

    // In welchen Bereich wollte die Anfrage? Am Pfad ablesbar, weil das
    // Kundenpanel unter /kunde liegt und das interne auf der Wurzel.
    $wollteZumKunden = request()->is('kunde', 'kunde/*');

    // Und wohin gehört dieser Zugang? Das ist die eigentliche Auskunft.
    $gehoertZumKunden = $nutzer?->istKunde() ?? false;

    $verwechslung = $nutzer !== null && $wollteZumKunden !== $gehoertZumKunden;

    $eigenerBereich = $gehoertZumKunden
        ? ['name' => 'Kundenbereich', 'url' => url('/kunde')]
        : ['name' => 'internen Bereich', 'url' => url('/')];

    // Abgemeldet wird der Bereich, in dem man gerade steht — nicht der, in den
    // der Zugang gehört. Andersherum wäre der Knopf wirkungslos: die
    // Anmeldung, die hier abgewiesen wird, ist die dieses Bereichs, und beide
    // haben seit config/auth.php ihre eigene.
    //
    // Über die eigene Route und nicht über Filaments Abmeldung: deren Route
    // liegt innerhalb des Panels und damit hinter derselben Schranke, die
    // gerade eben zu diesem 403 geführt hat — der Knopf hätte also wieder auf
    // dieser Seite geendet. Genau so ist es beim Ausprobieren passiert.
    $abmeldeBereich = $wollteZumKunden ? 'kunde' : 'intern';
@endphp

<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Kein Zugriff — Nils-Digital</title>
    <link rel="icon" href="/favicon-32x32.png" sizes="32x32">
    {{--
        Bewusst kein @vite und kein Stylesheet: eine Fehlerseite darf nicht
        davon abhängen, dass der Build in Ordnung ist. Fehlte das Manifest,
        flöge hier eine zweite Ausnahme — und statt einer erklärenden Seite
        stünde die allgemeine Fehlermeldung da, also genau das, was diese
        Seite ersetzen soll. Die Farben der Marke stehen deshalb direkt in den
        style-Attributen (die CSP erlaubt style-src 'unsafe-inline'), die
        Schriften fallen auf Systemschriften zurück.
    --}}
</head>
<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0d1117;color:#e5e7eb;font-family:'Roboto Mono',ui-monospace,monospace;padding:1.5rem;">
    <main style="max-width:34rem;width:100%;background:#1a1f27;border:1px solid #333c49;border-radius:0.75rem;padding:2rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;">
            <img src="/logo.png" alt="" style="height:2rem;width:2rem;">
            <span style="font-family:Fredoka,system-ui,sans-serif;font-weight:600;font-size:1.125rem;color:#00bcd4;">Nils-Digital</span>
        </div>

        @if ($verwechslung)
            <h1 style="font-family:Fredoka,system-ui,sans-serif;font-size:1.5rem;margin:0 0 1rem;">
                Falscher Bereich
            </h1>

            <p style="line-height:1.7;color:#9ca3af;margin:0 0 1rem;">
                Sie sind als <strong style="color:#e5e7eb;">{{ $nutzer->name }}</strong> angemeldet
                @if ($gehoertZumKunden)
                    — das ist ein Kundenzugang, und der gilt nur für den Kundenbereich.
                @else
                    — das ist ein interner Zugang, und der gilt nicht für den Kundenbereich.
                @endif
            </p>

            <p style="line-height:1.7;color:#9ca3af;margin:0 0 1.5rem;">
                Die beiden Bereiche haben getrennte Anmeldungen — Sie können in
                beiden gleichzeitig angemeldet sein. Melden Sie sich für den
                anderen Bereich dort einfach zusätzlich an.
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
                <a href="{{ $eigenerBereich['url'] }}"
                   style="display:inline-block;background:#00bcd4;color:#0d1117;font-weight:600;padding:0.625rem 1.25rem;border-radius:0.5rem;text-decoration:none;">
                    Zurück zum {{ $eigenerBereich['name'] }}
                </a>

                <form method="POST" action="{{ route('abmelden', $abmeldeBereich) }}" style="margin:0;">
                    @csrf
                    <button type="submit"
                            style="background:transparent;color:#e5e7eb;border:1px solid #333c49;font-family:inherit;font-size:1rem;padding:0.625rem 1.25rem;border-radius:0.5rem;cursor:pointer;">
                        Abmelden
                    </button>
                </form>
            </div>
        @else
            <h1 style="font-family:Fredoka,system-ui,sans-serif;font-size:1.5rem;margin:0 0 1rem;">
                Kein Zugriff
            </h1>

            <p style="line-height:1.7;color:#9ca3af;margin:0 0 1.5rem;">
                {{ $exception?->getMessage() ?: 'Diese Seite steht Ihnen nicht offen.' }}
            </p>

            <a href="{{ $nutzer ? $eigenerBereich['url'] : url('/') }}"
               style="display:inline-block;background:#00bcd4;color:#0d1117;font-weight:600;padding:0.625rem 1.25rem;border-radius:0.5rem;text-decoration:none;">
                Zur Startseite
            </a>
        @endif
    </main>
</body>
</html>

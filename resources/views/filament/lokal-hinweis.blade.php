{{--
    Der Hinweis "das hier ist nicht live".

    Er entstand aus einem konkreten Ärgernis: lokal und live sehen identisch
    aus — dasselbe Theme, dieselbe Marke, dieselben Daten, weil die lokale
    Datenbank eine Kopie der Live-Datenbank ist. Chrome vervollständigt in der
    Adresszeile auf die Live-Adresse, die hundertmal öfter besucht wurde, und
    dann steht man in der echten Anwendung und wundert sich, warum die
    Änderung von eben nicht da ist. Umgekehrt ist es schlimmer: man probiert
    etwas aus und tut es an echten Kundendaten.

    Deshalb zwei Signale statt einem. Der Rahmen wirkt aus dem Augenwinkel und
    ist auch dann noch zu erkennen, wenn das Fenster nur als Vorschau in der
    Fensterauswahl steht; das Schild nennt die Adresse, an der man tatsächlich
    ist. Der Rahmen allein wäre zu vage, das Schild allein zu leicht zu
    übersehen.

    Beides liegt über allem (z-50) und nimmt keine Klicks an
    (pointer-events-none) — ein Hinweis, der einen Knopf verdeckt, wird nach
    dem zweiten Mal abgeschaltet.

    Eingehängt über PanelsRenderHook::BODY_START in beiden Panel-Providern,
    und nur wenn app()->isLocal(). Der Haken sitzt in
    components/layout/base.blade.php und gilt damit auch für die Anmeldung —
    genau die Seite, auf der die Verwechslung anfängt.
--}}
<div aria-hidden="true" class="pointer-events-none fixed inset-0 z-50 ring-4 ring-inset ring-amber-500/70"></div>

<div class="pointer-events-none fixed bottom-0 left-0 z-50 flex items-center gap-2 rounded-tr-lg bg-amber-500 px-3 py-1.5 font-mono text-xs font-semibold text-amber-950 shadow-lg">
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.19-1.458-1.517-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
    </svg>

    <span>LOKAL</span>

    {{-- Die Adresse aus der Anfrage, nicht aus APP_URL: steht dort etwas
         anderes, als man aufgerufen hat, ist genau das die Information, die
         man sehen will (siehe der Hinweis zu APP_URL in docs/betrieb.md). --}}
    <span class="opacity-75">{{ request()->getHttpHost() }}</span>

    {{-- Die Erinnerung daran, dass die Kopie echte Kundendaten enthält. --}}
    <span class="opacity-75">· Kopie der Livedaten</span>
</div>

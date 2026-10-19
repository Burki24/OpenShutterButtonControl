# Debug-Ausgaben: OpenShutterButtonControl

Grundlage ist der abgenommene OpenHomeAlarm-Pilot und `SymconDevelopment/standards/DEBUG_OUTPUT.md`.
Die Kategorie lautet `<Modulklasse> | <Ereignis> | <Funktion>:<Zeile>`. Die Felder `Instanz`, `Vorgang`, `Aufruf`, `Meldung` und `Bezug` erklären den Ablauf auf Deutsch. Neue äußere Modulaufrufe erhalten eine neue Vorgangskennung, verschachtelte Funktionen teilen sie. Jede erfasste Funktion endet auch bei früher Rückgabe oder PHP-Ausnahme über `finally` mit ihrem tatsächlichen Ergebnis. Funktionen ohne Rückgabewert behaupten keinen fachlichen Erfolg.

## Erfasste Vorgänge

Tasterempfang, Kurz-/Langdruck, Positionsanforderung, OPEN/CLOSE und STOP auf der tatsächlich gestarteten Bewegungsvariable. Auch Initialisierung und Timeraufrufe sind erfasst.

Öffentliche Einstiege einschließlich Lebenszyklus und entscheidende interne Schalt-/Kommunikationsgrenzen erhalten Start und Ende. Reine Berechnungen und Getter werden nicht einzeln protokolliert. Geprüfte Quellwerte werden beim ohnehin erforderlichen Lesen erfasst, Statuswerte nach dem Schreiben. Steueraufträge nennen Variablen-ID und Wert; ihre Annahme durch Symcon bestätigt noch keine Wirkung am Gerät. Ungültige Zustände und Konfigurationen werden mit Bezug erklärt.

## Gemeinsame Quelle und Datenschutz

`DebugFlowHelper` stammt unverändert aus `Symcon_ModuleHelper` und nutzt den zentralen `DebugHelper` für Bereinigung und eine Grenze von 4096 Bytes. Die Subscription und das lokale Integritätsmanifest dokumentieren das Bundle. Der neue Helper ist noch lokale Entwicklungsarbeit; `source_sha: working-tree` bezeichnet ausdrücklich keinen veröffentlichten Commit. Andere vorhandene Helper werden nicht aktualisiert.

Codes, Zugangsdaten und unkontrollierte Schnittstellenpayloads gehören nicht in den Kontext. Der Debug-Kanal erhält keine persistente Zusatzhistorie. Ein Fehler dieses Kanals wird abgefangen und darf die sichere Abschaltung nicht verhindern. Native Wertabfragen und Steueraufträge werden nicht allein für Debug wiederholt.

## Nachweise

`php tests/debug-output.php` ergänzt die bestehenden Regressionen um echte Modulaufrufe, ausgeglichene Start-/Endpaare, Quellen-/Zielbezug und die sichere Abschaltung bei ausgefallenem Debug-Kanal. Der Gesamteinstieg bleibt `php tests/run.php`.

Die reale Abnahme von Lesbarkeit, Datenmenge, Gerätewirkung und Laufzeit auf der Zielinstallation ist offen. Dafür Taster-/Sensorauslösung, wiederholte Meldungen, Timerabschluss, Konfigurationswechsel, fehlende Aktionsziele und Abschaltung im nativen Debug-Fenster prüfen. Die OHA-Abnahme wird nicht auf dieses Modul übertragen.

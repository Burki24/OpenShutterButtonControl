# Projektregeln für OpenShutterButtonControl

Vor Änderungen zuerst `../SymconDevelopment/AGENTS.md`,
`../SymconDevelopment/ARCHITECTURE.md`, die dort genannten Prinzipien und
die auf die Aufgabe bezogenen Standards lesen. Projektkontext und Verträge
stehen in `README.md` und `OpenShutterButtonControl/README.md`.

Taster-, Bewegungs- und Positionsvariable werden über ihre Symcon-Variablen-IDs
zugeordnet. Namen und Pfade ersetzen diese IDs nicht. Es gilt
`../SymconDevelopment/standards/SOURCE_VARIABLE_IDENTITY.md`. Änderungen an
Registrierungen, Aktionen oder Konfigurationswechseln müssen das sichere
`STOP` auf der tatsächlich gestarteten Bewegungsvariable erhalten.

Vor Änderungen Arbeitsbaum, öffentliche Properties und Tests prüfen. Die
Kopien unter `libs/helper` nicht lokal bearbeiten. Kein installiertes Modul
ändern und keinen Commit, Push oder Release ohne ausdrücklichen Auftrag auslösen.

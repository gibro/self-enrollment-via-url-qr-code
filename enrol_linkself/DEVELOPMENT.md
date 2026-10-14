# enrol_linkself – Entwicklungsnotizen

Alpha-Skelett für Moodle 5.2, noch gegen keine Moodle-Instanz getestet. Installation und Nutzung stehen in der README.

## Entscheidungen im Code

- Ein Token (256 Bit, `random_bytes`) pro Instanz, gespeichert in `enrol.customchar1`.
  Der Link enthält nur das Token (`join.php?t=…`), keine Kurs-ID. Die Instanz wird per
  Datenbankabfrage über das Token gefunden. Das ersetzt den früher angedachten `hash_equals`-Vergleich,
  bei 256 Bit Zufall spielt ein Timing-Angriff keine Rolle.
- Der Link schreibt nie selbst ein. `join.php` verlangt Login und zeigt eine Bestätigung,
  eingeschrieben wird nur per POST mit gültigem sesskey.
- `allow_enrol()` ist false: Auch Trainer können niemanden über diese Methode einschreiben,
  es gibt keinen Batch-Weg.
- Schließen = nur `customint1` auf 0. Der Instanzstatus bleibt aktiviert. Laut Moodle-Doku ist eine
  Einschreibung nur aktiv, wenn auch die Instanz aktiviert ist; Verstecken oder Löschen der Instanz
  würde alle Eingeschriebenen aussperren. Deshalb liefert `can_hide_show_instance()` immer false,
  und `can_delete_instance()` ist nur erlaubt, solange niemand über die Instanz eingeschrieben ist.
- Keine Einschreibedauer, keine Statusauswahl im Formular: beides könnte Teilnehmende ungewollt sperren.
- Ein Link pro Kurs (`can_add_instance()`).
- Beim Wiederherstellen/Kopieren eines Kurses wird das Token nie übernommen. Die Instanz bekommt ein neues
  Token und startet geschlossen.
- Verfügbarkeitsprüfung: Instanz aktiv, offen, Zeitfenster, bereits eingeschrieben, Teilnehmendenlimit,
  Capability `enrol/linkself:enrolself`. Das Limit ist nicht gegen gleichzeitige Anfragen gesichert.

## Nicht geprüft, vor dem Einsatz testen

1. Pfad zu `config.php` in `join.php` und `link.php` (`../../config.php`): Das setzt voraus, dass unter
   `public/` eine Weiche liegt. Falls nicht, anpassen.
2. Login-Redirect: Ob Moodle die volle URL inklusive `t=` nach dem Login wiederherstellt (`require_login`
   setzt `wantsurl`). Mit abgemeldetem Browser testen.
3. Spalten `customchar1`, `customint1`, `customint3` der Tabelle `enrol` und die Ereignisklasse
   `\core\event\enrol_instance_updated::create_from_record()`.
4. Capability `enrol/linkself:enrolself` mit Archetyp `user`: Nicht angemeldete Kursmitglieder haben keine
   Kursrolle. Mit einem frischen Testkonto prüfen, ob die Einschreibung klappt.
5. QR-Code über `lib/tcpdf/tcpdf_barcodes_2d.php` (Klasse `TCPDF2DBarcode`), derselbe Weg wie Moodles
   `core_qrcode`. Mit TCPDF 6 getestet: Der erzeugte QR-Code lässt sich decodieren und enthält exakt die URL.
   TCPDF 7 hat diese Klasse nicht mehr, dann gibt der Helfer null zurück und die Seite zeigt nur den Link.
   Welche TCPDF-Version 5.2 mitbringt, ist ungeprüft. Ich habe nur gesehen, dass Moodle main noch auf
   `tcpdf_barcodes_2d.php` verweist.
6. `$plugin->requires` in `version.php` ist bewusst niedrig gesetzt (aus dem Gedächtnis), auf die
   5.2-Buildnummer anheben.
7. Das Token steht im Webserver-Zugriffslog. `join.php` sendet `Referrer-Policy: no-referrer`, damit es
   nicht an Fremdseiten weitergereicht wird.

## Testplan

1. Methode hinzufügen, Link öffnen (abgemeldet): Login, danach Bestätigungsseite mit Kursname.
2. Bestätigen: Person ist eingeschrieben, Rolle stimmt, Weiterleitung in den Kurs.
3. Einschreibung schließen: neue Person bekommt „geschlossen“, die zuvor eingeschriebene Person kommt
   weiterhin in den Kurs.
4. Einschreibung wieder öffnen, neuen Link erzeugen: alter Link meldet „ungültig“, Eingeschriebene bleiben aktiv.
5. Auge-Symbol und Löschen in der Methodenliste: nicht vorhanden bzw. gesperrt.
6. Als Trainer „Teilnehmer/innen einschreiben“ mit dieser Methode versuchen: nicht möglich.
7. Zeitfenster und Teilnehmendenlimit setzen und prüfen.
8. Kurs sichern und wiederherstellen: neue Instanz mit neuem Token, geschlossen.

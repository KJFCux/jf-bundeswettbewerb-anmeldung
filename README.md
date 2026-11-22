# Bundeswettbewerb Jugendfeuerwehr Anmeldung

Webanwendung zur Online-Anmeldung von Jugendfeuerwehr-Gruppen zum Bundeswettbewerb der Deutschen Jugendfeuerwehr
(z. B. auf Kreis- oder Abschnittsebene). Jede Gruppe trägt ihren Meldebogen mit neun Teilnehmenden und einem Ersatz ein
und kann ihn bis zum Anmeldeschluss über einen persönlichen Link jederzeit ändern. Die Anmeldungen werden als XML-Dateien
gespeichert und lassen sich in die [JF Bundeswettbewerbs-Software](https://github.com/KJFCux/jf-bundeswettbewerb) importieren.

![Screenshot](https://github.com/KJFCux/jf-bundeswettbewerb-anmeldung/assets/1337304/604af93d-c8a7-4ffd-80c5-c23592e646c6)

## Funktionen

- Anmeldung ohne Benutzerkonto, Zugriff über einen geheimen Link (`?anmeldung=<Schlüssel>`)
- Optional eine E-Mail mit dem Link an die anmeldende Person (die Adresse wird nicht gespeichert)
- Meldebogen mit 9 Teilnehmenden und 1 Ersatz (Vorname, Nachname, Geschlecht, Geburtsdatum)
- Altersprüfung im Browser und auf dem Server, Anzeige von Gesamtalter und Durchschnittsalter
- Anmeldeschluss: Danach ist die Anmeldung nur noch lesbar
- Download des Wertungsbogens nach dem Wettbewerb über denselben Link
- Verlauf: Jeder gespeicherte Stand wird zusätzlich gesichert
- Spam-Schutz durch ein unsichtbares Honeypot-Feld und eine Begrenzung pro IP-Adresse

## Voraussetzungen

- Webserver mit PHP 7.4 oder neuer (empfohlen: PHP 8.x)
- PHP-Erweiterungen `dom`, `simplexml`, `json` und `mbstring`
- Für den Versand des Links: funktionierender E-Mail-Versand über die PHP-Funktion `mail()`
- Schreibrechte des Webservers auf das Verzeichnis `xml/`
- Apache mit aktivierter `.htaccess`-Unterstützung (`AllowOverride`) oder eine entsprechende
  Konfiguration für andere Webserver (siehe [Datenschutz und Sicherheit](#datenschutz-und-sicherheit))

## Installation

1. Dateien auf den Webserver kopieren, z. B. in das Verzeichnis `/anmeldung/`.
2. `config.sample.php` nach `config.php` kopieren und die Werte anpassen (siehe [Konfiguration](#konfiguration)).
3. Sicherstellen, dass der Webserver in `xml/` schreiben darf.
4. Prüfen, dass `https://example.net/anmeldung/xml/` **nicht** abrufbar ist (erwartet: Fehler 403 oder 404).
5. Testanmeldung durchführen (mit E-Mail-Adresse, um den Versand zu prüfen). Die Testdatei danach aus `xml/` löschen.
6. Die URL der Seite an die Jugendfeuerwehren verteilen.

## Konfiguration

Alle Einstellungen stehen in der `config.php`:

| Schlüssel | Beschreibung |
|---|---|
| `title` | Titel im Browser-Tab |
| `logo` | Pfad oder URL zum Logo |
| `headline` | Überschrift der Seite |
| `description` | Beschreibungstext oberhalb des Meldebogens (HTML erlaubt), auch im Betreff der E-Mail |
| `organizationalunit` | Bezeichnung der Organisationseinheit, z. B. „Stadt, - Gemeinde“ |
| `organizationalunits` | Auswahlliste der Organisationseinheiten |
| `eventdate` | Datum des Wettbewerbs im Format `TT.MM.JJJJ`, Grundlage für die Altersprüfung |
| `block_registration_at` | Anmeldeschluss im ISO-8601-Format, z. B. `2025-05-17T06:00:00+02:00` |
| `mailabsender` | Absenderadresse der E-Mail |
| `url` | Öffentliche URL der Anwendung mit abschließendem `/`, wird für den Link in der E-Mail und in der XML-Datei verwendet |
| `mailmessage` | Text der E-Mail. Platzhalter: `{URL}` = Link zur Anmeldung |
| `history` | Jeden gespeicherten Stand zusätzlich in `xml/Verlauf/` sichern (Standard: `true`) |
| `ratelimit_registrations` | Höchstzahl neuer Anmeldungen pro IP-Adresse und Stunde (Standard: `10`, `0` = unbegrenzt) |
| `ratelimit_mails` | Höchstzahl versendeter E-Mails pro IP-Adresse und Stunde (Standard: `5`, `0` = unbegrenzt) |

`title`, `headline`, `description` und `organizationalunit` werden ungefiltert als HTML ausgegeben.
Dort nur vertrauenswürdige Inhalte eintragen.

Wird ein Logo von einer anderen Domain eingebunden, muss `img-src` in der Content-Security-Policy in der
`.htaccess` um diese Domain ergänzt werden, sonst blockiert der Browser das Bild.

## Altersprüfung

Das Alter wird – wie in der Wettbewerbsordnung – über den Geburtsjahrgang berechnet
(Jahr des Wettbewerbs minus Geburtsjahr):

- Erlaubt sind Jahrgänge mit einem Alter von 10 bis 18 Jahren.
- Zusätzlich muss das Mindestalter von 10 Jahren am Wettbewerbstag (`eventdate`) tatsächlich erreicht sein.
- Gesamt- und Durchschnittsalter werden aus den neun Teilnehmenden berechnet, der Ersatz zählt nicht mit.

Die Grenzen stehen als Konstanten `MINDESTALTER` und `HOECHSTALTER` am Anfang der `index.php`.
Das Geburtsdatum wird über ein Datumsfeld des Browsers eingegeben, das nur Daten im erlaubten Bereich zulässt.
Sollte trotzdem ein ungültiges Geburtsdatum ankommen (z. B. aus einem sehr alten Browser), wird die Anmeldung
gespeichert, das Datum aber als `0001-01-01` markiert. Die Gruppe sieht dann einen Hinweis und das betroffene Feld
wird rot hervorgehoben.

## Ablauf und Datenablage

- Bei der ersten Anmeldung wird ein zufälliger 48-stelliger Schlüssel erzeugt. Die Daten liegen in `xml/<Schlüssel>.xml`.
- Jugendfeuerwehr, Gruppenname und Organisationseinheit lassen sich nach der ersten Anmeldung nicht mehr ändern.
- Leere Zeilen (z. B. kein Ersatz) werden nicht gespeichert.
- Jeder gespeicherte Stand wird zusätzlich als `xml/Verlauf/<Schlüssel>_<JJJJMMTT-hhmmss-ms>.xml` abgelegt
  (abschaltbar über `history`). So lässt sich bei Rückfragen nachvollziehen, wer wann was geändert hat.
- Die XML-Dateien sind UTF-16-kodiert, Geburtsdaten z. B. als `2012-01-31T00:00:00`.
- Nach dem Anmeldeschluss (`block_registration_at`) können Anmeldungen weiterhin angesehen, aber nicht mehr gespeichert werden.

## Import in die Bundeswettbewerbs-Software

Nach dem Anmeldeschluss bzw. am Tag des Wettbewerbs alle `*.xml`-Dateien direkt aus `xml/` herunterladen
(**ohne** die Unterordner `Verlauf/` und `Wertungsbögen/`) und in die
[JF Bundeswettbewerbs-Software](https://github.com/KJFCux/jf-bundeswettbewerb) importieren. Dort werden alle Gruppen
angelegt und der Wettbewerb kann gestartet werden.

## Wertungsbögen bereitstellen

Nach dem Wettbewerb können die Gruppen ihren Wertungsbogen über ihren Anmelde-Link herunterladen. Dazu die
Excel-Datei je Gruppe unter dem Schlüssel der Anmeldung ablegen:

```
xml/Wertungsbögen/<Schlüssel>.xlsx
```

Der Schlüssel ist der Dateiname der XML-Datei bzw. der Wert hinter `?anmeldung=` in der `UrlderAnmeldung`.
Sobald die Datei vorhanden ist, erscheint auf der Anmeldeseite ein Download-Link. Die Datei wird über die `index.php`
ausgeliefert, das Verzeichnis `xml/` bleibt für direkte Zugriffe gesperrt.

## Datenschutz und Sicherheit

Die Anwendung verarbeitet personenbezogene Daten von Kindern und Jugendlichen (Name, Geburtsdatum, Geschlecht).
Bitte beachte:

- **Die Verzeichnisse `xml/` und `inc/` dürfen nicht über den Webserver abrufbar sein.** Für Apache liegt
  dafür jeweils eine `.htaccess` bei. Bei nginx muss der Zugriff in der Serverkonfiguration gesperrt werden:

  ```nginx
  location ~ ^/anmeldung/(xml/|inc/|\.) {
      deny all;
  }
  ```

- Wer den Link einer Anmeldung kennt, kann sie einsehen und ändern. Die Links sollten daher nur an die
  jeweils Verantwortlichen weitergegeben werden. Die `.htaccess` setzt `Referrer-Policy: no-referrer`
  und `X-Robots-Tag: noindex`, damit die Links nicht an Dritte gelangen oder in Suchmaschinen erscheinen.
- Die Anwendung nur über HTTPS betreiben.
- Die XML-Dateien, den Verlauf und die Wertungsbögen nach Abschluss der Veranstaltung gemäß eurem
  Löschkonzept entfernen.
- Für den Spam-Schutz wird in `xml/.ratelimit/` ein gesalzener Hash der IP-Adresse mit den Zeitpunkten der
  letzten Anmeldungen bzw. E-Mails gespeichert. Die Einträge werden nach einer Stunde verworfen und regelmäßig gelöscht.
- Die Dateien in `xml/` sind über `.gitignore` von der Versionsverwaltung ausgeschlossen und dürfen nie committet werden.

## Lokale Entwicklung

```bash
cp config.sample.php config.php   # eventdate und block_registration_at in die Zukunft setzen
php -S localhost:8000
```

Danach ist die Anwendung unter <http://localhost:8000> erreichbar. Der PHP-Entwicklungsserver wertet keine
`.htaccess` aus, `xml/` ist dort also direkt abrufbar. E-Mails werden lokal nur verschickt, wenn `sendmail` eingerichtet ist.

### Aufbau

| Datei/Verzeichnis | Inhalt |
|---|---|
| `index.php` | Formular, Prüfung und Speicherung der Anmeldung |
| `inc/functions.php` | Allgemeine Hilfsfunktionen (XML, E-Mail, Spam-Schutz), unabhängig vom Formular |
| `js/KJFCux.js` | Altersberechnung und Prüfung im Browser |
| `css/KJFCux.css` | Eigene Styles |
| `xml/` | Anmeldungen, Verlauf, Wertungsbögen und Spam-Schutz-Zähler (nicht öffentlich) |

### Bootstrap aktualisieren

Verwendet wird [Bootstrap 5.3.9](https://getbootstrap.com/), lokal in `css/` und `js/` eingebunden. Für ein Update
`bootstrap.min.css` und `bootstrap.bundle.min.js` (jeweils mit `.map`) aus dem `dist`-Ordner des
[npm-Pakets](https://www.npmjs.com/package/bootstrap) ersetzen und die `integrity`-Hashes in der `index.php` anpassen:

```bash
openssl dgst -sha384 -binary css/bootstrap.min.css | openssl base64 -A
openssl dgst -sha384 -binary js/bootstrap.bundle.min.js | openssl base64 -A
```

## Lizenz

[GNU Affero General Public License v3.0](LICENSE)

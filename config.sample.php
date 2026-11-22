<?php
// Kopiere diese Datei nach config.php und passe die Werte an.
$config = [];

// Titel im Browser-Tab
$config['title'] = 'Kreisjugendfeuerwehrtag im Landkreis Cuxhaven';
// Pfad oder URL zum Logo (bei externer URL muss die Content-Security-Policy in .htaccess angepasst werden)
$config['logo'] = '/wp-content/uploads/2019/04/kjf_logo_web_transparent_mit_text_FUTURA.png';
// Überschrift der Seite
$config['headline'] = 'Kreisjugendfeuerwehrtag';
// Beschreibungstext oberhalb des Meldebogens (HTML erlaubt), wird auch im Betreff der E-Mail verwendet
$config['description'] = 'Kreisjugendfeuerwehrtag am Samstag, den 01.06.2024 in Wellen';

// Bezeichnung und Auswahlliste der Organisationseinheiten
$config['organizationalunit'] = 'Stadt, - Gemeinde';
$config['organizationalunits'] = ['Beverstedt', 'Börde Lamstedt', 'Cuxhaven', 'Geestland', 'Land Hadeln', 'Hagen', 'Hemmoor', 'Loxstedt', 'Schiffdorf', 'Wurster Nordseeküste'];

// Datum der Veranstaltung (TT.MM.JJJJ), Grundlage für die Altersprüfung
$config['eventdate'] = '01.06.2024';

// Anmeldeschluss (ISO 8601). Danach ist die Seite nur noch lesbar, Änderungen werden abgelehnt.
$config['block_registration_at'] = '2024-05-17T06:00:00+02:00';

// Absenderadresse der E-Mails
$config['mailabsender'] = 'mail@example.net';
// Öffentliche URL der Anwendung (mit abschließendem Slash), wird für den Link in der E-Mail verwendet
$config['url'] = 'https://example.net/anmeldung/';
// Text der E-Mail mit dem Link zur Anmeldung. {URL} = Link zur Anmeldung
$config['mailmessage'] = "Hallo, du kannst deine Anmeldung unter dieser URL einsehen: \r\n\r\n{URL} \r\n\r\nViele Grüße";

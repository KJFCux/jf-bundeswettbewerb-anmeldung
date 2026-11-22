<?php

if (file_exists('config.php')) {
    include('config.php');
    if (!isset($config)) {
        die('Config not valid, please copy config.sample.php to config.php and reconfigure values');
    }
} else {
    die('Config not found, please copy config.sample.php to config.php and configure values');
}

// Erlaubte Werte für das Geschlecht
const GESCHLECHT_OPTIONEN = ['D', 'M', 'W'];
// Anzahl der Zeilen im Meldebogen: 9 Teilnehmende + 1 Ersatz
const ANZAHL_TEILNEHMENDE = 9;
const ANZAHL_ZEILEN = ANZAHL_TEILNEHMENDE + 1;
// Mindest- und Höchstalter (berechnet über den Geburtsjahrgang)
const MINDESTALTER = 10;
const HOECHSTALTER = 18;
// Verzeichnis der Wertungsbögen (Dateiname: <Schlüssel>.xlsx)
const WERTUNGSBOGEN_DIR = './xml/Wertungsbögen/';

$eventDate = DateTimeImmutable::createFromFormat('!d.m.Y', $config['eventdate']);
if ($eventDate === false) {
    die('eventdate in config.php is not a valid date (expected format: TT.MM.JJJJ)');
}
// Wer nach diesem Tag geboren ist, ist am Veranstaltungstag jünger als das Mindestalter
$allowDate = $eventDate->modify('-' . MINDESTALTER . ' year');

$registrationEnabled = true;
try {
    $blockRegistrationAt = new DateTimeImmutable($config['block_registration_at']);
    $registrationEnabled = $blockRegistrationAt > new DateTimeImmutable();
    // If the registration is blocked and the form is submitted, show an error message
    if (!$registrationEnabled && !empty($_POST)) {
        die('Anmeldung ist nicht mehr möglich. Der Anmeldeschluss war am ' . $blockRegistrationAt->format('d.m.Y H:i:s') . '.');
    }
} catch (Exception $e) {
    die('block_registration_at in config.php is not a valid date');
}

function generateRandomString($length = 10): string
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Liefert einen Wert aus den eingelesenen XML-Daten als String.
 * Leere XML-Elemente werden von json_encode() als leeres Array geliefert.
 */
function value($array, $key): string
{
    if (!is_array($array) || !isset($array[$key]) || is_array($array[$key])) {
        return '';
    }
    return (string)$array[$key];
}

function echo_if_isset($array, $key, $echo = null): void
{
    $value = value($array, $key);
    if ($value !== '') {
        echo $echo ?? e($value);
    }
}

function if_isset_dt($array, $key): string
{
    $value = value($array, $key);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('d.m.Y', $timestamp);
}

function input($string, $len = 64): string
{
    if (!is_string($string)) {
        return '';
    }
    return mb_strimwidth(trim($string), 0, $len);
}

/**
 * Erzeugt ein XML-Element mit Textinhalt. createElement($name, $value) escaped
 * "&" nicht, wodurch Werte wie "Musterdorf & Umgebung" leer gespeichert wurden.
 */
function xmlElement(DOMDocument $xml, string $name, string $value = ''): DOMElement
{
    $element = $xml->createElement($name);
    if ($value !== '') {
        $element->appendChild($xml->createTextNode($value));
    }
    return $element;
}

function mailSubject(string $subject): string
{
    return mb_encode_mimeheader($subject, 'UTF-8', 'B');
}

$XmlData = [];
$datafile = null;
$old_xml = null;
$wertungsbogen = null;
if (isset($_GET['anmeldung']) && is_string($_GET['anmeldung']) && ctype_alnum($_GET['anmeldung']) && file_exists('./xml/' . $_GET['anmeldung'] . '.xml')) {
    $old_xml = simplexml_load_file('./xml/' . $_GET['anmeldung'] . '.xml');
    if ($old_xml) {
        //Convert SimpleXml Object to associative Array
        $XmlData = json_decode(json_encode($old_xml), true);
        $datafile = $_GET['anmeldung'];
        if (file_exists(WERTUNGSBOGEN_DIR . $datafile . '.xlsx')) {
            $wertungsbogen = WERTUNGSBOGEN_DIR . $datafile . '.xlsx';
        }
    } else {
        $old_xml = null;
    }
}

// Wertungsbogen über PHP ausliefern, damit das Verzeichnis xml/ komplett gesperrt bleiben kann
if (isset($_GET['wertungsbogen'])) {
    if ($wertungsbogen === null) {
        http_response_code(404);
        die('Kein Wertungsbogen vorhanden.');
    }
    $filename = trim('Wertungsbogen ' . value($XmlData, 'GruppenName')) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"Wertungsbogen.xlsx\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Content-Length: ' . filesize($wertungsbogen));
    header('Cache-Control: private, no-store');
    readfile($wertungsbogen);
    exit;
}

// Bei nur einer Person liefert SimpleXML/JSON kein Array von Personen, sondern
// direkt die Person. Das wird hier vereinheitlicht.
$personen = $XmlData['Persons']['Person'] ?? [];
if (!is_array($personen)) {
    $personen = [];
} elseif ($personen !== [] && !isset($personen[0])) {
    $personen = [$personen];
}

if (isset($_POST['Gruppe']['Feuerwehr'], $_POST['Gruppe']['GruppenName'], $_POST['Persons']['Vorname'])
    && is_array($_POST['Persons']['Vorname'])) {

    // XML-Daten aufbauen
    $xml = new DOMDocument("1.0", "utf-16");

    // Gruppe-Element erstellen
    $gruppe = $xml->createElement("Gruppe");
    $xml->appendChild($gruppe);

    if ($old_xml === null) {
        // Feuerwehr, GruppenName, Organisationseinheit
        $organisationseinheit = input($_POST['Gruppe']['Organisationseinheit'] ?? '');
        if (!in_array($organisationseinheit, $config['organizationalunits'], true)) {
            $organisationseinheit = '';
        }
        $gruppe->appendChild(xmlElement($xml, "Feuerwehr", input($_POST["Gruppe"]["Feuerwehr"])));
        $gruppe->appendChild(xmlElement($xml, "GruppenName", input($_POST["Gruppe"]["GruppenName"])));
        $gruppe->appendChild(xmlElement($xml, "Organisationseinheit", $organisationseinheit));
        $gruppe->appendChild(xmlElement($xml, "TimeStampAnmeldung", (new DateTime())->format("Y-m-d\TH:i:s")));
    } else {
        // Bei Änderungen bleiben Feuerwehr, GruppenName und Organisationseinheit unverändert
        foreach (['Feuerwehr', 'GruppenName', 'Organisationseinheit', 'TimeStampAnmeldung'] as $feld) {
            $gruppe->appendChild(xmlElement($xml, $feld, value($XmlData, $feld)));
        }
    }

    // Persons-Element erstellen
    $persons = $xml->createElement("Persons");
    $gruppe->appendChild($persons);

    $invalid = false;
    $post = $_POST['Persons'];

    // Personen hinzufügen (maximal 9 Teilnehmende + Ersatz)
    foreach (array_slice($post['Vorname'], 0, ANZAHL_ZEILEN, true) as $index => $vorname) {
        $vorname = input($vorname);
        $nachname = input($post['Nachname'][$index] ?? '');

        // Leere Zeilen (z. B. kein Ersatz) überspringen
        if ($vorname === '' && $nachname === '') {
            continue;
        }

        $geschlecht = input($post['Geschlecht'][$index] ?? '', 1);
        if (!in_array($geschlecht, GESCHLECHT_OPTIONEN, true)) {
            $geschlecht = 'N';
        }

        $person = $xml->createElement("Person");
        $persons->appendChild($person);
        $person->appendChild(xmlElement($xml, "Vorname", $vorname));
        $person->appendChild(xmlElement($xml, "Nachname", $nachname));
        $person->appendChild(xmlElement($xml, "Geschlecht", $geschlecht));

        // Geburtsdatum prüfen und in das Format YYYY-MM-DDTHH:MM:SS konvertieren
        $date = DateTime::createFromFormat('!d.m.Y', input($post['Geburtsdatum'][$index] ?? '', 10));
        $errors = DateTime::getLastErrors();
        $alter = $date ? (int)$eventDate->format('Y') - (int)$date->format('Y') : null;
        if (!$date || !empty($errors['warning_count']) || $allowDate < $date
            || $alter < MINDESTALTER || $alter > HOECHSTALTER) {
            $date = new DateTime("0001-01-01 00:00:00"); //C# min value
            $invalid = true;
        }
        $person->appendChild(xmlElement($xml, "Geburtsdatum", $date->format("Y-m-d\TH:i:s")));
    }

    // XML speichern
    if ($datafile === null) {
        $datafile = generateRandomString(48);
    }
    $url = $config['url'] . '?anmeldung=' . $datafile;

    $gruppe->appendChild(xmlElement($xml, "UrlderAnmeldung", $url));
    $gruppe->appendChild(xmlElement($xml, "TimeStampAenderung", (new DateTime())->format("Y-m-d\TH:i:s")));

    $xml->formatOutput = true;
    if ($xml->save('./xml/' . $datafile . '.xml') === false) {
        http_response_code(500);
        die('Die Anmeldung konnte nicht gespeichert werden. Bitte wende dich an die Veranstaltungsleitung.');
    }

    $email = trim((string)($_POST['Email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = str_replace('{URL}', $url, $config['mailmessage']);
        $header = 'From: ' . $config['mailabsender'] . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
        mail($email, mailSubject('Anmeldung zum ' . strip_tags($config['description'])), $message, $header);
    }

    setcookie("saved", "1");
    if ($invalid) {
        setcookie("invalid", "1");
    }
    header('Location: ./?anmeldung=' . $datafile);
    exit;
}
$saved = false;
$invalid = false;
if (!empty($_COOKIE['saved'])) {
    $saved = true;
    setcookie("saved", "", time() - 1000);
}
if (!empty($_COOKIE['invalid'])) {
    $invalid = true;
    setcookie("invalid", "", time() - 1000);
}
$gespeichert = $old_xml !== null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $config['title']; ?></title>
    <link rel="stylesheet" href="./css/bootstrap.min.css"
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3">
    <link rel="stylesheet" href="./css/KJFCux.css">
</head>
<body>
<div class="container">
    <header class="container">
        <div class="row align-items-center">
            <div class="col-sm-2">
                <img src="<?php echo $config['logo']; ?>" alt="Logo" width="150">
            </div>
            <div class="col-sm-10">
                <h1 class="mt-4 mb-4 text-center"><?php echo $config['headline']; ?></h1>
            </div>
        </div>
    </header>
    <?php
    if ($saved) {
        ?>
        <div class="card text-white bg-success mb-4">
            <div class="card-body">
                Die Daten wurden erfolgreich gespeichert. Du kannst sie jederzeit über die aktuelle Seite aktualisieren.
                Wir empfehlen daher diesen Link zu speichern, um später darauf zugreifen zu können.
            </div>
        </div>
        <?php
    }
    if ($invalid) {
        ?>
        <div class="card text-white bg-danger mb-4">
            <div class="card-body">
                Achtung, Daten sind nicht valide. Bitte prüfe, ob alle Geburtsdaten korrekt eingetragen sind.
            </div>
        </div>
        <?php
    }
    if (!$registrationEnabled) {
        ?>
        <div class="card bg-light mb-4">
            <div class="card-body">
                Der Anmeldeschluss ist vorbei. Die Anmeldung kann nur noch angesehen, aber nicht mehr geändert werden.
            </div>
        </div>
        <?php
    }
    if ($wertungsbogen !== null) {
        ?>
        <div class="card text-white bg-success mb-4">
            <div class="card-body">
                <a href="./?anmeldung=<?php echo e($datafile); ?>&amp;wertungsbogen=1" class="text-white">Hier kannst du den Wertungsbogen herunterladen.</a>
            </div>
        </div>
        <?php
    }
    ?>
    <form method="POST" id="form">
        <div class="row mb-4">
            <div class="col-sm-3">
                <label for="Feuerwehr">Jugendfeuerwehr:</label>
                <input type="text" class="form-control" name="Gruppe[Feuerwehr]" id="Feuerwehr"
                       placeholder="Musterdorf" maxlength="64"
                       value="<?php echo_if_isset($XmlData, 'Feuerwehr'); ?>"
                       required <?php if ($gespeichert) { echo 'readonly'; } ?>>
            </div>
            <div class="col-sm-3">
                <label for="GruppenName">Gruppenname:</label>
                <input type="text" class="form-control" name="Gruppe[GruppenName]" id="GruppenName"
                       placeholder="Musterdorf Blau" maxlength="64"
                       value="<?php echo_if_isset($XmlData, 'GruppenName'); ?>"
                       required <?php if ($gespeichert) { echo 'readonly'; } ?>>
            </div>
            <div class="col-sm-3">
                <label for="Organisationseinheit"><?php echo $config['organizationalunit']; ?></label>
                <select class="form-select" name="Gruppe[Organisationseinheit]" id="Organisationseinheit"
                        required <?php if ($gespeichert) { echo 'disabled'; } ?>>
                    <option value="">Bitte wählen</option>
                    <?php foreach ($config['organizationalunits'] as $unit): ?>
                        <option value="<?php echo e($unit); ?>" <?php echo $unit === value($XmlData, 'Organisationseinheit') ? 'selected' : ''; ?>><?php echo e($unit); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label for="Email">E-Mail (optional):</label>
                <input type="email" class="form-control" name="Email" id="Email" placeholder="Email@anbieter.de"
                       autocomplete="email" data-bs-toggle="tooltip" data-bs-placement="right"
                       title="Wir senden dir einmalig einen Link zu dieser Anmeldung, damit du sie später ändern kannst. Deine E-Mail Adresse wird nicht gespeichert.">
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-sm-12">
                <p><?php echo $config['description']; ?></p>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-sm-12">
                <h4>Meldebogen</h4>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <p id="age-validation-message" class="text-danger"></p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Nr.</th>
                            <th>Vorname</th>
                            <th>Nachname</th>
                            <th>Geschlecht</th>
                            <th>Geburtsdatum</th>
                            <th>Alter</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        for ($i = 0; $i < ANZAHL_ZEILEN; $i++) {
                            $person = is_array($personen[$i] ?? null) ? $personen[$i] : [];
                            $ersatz = $i === ANZAHL_ZEILEN - 1;
                            $required = $ersatz ? '' : 'required';
                            $geburtsdatum = if_isset_dt($person, 'Geburtsdatum');
                            ?>
                            <tr<?php echo $ersatz ? ' class="ersatz"' : ''; ?>>
                                <td><?php echo $ersatz ? 'Ersatz' : ($i + 1) . '.'; ?></td>
                                <td>
                                    <input name="Persons[Vorname][]" type="text" class="form-control vorname"
                                           placeholder="Vorname" aria-label="Vorname" maxlength="64" <?php echo $required; ?>
                                           value="<?php echo_if_isset($person, 'Vorname'); ?>">
                                </td>
                                <td>
                                    <input name="Persons[Nachname][]" type="text" class="form-control nachname"
                                           placeholder="Nachname" aria-label="Nachname" maxlength="64" <?php echo $required; ?>
                                           value="<?php echo_if_isset($person, 'Nachname'); ?>">
                                </td>
                                <td>
                                    <select name="Persons[Geschlecht][]" class="form-select geschlecht" aria-label="Geschlecht" <?php echo $required; ?>>
                                        <option value="">Bitte wählen</option>
                                        <?php foreach (GESCHLECHT_OPTIONEN as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo value($person, 'Geschlecht') === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input name="Persons[Geburtsdatum][]" type="text" inputmode="numeric"
                                           class="form-control Geburtsdatum<?php echo $geburtsdatum === '01.01.0001' ? ' error' : ''; ?>"
                                           placeholder="TT.MM.JJJJ" aria-label="Geburtsdatum" maxlength="10" <?php echo $required; ?>
                                           value="<?php echo e($geburtsdatum); ?>">
                                </td>
                                <td class="age<?php echo $ersatz ? '' : ' average'; ?>"></td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm">
                <p>Gesamtalter: <span id="total-age">0</span> : <span id="age-count">0</span> = <span id="average">0</span></p>
                <p>Die Berechnung der Alter erfolgt anhand des Geburtsjahrgangs. Der Ersatz wird nicht mitgerechnet.</p>
            </div>
            <?php if ($registrationEnabled) { ?>
            <div class="col-sm text-center">
                <button class="btn btn-primary" type="submit">Anmeldung übermitteln</button>
            </div>
            <?php } ?>
        </div>
    </form>
</div>
<div id="settings" hidden data-eventdate="<?php echo e($config['eventdate']); ?>"
     data-min-age="<?php echo MINDESTALTER; ?>" data-max-age="<?php echo HOECHSTALTER; ?>"></div>
<script src="./js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"></script>
<script src="./js/KJFCux.js"></script>
</body>
</html>

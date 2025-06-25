<?php

if (file_exists('config.php')) {
    include('config.php');
    if (!isset($config)) {
        die('Config not valid, please copy config.sample.php to config.php and reconfigure values');
    }
} else {
    die('Config not found, please copy config.sample.php to config.php and configure values');
}

$eventDate = DateTimeImmutable::createFromFormat("d.m.Y  H:i:s", $config['eventdate'] . ' 00:00:00');

$allowDate = $eventDate->modify('-10 year');

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

function echo_if_isset($foo, $echo = null): void
{
    if (isset($foo) && !is_array($foo)) {
        if ($echo === null) {
            echo htmlspecialchars($foo);
        } else {
            echo $echo;
        }
    }
}

function if_isset_dt($foo): string
{
    if (isset($foo)) {
        return date('d.m.Y', strtotime($foo));
    }
    return '';
}


$XmlData = [];
$datafile = null;
$old_xml = null;
$result_xlsx = null;
if (isset($_GET['anmeldung']) && ctype_alnum($_GET['anmeldung']) && file_exists('./xml/' . $_GET['anmeldung'] . '.xml')) {
    $old_xml = simplexml_load_file('./xml/' . $_GET['anmeldung'] . '.xml');
    if ($old_xml) {
        //Convert SimpleXml Object to associative Array
        $XmlData = json_decode(json_encode($old_xml), TRUE);
        $datafile = $_GET['anmeldung'];
    }
    if(file_exists('./xml/Wertungsbögen/' . $_GET['anmeldung'] . '.xlsx')){
        $result_xlsx = './xml/Wertungsbögen/' . $_GET['anmeldung'] . '.xlsx';
    }
}

if (isset($_POST['Gruppe']['Feuerwehr']) && isset($_POST['Gruppe']['GruppenName']) && isset($_POST['Persons']['Vorname']) && isset($_POST['Persons']['Nachname']) && isset($_POST['Persons']['Geburtsdatum'])) {

    // XML-Daten aufbauen
    $xml = new DOMDocument("1.0", "utf-16");

    // Gruppe-Element erstellen
    $gruppe = $xml->createElement("Gruppe");
    $xml->appendChild($gruppe);

    if ($old_xml === null) {
        // Feuerwehr, GruppenName, Organisationseinheit
        $feuerwehr = $xml->createElement("Feuerwehr", mb_strimwidth(trim($_POST["Gruppe"]["Feuerwehr"]), 0, 64));
        $gruppe->appendChild($feuerwehr);

        $gruppenName = $xml->createElement("GruppenName", mb_strimwidth(trim($_POST["Gruppe"]["GruppenName"]), 0, 64));
        $gruppe->appendChild($gruppenName);

        if (!isset($_POST['Gruppe']["Organisationseinheit"])) {
            $_POST['Gruppe']["Organisationseinheit"] = '';
        }
        $organisationseinheit = $xml->createElement("Organisationseinheit", mb_strimwidth(trim($_POST["Gruppe"]["Organisationseinheit"]), 0, 64));
        $gruppe->appendChild($organisationseinheit);

        $timeStampAnmeldung = $xml->createElement("TimeStampAnmeldung", (new DateTime())->format("Y-m-d\TH:i:s"));
        $gruppe->appendChild($timeStampAnmeldung);
    } else {
        // Feuerwehr, GruppenName, Organisationseinheit
        $feuerwehr = $xml->createElement("Feuerwehr", $XmlData['Feuerwehr']);
        $gruppe->appendChild($feuerwehr);

        $gruppenName = $xml->createElement("GruppenName", $XmlData["GruppenName"]);
        $gruppe->appendChild($gruppenName);

        $organisationseinheit = $xml->createElement("Organisationseinheit", $XmlData["Organisationseinheit"]);
        $gruppe->appendChild($organisationseinheit);

        $timeStampAnmeldung = $xml->createElement("TimeStampAnmeldung", $XmlData["TimeStampAnmeldung"]);
        $gruppe->appendChild($timeStampAnmeldung);
    }

    // Persons-Element erstellen
    $persons = $xml->createElement("Persons");
    $gruppe->appendChild($persons);


    // Personen hinzufügen
    foreach ($_POST["Persons"]["Vorname"] as $index => $vorname) {

        if (isset($_POST["Persons"]["Nachname"][$index])) {
            $nachname = $_POST["Persons"]["Nachname"][$index];
        } else {
            $nachname = '';
        }
        if (isset($_POST["Persons"]["Nachname"][$index])) {
            $geschlecht = $_POST["Persons"]["Geschlecht"][$index];
        } else {
            $geschlecht = 'N';
        }

        //If Replacement ist empty
        if ($vorname == '' && $nachname == '') {
            continue;
        }

        $person = $xml->createElement("Person");
        $persons->appendChild($person);

        $person->appendChild($xml->createElement("Vorname", mb_strimwidth(trim($vorname), 0, 64)));

        $person->appendChild($xml->createElement("Nachname", mb_strimwidth(trim($nachname), 0, 64)));

        $person->appendChild($xml->createElement("Geschlecht", mb_strimwidth(trim($geschlecht), 0, 1)));

        $date = false;
        if (isset($_POST["Persons"]["Geburtsdatum"][$index])) {
            // Geburtsdatum in das Format YYYY-MM-DD konvertieren
            $date = DateTime::createFromFormat("d.m.Y  H:i:s", $_POST["Persons"]["Geburtsdatum"][$index] . ' 00:00:00');
        }
        if (!$date || $allowDate < $date
            || ($eventDate->format('Y') - $date->format('Y')) < 10 || ($eventDate->format('Y') - $date->format('Y')) > 18) {
            $date = new DateTime("0001-01-01 00:00:00"); //C# min value
            setcookie("invalid", true);
        }
        $geburtsdatum = $xml->createElement("Geburtsdatum", $date->format("Y-m-d\TH:i:s"));
        $person->appendChild($geburtsdatum);
    }

    // XML speichern
    if ($datafile === null) {
        $datafile = generateRandomString(48);
    }
    $url = $config['url'] . '?anmeldung=' . $datafile;

    $urlderAnmeldung = $xml->createElement("UrlderAnmeldung", $url);
    $gruppe->appendChild($urlderAnmeldung);

    $timeStampAenderung = $xml->createElement("TimeStampAenderung", (new DateTime())->format("Y-m-d\TH:i:s"));
    $gruppe->appendChild($timeStampAenderung);

    $xml->formatOutput = true;
    $xml->save('./xml/' . $datafile . '.xml');

    if (isset($_POST['Email']) && $_POST['Email'] != '') {
        $message = str_replace('{URL}', $url, $config['mailmessage']);
        $header = 'From: ' . $config['mailabsender'] . "\r\n" . 'Content-Type: text/plain; charset=utf-8' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        mail(trim($_POST['Email']), 'Anmeldung zum ' . $config['description'], $message, $header);
    }


    header('Location: ./?anmeldung=' . $datafile);
    setcookie("saved", true);
    exit;
}
$saved = false;
$invalid = false;
if (isset($_COOKIE['saved']) && $_COOKIE['saved']) {
    $saved = true;
    setcookie("saved", false, time() - 1000);
}
if (isset($_COOKIE['invalid']) && $_COOKIE['invalid']) {
    $invalid = true;
    setcookie("invalid", false, time() - 1000);
}

//Javascript Hash created with: curl https://DOMAIN/js/KJFCux.js | openssl dgst -sha384 -binary | openssl base64 -A

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['title']; ?></title>
    <link rel="stylesheet" href="./css/bootstrap.min.css"
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3">
    <link rel="stylesheet" href="./css/KJFCux.css"
          integrity="sha384-O+mnne/csN6seug8TlJ6t1LftNZ+QCWqXGLN0VdX4dP5JRvlVj35NVpvE0jhjoMn">
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
        <div class="card text-white bg-success">
            <div class="card-body">
                Die Daten wurden erfolgreich gespeichert. Du kannst sie jederzeit über die aktuelle Seite aktualisieren.
                Wir empfehlen daher diesen Link zu speichern, um später darauf zugreifen zu können.
            </div>
        </div>
        <?php
    }
    if ($invalid) {
        ?>
        <div class="card text-white bg-danger">
            <div class="card-body">
                Achtung, Daten sind nicht valide. Bitte prüfe, ob alle Jahrgänge korrekt eingetragen sind.
            </div>
        </div>
        <?php
    }
    if(isset($result_xlsx) && file_exists($result_xlsx)) {
        ?>
        <div class="card text-white bg-success mb-4">
            <div class="card-body">
                <a href="<?php echo $result_xlsx; ?>" class="text-white">Hier kannst du den Wertungsbogen herunterladen.</a>
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
                       placeholder="Musterdorf"
                       value="<?php echo_if_isset($XmlData['Feuerwehr']); ?>"
                       required <?php echo_if_isset($XmlData['Feuerwehr'], 'readonly'); ?>>
            </div>
            <div class="col-sm-3">
                <label for="GruppenName">Gruppenname:</label>
                <input type="text" class="form-control" name="Gruppe[GruppenName]" id="GruppenName"
                       placeholder="Musterdorf Blau"
                       value="<?php echo_if_isset($XmlData['GruppenName']); ?>"
                       required <?php echo_if_isset($XmlData['Feuerwehr'], 'readonly'); ?>>
            </div>
            <div class="col-sm-3">
                <label for="Organisationseinheit"><?php echo $config['organizationalunit']; ?></label>
                <select class="form-control" name="Gruppe[Organisationseinheit]" id="Organisationseinheit"
                        required <?php echo_if_isset($XmlData['Feuerwehr'], 'disabled'); ?>>
                    <?php foreach ($config['organizationalunits'] as $unit): ?>
                        <option value="<?php echo $unit; ?>" <?php echo (isset($XmlData['Organisationseinheit']) && $unit === $XmlData['Organisationseinheit']) ? 'selected' : ''; ?>><?php echo $unit; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label for="Email">E-Mail (optional):</label>
                <input type="text" class="form-control" name="Email" id="Email" placeholder="Email@anbieter.de"
                       data-bs-toggle="tooltip" data-bs-placement="right"
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
                    <table class="table">
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
                        $person = $XmlData['Persons'][0]['Person'];
                        for ($i = 0; $i <= 9; $i++) {
                            $person = $XmlData['Persons']['Person'][$i];

                            $start = ($i + 1) . '.';
                            $ageClass = 'age average';
                            $required = 'required ';

                            if ($i == 9) {
                                $start = 'Ersatz';
                                $ageClass = 'age';
                                $required = '';
                            }
                            ?>
                            <tr>
                                <td><?php echo $start; ?></td>
                                <td>
                                    <input name="Persons[Vorname][]" type="text" class="form-control"
                                           placeholder="Vorname"
                                        <?php echo $required; ?> value="<?php echo_if_isset($person['Vorname']); ?>">
                                </td>
                                <td>
                                    <input name="Persons[Nachname][]" type="text" class="form-control"
                                           placeholder="Nachname" <?php echo $required; ?>
                                           value="<?php echo_if_isset($person['Nachname']); ?>">
                                </td>
                                <td>
                                    <select name="Persons[Geschlecht][]" class="form-control">
                                        <option value="D" <?php echo isset($person['Geschlecht']) && $person['Geschlecht'] == 'D' ? 'selected' : '' ?>>
                                            D
                                        </option>
                                        <option value="M" <?php echo isset($person['Geschlecht']) && $person['Geschlecht'] == 'M' ? 'selected' : '' ?>>
                                            M
                                        </option>
                                        <option value="W" <?php echo isset($person['Geschlecht']) && $person['Geschlecht'] == 'W' ? 'selected' : '' ?>>
                                            W
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input name="Persons[Geburtsdatum][]" type="text"
                                           class="form-control Geburtsdatum<?php echo (if_isset_dt($person['Geburtsdatum']) == '01.01.0001') ? ' error' : ''; ?>"
                                           placeholder="TT.MM.JJJJ" <?php echo $required; ?>
                                           value="<?php echo if_isset_dt($person['Geburtsdatum']); ?>">
                                </td>
                                <td class="<?php echo $ageClass; ?>"></td>
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
                <p>Gesamtalter: <span id="total-age">0</span> : 9 = <span id="average">0</span></p>
                <p>Die Berechnung der Alter erfolgt anhand des Geburtsjahrgangs.</p>
            </div>
            <?php if($registrationEnabled){ ?>
            <div class="col-sm text-center">
                <button class="btn btn-primary" type="submit">Anmeldung übermitteln</button>
            </div>
            <?php } ?>
        </div>
    </form>
    <span id="eventdate" hidden><?php echo $config['eventdate']; ?></span>
</div>
<script src="./js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"></script>
<script src="./js/KJFCux.js"
        integrity="sha384-QbaHC+Eb61iElgkVWOguJB/AYCo+3+V9lIYoKmNiXWJti84T4XMJ30tBgNRlDeGs"></script>
</body>
</html>



<?php

if (file_exists('config.php')) {
    include('config.php');
    if(!isset($config)) {
        die('Config not valid, please copy config.sample.php to config.php and reconfigure values');
    }
} else {
    die('Config not found, please copy config.sample.php to config.php and configure values');
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
    if (isset($foo)) {
        if ($echo === null) {
            echo $foo;
        } else {
            echo $echo;
        }
    }
}

function echo_if_isset_dt($foo): void
{
    if (isset($foo)) {
        echo date('d.m.Y', strtotime($foo));
    }
}


$XmlData = [];
$datafile = null;
$old_xml = null;
if (isset($_GET['anmeldung']) && ctype_alnum($_GET['anmeldung']) && file_exists('./xml/' . $_GET['anmeldung'] . '.xml')) {
    $old_xml = simplexml_load_file('./xml/' . $_GET['anmeldung'] . '.xml');
    if ($old_xml) {
        //Convert SimpleXml Object to associative Array
        $XmlData = json_decode(json_encode($old_xml), TRUE);
        $datafile = $_GET['anmeldung'];
    }
}

if (isset($_POST['Gruppe']["Feuerwehr"]) && isset($_POST['Gruppe']["GruppenName"]) && isset($_POST['Gruppe']["Organisationseinheit"]) && isset($_POST['Persons']) && isset($_POST['Persons']['Vorname']) && isset($_POST['Persons']['Nachname']) && isset($_POST['Persons']['Geburtsdatum'])) {

    // XML-Daten aufbauen
    $xml = new DOMDocument("1.0", "utf-16");

    // Gruppe-Element erstellen
    $gruppe = $xml->createElement("Gruppe");
    $xml->appendChild($gruppe);

    if ($old_xml === null) {
        // Feuerwehr, GruppenName, Organisationseinheit
        $feuerwehr = $xml->createElement("Feuerwehr", mb_strimwidth($_POST["Gruppe"]["Feuerwehr"], 0, 64));
        $gruppe->appendChild($feuerwehr);

        $gruppenName = $xml->createElement("GruppenName", mb_strimwidth($_POST["Gruppe"]["GruppenName"], 0, 64));
        $gruppe->appendChild($gruppenName);

        $organisationseinheit = $xml->createElement("Organisationseinheit", mb_strimwidth($_POST["Gruppe"]["Organisationseinheit"], 0, 64));
        $gruppe->appendChild($organisationseinheit);
    } else {
        // Feuerwehr, GruppenName, Organisationseinheit
        $feuerwehr = $xml->createElement("Feuerwehr", $XmlData['Feuerwehr']);
        $gruppe->appendChild($feuerwehr);

        $gruppenName = $xml->createElement("GruppenName", $XmlData["GruppenName"]);
        $gruppe->appendChild($gruppenName);

        $organisationseinheit = $xml->createElement("Organisationseinheit", $XmlData["Organisationseinheit"]);
        $gruppe->appendChild($organisationseinheit);
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

        $person->appendChild($xml->createElement("Vorname", mb_strimwidth($vorname, 0, 64)));

        $person->appendChild($xml->createElement("Nachname", mb_strimwidth($nachname, 0, 64)));

        $person->appendChild($xml->createElement("Geschlecht", mb_strimwidth($geschlecht, 0, 1)));

        $date = false;
        if (isset($_POST["Persons"]["Geburtsdatum"][$index])) {
            // Geburtsdatum in das Format YYYY-MM-DD konvertieren
            $date = DateTime::createFromFormat("d.m.Y  H:i:s", $_POST["Persons"]["Geburtsdatum"][$index] . ' 00:00:00');
        }
        if (!$date || ($config['year'] - $date->format('Y')) < 10 || ($config['year'] - $date->format('Y')) > 18) {
            $date = (new DateTime)->setTimestamp(0);
            setcookie("invalid", true);
        }
        $geburtsdatum = $xml->createElement("Geburtsdatum", $date->format("Y-m-d\TH:i:s"));

        $person->appendChild($geburtsdatum);
    }

    // XML speichern
    if ($datafile === null) {
        $datafile = generateRandomString(48);
    }
    $xml->formatOutput = true;
    $xml->save('./xml/' . $datafile . '.xml');

    header('Location: ./?anmeldung=' . $datafile);
    setcookie("saved", true);
    exit;
}
$saved = false;
$invalid = false;
if ($_COOKIE['saved']) {
    $saved = true;
    setcookie("saved", false, time() - 1000);
}
if ($_COOKIE['invalid']) {
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
          integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N">
    <style>
        .error {
            border-color: #dc3545 !important;
        }
    </style>
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
    ?>
    <form method="POST" id="form">
        <div class="row mb-4">
            <div class="col-sm-4">
                <label for="Feuerwehr">Jugendfeuerwehr:</label>
                <input type="text" class="form-control" name="Gruppe[Feuerwehr]" id="Feuerwehr"
                       placeholder="Musterdorf"
                       value="<?php echo_if_isset($XmlData['Feuerwehr']); ?>" <?php echo_if_isset($XmlData['Feuerwehr'], 'readonly'); ?>>
            </div>
            <div class="col-sm-4">
                <label for="GruppenName">Gruppenname:</label>
                <input type="text" class="form-control" name="Gruppe[GruppenName]" id="GruppenName"
                       placeholder="Musterdorf Blau"
                       value="<?php echo_if_isset($XmlData['GruppenName']); ?>" <?php echo_if_isset($XmlData['Feuerwehr'], 'readonly'); ?>>
            </div>
            <div class="col-sm-4">
                <label for="Organisationseinheit">Stadt, - Gemeinde:</label>
                <input type="text" class="form-control" name="Gruppe[Organisationseinheit]" id="Organisationseinheit"
                       placeholder="Mustergemeinde"
                       value="<?php echo_if_isset($XmlData['Organisationseinheit']); ?>" <?php echo_if_isset($XmlData['Feuerwehr'], 'readonly'); ?>>
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
                                    <input name="Persons[Geburtsdatum][]" type="text" class="form-control Geburtsdatum"
                                           placeholder="TT.MM.JJJJ" <?php echo $required; ?>
                                           value="<?php echo_if_isset_dt($person['Geburtsdatum']); ?>">
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
                <p>Gesamtalter: <span id="total-age">0</span>, : 9 = <span id="average">0</span></p>
                <p>Die Berechnung der Alters erfolgt anhand des Geburtsjahrgangs</p>
            </div>
            <div class="col-sm text-center">
                <button class="btn btn-primary" type="submit">Anmeldung übermitteln</button>
            </div>
        </div>
    </form>
    <span id="currentyear" hidden><?php echo $config['year'];?></span>
</div>
<script src="./js/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8="></script>
<script src="./js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"></script>
<script src="./js/KJFCux.js"
        integrity="sha384-Dy5ijGSRwKoXXEiSm8EDzJtIvaDjrExlA72orHU/b6uftxNFw1HCbGlQShbhOjWZ"></script>
</body>
</html>



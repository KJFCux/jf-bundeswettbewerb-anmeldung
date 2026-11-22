<?php
/**
 * Allgemeine Hilfsfunktionen, unabhängig vom konkreten Anmeldeformular.
 * Die Datei ist bewusst ohne Abhängigkeiten gehalten, damit sie auch in
 * anderen Anmelde-Anwendungen (z. B. der KZL-Anmeldung) genutzt werden kann.
 */

function generateRandomString(int $length = 10): string
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/** HTML-Ausgabe escapen */
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

/** Formularwert bereinigen und auf eine Maximallänge kürzen */
function input($string, int $len = 64): string
{
    if (!is_string($string)) {
        return '';
    }
    return mb_strimwidth(trim($string), 0, $len);
}

/**
 * Liest eine XML-Datei als assoziatives Array ein. Liefert null, wenn die
 * Datei nicht gelesen werden kann.
 */
function loadXmlData(string $file): ?array
{
    $xml = @simplexml_load_file($file);
    if (!$xml) {
        return null;
    }
    $data = json_decode(json_encode($xml), true);
    return is_array($data) ? $data : [];
}

/**
 * SimpleXML/JSON liefert bei nur einem Kindelement kein Array, sondern direkt
 * das Element. Diese Funktion liefert immer eine Liste.
 */
function asList($value): array
{
    if (!is_array($value) || $value === []) {
        return [];
    }
    return isset($value[0]) ? $value : [$value];
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

/** Betreff einer E-Mail mit Umlauten korrekt kodieren */
function mailSubject(string $subject): string
{
    return mb_encode_mimeheader($subject, 'UTF-8', 'B');
}

/**
 * Einfache Begrenzung pro IP-Adresse (z. B. gegen massenhaften E-Mail-Versand).
 * Gespeichert wird nur ein gesalzener Hash der IP-Adresse mit den Zeitpunkten
 * der letzten Aktionen. Einträge werden nach Ablauf des Zeitfensters gelöscht.
 *
 * @param string $dir    Verzeichnis für die Zähler (darf nicht öffentlich abrufbar sein)
 * @param string $action Name der Aktion, z. B. "mail"
 * @param int    $max    Erlaubte Anzahl im Zeitfenster (0 = unbegrenzt)
 * @param int    $window Zeitfenster in Sekunden
 * @return bool true, wenn die Aktion erlaubt ist (und gezählt wurde)
 */
function rateLimit(string $dir, string $action, int $max, int $window = 3600): bool
{
    if ($max <= 0) {
        return true;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return true; // Ohne Schreibrechte nicht blockieren
    }

    $saltFile = $dir . '/salt';
    if (!file_exists($saltFile)) {
        @file_put_contents($saltFile, bin2hex(random_bytes(32)), LOCK_EX);
    }
    $salt = (string)@file_get_contents($saltFile);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $file = $dir . '/' . $action . '_' . hash_hmac('sha256', $ip, $salt);

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return true;
    }
    flock($handle, LOCK_EX);
    $now = time();
    $entries = json_decode((string)stream_get_contents($handle), true);
    $entries = array_values(array_filter(is_array($entries) ? $entries : [], function ($time) use ($now, $window) {
        return is_int($time) && $time > $now - $window;
    }));
    $allowed = count($entries) < $max;
    if ($allowed) {
        $entries[] = $now;
    }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($entries));
    flock($handle, LOCK_UN);
    fclose($handle);

    // Gelegentlich abgelaufene Zähler anderer IP-Adressen aufräumen
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . '/*_*') ?: [] as $old) {
            if (filemtime($old) < $now - $window) {
                @unlink($old);
            }
        }
    }
    return $allowed;
}

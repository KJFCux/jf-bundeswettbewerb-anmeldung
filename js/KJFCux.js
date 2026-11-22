// Wandelt ein Datum im Format JJJJ-MM-TT (Datumsfeld) oder TT.MM.JJJJ (ältere Browser)
// in ein Date-Objekt um. Liefert null bei ungültigen Daten wie dem 31.02.
function parseDate(value) {
    let match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    let year, month, day;
    if (match) {
        [year, month, day] = [match[1], match[2], match[3]].map(Number);
    } else if ((match = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/))) {
        [day, month, year] = [match[1], match[2], match[3]].map(Number);
    } else {
        return null;
    }
    const date = new Date(year, month - 1, day);
    // Date springt bei ungültigen Tagen in den nächsten Monat
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }
    return date;
}

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
    new bootstrap.Tooltip(element);
});

document.addEventListener('DOMContentLoaded', function () {
    const settings = document.querySelector('#settings').dataset;
    const eventDate = parseDate(settings.eventdate);
    const minAge = Number(settings.minAge);
    const maxAge = Number(settings.maxAge);

    const validationMessage = document.querySelector('#age-validation-message');
    const birthdays = Array.from(document.querySelectorAll('.Geburtsdatum'));
    const ageCells = Array.from(document.querySelectorAll('.average'));
    const totalAge = document.querySelector('#total-age');
    const ageCount = document.querySelector('#age-count');
    const totalAverage = document.querySelector('#average');

    // Ersatz: Geschlecht und Geburtsdatum sind Pflicht, sobald ein Name eingetragen ist
    const ersatz = document.querySelector('tr.ersatz');
    function updateErsatzRequired() {
        if (!ersatz) return;
        const filled = ersatz.querySelector('.vorname').value.trim() !== ''
            || ersatz.querySelector('.nachname').value.trim() !== '';
        ersatz.querySelectorAll('input, select').forEach(function (element) {
            element.required = filled;
        });
    }

    // Prüft ein Geburtsdatum und gibt die Fehlermeldung zurück (leer = gültig)
    function validateRow(element) {
        const ageCell = element.closest('tr').querySelector('td:last-child');
        element.classList.remove('error');
        ageCell.textContent = '';

        const value = element.value.trim();
        // Unvollständige Eingaben liefert das Datumsfeld als leeren Wert
        if (value === '') {
            if (element.validity.badInput) {
                element.classList.add('error');
                return 'Bitte gib ein vollständiges Geburtsdatum ein.';
            }
            return '';
        }

        const birthday = parseDate(value);
        if (!birthday || birthday.getFullYear() < 1900) {
            element.classList.add('error');
            return 'Bitte gib ein gültiges Geburtsdatum ein.';
        }

        // Alter anhand des Geburtsjahrgangs
        const age = eventDate.getFullYear() - birthday.getFullYear();
        ageCell.textContent = age;

        if (age < minAge || age > maxAge) {
            element.classList.add('error');
            return 'Das Alter muss zwischen ' + minAge + ' und ' + maxAge + ' Jahren liegen.';
        }
        // Das Mindestalter muss am Veranstaltungstag tatsächlich erreicht sein
        const minAgeDate = new Date(birthday);
        minAgeDate.setFullYear(birthday.getFullYear() + minAge);
        if (age === minAge && eventDate < minAgeDate) {
            element.classList.add('error');
            return 'Das Mindestalter beträgt ' + minAge + ' Jahre.';
        }
        return '';
    }

    function calculateTotalAge() {
        let age = 0;
        let count = 0;
        ageCells.forEach(function (element) {
            if (element.textContent !== '') {
                count++;
                age += Number(element.textContent);
            }
        });
        totalAge.textContent = age;
        ageCount.textContent = count;
        totalAverage.textContent = count === 0 ? '0' : (Math.round(age * 100 / count) / 100).toString();
    }

    function validate() {
        let message = '';
        birthdays.forEach(function (element) {
            message = validateRow(element) || message;
        });
        validationMessage.textContent = message;
        calculateTotalAge();
        updateErsatzRequired();
    }

    validate();

    birthdays.forEach(function (element) {
        element.addEventListener('change', validate);
        element.addEventListener('blur', validate);
    });
    if (ersatz) {
        ersatz.querySelectorAll('.vorname, .nachname').forEach(function (element) {
            element.addEventListener('input', updateErsatzRequired);
        });
    }

    document.querySelector('#form').addEventListener('submit', function (event) {
        validate();
        if (validationMessage.textContent !== '') {
            alert(validationMessage.textContent);
            event.preventDefault();
        }
    });
});

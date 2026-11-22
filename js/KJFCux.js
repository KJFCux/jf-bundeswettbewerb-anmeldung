// Wandelt ein Datum im Format TT.MM.JJJJ in ein Date-Objekt um
function parseDate(value) {
    const parts = value.split('.');
    return new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
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
    const dateRegex = /^(0?[1-9]|[12][0-9]|3[01])\.(0?[1-9]|1[012])\.((19|20)\d\d)$/;

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
        if (value === '') return '';

        const match = value.match(dateRegex);
        const birthday = match ? parseDate(value) : null;
        // Ungültige Tage wie 31.02. erkennen (Date springt sonst in den nächsten Monat)
        if (!birthday || birthday.getDate() !== parseInt(match[1], 10) || birthday.getMonth() !== parseInt(match[2], 10) - 1) {
            element.classList.add('error');
            return 'Bitte gib ein gültiges Geburtsdatum im Format TT.MM.JJJJ ein.';
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

function getEventDate(eventDate){
    let dateParts = eventDate.split('.');
    let day = parseInt(dateParts[0]);
    let month = parseInt(dateParts[1]) - 1;
    let year = parseInt(dateParts[2]);

    return new Date(year, month, day);
}

let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})

document.addEventListener('DOMContentLoaded', function() {
    const validationMessage = document.querySelector('#age-validation-message');
    const birthday = Array.from(document.querySelectorAll('.Geburtsdatum'));
    const average = Array.from(document.querySelectorAll('.average'));
    const totalAge = document.querySelector('#total-age');
    const totalAverage = document.querySelector('#average');
    const eventDate = getEventDate(document.querySelector('#eventdate').textContent);

    function validate() {
        // Date validation
        validationMessage.textContent = '';
        birthday.forEach(function (element) {
            calculateAgeForRow(element);
        });
        calculateTotalAge();
    }

    validate();

    // Date validation
    birthday.forEach(function (element) {
        element.addEventListener('change', function () {
            validate();
        });
    });

    document.querySelector('#form').addEventListener('submit', function (event) {
        validate();
        if (validationMessage.textContent !== '') {
            alert(validationMessage.textContent);
            event.preventDefault();
        }
    });

    function calculateTotalAge() {
        // Update total age
        let age = 0;
        let count = 0;
        average.forEach(function (element) {
            if (element.textContent !== "") {
                count++;
                age += Number(element.textContent);
            }
        });

        if (count !== 0) {
            totalAge.textContent = age;
            totalAverage.textContent = Math.floor(age / count).toString();
        }

    }

    function calculateAgeForRow(element) {
        element.classList.remove('error');
        let inputDate = element.value;
        if (inputDate === '') return;
        let dateRegex = /^(0?[1-9]|[12][0-9]|3[01])[.](0?[1-9]|1[012])[.]((19|20)\d\d)$/;
        if (!dateRegex.test(inputDate)) {
            element.classList.add('error');
            validationMessage.textContent = 'Bitte geben Sie ein gültiges Geburtsdatum im Format DD.MM.YYYY ein.';
            return;
        }

        //Mindestalter 10 Jahre
        let allowAge = getEventDate(inputDate);

        let age = eventDate.getFullYear() - allowAge.getFullYear();

        element.closest('tr').querySelector('td:last-child').textContent = age;

        if (age < 10 || age > 18) {
            element.classList.add('error');
            validationMessage.textContent = 'Das Alter muss zwischen 10 und 18 Jahren liegen.';
            return;
        }

        if (age === 10 && eventDate < allowAge) {
            element.classList.add('error');
            validationMessage.textContent = 'Das Mindestalter beträgt 10 Jahre';
            return;
        }
    }
});
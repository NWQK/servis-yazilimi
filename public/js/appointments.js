"use strict";
document.querySelectorAll('.booking-day-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const inputs = button.closest('tr').querySelectorAll('input[type="checkbox"]');
        const open = !Array.from(inputs).every(function (input) { return input.checked; });
        inputs.forEach(function (input) { input.checked = open; });
    });
});

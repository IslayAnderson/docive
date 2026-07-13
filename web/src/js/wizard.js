document.querySelectorAll('.o-card input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (radio) {
            radio.closest('.o-card').classList.toggle('is-selected', radio.checked);
        });
    });
});

var wizardForm = document.querySelector('form');
if (wizardForm) {
    wizardForm.addEventListener('submit', function () {
        var overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.add('is-visible');
        }
    });
}

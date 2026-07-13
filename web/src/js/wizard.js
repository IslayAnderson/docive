document.querySelectorAll('.o-card input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (radio) {
            radio.closest('.o-card').classList.toggle('is-selected', radio.checked);
        });
    });
});

var customTypeInput = document.getElementById('document_type_custom');
if (customTypeInput) {
    customTypeInput.addEventListener('input', function () {
        if (customTypeInput.value.trim() !== '') {
            document.querySelectorAll('input[name="document_type"]').forEach(function (radio) {
                radio.checked = false;
                radio.closest('.o-card').classList.remove('is-selected');
            });
        }
    });

    document.querySelectorAll('input[name="document_type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) {
                customTypeInput.value = '';
            }
        });
    });
}

var wizardForm = document.querySelector('form');
if (wizardForm) {
    wizardForm.addEventListener('submit', function () {
        var overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.add('is-visible');
        }
    });
}

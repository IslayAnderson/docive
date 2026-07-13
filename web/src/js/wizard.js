document.querySelectorAll('.o-card input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (radio) {
            radio.closest('.o-card').classList.toggle('is-selected', radio.checked);
        });
    });
});

(function () {
    'use strict';
    var opts = document.querySelectorAll('.pay-gateway-option input[type=radio]');
    for (var i = 0; i < opts.length; i++) {
        opts[i].addEventListener('change', function () {
            // The selected/unselected styles are handled by CSS via :checked;
            // this clears any stray inline styles from previous interactions.
            var labels = document.querySelectorAll('.pay-gateway-option label');
            for (var j = 0; j < labels.length; j++) {
                labels[j].style.borderColor = '';
                labels[j].style.background = '';
            }
        });
    }
})();

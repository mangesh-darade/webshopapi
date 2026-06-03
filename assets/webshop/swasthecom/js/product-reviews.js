(function () {
    'use strict';
    var form = document.getElementById('pr-form');
    if (!form) return;

    var ratingGroup = document.getElementById('pr-rating-group');
    var ratingInputs = ratingGroup ? ratingGroup.querySelectorAll('input[name="rating"]') : [];
    var ratingCount = document.getElementById('pr-rating-count');
    var ratingHelp = document.getElementById('pr-rating-help');

    var titleEl = document.getElementById('review_title');
    var titleCount = document.getElementById('pr-title-count');
    var titleHelp = document.getElementById('pr-title-help');

    var bodyEl = document.getElementById('review_details');
    var bodyCount = document.getElementById('pr-body-count');
    var bodyHelp = document.getElementById('pr-body-help');

    var submitBtn = document.getElementById('pr-submit');
    var flash = document.getElementById('pr-flash');

    function getRating() {
        for (var i = 0; i < ratingInputs.length; i++) {
            if (ratingInputs[i].checked) return parseInt(ratingInputs[i].value, 10) || 0;
        }
        return 0;
    }

    function updateRatingUI() {
        var r = getRating();
        if (ratingCount) ratingCount.textContent = r > 0 ? r + '/5' : '';
        if (ratingGroup) ratingGroup.classList.toggle('pr-input-error', false);
        if (ratingHelp) ratingHelp.classList.toggle('is-ok', r > 0);
    }

    function updateLenUI(el, countEl, helpEl, minLen) {
        if (!el) return;
        var l = el.value.length;
        var max = el.getAttribute('maxlength') || '';
        if (countEl) countEl.textContent = l + (max ? '/' + max : '');
        if (helpEl) {
            helpEl.classList.toggle('is-ok', l >= minLen);
            helpEl.classList.toggle('is-bad', l > 0 && l < minLen);
        }
        if (l >= minLen) el.classList.remove('pr-input-error');
    }

    for (var i = 0; i < ratingInputs.length; i++) {
        ratingInputs[i].addEventListener('change', updateRatingUI);
    }
    if (titleEl) titleEl.addEventListener('input', function () { updateLenUI(titleEl, titleCount, titleHelp, 3); });
    if (bodyEl) bodyEl.addEventListener('input', function () { updateLenUI(bodyEl, bodyCount, bodyHelp, 10); });

    updateRatingUI();
    updateLenUI(titleEl, titleCount, titleHelp, 3);
    updateLenUI(bodyEl, bodyCount, bodyHelp, 10);

    form.addEventListener('submit', function (e) {
        var rating = getRating();
        var title = titleEl ? titleEl.value.trim() : '';
        var body = bodyEl ? bodyEl.value.trim() : '';

        var firstBad = null;
        if (rating < 1) {
            if (ratingGroup) ratingGroup.classList.add('pr-input-error');
            firstBad = firstBad || ratingGroup;
        }
        if (title.length < 3) {
            if (titleEl) titleEl.classList.add('pr-input-error');
            firstBad = firstBad || titleEl;
        }
        if (body.length < 10) {
            if (bodyEl) bodyEl.classList.add('pr-input-error');
            firstBad = firstBad || bodyEl;
        }
        if (firstBad) {
            e.preventDefault();
            try { firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
            if (firstBad.focus) {
                if (firstBad === ratingGroup && ratingInputs.length) {
                    ratingInputs[ratingInputs.length - 1].focus();
                } else {
                    firstBad.focus();
                }
            }
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }
    });

    if (flash) {
        var bad = form.querySelector('.pr-input-error');
        if (bad && bad.scrollIntoView) {
            try { bad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { }
        }
    }
})();

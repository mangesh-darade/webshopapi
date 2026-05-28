if (document.querySelector("#contact-us-nav")) {
  document
    .querySelector("#contact-us-nav")
    .classList.add("highlight-nav-option");
}

(function () {
  "use strict";

  function setAlertHtml(container, html) {
    if (!container) return;
    container.innerHTML = html || "";
  }

  function escapeHtml(text) {
    var map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text || "").replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  function renderError(messages) {
    var list = Array.isArray(messages) ? messages : ["Please try again."];
    var items = "";
    for (var i = 0; i < list.length; i++) {
      items += "<li>" + escapeHtml(list[i]) + "</li>";
    }
    return (
      '<div class="contact-us-alert contact-us-alert-error" role="alert"><ul>' +
      items +
      "</ul></div>"
    );
  }

  function renderSuccess(message) {
    return (
      '<div class="contact-us-alert contact-us-alert-success" role="alert">' +
      escapeHtml(message || "Thank you. Your message has been submitted successfully.") +
      "</div>"
    );
  }

  function updateCsrf(form, payload) {
    if (!form || !payload || !payload.csrf_name || !payload.csrf_hash) return;
    var selector = 'input[name="' + payload.csrf_name.replace(/"/g, '\\"') + '"]';
    var tokenInput = form.querySelector(selector);
    if (tokenInput) {
      tokenInput.value = payload.csrf_hash;
    }
    if (window.GP_CSRF && typeof window.GP_CSRF === "object") {
      window.GP_CSRF.name = payload.csrf_name;
      window.GP_CSRF.hash = payload.csrf_hash;
    }
  }

  function bindContactAjax(form) {
    if (!form || form.__contactAjaxWired) return;
    form.__contactAjaxWired = true;

    var alertWrap = form.parentNode ? form.parentNode.querySelector("[data-contact-alert-wrap]") : null;
    var submitBtn = form.querySelector("[data-contact-submit-btn]");
    var normalBtnText = submitBtn ? submitBtn.textContent : "Send Message";
    var loadingHtml = '<span class="contact-us-btn-spinner" aria-hidden="true"></span><span>Sending...</span>';

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add("is-loading");
        submitBtn.innerHTML = loadingHtml;
      }

      setAlertHtml(alertWrap, "");
      var formData = new FormData(form);

      fetch(form.action, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Accept": "application/json",
        },
        credentials: "same-origin",
      })
        .then(function (res) {
          return res
            .json()
            .catch(function () {
              return null;
            })
            .then(function (json) {
              return { ok: res.ok, body: json };
            });
        })
        .then(function (result) {
          var body = result && result.body ? result.body : null;
          if (!body) {
            setAlertHtml(alertWrap, renderError(["Unexpected server response."]));
            return;
          }

          updateCsrf(form, body);

          if (body.status === "SUCCESS") {
            setAlertHtml(alertWrap, renderSuccess(body.message));
            form.reset();
            return;
          }

          if (Array.isArray(body.errors) && body.errors.length > 0) {
            setAlertHtml(alertWrap, renderError(body.errors));
            return;
          }

          setAlertHtml(alertWrap, renderError([body.message || "Could not submit your message."]));
        })
        .catch(function () {
          setAlertHtml(alertWrap, renderError(["Could not connect to server. Please try again."]));
        })
        .then(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove("is-loading");
            submitBtn.textContent = normalBtnText;
          }
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var forms = document.querySelectorAll("form[data-contact-us-form='1']");
    for (var i = 0; i < forms.length; i++) {
      bindContactAjax(forms[i]);
    }
  });
})();

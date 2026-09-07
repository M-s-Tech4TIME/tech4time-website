/* ==========================================================================
   Tech4TIME — forms.js
   Client-side validation and submission for the contact and careers forms.

   The validation rules are ported from the NextJS source's
   src/app/contact/validation.ts so the two sites accept exactly the same input.

   Progressive enhancement: every form works without this script. The markup
   carries native `required`, `type` and `maxlength` attributes and posts
   normally to its PHP handler; this module upgrades that to inline messages
   and a background submit. Client-side checks are a convenience only — the PHP
   handler validates everything again server-side.

   Exposes window.Tech4Time.forms for main.js to initialise.
   ========================================================================== */

(function (global) {
  "use strict";

  /* ----------------------------------------------------------------------
     Validators (ported 1:1 from validation.ts)
     Each returns an error string, or null when the value is acceptable.
     ---------------------------------------------------------------------- */

  var validators = {
    name: function (value) {
      var name = value.trim();
      if (!name) return "Name is required";
      if (name.length < 2) return "Name must be at least 2 characters";
      if (name.length > 50) return "Name must be less than 50 characters";
      if (/[<>{}[\]\\]/.test(name)) return "Name contains invalid characters";
      return null;
    },

    email: function (value) {
      var email = value.trim();
      if (!email) return "Email is required";
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return "Please enter a valid email address";
      }
      if (email.length > 100) return "Email is too long";
      return null;
    },

    phone: function (value) {
      if (!value.trim()) return "Phone number is required";

      var cleaned = value.replace(/[\s\-().]/g, "");

      if (cleaned.length < 10) {
        return "Phone number is too short (minimum 10 digits)";
      }
      if (cleaned.length > 16) {
        return "Phone number is too long (maximum 16 digits)";
      }

      /* Bangladeshi formats: 01712345678, +8801712345678, 008801712345678 */
      var isBD = /^(?:\+?88|0088)?01[3-9]\d{8}$/.test(cleaned);
      /* Generic international format with country code. */
      var isIntl = /^\+?[1-9]\d{7,14}$/.test(cleaned);

      if (!isBD && !isIntl) {
        return (
          "Please enter a valid phone number. Examples: 01712345678, " +
          "+8801712345678, or international format: +12125551234"
        );
      }

      var local = cleaned.replace(/^\+?88|^0088/, "");
      if (local.indexOf("01") === 0) {
        var prefixes = ["013", "014", "015", "016", "017", "018", "019"];
        if (prefixes.indexOf(local.substring(0, 3)) === -1) {
          return (
            "Invalid Bangladeshi operator prefix. Valid prefixes: " +
            "013, 014, 015, 016, 017, 018, 019"
          );
        }
      }

      return null;
    },

    message: function (value) {
      var message = value.trim();
      if (!message) return "Message is required";
      if (message.length < 10) return "Message must be at least 10 characters";
      if (message.length > 5000) {
        return "Message must be less than 5000 characters";
      }

      /* No /g flag: these are single-shot tests, and a global regex would
         carry lastIndex between calls and start skipping matches. */
      var suspicious = [
        /(http|https):\/\//i,
        /<script>/i,
        /on\w+=/i,
        /javascript:/i,
        /eval\(/i,
        /document\./i,
        /window\./i,
        /alert\(/i,
      ];

      for (var i = 0; i < suspicious.length; i += 1) {
        if (suspicious[i].test(message)) {
          return (
            "Message contains suspicious content. Please remove any HTML, " +
            "scripts, or URLs."
          );
        }
      }

      return null;
    },

    /* A consent box carries the same value whether or not it is ticked, so
       this one reads the field rather than the string. */
    consent: function (value, field) {
      if (field && !field.checked) {
        return "Please confirm you have read the privacy policy";
      }
      return null;
    },

    subject: function (value) {
      var subject = value.trim();
      if (!subject) return "Subject is required";
      if (subject.length > 120) return "Subject must be less than 120 characters";
      if (/[<>{}[\]\\]/.test(subject)) return "Subject contains invalid characters";
      return null;
    },
  };

  /* ----------------------------------------------------------------------
     Field helpers
     ---------------------------------------------------------------------- */

  function errorNodeFor(field) {
    var id = field.getAttribute("aria-describedby");
    return id ? document.getElementById(id.split(" ")[0]) : null;
  }

  function showError(field, message) {
    field.setAttribute("aria-invalid", "true");
    var node = errorNodeFor(field);
    if (node) {
      node.textContent = message;
    }
  }

  function clearError(field) {
    field.removeAttribute("aria-invalid");
    var node = errorNodeFor(field);
    if (node) {
      node.textContent = "";
    }
  }

  function validateField(field) {
    var rule = validators[field.dataset.validate || field.name];
    if (!rule) return true;

    var error = rule(field.value, field);
    if (error) {
      showError(field, error);
      return false;
    }
    clearError(field);
    return true;
  }

  function setStatus(form, message, isError) {
    var status = form.querySelector("[data-form-status]");
    if (!status) return;
    status.textContent = message;
    status.setAttribute("data-error", isError ? "true" : "false");
  }

  /* ----------------------------------------------------------------------
     Submission
     ---------------------------------------------------------------------- */

  /* WHERE THE FORM POSTS, AND HOW IT IS EMPTIED AFTERWARDS — read off the
     prototype, never off the form.

     HTMLFormElement is declared [LegacyOverrideBuiltIns] in the HTML
     specification, so a control's name wins over the interface's own property
     of that name. A form holding <input name="action"> answers form.action
     with that input rather than a URL, and fetch() then posts to
     /[object%20HTMLInputElement]; one named "method" breaks the verb; one
     named "reset" makes form.reset() a TypeError and the form is never
     cleared. This form already shadows form.name with its own name field, and
     the three properties below are three field names away from the same fate.

     It is not hypothetical: it is what took every button on the admin's
     careers screen down, silently, for ten days — the comment above the same
     fix in the backend's admin-forms.js tells that half of the story.
     tools/check_form_dom.py holds the rule in both repositories, and
     docs/90-decisions/0022-form-properties-are-read-off-the-prototype.md
     records why.

     Each getter carries the whole of the specification's behaviour already —
     the action attribute resolved against the document's base URL, the
     document's own URL when there is no attribute, and "get" or "post"
     normalised — so there is nothing here to reimplement or to keep in step. */
  var FORM = global.HTMLFormElement ? global.HTMLFormElement.prototype : null;

  function own(name) {
    var d = FORM && Object.getOwnPropertyDescriptor
      ? Object.getOwnPropertyDescriptor(FORM, name)
      : null;
    return d && typeof d.get === "function" ? d.get : null;
  }

  var FORM_ACTION = own("action");
  var FORM_METHOD = own("method");

  function postUrl(form) {
    if (FORM_ACTION) {
      return FORM_ACTION.call(form) || global.location.href;
    }

    var attr = form.getAttribute("action");
    return attr && typeof global.URL === "function"
      ? new global.URL(attr, document.baseURI).href
      : global.location.href;
  }

  function postVerb(form) {
    var verb = FORM_METHOD ? FORM_METHOD.call(form) : form.getAttribute("method");
    return verb ? String(verb).toUpperCase() : "POST";
  }

  function empty(form) {
    if (FORM && typeof FORM.reset === "function") {
      FORM.reset.call(form);
    }
  }

  function submit(form) {
    var button = form.querySelector('[type="submit"]');
    var original = button ? button.innerHTML : "";

    if (button) {
      button.disabled = true;
      button.textContent = "Sending…";
    }
    setStatus(form, "", false);

    fetch(postUrl(form), {
      method: postVerb(form),
      body: new FormData(form),
      headers: { Accept: "application/json" },
    })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            /* A handler that errored may return HTML rather than JSON. */
            return { ok: response.ok };
          })
          .then(function (data) {
            if (!response.ok || data.ok === false) {
              throw new Error(data.error || "Something went wrong.");
            }
            return data;
          });
      })
      .then(function (data) {
        empty(form);
        setStatus(
          form,
          data.message || "Thank you — your message has been sent.",
          false
        );
      })
      .catch(function (error) {
        setStatus(
          form,
          error.message ||
            "We could not send your message. Please try again, or email " +
              "info@tech4time.bd directly.",
          true
        );
      })
      .finally(function () {
        if (button) {
          button.disabled = false;
          button.innerHTML = original;
        }
      });
  }

  /* ----------------------------------------------------------------------
     Wiring
     ---------------------------------------------------------------------- */

  function enhance(form) {
    /* Take over native validation bubbles so messages render inline and are
       announced by the live region instead. */
    form.setAttribute("novalidate", "");

    var fields = Array.prototype.slice.call(
      form.querySelectorAll("[data-validate], input[name], textarea[name]")
    );
    var submitted = false;

    fields.forEach(function (field) {
      /* Re-check on blur, but only after a first submit attempt — validating
         a field the visitor has not finished filling in is just nagging. */
      field.addEventListener("blur", function () {
        if (submitted) {
          validateField(field);
        }
      });

      /* Clear a standing error as soon as the visitor starts fixing it. */
      field.addEventListener("input", function () {
        if (field.getAttribute("aria-invalid") === "true") {
          validateField(field);
        }
      });
    });

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      submitted = true;

      var invalid = fields.filter(function (field) {
        return !validateField(field);
      });

      if (invalid.length) {
        setStatus(
          form,
          "Please correct the " +
            invalid.length +
            (invalid.length === 1 ? " field" : " fields") +
            " highlighted below.",
          true
        );
        invalid[0].focus();
        return;
      }

      submit(form);
    });
  }

  function init() {
    var forms = document.querySelectorAll("[data-enhanced-form]");
    Array.prototype.forEach.call(forms, enhance);
  }

  global.Tech4Time = global.Tech4Time || {};
  global.Tech4Time.forms = { init: init, validators: validators };
})(window);

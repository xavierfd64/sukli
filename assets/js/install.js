(function () {
  "use strict";

  function postForm(url, params) {
    var body = new URLSearchParams(params);
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    }).then(function (res) {
      return res.json().then(function (data) {
        return { status: res.status, data: data };
      });
    });
  }

  // ---- Step 2: Test Connection ------------------------------------------

  var testBtn = document.getElementById("install-test-btn");
  if (testBtn) {
    testBtn.addEventListener("click", function () {
      var form = document.getElementById("install-db-form");
      var resultBox = document.getElementById("install-test-result");
      var fd = new FormData(form);
      var params = { _csrf: window.SUKLI_CSRF };
      fd.forEach(function (value, key) { if (key !== "_csrf") params[key] = value; });

      var testConnectionUrl = form.action.replace(/\/install\/database$/, "/install/api/test-connection");

      testBtn.disabled = true;
      var originalLabel = testBtn.textContent;
      testBtn.textContent = "Testing...";

      postForm(testConnectionUrl, params).then(function (result) {
        resultBox.style.display = "block";
        resultBox.className = "alert " + (result.data.ok ? "alert-success" : "alert-error");
        resultBox.textContent = result.data.message;
      }).catch(function () {
        resultBox.style.display = "block";
        resultBox.className = "alert alert-error";
        resultBox.textContent = "Could not reach the server. Please try again.";
      }).finally(function () {
        testBtn.disabled = false;
        testBtn.textContent = originalLabel;
      });
    });
  }

  // ---- Step 5: live installation checklist -------------------------------

  var runBtn = document.getElementById("install-run-btn");
  if (runBtn) {
    var reviewEl = document.getElementById("install-review");
    var progressEl = document.getElementById("install-progress");
    var completeEl = document.getElementById("install-complete");
    var fillEl = document.getElementById("install-progress-fill");
    var errorEl = document.getElementById("install-progress-error");
    var checklist = document.getElementById("install-checklist");

    var steps = [
      { key: "requirements", url: "/install/api/check-requirements" },
      { key: "connect", url: "/install/api/connect" },
      { key: "tables", url: "/install/api/create-tables" },
      { key: "admin", url: "/install/api/create-admin" },
      { key: "store", url: "/install/api/store-settings" },
      { key: "finalize", url: "/install/api/finalize" },
    ];

    function setItemState(key, state) {
      var li = checklist.querySelector('[data-key="' + key + '"]');
      if (!li) return;
      li.classList.remove("is-active", "is-done", "is-error");
      li.classList.add(state);
      var statusEl = li.querySelector(".ic-status");
      if (state === "is-active") statusEl.textContent = "⏳";
      if (state === "is-done") statusEl.textContent = "✓";
      if (state === "is-error") statusEl.textContent = "✕";
    }

    function updateProgress(doneCount) {
      var pct = Math.round((doneCount / steps.length) * 100);
      fillEl.style.width = pct + "%";
    }

    function runStep(index) {
      if (index >= steps.length) {
        updateProgress(steps.length);
        progressEl.style.display = "none";
        completeEl.style.display = "block";
        return;
      }

      var step = steps[index];
      setItemState(step.key, "is-active");

      postForm(step.url, { _csrf: window.SUKLI_CSRF })
        .then(function (result) {
          if (!result.data.ok) {
            setItemState(step.key, "is-error");
            errorEl.style.display = "block";
            errorEl.textContent = result.data.message || "Installation failed at this step.";
            return;
          }
          setItemState(step.key, "is-done");
          updateProgress(index + 1);
          runStep(index + 1);
        })
        .catch(function () {
          setItemState(step.key, "is-error");
          errorEl.style.display = "block";
          errorEl.textContent = "Could not reach the server. Please check your connection and try again.";
        });
    }

    runBtn.addEventListener("click", function () {
      reviewEl.style.display = "none";
      progressEl.style.display = "block";
      errorEl.style.display = "none";
      updateProgress(0);
      runStep(0);
    });
  }
})();

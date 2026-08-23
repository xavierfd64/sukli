(function () {
  "use strict";

  var sidebar = document.querySelector(".sidebar");
  var overlay = document.querySelector(".sidebar-overlay");
  var hamburgers = document.querySelectorAll("[data-toggle-sidebar]");

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove("is-open");
    if (overlay) overlay.style.display = "none";
  }

  function openSidebar() {
    if (sidebar) sidebar.classList.add("is-open");
    if (overlay) overlay.style.display = "block";
  }

  hamburgers.forEach(function (btn) {
    btn.addEventListener("click", function () {
      if (sidebar && sidebar.classList.contains("is-open")) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  });

  if (overlay) {
    overlay.addEventListener("click", closeSidebar);
  }

  // "More" button on the bottom nav reuses the same sidebar drawer.
  document.querySelectorAll("[data-open-more]").forEach(function (btn) {
    btn.addEventListener("click", openSidebar);
  });

  // Generic modal open/close: [data-modal-target="#id"] / [data-modal-close]
  document.querySelectorAll("[data-modal-target]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var target = document.querySelector(btn.getAttribute("data-modal-target"));
      if (target) target.classList.add("is-open");
    });
  });
  document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      btn.closest(".modal-backdrop").classList.remove("is-open");
    });
  });
  document.querySelectorAll(".modal-backdrop").forEach(function (backdrop) {
    backdrop.addEventListener("click", function (e) {
      if (e.target === backdrop) backdrop.classList.remove("is-open");
    });
  });

  // Confirm before destructive form submits: <form data-confirm="Are you sure?">
  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      if (!window.confirm(form.getAttribute("data-confirm"))) {
        e.preventDefault();
      }
    });
  });

  // Auto-dismiss flash alerts after a few seconds.
  document.querySelectorAll("[data-flash]").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity .3s ease";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 300);
    }, 4000);
  });
})();

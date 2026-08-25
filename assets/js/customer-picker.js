/**
 * Lightweight client-side "search by name or contact number" customer
 * combobox, shared by any form that needs to attach an optional customer
 * to a record (E-Load, GCash) without a full page/AJAX round trip — the
 * customer list is embedded once per page, same as POS already does.
 */
function SukliCustomerPicker(opts) {
  "use strict";

  var input = opts.input;
  var resultsEl = opts.results;
  var selectedEl = opts.selected;
  var customers = opts.customers || [];
  var onSelect = opts.onSelect || function () {};
  var onClear = opts.onClear || function () {};
  var current = null;

  function render(term) {
    resultsEl.innerHTML = "";
    if (!term) return;
    var t = term.toLowerCase();
    var matches = customers.filter(function (c) {
      return c.name.toLowerCase().indexOf(t) !== -1 || (c.contact_number && c.contact_number.indexOf(t) !== -1);
    }).slice(0, 8);

    matches.forEach(function (c) {
      var row = document.createElement("div");
      row.className = "pos-customer-result";
      row.innerHTML = "<div>" + c.name + "</div>" + (c.contact_number ? '<div class="pcr-contact">' + c.contact_number + "</div>" : "");
      row.addEventListener("click", function () {
        current = c;
        input.value = "";
        resultsEl.innerHTML = "";
        selectedEl.innerHTML =
          '<span class="pos-customer-selected-chip">' + c.name + ' <button type="button" class="ppc-clear">&times;</button></span>';
        selectedEl.querySelector(".ppc-clear").addEventListener("click", function () {
          current = null;
          selectedEl.innerHTML = "";
          onClear();
        });
        onSelect(c);
      });
      resultsEl.appendChild(row);
    });
  }

  input.addEventListener("input", function () {
    current = null;
    selectedEl.innerHTML = "";
    onClear();
    render(input.value.trim());
  });

  return {
    getSelected: function () { return current; },
  };
}

(function () {
  "use strict";

  var paymentMethods = window.SUKLI_PAYMENT_METHODS || [];
  var customers = window.SUKLI_CUSTOMERS || [];
  var products = window.SUKLI_ELOAD_PRODUCTS || [];

  function money(n) { return SukliPayment.money(n, window.SUKLI_CURRENCY); }

  // ------------------------------------------------------------------
  // Step 1: Customer (defaults to Walk-In when nothing is selected)
  // ------------------------------------------------------------------
  var customerIdHidden = document.getElementById("eload-customer-hidden");
  var customerNameHidden = document.getElementById("eload-customer-name-hidden");
  var contactHidden = document.getElementById("eload-contact-hidden");
  var selectedCustomerId = null;

  SukliCustomerPicker({
    input: document.getElementById("eload-customer-search"),
    results: document.getElementById("eload-customer-results"),
    selected: document.getElementById("eload-customer-selected"),
    customers: customers,
    onSelect: function (c) {
      selectedCustomerId = c.id;
      customerIdHidden.value = c.id;
      customerNameHidden.value = c.name;
      contactHidden.value = c.contact_number || "";
      updateUtangAvailability();
    },
    onClear: function () {
      selectedCustomerId = null;
      customerIdHidden.value = "";
      customerNameHidden.value = "Walk-In";
      contactHidden.value = "";
      updateUtangAvailability();
    },
  });
  customerNameHidden.value = "Walk-In";

  // ------------------------------------------------------------------
  // Step 2 + 3: Network -> Product
  // ------------------------------------------------------------------
  var networkSelect = document.getElementById("eload-network-select");
  var productStep = document.getElementById("eload-product-step");
  var productGrid = document.getElementById("eload-product-grid");
  var noProductsMsg = document.getElementById("eload-no-products");
  var selectedSummary = document.getElementById("eload-selected-summary");
  var selectedNameEl = document.getElementById("eload-selected-name");
  var selectedPriceEl = document.getElementById("eload-selected-price");
  var openPaymentBtn = document.getElementById("eload-open-payment");
  var productIdHidden = document.getElementById("eload-product-id-hidden");

  var selectedProduct = null;

  function renderProductGrid(network) {
    productGrid.innerHTML = "";
    var matches = products.filter(function (p) { return p.network === network; });
    matches.forEach(function (p) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "pos-product";
      btn.innerHTML =
        '<div class="pos-product-name">' + p.name + "</div>" +
        '<div class="text-muted" style="font-size:11px;margin-bottom:4px;">Load Value: ' + money(p.load_value) + "</div>" +
        '<div class="pos-product-price">' + money(p.selling_price) + "</div>";
      btn.addEventListener("click", function () {
        selectedProduct = p;
        productGrid.querySelectorAll(".pos-product").forEach(function (b) { b.classList.remove("is-active-product"); });
        btn.classList.add("is-active-product");
        selectedNameEl.textContent = p.network + " " + p.name;
        selectedPriceEl.textContent = money(p.selling_price);
        selectedSummary.style.display = "block";
        productIdHidden.value = p.id;
        openPaymentBtn.disabled = false;
      });
      productGrid.appendChild(btn);
    });
    noProductsMsg.style.display = matches.length === 0 ? "block" : "none";
  }

  if (networkSelect) {
    networkSelect.addEventListener("change", function () {
      selectedProduct = null;
      selectedSummary.style.display = "none";
      openPaymentBtn.disabled = true;
      productIdHidden.value = "";
      if (!networkSelect.value) {
        productStep.style.display = "none";
        return;
      }
      productStep.style.display = "block";
      renderProductGrid(networkSelect.value);
    });
  }

  // ------------------------------------------------------------------
  // Payment modal — same shape as POS's, total is the selected product's price
  // ------------------------------------------------------------------
  var modal = document.getElementById("eload-payment-modal");
  if (!modal) return; // no payment methods enabled

  var totalEl = document.getElementById("epm-total");
  var splitToggle = document.getElementById("epm-split-toggle");
  var single = document.getElementById("epm-single");
  var split = document.getElementById("epm-split");
  var methodTabs = document.getElementById("epm-method-tabs");
  var cashFields = document.getElementById("epm-cash-fields");
  var tendered = document.getElementById("epm-tendered");
  var changeEl = document.getElementById("epm-change");
  var utangHint = document.getElementById("epm-utang-hint");
  var splitRows = document.getElementById("epm-split-rows");
  var addRowBtn = document.getElementById("epm-add-row");
  var splitAllocatedEl = document.getElementById("epm-split-allocated");
  var splitRemainingEl = document.getElementById("epm-split-remaining");
  var confirmBtn = document.getElementById("epm-confirm");

  var selectedMethod = null;

  function currentTotal() { return selectedProduct ? selectedProduct.selling_price : 0; }
  function isSplitMode() { return splitToggle && splitToggle.checked; }
  function splitRowEls() { return Array.prototype.slice.call(splitRows.querySelectorAll(".pos-split-row")); }
  function splitUsesUtang() {
    return splitRowEls().some(function (row) { return row.querySelector("select").value === "utang"; });
  }
  function rowsData() {
    return splitRowEls().map(function (row) {
      return { method: row.querySelector("select").value, amount: row.querySelector("input").value };
    });
  }

  function updateUtangAvailability() {
    // Utang needs a real customer (Walk-In doesn't qualify) — matches the server-side rule exactly.
    updateConfirmState();
  }

  function addSplitRow() {
    var row = document.createElement("div");
    row.className = "pos-split-row";
    var options = paymentMethods.map(function (m) { return '<option value="' + m.key + '">' + m.name + "</option>"; }).join("");
    row.innerHTML =
      '<select class="form-control">' + options + "</select>" +
      '<input type="number" step="0.01" min="0" class="form-control" placeholder="Amount">' +
      '<button type="button" class="psr-remove">&times;</button>';
    splitRows.appendChild(row);
    row.querySelector("select").addEventListener("change", recomputeSplit);
    row.querySelector("input").addEventListener("input", recomputeSplit);
    row.querySelector(".psr-remove").addEventListener("click", function () { row.remove(); recomputeSplit(); });
    recomputeSplit();
  }
  if (addRowBtn) addRowBtn.addEventListener("click", addSplitRow);

  function recomputeSplit() {
    var state = SukliPayment.splitState(rowsData(), currentTotal());
    splitAllocatedEl.textContent = money(state.allocated);
    splitRemainingEl.textContent = money(state.remaining);
    updateConfirmState();
  }

  if (methodTabs) {
    methodTabs.addEventListener("click", function (e) {
      var a = e.target.closest("a[data-method]");
      if (!a) return;
      e.preventDefault();
      selectedMethod = a.getAttribute("data-method");
      methodTabs.querySelectorAll("a").forEach(function (x) { x.classList.remove("is-active"); });
      a.classList.add("is-active");
      cashFields.style.display = selectedMethod === "cash" ? "block" : "none";
      utangHint.style.display = selectedMethod === "utang" && !selectedCustomerId ? "block" : "none";
      updateChange();
      updateConfirmState();
    });
  }

  function updateChange() {
    if (!tendered) return;
    changeEl.textContent = money(SukliPayment.cashChange(parseFloat(tendered.value) || 0, currentTotal()));
  }
  if (tendered) tendered.addEventListener("input", function () { updateChange(); updateConfirmState(); });

  if (splitToggle) {
    splitToggle.addEventListener("change", function () {
      if (isSplitMode()) {
        single.style.display = "none";
        split.style.display = "block";
        if (splitRowEls().length === 0) { addSplitRow(); addSplitRow(); }
        recomputeSplit();
      } else {
        split.style.display = "none";
        single.style.display = "block";
      }
      updateConfirmState();
    });
  }

  function updateConfirmState() {
    var total = currentTotal();
    var ok = false;

    if (isSplitMode()) {
      var state = SukliPayment.splitState(rowsData(), total);
      ok = state.ok;
      if (ok && splitUsesUtang() && !selectedCustomerId) ok = false;
    } else if (selectedMethod) {
      if (selectedMethod === "cash") {
        ok = (parseFloat(tendered.value) || 0) >= total;
      } else {
        ok = true;
      }
      if (ok && selectedMethod === "utang" && !selectedCustomerId) ok = false;
    }

    confirmBtn.disabled = !ok || !selectedProduct;
  }

  if (openPaymentBtn) {
    openPaymentBtn.addEventListener("click", function () {
      totalEl.textContent = money(currentTotal());
      selectedMethod = null;
      if (methodTabs) methodTabs.querySelectorAll("a").forEach(function (x) { x.classList.remove("is-active"); });
      cashFields.style.display = "none";
      utangHint.style.display = "none";
      if (tendered) tendered.value = "";
      changeEl.textContent = money(0);
      splitRows.innerHTML = "";
      if (splitToggle) splitToggle.checked = false;
      split.style.display = "none";
      single.style.display = "block";
      updateConfirmState();
    });
  }

  if (confirmBtn) {
    confirmBtn.addEventListener("click", function () {
      var payments = [];
      if (isSplitMode()) {
        splitRowEls().forEach(function (row) {
          payments.push({ method: row.querySelector("select").value, amount: parseFloat(row.querySelector("input").value) || 0 });
        });
      } else {
        var amount = selectedMethod === "cash" ? (parseFloat(tendered.value) || 0) : currentTotal();
        payments.push({ method: selectedMethod, amount: amount });
      }
      document.getElementById("eload-payments-json").value = JSON.stringify(payments);
      document.getElementById("eload-form").submit();
    });
  }
})();

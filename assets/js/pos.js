(function () {
  "use strict";

  var cart = {}; // productId -> {id, name, price, qty, stock}
  var paymentMethods = window.SUKLI_PAYMENT_METHODS || [];
  var customers = window.SUKLI_CUSTOMERS || [];

  var grid = document.getElementById("pos-grid");
  var searchInput = document.getElementById("pos-search");
  var barcodeInput = document.getElementById("pos-barcode");
  var tabs = document.getElementById("pos-category-tabs");
  var cartItemsEl = document.getElementById("pos-cart-items");
  var cartEmptyEl = document.getElementById("pos-cart-empty");
  var subtotalEl = document.getElementById("pos-subtotal");
  var totalEl = document.getElementById("pos-total");
  var discountInput = document.getElementById("pos-discount");
  var openPaymentBtn = document.getElementById("pos-open-payment");

  function money(n) {
    return window.SUKLI_CURRENCY + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function addToCart(el) {
    var id = el.getAttribute("data-id");
    var stock = parseInt(el.getAttribute("data-stock"), 10);
    var existing = cart[id];
    var currentQty = existing ? existing.qty : 0;

    if (currentQty + 1 > stock) {
      alert("Only " + stock + " in stock.");
      return;
    }

    if (existing) {
      existing.qty += 1;
    } else {
      cart[id] = {
        id: id,
        name: el.getAttribute("data-pname"),
        price: parseFloat(el.getAttribute("data-price")),
        qty: 1,
        stock: stock,
      };
    }
    renderCart();
  }

  if (grid) {
    grid.addEventListener("click", function (e) {
      var btn = e.target.closest(".pos-product");
      if (btn) addToCart(btn);
    });
  }

  function filterGrid() {
    var term = (searchInput.value || "").toLowerCase().trim();
    var activeTab = tabs.querySelector("a.is-active");
    var category = activeTab ? activeTab.getAttribute("data-category") : "all";

    grid.querySelectorAll(".pos-product").forEach(function (el) {
      var matchesTerm = term === "" || el.getAttribute("data-name").indexOf(term) !== -1;
      var matchesCategory = category === "all" || el.getAttribute("data-category") === category;
      el.classList.toggle("is-hidden", !(matchesTerm && matchesCategory));
    });
  }

  if (searchInput) searchInput.addEventListener("input", filterGrid);

  if (tabs) {
    tabs.addEventListener("click", function (e) {
      var a = e.target.closest("a");
      if (!a) return;
      e.preventDefault();
      tabs.querySelectorAll("a").forEach(function (x) { x.classList.remove("is-active"); });
      a.classList.add("is-active");
      filterGrid();
    });
  }

  // --- Barcode: auto-detect on scan (no Enter required), with Enter as a manual fallback. ---
  if (barcodeInput && grid) {
    var barcodeMap = {};
    grid.querySelectorAll(".pos-product").forEach(function (el) {
      var bc = el.getAttribute("data-barcode");
      if (bc) barcodeMap[bc] = el;
    });

    var barcodeDebounce = null;

    function tryAutoScan() {
      var code = barcodeInput.value.trim();
      if (!code) return;
      var found = barcodeMap[code];
      if (found) {
        addToCart(found);
        barcodeInput.value = "";
      }
    }

    barcodeInput.addEventListener("input", function () {
      if (barcodeDebounce) clearTimeout(barcodeDebounce);
      barcodeDebounce = setTimeout(tryAutoScan, 120);
    });

    barcodeInput.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault();
      if (barcodeDebounce) clearTimeout(barcodeDebounce);
      var code = barcodeInput.value.trim();
      if (!code) return;
      if (barcodeMap[code]) {
        addToCart(barcodeMap[code]);
        barcodeInput.value = "";
      } else {
        alert("No product found for barcode: " + code);
        barcodeInput.value = "";
      }
      barcodeInput.focus();
    });
  }

  function changeQty(id, delta) {
    var item = cart[id];
    if (!item) return;
    item.qty += delta;
    if (item.qty > item.stock) item.qty = item.stock;
    if (item.qty <= 0) delete cart[id];
    renderCart();
  }

  function removeItem(id) {
    delete cart[id];
    renderCart();
  }

  if (cartItemsEl) {
    cartItemsEl.addEventListener("click", function (e) {
      var id = e.target.getAttribute("data-cart-id");
      if (!id) return;
      if (e.target.classList.contains("pci-inc")) changeQty(id, 1);
      if (e.target.classList.contains("pci-dec")) changeQty(id, -1);
      if (e.target.classList.contains("pci-remove")) removeItem(id);
    });
  }

  function totals() {
    var subtotal = 0;
    Object.keys(cart).forEach(function (id) { subtotal += cart[id].price * cart[id].qty; });
    var discountPct = Math.max(0, Math.min(100, parseFloat(discountInput.value) || 0));
    var total = subtotal - subtotal * (discountPct / 100);
    return { subtotal: subtotal, discountPct: discountPct, total: Math.round(total * 100) / 100 };
  }

  function renderCart() {
    var ids = Object.keys(cart);
    cartItemsEl.innerHTML = "";
    if (ids.length === 0) {
      cartItemsEl.appendChild(cartEmptyEl);
    } else {
      ids.forEach(function (id) {
        var item = cart[id];
        var row = document.createElement("div");
        row.className = "pos-cart-item";
        row.innerHTML =
          '<div class="pci-name">' + item.name + '</div>' +
          '<div class="pos-qty-controls">' +
            '<button type="button" class="pci-dec" data-cart-id="' + id + '">-</button>' +
            '<span>' + item.qty + '</span>' +
            '<button type="button" class="pci-inc" data-cart-id="' + id + '">+</button>' +
          '</div>' +
          '<div class="pci-total">' + money(item.price * item.qty) + '</div>' +
          '<button type="button" class="pci-remove" data-cart-id="' + id + '">&times;</button>';
        cartItemsEl.appendChild(row);
      });
    }

    var t = totals();
    subtotalEl.textContent = money(t.subtotal);
    totalEl.textContent = money(t.total);
    openPaymentBtn.disabled = ids.length === 0;
  }

  if (discountInput) discountInput.addEventListener("input", renderCart);

  var clearBtn = document.getElementById("pos-clear-cart");
  if (clearBtn) {
    clearBtn.addEventListener("click", function (e) {
      e.preventDefault();
      cart = {};
      renderCart();
    });
  }

  renderCart();

  // --------------------------------------------------------------------
  // Payment modal
  // --------------------------------------------------------------------
  var pmModal = document.getElementById("payment-modal");
  var pmTotalEl = document.getElementById("pm-total");
  var pmSplitToggle = document.getElementById("pm-split-toggle");
  var pmSingle = document.getElementById("pm-single");
  var pmSplit = document.getElementById("pm-split");
  var pmMethodTabs = document.getElementById("pm-method-tabs");
  var pmCashFields = document.getElementById("pm-cash-fields");
  var pmTendered = document.getElementById("pm-tendered");
  var pmChange = document.getElementById("pm-change");
  var pmSplitRows = document.getElementById("pm-split-rows");
  var pmAddRow = document.getElementById("pm-add-row");
  var pmSplitAllocated = document.getElementById("pm-split-allocated");
  var pmSplitRemaining = document.getElementById("pm-split-remaining");
  var pmCustomerFields = document.getElementById("pm-customer-fields");
  var pmCustomerSearch = document.getElementById("pm-customer-search");
  var pmCustomerResults = document.getElementById("pm-customer-results");
  var pmCustomerSelected = document.getElementById("pm-customer-selected");
  var pmConfirm = document.getElementById("pm-confirm");

  var pmSelectedMethod = null;
  var pmSelectedCustomerId = null;
  var pmSplitRowCount = 0;

  if (!pmModal) return; // no payment methods enabled / view not present

  function methodName(key) {
    var m = paymentMethods.filter(function (x) { return x.key === key; })[0];
    return m ? m.name : key;
  }

  function currentTotal() {
    return totals().total;
  }

  function isSplitMode() {
    return pmSplitToggle && pmSplitToggle.checked;
  }

  function splitRowEls() {
    return Array.prototype.slice.call(pmSplitRows.querySelectorAll(".pos-split-row"));
  }

  function splitUsesUtang() {
    return splitRowEls().some(function (row) {
      return row.querySelector("select").value === "utang";
    });
  }

  function addSplitRow() {
    pmSplitRowCount += 1;
    var row = document.createElement("div");
    row.className = "pos-split-row";
    var options = paymentMethods.map(function (m) {
      return '<option value="' + m.key + '">' + m.name + '</option>';
    }).join("");
    row.innerHTML =
      '<select class="form-control">' + options + '</select>' +
      '<input type="number" step="0.01" min="0" class="form-control" placeholder="Amount">' +
      '<button type="button" class="psr-remove">&times;</button>';
    pmSplitRows.appendChild(row);

    row.querySelector("select").addEventListener("change", recomputeSplit);
    row.querySelector("input").addEventListener("input", recomputeSplit);
    row.querySelector(".psr-remove").addEventListener("click", function () {
      row.remove();
      recomputeSplit();
    });

    recomputeSplit();
  }

  if (pmAddRow) pmAddRow.addEventListener("click", addSplitRow);

  function recomputeSplit() {
    var total = currentTotal();
    var allocated = 0;
    splitRowEls().forEach(function (row) {
      allocated += parseFloat(row.querySelector("input").value) || 0;
    });
    allocated = Math.round(allocated * 100) / 100;
    pmSplitAllocated.textContent = money(allocated);
    pmSplitRemaining.textContent = money(Math.max(0, total - allocated));

    pmCustomerFields.style.display = splitUsesUtang() ? "block" : "none";
    updateConfirmState();
  }

  function resetCustomerPicker() {
    pmSelectedCustomerId = null;
    pmCustomerSearch.value = "";
    pmCustomerResults.innerHTML = "";
    pmCustomerSelected.innerHTML = "";
  }

  function renderCustomerResults(term) {
    pmCustomerResults.innerHTML = "";
    if (!term) return;
    var t = term.toLowerCase();
    var matches = customers.filter(function (c) {
      return c.name.toLowerCase().indexOf(t) !== -1 || (c.contact_number && c.contact_number.indexOf(t) !== -1);
    }).slice(0, 8);

    matches.forEach(function (c) {
      var row = document.createElement("div");
      row.className = "pos-customer-result";
      row.innerHTML = '<div>' + c.name + '</div>' + (c.contact_number ? '<div class="pcr-contact">' + c.contact_number + '</div>' : "");
      row.addEventListener("click", function () {
        pmSelectedCustomerId = c.id;
        pmCustomerSearch.value = "";
        pmCustomerResults.innerHTML = "";
        pmCustomerSelected.innerHTML =
          '<span class="pos-customer-selected-chip">Selected: ' + c.name + ' <button type="button" id="pm-customer-clear">&times;</button></span>';
        document.getElementById("pm-customer-clear").addEventListener("click", function () {
          resetCustomerPicker();
          updateConfirmState();
        });
        updateConfirmState();
      });
      pmCustomerResults.appendChild(row);
    });
  }

  if (pmCustomerSearch) {
    pmCustomerSearch.addEventListener("input", function () {
      pmSelectedCustomerId = null;
      pmCustomerSelected.innerHTML = "";
      renderCustomerResults(pmCustomerSearch.value.trim());
      updateConfirmState();
    });
  }

  if (pmMethodTabs) {
    pmMethodTabs.addEventListener("click", function (e) {
      var a = e.target.closest("a[data-method]");
      if (!a) return;
      e.preventDefault();
      pmSelectedMethod = a.getAttribute("data-method");
      pmMethodTabs.querySelectorAll("a").forEach(function (x) { x.classList.remove("is-active"); });
      a.classList.add("is-active");
      pmCashFields.style.display = pmSelectedMethod === "cash" ? "block" : "none";
      pmCustomerFields.style.display = pmSelectedMethod === "utang" ? "block" : "none";
      updateChange();
      updateConfirmState();
    });
  }

  function updateChange() {
    if (!pmTendered) return;
    var tendered = parseFloat(pmTendered.value) || 0;
    pmChange.textContent = money(Math.max(0, tendered - currentTotal()));
  }
  if (pmTendered) pmTendered.addEventListener("input", function () { updateChange(); updateConfirmState(); });

  if (pmSplitToggle) {
    pmSplitToggle.addEventListener("change", function () {
      if (isSplitMode()) {
        pmSingle.style.display = "none";
        pmSplit.style.display = "block";
        if (splitRowEls().length === 0) {
          addSplitRow();
          addSplitRow();
        }
        recomputeSplit();
      } else {
        pmSplit.style.display = "none";
        pmSingle.style.display = "block";
        pmCustomerFields.style.display = pmSelectedMethod === "utang" ? "block" : "none";
      }
      updateConfirmState();
    });
  }

  function updateConfirmState() {
    var total = currentTotal();
    var ok = false;

    if (isSplitMode()) {
      var rows = splitRowEls();
      var allocated = 0;
      var valid = rows.length > 0;
      rows.forEach(function (row) {
        var amt = parseFloat(row.querySelector("input").value) || 0;
        if (amt <= 0) valid = false;
        allocated += amt;
      });
      allocated = Math.round(allocated * 100) / 100;
      ok = valid && Math.abs(allocated - total) < 0.01;
      if (ok && splitUsesUtang() && !pmSelectedCustomerId) ok = false;
    } else {
      if (pmSelectedMethod) {
        if (pmSelectedMethod === "cash") {
          var tendered = parseFloat(pmTendered.value) || 0;
          ok = tendered >= total;
        } else {
          ok = true;
        }
        if (ok && pmSelectedMethod === "utang" && !pmSelectedCustomerId) ok = false;
      }
    }

    pmConfirm.disabled = !ok;
  }

  if (openPaymentBtn) {
    openPaymentBtn.addEventListener("click", function () {
      pmTotalEl.textContent = money(currentTotal());
      pmSelectedMethod = null;
      if (pmMethodTabs) pmMethodTabs.querySelectorAll("a").forEach(function (x) { x.classList.remove("is-active"); });
      pmCashFields.style.display = "none";
      if (pmTendered) pmTendered.value = "";
      pmChange.textContent = money(0);
      resetCustomerPicker();
      pmCustomerFields.style.display = "none";
      pmSplitRows.innerHTML = "";
      pmSplitRowCount = 0;
      if (pmSplitToggle) pmSplitToggle.checked = false;
      pmSplit.style.display = "none";
      pmSingle.style.display = "block";
      updateConfirmState();
    });
  }

  if (pmConfirm) {
    pmConfirm.addEventListener("click", function () {
      var payments = [];
      var customerId = "";

      if (isSplitMode()) {
        splitRowEls().forEach(function (row) {
          var method = row.querySelector("select").value;
          var amount = parseFloat(row.querySelector("input").value) || 0;
          payments.push({ method: method, amount: amount });
        });
        if (splitUsesUtang()) customerId = pmSelectedCustomerId || "";
      } else {
        var amount = pmSelectedMethod === "cash" ? (parseFloat(pmTendered.value) || 0) : currentTotal();
        payments.push({ method: pmSelectedMethod, amount: amount });
        if (pmSelectedMethod === "utang") customerId = pmSelectedCustomerId || "";
      }

      var t = totals();
      document.getElementById("pos-cart-json").value = JSON.stringify(
        Object.keys(cart).map(function (id) { return { id: id, qty: cart[id].qty }; })
      );
      document.getElementById("pos-payments-json").value = JSON.stringify(payments);
      document.getElementById("pos-discount-hidden").value = t.discountPct;
      document.getElementById("pos-customer-hidden").value = customerId;

      document.getElementById("pos-form").submit();
    });
  }
})();

(function () {
  "use strict";

  var cart = {}; // productId -> {id, name, price, qty, stock}
  var selectedMethod = null;

  var grid = document.getElementById("pos-grid");
  var searchInput = document.getElementById("pos-search");
  var barcodeInput = document.getElementById("pos-barcode");
  var tabs = document.getElementById("pos-category-tabs");
  var cartItemsEl = document.getElementById("pos-cart-items");
  var cartEmptyEl = document.getElementById("pos-cart-empty");
  var subtotalEl = document.getElementById("pos-subtotal");
  var totalEl = document.getElementById("pos-total");
  var discountInput = document.getElementById("pos-discount");
  var submitBtn = document.getElementById("pos-submit");
  var cashGroup = document.getElementById("pos-cash-group");
  var tenderedInput = document.getElementById("pos-tendered");
  var changeEl = document.getElementById("pos-change");
  var customerGroup = document.getElementById("pos-customer-group");
  var customerSelect = document.getElementById("pos-customer");

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

  if (barcodeInput) {
    barcodeInput.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault();
      var code = barcodeInput.value.trim();
      barcodeInput.value = "";
      if (!code) return;

      var found = grid.querySelector('.pos-product[data-barcode="' + CSS.escape(code) + '"]');
      if (found) {
        addToCart(found);
      } else {
        alert("No product found for barcode: " + code);
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
    return { subtotal: subtotal, discountPct: discountPct, total: total };
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
    updateChange();
    updateSubmitState();
  }

  if (discountInput) discountInput.addEventListener("input", renderCart);

  document.querySelectorAll(".pay-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      selectedMethod = btn.getAttribute("data-method");
      document.querySelectorAll(".pay-btn").forEach(function (b) { b.classList.remove("is-selected"); });
      btn.classList.add("is-selected");
      cashGroup.style.display = selectedMethod === "cash" ? "block" : "none";
      if (customerGroup) customerGroup.style.display = selectedMethod === "utang" ? "block" : "none";
      updateChange();
      updateSubmitState();
    });
  });

  function updateChange() {
    if (!tenderedInput) return;
    var t = totals();
    var tendered = parseFloat(tenderedInput.value) || 0;
    changeEl.textContent = money(Math.max(0, tendered - t.total));
  }
  if (tenderedInput) tenderedInput.addEventListener("input", updateChange);

  function updateSubmitState() {
    var hasItems = Object.keys(cart).length > 0;
    var ok = hasItems && !!selectedMethod;
    if (selectedMethod === "utang" && customerSelect && !customerSelect.value) ok = false;
    submitBtn.disabled = !ok;
  }
  if (customerSelect) customerSelect.addEventListener("change", updateSubmitState);

  var clearBtn = document.getElementById("pos-clear-cart");
  if (clearBtn) {
    clearBtn.addEventListener("click", function (e) {
      e.preventDefault();
      cart = {};
      renderCart();
    });
  }

  var form = document.getElementById("pos-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      var t = totals();
      var payload = Object.keys(cart).map(function (id) { return { id: id, qty: cart[id].qty }; });
      document.getElementById("pos-cart-json").value = JSON.stringify(payload);
      document.getElementById("pos-payment-method").value = selectedMethod || "";
      document.getElementById("pos-discount-hidden").value = t.discountPct;
      document.getElementById("pos-tendered-hidden").value = tenderedInput ? (tenderedInput.value || t.total) : t.total;
      document.getElementById("pos-customer-hidden").value = customerSelect ? customerSelect.value : "";

      if (payload.length === 0 || !selectedMethod) {
        e.preventDefault();
      }
    });
  }

  renderCart();
})();

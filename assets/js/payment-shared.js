/**
 * Payment math shared by every page that takes a payment against a total
 * (POS, E-Load) — mirrors the server-side validation in
 * Sukli\Services\PaymentProcessor so the client-side "Complete" button only
 * ever enables when the server would actually accept the submission, and
 * so change/split math can't drift between pages that both compute it.
 */
var SukliPayment = {
  money: function (n, currency) {
    return (currency || "₱") + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },

  /** Change due for a single cash payment. Never negative. */
  cashChange: function (tendered, total) {
    return Math.max(0, Math.round((tendered - total) * 100) / 100);
  },

  /**
   * @param rows Array of {method, amount}
   * @return {allocated, remaining, ok} ok = every row has a positive amount and rows sum to the total (within a cent).
   */
  splitState: function (rows, total) {
    var allocated = 0;
    var valid = rows.length > 0;
    rows.forEach(function (r) {
      var amt = parseFloat(r.amount) || 0;
      if (amt <= 0) valid = false;
      allocated += amt;
    });
    allocated = Math.round(allocated * 100) / 100;
    var remaining = Math.round((total - allocated) * 100) / 100;
    return { allocated: allocated, remaining: Math.max(0, remaining), ok: valid && Math.abs(allocated - total) < 0.01 };
  },
};

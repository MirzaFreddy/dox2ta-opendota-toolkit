(function () {
  function getCellValue(row, idx, type) {
    const cell = row.children[idx];
    if (!cell) return "";
    let v = cell.textContent.trim();
    if (type === "num") {
      v = v.replace(/[%٬,]/g, "");
      const n = parseFloat(v);
      return isNaN(n) ? -Infinity : n;
    }
    return v;
  }
  function sortTable(table, idx, type, dir) {
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.querySelectorAll("tr"));
    rows.sort(function (a, b) {
      const va = getCellValue(a, idx, type);
      const vb = getCellValue(b, idx, type);
      if (va < vb) return dir === "asc" ? -1 : 1;
      if (va > vb) return dir === "asc" ? 1 : -1;
      return 0;
    });
    rows.forEach((r) => tbody.appendChild(r));
  }
  function init() {
    document.querySelectorAll("table.odr-sortable").forEach(function (table) {
      const headers = table.tHead
        ? Array.from(table.tHead.querySelectorAll("th"))
        : [];
      headers.forEach(function (th, i) {
        const type = th.getAttribute("data-type") || "str";
        th.style.cursor = "pointer";
        th.addEventListener("click", function () {
          const current = th.getAttribute("data-sort") || "none";
          const next = current === "asc" ? "desc" : "asc";
          headers.forEach((h) => h.removeAttribute("data-sort"));
          th.setAttribute("data-sort", next);
          sortTable(table, i, type, next);
        });
      });
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();

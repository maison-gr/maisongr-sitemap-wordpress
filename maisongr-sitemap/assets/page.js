/* The sitemap page's filter, generated from maisongr-sitemap by scripts/assets.mjs. Do not edit. */
(function () {
  var q = document.getElementById("q");
  if (!q) return;
  var rows = [].slice.call(document.querySelectorAll(".row[data-u]"));
  var homes = rows.map(function (r) { return r.parentNode; });
  var groups = [].slice.call(document.querySelectorAll(".group"));
  var chips = [].slice.call(document.querySelectorAll(".chip[data-f]"));
  var sort = document.getElementById("recent");
  var list = document.getElementById("recent-list");
  var jump = document.querySelector(".jump");
  var tally = document.getElementById("tally");
  var empty = document.getElementById("empty");
  var lang = "", recent = false;
  function show(r) {
    var data = r.getAttribute("data-v");
    if (!data) return;
    var versions = JSON.parse(data), url = r.querySelector(".url"), langs = r.querySelector(".langs"), pick = null;
    if (!r.own) r.own = [url.getAttribute("hreflang"), url.getAttribute("href")];
    versions.forEach(function (v) { if (v[0] === lang) pick = v; });
    pick = pick || r.own;
    if (url.getAttribute("href") === pick[1]) return;
    url.setAttribute("href", pick[1]);
    url.setAttribute("hreflang", pick[0]);
    url.textContent = pick[1];
    langs.textContent = "";
    versions.forEach(function (v) {
      var current = v[1] === pick[1], tag = document.createElement(current ? "span" : "a");
      tag.className = current ? "lang is-current" : "lang";
      if (!current) { tag.setAttribute("href", v[1]); tag.setAttribute("hreflang", v[0]); }
      tag.textContent = v[0].toUpperCase();
      langs.appendChild(tag);
    });
  }
  function order() {
    if (recent) {
      var target = list.querySelector(".rows");
      rows.slice().sort(function (a, b) {
        var x = a.getAttribute("data-d") || "", y = b.getAttribute("data-d") || "";
        return x < y ? 1 : x > y ? -1 : 0;
      }).forEach(function (r) { target.appendChild(r); });
    } else {
      rows.forEach(function (r, i) { homes[i].appendChild(r); });
    }
  }
  function apply() {
    var term = q.value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (r) {
      var ok = (!term || r.getAttribute("data-u").indexOf(term) !== -1) && (!lang || (" " + (r.getAttribute("data-l") || "") + " ").indexOf(" " + lang + " ") !== -1);
      r.hidden = !ok;
      if (ok) shown++;
      show(r);
    });
    groups.forEach(function (g) { g.hidden = (g === list) !== recent || !g.querySelector(".row:not([hidden])"); });
    if (jump) jump.hidden = recent;
    tally.textContent = term || lang ? (tally.getAttribute("data-t") || "{shown} of {total} pages").replace("{shown}", shown).replace("{total}", rows.length) : "";
    empty.hidden = shown !== 0;
  }
  q.addEventListener("input", apply);
  chips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      lang = chip.getAttribute("data-f");
      chips.forEach(function (c) { c.setAttribute("aria-pressed", c === chip ? "true" : "false"); });
      apply();
    });
  });
  if (sort) {
    sort.addEventListener("click", function () {
      recent = !recent;
      sort.setAttribute("aria-pressed", recent ? "true" : "false");
      order();
      apply();
    });
  }
})();

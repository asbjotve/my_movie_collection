const items = [
  {
    id: "lotr-1",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    type: "movie",
    release: "2001-12-19",
    status: "Sett",
    original: "The Lord of the Rings: The Fellowship of the Ring",
    runtime: "178 min / 228 min (Extended)",
    palette: ["#315b8c", "#122036"],
    summary: "Denne varianten prioriterer lesbar katalogoversikt fremfor store visuelle kort.",
    collections: ["Extended Edition Trilogy Box", "4K Steelbook"],
    sources: ["tmdb: 120", "invelos: 794043785727"]
  },
  {
    id: "blade-runner",
    title: "Blade Runner",
    type: "movie",
    release: "1982-06-25",
    status: "Ikke sett",
    original: "Blade Runner",
    runtime: "117 min",
    palette: ["#7c4e44", "#20111a"],
    summary: "Mer rett på sak hvis siden først og fremst skal brukes som arbeidsflate mot databasen.",
    collections: ["Final Cut Collector's Edition"],
    sources: ["tmdb: 78"]
  },
  {
    id: "zimmer-prague",
    title: "Hans Zimmer: Live in Prague",
    type: "concert",
    release: "2017-11-03",
    status: "Sett",
    original: "Hans Zimmer: Live in Prague",
    runtime: "138 min",
    palette: ["#553781", "#131221"],
    summary: "Strukturen fungerer også for konserter og andre typer innhold.",
    collections: ["Standard Blu-ray"],
    sources: ["tvdb: 341794"]
  }
];

const state = { q: "", type: "", selectedId: items[0].id };
const tableBody = document.getElementById("tableBody");
const detailPanel = document.getElementById("detailPanel");
const resultCount = document.getElementById("resultCount");

function filteredItems() {
  return items.filter((item) => {
    if (state.type && item.type !== state.type) return false;
    if (!state.q) return true;
    return [item.title, item.original, ...item.collections, ...item.sources].join(" ").toLowerCase().includes(state.q);
  });
}

function selectedItem(filtered) {
  const found = filtered.find((item) => item.id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.id ?? null;
  return filtered[0] ?? null;
}

function renderTable(filtered) {
  resultCount.textContent = String(filtered.length);
  tableBody.innerHTML = filtered.map((item) => `
    <tr class="${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}">
      <td>${item.title}</td>
      <td>${item.type}</td>
      <td>${item.release}</td>
      <td><span class="pill ${item.status === "Sett" ? "good" : ""}">${item.status}</span></td>
    </tr>
  `).join("");
  tableBody.querySelectorAll("[data-id]").forEach((row) => {
    row.addEventListener("click", () => { state.selectedId = row.dataset.id; render(); });
  });
}

function renderDetail(item) {
  if (!item) {
    detailPanel.innerHTML = "";
    return;
  }
  detailPanel.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <p class="eyebrow">Valgt rad</p>
      <h2>${item.title}</h2>
      <p class="subtitle">${item.original}</p>
      <p class="summary">${item.summary}</p>
    </section>
    <article class="detail-card">
      <h3>Nøkkeldata</h3>
      <div class="stack">
        <div class="item"><strong>Release:</strong> ${item.release}</div>
        <div class="item"><strong>Runtime:</strong> ${item.runtime}</div>
        <div class="item"><strong>Status:</strong> ${item.status}</div>
      </div>
    </article>
    <article class="detail-card">
      <h3>Utgaver</h3>
      <div class="stack">${item.collections.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
    </article>
    <article class="detail-card">
      <h3>Kilder</h3>
      <div class="stack">${item.sources.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
    </article>
  `;
}

function render() {
  const filtered = filteredItems();
  const current = selectedItem(filtered);
  renderTable(filtered);
  renderDetail(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });

render();

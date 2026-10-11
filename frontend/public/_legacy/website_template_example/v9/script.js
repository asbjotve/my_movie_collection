const items = [
  { id: "lotr", title: "The Lord of the Rings: The Fellowship of the Ring", type: "movie", year: "2001", runtime: "178 min", watched: true, palette: ["#305a8b", "#142035"], summary: "Denne varianten prøver å starte med samlingsbildet før valgt tittel får detaljplass.", collections: ["Extended Edition Trilogy Box", "4K Steelbook"], sources: ["tmdb: 120", "invelos: 794043785727"] },
  { id: "blade", title: "Blade Runner", type: "movie", year: "1982", runtime: "117 min", watched: false, palette: ["#7a4c43", "#1f131c"], summary: "Nøkkeltall øverst kan være nyttig hvis du vil ha mer dashboardfølelse.", collections: ["Final Cut Collector's Edition"], sources: ["tmdb: 78"] },
  { id: "zimmer", title: "Hans Zimmer: Live in Prague", type: "concert", year: "2017", runtime: "138 min", watched: true, palette: ["#563981", "#141320"], summary: "Samme struktur kan brukes selv om samlingen utvides med flere innholdstyper.", collections: ["Standard Blu-ray"], sources: ["tvdb: 341794"] }
];

const state = { q: "", type: "", selectedId: items[0].id };
const statsRow = document.getElementById("statsRow");
const listGrid = document.getElementById("listGrid");
const detailPanel = document.getElementById("detailPanel");

function filteredItems() {
  return items.filter((item) => {
    if (state.type && item.type !== state.type) return false;
    if (!state.q) return true;
    return [item.title, item.type].join(" ").toLowerCase().includes(state.q);
  });
}

function selectedItem(filtered) {
  const found = filtered.find((item) => item.id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.id ?? null;
  return filtered[0] ?? null;
}

function renderStats(filtered) {
  const watchedCount = filtered.filter((item) => item.watched).length;
  const movieCount = filtered.filter((item) => item.type === "movie").length;
  const sourceCount = filtered.reduce((sum, item) => sum + item.sources.length, 0);
  statsRow.innerHTML = `
    <article class="stat-card"><span class="muted">Titler</span><strong>${filtered.length}</strong><p class="muted">I gjeldende utvalg</p></article>
    <article class="stat-card"><span class="muted">Sett</span><strong>${watchedCount}</strong><p class="muted">Valgte titler</p></article>
    <article class="stat-card"><span class="muted">Filmer</span><strong>${movieCount}</strong><p class="muted">Av filtrerte titler</p></article>
    <article class="stat-card"><span class="muted">Kilder</span><strong>${sourceCount}</strong><p class="muted">Registrerte koblinger</p></article>
  `;
}

function renderGrid(filtered) {
  listGrid.innerHTML = filtered.map((item) => `
    <button class="list-card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
      <div class="meta">
        <span>${item.type}</span>
        <span>${item.year}</span>
      </div>
      <h3>${item.title}</h3>
      <div class="meta">
        <span>${item.runtime}</span>
        <span>${item.collections.length} utgave${item.collections.length > 1 ? "r" : ""}</span>
      </div>
    </button>
  `).join("");
  listGrid.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => { state.selectedId = button.dataset.id; render(); });
  });
}

function renderDetail(item) {
  if (!item) {
    detailPanel.innerHTML = "";
    return;
  }
  detailPanel.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <p class="eyebrow">Valgt tittel</p>
      <h2>${item.title}</h2>
      <p class="subtitle">${item.year} · ${item.runtime}</p>
      <p class="summary">${item.summary}</p>
    </section>
    <section class="detail-layout">
      <article class="detail-card">
        <h3>Utgaver</h3>
        <div class="stack">${item.collections.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
      </article>
      <article class="detail-card">
        <h3>Kilder</h3>
        <div class="stack">${item.sources.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
      </article>
    </section>
  `;
}

function render() {
  const filtered = filteredItems();
  renderStats(filtered);
  renderGrid(filtered);
  renderDetail(selectedItem(filtered));
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });

render();

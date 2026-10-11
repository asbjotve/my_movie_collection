const items = [
  {
    id: "lotr-1",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original: "The Lord of the Rings: The Fellowship of the Ring",
    type: "movie",
    year: "2001",
    runtime: "178 min / 228 min (Extended)",
    palette: ["#305a8b", "#152138"],
    summary: "Denne varianten fokuserer på én valgt tittel og bytter detaljinnhold via faner i stedet for lange sider.",
    overview: ["Aldersgrense: 12", "Status: Sett", "content_id: f65e6f10-7c4e-4db4-9108-001"],
    collections: ["Extended Edition Trilogy Box", "4K Steelbook"],
    sources: ["tmdb: 120", "invelos: 794043785727"]
  },
  {
    id: "blade-runner",
    title: "Blade Runner",
    original: "Blade Runner",
    type: "movie",
    year: "1982",
    runtime: "117 min",
    palette: ["#7c4c43", "#1f131c"],
    summary: "Passer hvis du vil holde fokuset på valgt tittel og bare vise én gruppe data om gangen.",
    overview: ["Aldersgrense: 15", "Status: Ikke sett", "content_id: a14bbabc-7f9c-438e-8a32-002"],
    collections: ["Final Cut Collector's Edition"],
    sources: ["tmdb: 78"]
  },
  {
    id: "zimmer-prague",
    title: "Hans Zimmer: Live in Prague",
    original: "Hans Zimmer: Live in Prague",
    type: "concert",
    year: "2017",
    runtime: "138 min",
    palette: ["#573981", "#141320"],
    summary: "Faneoppsettet gir en ryddig detaljflate også for andre typer innhold.",
    overview: ["Aldersgrense: A", "Status: Sett", "content_id: 84ff6211-e7a7-4af3-9ddb-003"],
    collections: ["Standard Blu-ray"],
    sources: ["tvdb: 341794"]
  }
];

const state = { q: "", type: "", selectedId: items[0].id, tab: "overview" };
const cardGrid = document.getElementById("cardGrid");
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

function renderCards(filtered) {
  resultCount.textContent = String(filtered.length);
  cardGrid.innerHTML = filtered.map((item) => `
    <button class="card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}">
      <div class="pill">${item.type}</div>
      <div class="card-title">${item.title}</div>
      <div class="meta">
        <span>${item.year}</span>
        <span>${item.runtime}</span>
      </div>
    </button>
  `).join("");
  cardGrid.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => {
      state.selectedId = button.dataset.id;
      render();
    });
  });
}

function currentTabContent(item) {
  const entries = state.tab === "overview" ? item.overview : state.tab === "collections" ? item.collections : item.sources;
  return entries.map((entry) => `<div class="mini-card">${entry}</div>`).join("");
}

function renderDetail(item) {
  if (!item) {
    detailPanel.innerHTML = "";
    return;
  }
  detailPanel.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="pill">${item.type}</div>
      <h2>${item.title}</h2>
      <p class="subtitle">${item.original}</p>
      <p class="summary">${item.summary}</p>
    </section>
    <section class="tabs">
      <button class="tab-button ${state.tab === "overview" ? "is-active" : ""}" data-tab="overview">Oversikt</button>
      <button class="tab-button ${state.tab === "collections" ? "is-active" : ""}" data-tab="collections">Utgaver</button>
      <button class="tab-button ${state.tab === "sources" ? "is-active" : ""}" data-tab="sources">Kilder</button>
    </section>
    <article class="tab-card">
      <h3>${state.tab === "overview" ? "Oversikt" : state.tab === "collections" ? "Utgaver" : "Kilder"}</h3>
      <div class="stack">${currentTabContent(item)}</div>
    </article>
  `;
  detailPanel.querySelectorAll("[data-tab]").forEach((button) => {
    button.addEventListener("click", () => {
      state.tab = button.dataset.tab;
      render();
    });
  });
}

function render() {
  const filtered = filteredItems();
  const current = selectedItem(filtered);
  renderCards(filtered);
  renderDetail(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });

render();

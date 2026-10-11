const items = [
  { id: "lotr", title: "The Lord of the Rings: The Fellowship of the Ring", type: "movie", year: "2001", watched: true, runtime: "178 min", palette: ["#315a8c", "#142034"], summary: "Dette er mer en arbeidsflate enn en publikumsside, med filtre alltid synlige til venstre.", collections: ["Extended Edition Trilogy Box", "4K Steelbook"], sources: ["tmdb: 120", "invelos: 794043785727"] },
  { id: "blade", title: "Blade Runner", type: "movie", year: "1982", watched: false, runtime: "117 min", palette: ["#7a4c43", "#1f131c"], summary: "God hvis du vil veksle raskt mellom mange titler uten å miste oversikten.", collections: ["Final Cut Collector's Edition"], sources: ["tmdb: 78"] },
  { id: "zimmer", title: "Hans Zimmer: Live in Prague", type: "concert", year: "2017", watched: true, runtime: "138 min", palette: ["#563981", "#141320"], summary: "Samme mønster fungerer for konserter og andre typer innhold.", collections: ["Standard Blu-ray"], sources: ["tvdb: 341794"] }
];

const state = { q: "", type: "", unwatched: false, selectedId: items[0].id };
const listPanel = document.getElementById("listPanel");
const detailPanel = document.getElementById("detailPanel");
const resultCount = document.getElementById("resultCount");

function filteredItems() {
  return items.filter((item) => {
    if (state.type && item.type !== state.type) return false;
    if (state.unwatched && item.watched) return false;
    if (!state.q) return true;
    return [item.title, item.type, ...item.collections].join(" ").toLowerCase().includes(state.q);
  });
}

function selectedItem(filtered) {
  const found = filtered.find((item) => item.id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.id ?? null;
  return filtered[0] ?? null;
}

function renderList(filtered) {
  resultCount.textContent = String(filtered.length);
  listPanel.innerHTML = filtered.map((item) => `
    <button class="list-card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}">
      <span class="pill">${item.type}</span>
      <h3>${item.title}</h3>
      <div class="meta">
        <span>${item.year}</span>
        <span>${item.runtime}</span>
      </div>
      <span class="pill ${item.watched ? "good" : ""}">${item.watched ? "Sett" : "Ikke sett"}</span>
    </button>
  `).join("");
  listPanel.querySelectorAll("[data-id]").forEach((button) => {
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
    <section class="cards">
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
  const current = selectedItem(filtered);
  renderList(filtered);
  renderDetail(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });
document.getElementById("watchedFilter").addEventListener("change", (event) => { state.unwatched = event.target.checked; render(); });

render();

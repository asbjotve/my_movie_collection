const items = [
  { id: "lotr", title: "The Lord of the Rings: The Fellowship of the Ring", subtitle: "The Lord of the Rings: The Fellowship of the Ring", summary: "Denne varianten kutter ned på alt unødvendig og lar valgt tittel stå i sentrum.", palette: ["#4769b8", "#1f315e"], collections: ["Extended Edition Trilogy Box", "4K Steelbook"], sources: ["tmdb: 120", "invelos: 794043785727"] },
  { id: "blade", title: "Blade Runner", subtitle: "Blade Runner", summary: "Målet er en rolig, lys og lettlest detaljside som kan være et godt utgangspunkt for videre UI-arbeid.", palette: ["#8b5b52", "#4b2d36"], collections: ["Final Cut Collector's Edition"], sources: ["tmdb: 78"] },
  { id: "zimmer", title: "Hans Zimmer: Live in Prague", subtitle: "Hans Zimmer: Live in Prague", summary: "Et minimalistisk uttrykk kan gjøre det enklere å fokusere på struktur før man tar stilling til mer avansert design.", palette: ["#6b5ab2", "#372d63"], collections: ["Standard Blu-ray"], sources: ["tvdb: 341794"] }
];

const state = { q: "", selectedId: items[0].id };
const listPanel = document.getElementById("listPanel");
const detailPanel = document.getElementById("detailPanel");

function filteredItems() {
  return items.filter((item) => !state.q || item.title.toLowerCase().includes(state.q));
}

function selectedItem(filtered) {
  const found = filtered.find((item) => item.id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.id ?? null;
  return filtered[0] ?? null;
}

function renderList(filtered) {
  listPanel.innerHTML = filtered.map((item) => `
    <button class="card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}">
      <h3>${item.title}</h3>
      <p class="muted">${item.collections.length} utgave${item.collections.length > 1 ? "r" : ""}</p>
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
      <p class="subtitle">${item.subtitle}</p>
      <p class="summary">${item.summary}</p>
    </section>
    <section class="detail-grid">
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

document.getElementById("searchInput").addEventListener("input", (event) => {
  state.q = event.target.value.trim().toLowerCase();
  render();
});

render();

const items = [
  { id: "lotr", title: "The Lord of the Rings: The Fellowship of the Ring", type: "movie", year: "2001", runtime: "178 min", sources: 2, palette: ["#315a8a", "#132036"], summary: "Mer filmplakat-orientert førsteside der hovedinntrykket er viktigere enn tabellstruktur." },
  { id: "blade", title: "Blade Runner", type: "movie", year: "1982", runtime: "117 min", sources: 1, palette: ["#7c4d43", "#1f131c"], summary: "Overlay-informasjon nederst i heroen gjør løsningen mer kinoinspirert." },
  { id: "zimmer", title: "Hans Zimmer: Live in Prague", type: "concert", year: "2017", runtime: "138 min", sources: 1, palette: ["#583982", "#141320"], summary: "Samme oppsett kan brukes også for konserter og annet innhold." }
];

const state = { q: "", type: "", selectedId: items[0].id };
const heroSection = document.getElementById("heroSection");
const posterWall = document.getElementById("posterWall");

function filteredItems() {
  return items.filter((item) => {
    if (state.type && item.type !== state.type) return false;
    if (!state.q) return true;
    return [item.title, item.type].join(" ").toLowerCase().includes(state.q);
  });
}

function currentItem(filtered) {
  const found = filtered.find((item) => item.id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.id ?? null;
  return filtered[0] ?? null;
}

function renderHero(item) {
  if (!item) {
    heroSection.innerHTML = "";
    return;
  }
  heroSection.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="hero-inner">
        <div class="hero-copy">
          <p class="eyebrow">Valgt tittel</p>
          <h2>${item.title}</h2>
          <div class="meta-row">
            <span class="pill">${item.type}</span>
            <span class="pill">${item.year}</span>
            <span class="pill">${item.runtime}</span>
            <span class="pill">${item.sources} kilder</span>
          </div>
          <p class="summary">${item.summary}</p>
        </div>
      </div>
    </section>
  `;
}

function renderWall(filtered) {
  posterWall.innerHTML = filtered.map((item) => `
    <button class="poster-card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
      <h3>${item.title}</h3>
      <div class="meta">
        <span>${item.year}</span>
        <span>${item.runtime}</span>
      </div>
    </button>
  `).join("");
  posterWall.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => { state.selectedId = button.dataset.id; render(); });
  });
}

function render() {
  const filtered = filteredItems();
  const selected = currentItem(filtered);
  renderHero(selected);
  renderWall(filtered);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });

render();

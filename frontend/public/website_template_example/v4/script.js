const items = [
  {
    id: "lotr-1",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original: "The Lord of the Rings: The Fellowship of the Ring",
    type: "movie",
    year: "2001",
    runtime: "178 min / Extended tilgjengelig",
    age: "12",
    watched: true,
    palette: ["#325d8f", "#142033"],
    summary: "Her er toppflaten viktigst: én valgt tittel får stort visuelt rom, mens resten av informasjonen legges i mindre kort under.",
    collections: ["Extended Edition Trilogy Box", "4K Steelbook"],
    sources: ["tmdb: 120", "invelos: 794043785727"],
    notes: ["2 utgaver registrert", "Passer godt for filmfokusert førsteside"]
  },
  {
    id: "blade-runner",
    title: "Blade Runner",
    original: "Blade Runner",
    type: "movie",
    year: "1982",
    runtime: "117 min",
    age: "15",
    watched: false,
    palette: ["#805045", "#1d1421"],
    summary: "Mindre visuelt støy og tydeligere skille mellom hovedpresentasjon og sekundære metadata.",
    collections: ["Final Cut Collector's Edition"],
    sources: ["tmdb: 78"],
    notes: ["1 utgave registrert", "Bra hvis forsiden skal minne om offentlig filmkatalog"]
  },
  {
    id: "zimmer-prague",
    title: "Hans Zimmer: Live in Prague",
    original: "Hans Zimmer: Live in Prague",
    type: "concert",
    year: "2017",
    runtime: "138 min",
    age: "A",
    watched: true,
    palette: ["#5b3a83", "#11111f"],
    summary: "Samme idé kan brukes for konserter uten at designet må endres mye.",
    collections: ["Standard Blu-ray"],
    sources: ["tvdb: 341794"],
    notes: ["Midlertidige metadata kan også passe her"]
  }
];

const state = { q: "", type: "", selectedId: items[0].id };
const resultCount = document.getElementById("resultCount");
const heroSection = document.getElementById("heroSection");
const cardRail = document.getElementById("cardRail");
const detailGrid = document.getElementById("detailGrid");

function label(type) {
  return { movie: "Film", series: "Serie", concert: "Konsert" }[type] || type;
}

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

function renderHero(item) {
  if (!item) {
    heroSection.innerHTML = "";
    detailGrid.innerHTML = "";
    return;
  }
  heroSection.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="hero-content">
        <div class="poster">
          <span class="poster-type">${label(item.type)}</span>
          <div class="poster-title">${item.title}</div>
        </div>
        <div class="hero-copy">
          <p class="eyebrow">Valgt tittel</p>
          <h2>${item.title}</h2>
          <p class="subtitle">${item.original}</p>
          <div class="hero-meta">
            <span class="pill">${item.year}</span>
            <span class="pill">${item.runtime}</span>
            <span class="pill">${item.age} år</span>
            <span class="pill">${item.watched ? "Sett" : "Ikke sett"}</span>
          </div>
          <p class="summary">${item.summary}</p>
        </div>
      </div>
    </section>
  `;
}

function renderRail(filtered) {
  resultCount.textContent = String(filtered.length);
  cardRail.innerHTML = filtered.map((item) => `
    <button class="rail-card ${item.id === state.selectedId ? "is-active" : ""}" data-id="${item.id}">
      <div class="rail-cover" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
        <div class="rail-title">${item.title}</div>
      </div>
      <div class="rail-body">
        <span class="pill">${label(item.type)}</span>
        <div class="card-meta">
          <span>${item.year}</span>
          <span>${item.collections.length} utgave${item.collections.length > 1 ? "r" : ""}</span>
        </div>
      </div>
    </button>
  `).join("");
  cardRail.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => { state.selectedId = button.dataset.id; render(); });
  });
}

function renderDetails(item) {
  if (!item) return;
  detailGrid.innerHTML = `
    <article class="mini-card">
      <h3>Utgaver</h3>
      <div class="stack">${item.collections.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
    </article>
    <article class="mini-card">
      <h3>Kilder</h3>
      <div class="stack">${item.sources.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
    </article>
    <article class="mini-card">
      <h3>Notater</h3>
      <div class="stack">${item.notes.map((entry) => `<div class="item">${entry}</div>`).join("")}</div>
    </article>
  `;
}

function render() {
  const filtered = filteredItems();
  const current = selectedItem(filtered);
  renderHero(current);
  renderRail(filtered);
  renderDetails(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });

render();

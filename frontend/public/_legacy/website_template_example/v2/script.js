const items = [
  {
    content_id: "f65e6f10-7c4e-4db4-9108-001",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original_title: "The Lord of the Rings: The Fellowship of the Ring",
    content_type: "movie",
    first_release: "2001-12-19",
    runtime_txt: "178 min / Extended tilgjengelig",
    age_restriction: "12",
    watched_flag: true,
    temporary_flag: false,
    palette: ["#325d8f", "#142033"],
    summary: "Stor hero og roligere underseksjoner gjør at siden minner mer om en detaljside enn et register.",
    tagline: "Ett verk, flere utgaver, tydelig plassert i samlingen.",
    sources: [
      { source: "tmdb", external_id: "120" },
      { source: "invelos", external_id: "794043785727" }
    ],
    collections: [
      { label: "Extended Edition Trilogy Box", format: "Blu-ray", location: "Hylle A", discs: 2 },
      { label: "4K Steelbook", format: "4K UHD", location: "Skuff 2", discs: 1 }
    ],
    discHighlights: ["Disc 1: hovedfilm", "Disc 2: bonusmateriale med kommentarspor og making-of"]
  },
  {
    content_id: "a14bbabc-7f9c-438e-8a32-002",
    title: "Blade Runner",
    original_title: "Blade Runner",
    content_type: "movie",
    first_release: "1982-06-25",
    runtime_txt: "117 min",
    age_restriction: "15",
    watched_flag: false,
    temporary_flag: false,
    palette: ["#805045", "#1d1421"],
    summary: "Kort metadata i heroen, med fysisk samlingsdata i små paneler lenger ned.",
    tagline: "Fysisk utgave blir sekundærinfo under hovedpresentasjonen.",
    sources: [{ source: "tmdb", external_id: "78" }],
    collections: [{ label: "Final Cut Collector's Edition", format: "Blu-ray", location: "Hylle B", discs: 2 }],
    discHighlights: ["Disc 1: Final Cut", "Disc 2: Dangerous Days-dokumentaren"]
  },
  {
    content_id: "84ff6211-e7a7-4af3-9ddb-003",
    title: "Hans Zimmer: Live in Prague",
    original_title: "Hans Zimmer: Live in Prague",
    content_type: "concert",
    first_release: "2017-11-03",
    runtime_txt: "138 min",
    age_restriction: "A",
    watched_flag: true,
    temporary_flag: true,
    palette: ["#5b3a83", "#11111f"],
    summary: "Samme oppsett fungerer også for konserter og midlertidige poster.",
    tagline: "Mykere statusmarkør, ellers samme struktur.",
    sources: [{ source: "tvdb", external_id: "341794" }],
    collections: [{ label: "Standard Blu-ray", format: "Blu-ray", location: "Konsert-reol", discs: 1 }],
    discHighlights: ["Disc 1: konsertopptak"]
  }
];

const state = { q: "", type: "", unwatched: false, selectedId: items[0].content_id };
const heroSection = document.getElementById("heroSection");
const cardRail = document.getElementById("cardRail");
const detailSections = document.getElementById("detailSections");
const resultCount = document.getElementById("resultCount");

function label(type) {
  return { movie: "Film", series: "Serie", concert: "Konsert" }[type] || type;
}

function filterItems() {
  return items.filter((item) => {
    if (state.type && item.content_type !== state.type) return false;
    if (state.unwatched && item.watched_flag) return false;
    if (!state.q) return true;
    return [item.title, item.original_title, ...item.collections.map((c) => c.label)]
      .join(" ")
      .toLowerCase()
      .includes(state.q);
  });
}

function getSelected(filtered) {
  const selected = filtered.find((item) => item.content_id === state.selectedId);
  if (selected) return selected;
  state.selectedId = filtered[0]?.content_id ?? null;
  return filtered[0] ?? null;
}

function renderHero(item) {
  if (!item) {
    heroSection.innerHTML = '<div class="empty-state">Ingen titler matcher filtrene.</div>';
    return;
  }
  const discCount = item.collections.reduce((sum, collection) => sum + collection.discs, 0);
  heroSection.innerHTML = `
    <article class="hero-surface" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="hero-backdrop"></div>
      <div class="hero-content">
        <div class="poster-card">
          <span class="poster-type">${label(item.content_type)}</span>
          <div class="poster-title">${item.title}</div>
        </div>
        <div class="hero-copy">
          <p class="eyebrow">Detaljvisning</p>
          <h2>${item.title}</h2>
          <p class="subtitle">${item.original_title}</p>
          <div class="hero-pills">
            <span class="pill">${item.first_release.slice(0, 4)}</span>
            <span class="pill">${item.runtime_txt}</span>
            <span class="pill">${item.age_restriction} år</span>
            <span class="pill ${item.watched_flag ? "good" : "warn"}">${item.watched_flag ? "Sett" : "Ikke sett"}</span>
            ${item.temporary_flag ? '<span class="pill danger">Midlertidig metadata</span>' : ""}
          </div>
          <p class="summary">${item.summary}</p>
          <div class="stat-row">
            <div class="stat-chip"><strong>${item.collections.length}</strong><span>Utgaver</span></div>
            <div class="stat-chip"><strong>${discCount}</strong><span>Disker</span></div>
            <div class="stat-chip"><strong>${item.sources.length}</strong><span>Kilder</span></div>
          </div>
        </div>
      </div>
    </article>
  `;
}

function renderRail(filtered) {
  resultCount.textContent = `${filtered.length} titler`;
  if (!filtered.length) {
    cardRail.innerHTML = '<div class="empty-state">Prøv et annet søk eller filter.</div>';
    return;
  }
  cardRail.innerHTML = filtered.map((item) => `
    <button class="rail-card ${item.content_id === state.selectedId ? "is-active" : ""}" data-id="${item.content_id}">
      <div class="rail-card-poster" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
        <div class="rail-card-title">${item.title}</div>
      </div>
      <div class="rail-card-body">
        <span class="pill">${label(item.content_type)}</span>
        <div class="meta-row">
          <span>${item.first_release.slice(0, 4)}</span>
          <span>${item.collections.length} utgave${item.collections.length > 1 ? "r" : ""}</span>
        </div>
        <p class="subtitle">${item.tagline}</p>
      </div>
    </button>
  `).join("");
  cardRail.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => { state.selectedId = button.dataset.id; render(); });
  });
}

function renderDetails(item) {
  if (!item) {
    detailSections.innerHTML = "";
    return;
  }
  detailSections.innerHTML = `
    <div class="detail-column">
      <article class="mini-panel">
        <p class="eyebrow">Fysiske utgaver</p>
        <h3>Det du eier</h3>
        <p>Produkter knyttet til samme content-rad.</p>
        ${item.collections.map((collection) => `
          <div class="list-card">
            <h3>${collection.label}</h3>
            <div class="collection-list">
              <span class="pill">${collection.format}</span>
              <span class="pill">${collection.discs} disk${collection.discs > 1 ? "er" : ""}</span>
              <span class="pill">${collection.location}</span>
            </div>
          </div>
        `).join("")}
      </article>
      <article class="mini-panel">
        <p class="eyebrow">Disker</p>
        <h3>Høydepunkter</h3>
        <p>Kort sammendrag i stedet for full tabell.</p>
        ${item.discHighlights.map((line) => `<div class="list-card"><h3>${line}</h3></div>`).join("")}
      </article>
    </div>
    <div class="detail-column">
      <article class="mini-panel">
        <p class="eyebrow">Eksterne kilder</p>
        <h3>Koblinger</h3>
        <p>Plassholder for API-data senere.</p>
        ${item.sources.map((source) => `<div class="list-card"><h3>${source.source}</h3><p>${source.external_id}</p></div>`).join("")}
      </article>
    </div>
  `;
}

function render() {
  const filtered = filterItems();
  const selected = getSelected(filtered);
  renderHero(selected);
  renderRail(filtered);
  renderDetails(selected);
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });
document.getElementById("watchedFilter").addEventListener("change", (event) => { state.unwatched = event.target.checked; render(); });

render();

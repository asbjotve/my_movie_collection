const items = [
  {
    content_id: "f65e6f10-7c4e-4db4-9108-001",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original_title: "The Lord of the Rings: The Fellowship of the Ring",
    content_type: "movie",
    first_release: "2001-12-19",
    runtime_txt: "178 min / Extended",
    age_restriction: "12",
    watched_flag: true,
    palette: ["rgba(72,105,184,.95)", "rgba(26,39,68,.95)"],
    summary: "Mer app-preget oppsett, der biblioteket står fast til venstre og detaljvisningen oppdateres til høyre.",
    collections: [
      { label: "Extended Edition Trilogy Box", format: "Blu-ray", location: "Hylle A" },
      { label: "4K Steelbook", format: "4K UHD", location: "Skuff 2" }
    ],
    sources: [{ source: "tmdb", external_id: "120" }, { source: "invelos", external_id: "794043785727" }]
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
    palette: ["rgba(137,79,64,.95)", "rgba(40,20,29,.95)"],
    summary: "Passer hvis du vil jobbe mer som i en intern katalog-applikasjon enn som på en offentlig detaljside.",
    collections: [{ label: "Final Cut Collector's Edition", format: "Blu-ray", location: "Hylle B" }],
    sources: [{ source: "tmdb", external_id: "78" }]
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
    palette: ["rgba(88,58,131,.95)", "rgba(17,17,31,.95)"],
    summary: "Konserter og andre typer innhold får samme struktur uten ekstra kompleksitet.",
    collections: [{ label: "Standard Blu-ray", format: "Blu-ray", location: "Konsert-reol" }],
    sources: [{ source: "tvdb", external_id: "341794" }]
  }
];

const state = { q: "", type: "", unwatched: false, selectedId: items[0].content_id };
const listPanel = document.getElementById("listPanel");
const detailPanel = document.getElementById("detailPanel");
const resultCount = document.getElementById("resultCount");

function label(type) {
  return { movie: "Film", series: "Serie", concert: "Konsert" }[type] || type;
}

function filterItems() {
  return items.filter((item) => {
    if (state.type && item.content_type !== state.type) return false;
    if (state.unwatched && item.watched_flag) return false;
    if (!state.q) return true;
    return [item.title, item.original_title, ...item.collections.map((c) => c.label)].join(" ").toLowerCase().includes(state.q);
  });
}

function getSelected(filtered) {
  const selected = filtered.find((item) => item.content_id === state.selectedId);
  if (selected) return selected;
  state.selectedId = filtered[0]?.content_id ?? null;
  return filtered[0] ?? null;
}

function renderList(filtered) {
  resultCount.textContent = String(filtered.length);
  if (!filtered.length) {
    listPanel.innerHTML = '<div class="empty-state">Ingen treff.</div>';
    return;
  }
  listPanel.innerHTML = filtered.map((item) => `
    <button class="list-card ${item.content_id === state.selectedId ? "is-active" : ""}" data-id="${item.content_id}">
      <span class="pill">${label(item.content_type)}</span>
      <h3>${item.title}</h3>
      <div class="meta-line">
        <span>${item.first_release}</span>
        <span>${item.runtime_txt}</span>
      </div>
      <span class="pill ${item.watched_flag ? "good" : ""}">${item.watched_flag ? "Sett" : "Ikke sett"}</span>
    </button>
  `).join("");
  listPanel.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => { state.selectedId = button.dataset.id; render(); });
  });
}

function renderDetail(item) {
  if (!item) {
    detailPanel.innerHTML = '<div class="panel empty-state">Velg en tittel.</div>';
    return;
  }
  detailPanel.innerHTML = `
    <section class="hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <span class="pill">${label(item.content_type)}</span>
      <h2>${item.title}</h2>
      <div class="subtitle">${item.original_title}</div>
      <div class="summary">${item.summary}</div>
    </section>

    <section class="detail-grid">
      <article class="detail-card">
        <h3>Nøkkeldata</h3>
        <div class="detail-list">
          <div class="detail-item"><strong>Release:</strong> ${item.first_release}</div>
          <div class="detail-item"><strong>Runtime:</strong> ${item.runtime_txt}</div>
          <div class="detail-item"><strong>Aldersgrense:</strong> ${item.age_restriction}</div>
          <div class="detail-item"><strong>Status:</strong> ${item.watched_flag ? "Sett" : "Ikke sett"}</div>
        </div>
      </article>

      <article class="detail-card">
        <h3>Utgaver</h3>
        <div class="detail-list">
          ${item.collections.map((collection) => `
            <div class="detail-item">
              <strong>${collection.label}</strong>
              <div class="meta-line">
                <span>${collection.format}</span>
                <span>${collection.location}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </article>

      <article class="detail-card">
        <h3>Eksterne kilder</h3>
        <div class="detail-list">
          ${item.sources.map((source) => `
            <div class="detail-item">
              <strong>${source.source}</strong>
              <div class="meta-line"><span>${source.external_id}</span></div>
            </div>
          `).join("")}
        </div>
      </article>
    </section>
  `;
}

function render() {
  const filtered = filterItems();
  renderList(filtered);
  renderDetail(getSelected(filtered));
}

document.getElementById("searchInput").addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById("typeFilter").addEventListener("change", (event) => { state.type = event.target.value; render(); });
document.getElementById("watchedFilter").addEventListener("change", (event) => { state.unwatched = event.target.checked; render(); });

render();

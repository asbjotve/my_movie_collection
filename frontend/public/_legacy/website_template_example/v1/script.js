const items = [
  {
    content_id: "f65e6f10-7c4e-4db4-9108-001",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original_title: "The Lord of the Rings: The Fellowship of the Ring",
    content_type: "movie",
    first_release: "2001-12-19",
    runtime_txt: "178 min / 228 min (Extended)",
    age_restriction: "12",
    watched_flag: true,
    palette: ["rgba(122,162,255,.35)", "rgba(61,220,151,.18)"],
    summary: "Én content-rad kan kobles til flere fysiske utgaver, men detaljvisningen holder fokuset på tittelen først.",
    collections: [
      { label: "Extended Edition Trilogy Box", format: "Blu-ray", barcode: null, box_set_barcode: "5051895416229" },
      { label: "4K Steelbook", format: "4K UHD", barcode: "7333018024029", box_set_barcode: null }
    ],
    sources: [
      { source: "tmdb", external_id: "120", fetched_at: "2026-05-17 20:15" },
      { source: "invelos", external_id: "794043785727", fetched_at: "2026-05-18 09:20" }
    ]
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
    palette: ["rgba(122,162,255,.25)", "rgba(255,140,110,.18)"],
    summary: "Et enklere oppsett der kortet gir oversikt og detaljseksjonen viser de viktigste koblingene.",
    collections: [
      { label: "Final Cut Collector's Edition", format: "Blu-ray", barcode: "5051895037714", box_set_barcode: null }
    ],
    sources: [
      { source: "tmdb", external_id: "78", fetched_at: "2026-06-01 21:08" }
    ]
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
    palette: ["rgba(122,162,255,.28)", "rgba(177,98,255,.2)"],
    summary: "Midlertidige poster kan vises på samme måte uten at forsiden trenger å bli overlesset.",
    collections: [
      { label: "Standard Blu-ray", format: "Blu-ray", barcode: "5051300201723", box_set_barcode: null }
    ],
    sources: [
      { source: "tvdb", external_id: "341794", fetched_at: "2026-04-21 18:02" }
    ]
  }
];

const state = { q: "", type: "", unwatched: false, selectedId: items[0].content_id };
const searchInput = document.getElementById("searchInput");
const typeFilter = document.getElementById("typeFilter");
const watchedFilter = document.getElementById("watchedFilter");
const resultCount = document.getElementById("resultCount");
const contentGrid = document.getElementById("contentGrid");
const detailSection = document.getElementById("detailSection");

function labelForType(type) {
  return { movie: "FILM", series: "SERIE", concert: "KONSERT" }[type] || String(type || "").toUpperCase();
}

function filterItems() {
  return items.filter((item) => {
    if (state.type && item.content_type !== state.type) return false;
    if (state.unwatched && item.watched_flag) return false;
    if (!state.q) return true;
    const haystack = [item.title, item.original_title, ...item.collections.flatMap((c) => [c.label, c.barcode, c.box_set_barcode])]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
    return haystack.includes(state.q);
  });
}

function getSelected(filtered) {
  const selected = filtered.find((item) => item.content_id === state.selectedId);
  if (selected) return selected;
  state.selectedId = filtered[0]?.content_id ?? null;
  return filtered[0] ?? null;
}

function renderGrid(filtered) {
  resultCount.textContent = String(filtered.length);
  if (!filtered.length) {
    contentGrid.innerHTML = '<div class="panel empty-state muted">Ingen titler matcher filtrene.</div>';
    return;
  }

  contentGrid.innerHTML = filtered.map((item) => `
    <button type="button" class="content-card ${item.content_id === state.selectedId ? "is-active" : ""}" data-id="${item.content_id}">
      <div class="cover-block" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
        <div class="type-pill">${labelForType(item.content_type)}</div>
      </div>
      <div class="card-body">
        <div class="card-title">${item.title}</div>
        <div class="meta-line">
          <span>${item.first_release}</span>
          <span>${item.runtime_txt}</span>
          <span>${item.age_restriction}</span>
        </div>
        <div class="status-pill ${item.watched_flag ? "good" : ""}">${item.watched_flag ? "SETT" : "IKKE SETT"}</div>
      </div>
    </button>
  `).join("");

  contentGrid.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => {
      state.selectedId = button.dataset.id;
      render();
    });
  });
}

function renderDetail(item) {
  if (!item) {
    detailSection.innerHTML = "";
    return;
  }

  detailSection.innerHTML = `
    <div class="detail-wrap">
      <div class="panel poster-panel">
        <div class="poster-hero" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
          <div class="type-pill">${labelForType(item.content_type)}</div>
        </div>
        <div class="meta-box">
          <div class="muted small">content_id</div>
          <div class="mono">${item.content_id}</div>
        </div>
      </div>
      <div class="detail-stack">
        <div class="panel">
          <div class="detail-title">${item.title}</div>
          <div class="detail-subtitle">Original: ${item.original_title}</div>
          <div class="facts-grid">
            <div><span class="muted">Første release:</span> ${item.first_release}</div>
            <div><span class="muted">Runtime:</span> ${item.runtime_txt}</div>
            <div><span class="muted">Aldersgrense:</span> ${item.age_restriction}</div>
            <div><span class="muted">Sett:</span> ${item.watched_flag ? "Ja" : "Nei"}</div>
          </div>
          <div class="detail-subtitle">${item.summary}</div>
        </div>
        <div class="panel">
          <div class="section-title">Utgaver du eier</div>
          <div class="item-list">
            ${item.collections.map((c) => `
              <div class="list-item">
                <div class="list-item-head">
                  <div class="list-item-title">${c.label}</div>
                  <div class="muted">${c.format}</div>
                </div>
                <div class="meta-line">
                  <span>barcode: ${c.barcode || "-"}</span>
                  <span>box_set_barcode: ${c.box_set_barcode || "-"}</span>
                </div>
              </div>
            `).join("")}
          </div>
        </div>
        <div class="panel">
          <div class="section-title">Eksterne kilder</div>
          <div class="item-list">
            ${item.sources.map((source) => `
              <div class="list-item">
                <div class="list-item-head">
                  <div class="list-item-title">${source.source}</div>
                  <div class="muted">${source.fetched_at}</div>
                </div>
                <div class="meta-line"><span>external_id: ${source.external_id}</span></div>
              </div>
            `).join("")}
          </div>
        </div>
      </div>
    </div>
  `;
}

function render() {
  const filtered = filterItems();
  renderGrid(filtered);
  renderDetail(getSelected(filtered));
}

searchInput.addEventListener("input", (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
typeFilter.addEventListener("change", (event) => { state.type = event.target.value; render(); });
watchedFilter.addEventListener("change", (event) => { state.unwatched = event.target.checked; render(); });

render();

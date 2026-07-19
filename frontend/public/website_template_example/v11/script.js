const items = [
  {
    content_id: "f65e6f10-7c4e-4db4-9108-001",
    title: "The Lord of the Rings: The Fellowship of the Ring",
    original_title: "The Lord of the Rings: The Fellowship of the Ring",
    content_type: "movie",
    first_release: "2001-12-19",
    runtime: 178,
    runtime_txt: "178 min / 228 min (Extended)",
    age_restriction: "12",
    watched_flag: true,
    temporary_flag: false,
    cover_image: null,
    tagline: "Ett verk, flere fysiske utgaver og full sporbarhet ned til bonusinnhold på hver disk.",
    summary: "Denne varianten bruker TMDB-lignende toppflate for selve tittelen, men lar den underliggende databasestrukturen styre detaljene lenger ned. Her kan én content-rad ha flere fysiske utgaver, flere kopier per utgave, disker knyttet til hver kopi og bonusinnhold per disk.",
    palette: ["#325d8f", "#142033"],
    sources: [
      { source: "tmdb", external_id: "120", fetched_at: "2026-06-18 21:14", note: "Basisdata og releaseinformasjon." },
      { source: "invelos", external_id: "794043785727", fetched_at: "2026-06-18 21:19", note: "Produktdata for fysisk utgave." }
    ],
    collections: [
      {
        collection_id: "c-box-lotr",
        title: "Extended Edition Trilogy Box",
        format: "Blu-ray",
        barcode: "5051895415429",
        box_set_barcode: "5051895415429",
        box_set_title_sort: 1,
        copies: [
          { copy_id: 1, label: "Eksemplar 1", note: "Hovedeksemplar i stue." }
        ],
        discs: [
          {
            disc_id: "disc-lotr-1",
            type_disc: "Blu-ray",
            format: "1080p",
            box_set_disc_order: 1,
            related_content_id: null,
            storage: [{ storage_id: "shelf-a", label: "Hylle A", number_in_storage: 11, max_capacity: 40 }],
            bonus_items: [
              { seq_no: 1, title: "Hovedfilm del 1", item_type: "movie", runtime_seconds: 5340, notes: "Kommentarspor tilgjengelig." }
            ]
          },
          {
            disc_id: "disc-lotr-2",
            type_disc: "Blu-ray",
            format: "1080p",
            box_set_disc_order: 2,
            related_content_id: null,
            storage: [{ storage_id: "shelf-a", label: "Hylle A", number_in_storage: 12, max_capacity: 40 }],
            bonus_items: [
              { seq_no: 1, title: "Hovedfilm del 2", item_type: "movie", runtime_seconds: 4860, notes: "Extended cut fortsetter her." },
              { seq_no: 2, title: "Behind the Scenes", item_type: "bonus", runtime_seconds: 4200, notes: "Making-of og designgalleri." }
            ]
          }
        ]
      },
      {
        collection_id: "c-4k-lotr",
        title: "4K Steelbook",
        format: "4K UHD",
        barcode: "7333018023894",
        box_set_barcode: null,
        box_set_title_sort: 2,
        copies: [
          { copy_id: 1, label: "Eksemplar 1", note: "Kun film, ingen bonusdisk." }
        ],
        discs: [
          {
            disc_id: "disc-lotr-4k-1",
            type_disc: "UHD",
            format: "2160p HDR",
            box_set_disc_order: 1,
            related_content_id: null,
            storage: [{ storage_id: "drawer-2", label: "Skuff 2", number_in_storage: 4, max_capacity: 18 }],
            bonus_items: [
              { seq_no: 1, title: "Feature Presentation", item_type: "movie", runtime_seconds: 10680, notes: "Kinoversjon." }
            ]
          }
        ]
      }
    ]
  },
  {
    content_id: "a14bbabc-7f9c-438e-8a32-002",
    title: "Blade Runner",
    original_title: "Blade Runner",
    content_type: "movie",
    first_release: "1982-06-25",
    runtime: 117,
    runtime_txt: "117 min",
    age_restriction: "15",
    watched_flag: false,
    temporary_flag: false,
    cover_image: null,
    tagline: "En rolig detaljside fungerer godt også når samlingsdataene er korte og presise.",
    summary: "Her vises en tittel med én hovedutgave, to kopier og separate disker med både film og dokumentar. Dette gir en god mal for titler som er enklere enn bokssett, men fortsatt trenger mer enn bare én rad metadata.",
    palette: ["#805045", "#1d1421"],
    sources: [
      { source: "tmdb", external_id: "78", fetched_at: "2026-06-16 19:48", note: "Tittel, plakatreferanse og premieredato." },
      { source: "invelos", external_id: "085391163420", fetched_at: "2026-06-16 19:52", note: "Barcode og utgaveinformasjon." }
    ],
    collections: [
      {
        collection_id: "c-br-final",
        title: "Final Cut Collector's Edition",
        format: "Blu-ray",
        barcode: "085391163420",
        box_set_barcode: "085391163420",
        box_set_title_sort: 1,
        copies: [
          { copy_id: 1, label: "Eksemplar 1", note: "Komplett og i god stand." },
          { copy_id: 2, label: "Eksemplar 2", note: "Reservekopi i bod." }
        ],
        discs: [
          {
            disc_id: "disc-br-1",
            type_disc: "Blu-ray",
            format: "1080p",
            box_set_disc_order: 1,
            related_content_id: null,
            storage: [{ storage_id: "shelf-b", label: "Hylle B", number_in_storage: 7, max_capacity: 36 }],
            bonus_items: [
              { seq_no: 1, title: "Final Cut", item_type: "movie", runtime_seconds: 7020, notes: "Hovedfilmen." }
            ]
          },
          {
            disc_id: "disc-br-2",
            type_disc: "Blu-ray",
            format: "1080p",
            box_set_disc_order: 2,
            related_content_id: null,
            storage: [{ storage_id: "archive-1", label: "Arkivboks 1", number_in_storage: 2, max_capacity: 12 }],
            bonus_items: [
              { seq_no: 1, title: "Dangerous Days", item_type: "documentary", runtime_seconds: 12840, notes: "Langdokumentar om produksjonen." },
              { seq_no: 2, title: "Deleted Scenes", item_type: "bonus", runtime_seconds: 900, notes: "Korte arkivklipp." }
            ]
          }
        ]
      }
    ]
  },
  {
    content_id: "84ff6211-e7a7-4af3-9ddb-003",
    title: "Hans Zimmer: Live in Prague",
    original_title: "Hans Zimmer: Live in Prague",
    content_type: "concert",
    first_release: "2017-11-03",
    runtime: 138,
    runtime_txt: "138 min",
    age_restriction: "A",
    watched_flag: true,
    temporary_flag: true,
    cover_image: null,
    tagline: "Samme oppsett tåler andre typer innhold så lenge content ligger i sentrum og samlingen er sekundær.",
    summary: "Konsertutgivelser og midlertidige metadata kan bruke samme detaljside. Her blir det tydelig hvordan watched_flag, temporary_flag, eksterne kilder og lagringsplass kan vises uten at UI-et kollapser til en ren adminvisning.",
    palette: ["#644db2", "#16142c"],
    sources: [
      { source: "tvdb", external_id: "341794", fetched_at: "2026-06-14 13:02", note: "Foreløpig metadata, trenger verifisering." }
    ],
    collections: [
      {
        collection_id: "c-zimmer",
        title: "Standard Blu-ray",
        format: "Blu-ray",
        barcode: "5051300533472",
        box_set_barcode: null,
        box_set_title_sort: 1,
        copies: [
          { copy_id: 1, label: "Eksemplar 1", note: "Konsertseksjonen." }
        ],
        discs: [
          {
            disc_id: "disc-zimmer-1",
            type_disc: "Blu-ray",
            format: "1080p DTS-HD MA",
            box_set_disc_order: 1,
            related_content_id: null,
            storage: [{ storage_id: "concert-rack", label: "Konsert-reol", number_in_storage: 3, max_capacity: 20 }],
            bonus_items: [
              { seq_no: 1, title: "Live in Prague", item_type: "concert", runtime_seconds: 8280, notes: "Hovedkonsert." },
              { seq_no: 2, title: "Backstage Featurette", item_type: "bonus", runtime_seconds: 480, notes: "Kort bonusspor." }
            ]
          }
        ]
      }
    ]
  }
];

const state = {
  q: "",
  type: "",
  selectedId: items[0].content_id
};

const heroSection = document.getElementById("heroSection");
const cardRail = document.getElementById("cardRail");
const resultCount = document.getElementById("resultCount");
const mainColumn = document.getElementById("mainColumn");
const sideColumn = document.getElementById("sideColumn");

function label(type) {
  return { movie: "Film", series: "Serie", concert: "Konsert" }[type] || type;
}

function formatDate(date) {
  return new Intl.DateTimeFormat("no-NO", { day: "numeric", month: "short", year: "numeric" }).format(new Date(date));
}

function runtimeLabel(seconds) {
  const minutes = Math.round(seconds / 60);
  return `${minutes} min`;
}

function totals(item) {
  const copies = item.collections.reduce((sum, collection) => sum + collection.copies.length, 0);
  const discs = item.collections.reduce((sum, collection) => sum + collection.discs.length, 0);
  const bonusItems = item.collections.reduce(
    (sum, collection) => sum + collection.discs.reduce((discSum, disc) => discSum + disc.bonus_items.length, 0),
    0
  );

  return {
    collections: item.collections.length,
    copies,
    discs,
    bonusItems,
    sources: item.sources.length
  };
}

function flattenedStorage(item) {
  const entries = [];

  item.collections.forEach((collection) => {
    collection.discs.forEach((disc) => {
      disc.storage.forEach((storage) => {
        entries.push({
          label: storage.label,
          number_in_storage: storage.number_in_storage,
          max_capacity: storage.max_capacity,
          discLabel: `${collection.title} · disk ${disc.box_set_disc_order}`
        });
      });
    });
  });

  return entries;
}

function matches(item) {
  if (state.type && item.content_type !== state.type) return false;
  if (!state.q) return true;

  const haystack = [
    item.title,
    item.original_title,
    ...item.sources.flatMap((source) => [source.source, source.external_id]),
    ...item.collections.flatMap((collection) => [
      collection.title,
      collection.barcode,
      collection.box_set_barcode ?? "",
      ...collection.discs.flatMap((disc) => [
        disc.disc_id,
        ...disc.storage.map((storage) => storage.label),
        ...disc.bonus_items.map((bonus) => bonus.title)
      ])
    ])
  ].join(" ").toLowerCase();

  return haystack.includes(state.q);
}

function filteredItems() {
  return items.filter(matches);
}

function selectedItem(filtered) {
  const found = filtered.find((item) => item.content_id === state.selectedId);
  if (found) return found;
  state.selectedId = filtered[0]?.content_id ?? null;
  return filtered[0] ?? null;
}

function renderHero(item) {
  if (!item) {
    heroSection.innerHTML = '<div class="hero-surface"><div class="empty-state">Ingen titler matcher søket.</div></div>';
    return;
  }

  const stats = totals(item);
  heroSection.innerHTML = `
    <article class="hero-surface" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="hero-content">
        <div class="poster-card">
          <div class="poster-top">
            <span class="poster-type">${label(item.content_type)}</span>
            <span class="poster-mark">${item.first_release.slice(0, 4)}</span>
          </div>
          <div>
            <div class="poster-title">${item.title}</div>
            <p class="poster-caption">${stats.collections} utgave${stats.collections > 1 ? "r" : ""} · ${stats.discs} disk${stats.discs > 1 ? "er" : ""}</p>
          </div>
        </div>

        <div class="hero-copy">
          <p class="eyebrow">Content</p>
          <h2>${item.title}</h2>
          <p class="subtitle">${item.original_title}</p>
          <p class="tagline">${item.tagline}</p>

          <div class="hero-meta">
            <span class="pill">${formatDate(item.first_release)}</span>
            <span class="pill">${item.runtime_txt}</span>
            <span class="pill">${item.age_restriction} år</span>
            <span class="pill ${item.watched_flag ? "good" : "warn"}">${item.watched_flag ? "Sett" : "Ikke sett"}</span>
            ${item.temporary_flag ? '<span class="pill danger">Midlertidig metadata</span>' : ""}
          </div>

          <p class="summary">${item.summary}</p>

          <div class="hero-stats">
            <div class="stat-chip"><strong>${stats.collections}</strong><span>utgaver</span></div>
            <div class="stat-chip"><strong>${stats.copies}</strong><span>kopier</span></div>
            <div class="stat-chip"><strong>${stats.discs}</strong><span>disker</span></div>
            <div class="stat-chip"><strong>${stats.sources}</strong><span>kilder</span></div>
          </div>
        </div>
      </div>
    </article>
  `;
}

function renderRail(filtered) {
  resultCount.textContent = String(filtered.length);

  if (!filtered.length) {
    cardRail.innerHTML = '<div class="empty-state">Prøv et annet søk eller filtrer på en annen type.</div>';
    return;
  }

  cardRail.innerHTML = filtered.map((item) => {
    const stats = totals(item);
    return `
      <button class="rail-card ${item.content_id === state.selectedId ? "is-active" : ""}" data-id="${item.content_id}">
        <div class="rail-visual" style="--card-start:${item.palette[0]}; --card-end:${item.palette[1]};">
          <span class="pill">${label(item.content_type)}</span>
          <div class="rail-title">${item.title}</div>
        </div>
        <div class="rail-body">
          <div class="pill-row">
            <span class="pill">${item.first_release.slice(0, 4)}</span>
            <span class="pill">${stats.collections} utgave${stats.collections > 1 ? "r" : ""}</span>
          </div>
          <p>${item.tagline}</p>
        </div>
      </button>
    `;
  }).join("");

  cardRail.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => {
      state.selectedId = button.dataset.id;
      render();
    });
  });
}

function renderOverview(item) {
  const stats = totals(item);

  mainColumn.innerHTML = `
    <article class="section-card">
      <p class="eyebrow">Oversikt</p>
      <h3>Datamodell omsatt til detaljside</h3>
      <p>Heroen viser content-feltene først. Resten av modellen brytes ned i egne seksjoner under, slik at siden føles som en filmside og ikke en databaseadmin-side.</p>

      <dl class="fact-grid">
        <div class="fact-item"><dt>content_id</dt><dd>${item.content_id}</dd></div>
        <div class="fact-item"><dt>content_type</dt><dd>${label(item.content_type)}</dd></div>
        <div class="fact-item"><dt>first_release</dt><dd>${formatDate(item.first_release)}</dd></div>
        <div class="fact-item"><dt>runtime</dt><dd>${item.runtime} min</dd></div>
        <div class="fact-item"><dt>watched_flag</dt><dd>${item.watched_flag ? "1 / sett" : "0 / ikke sett"}</dd></div>
        <div class="fact-item"><dt>temporary_flag</dt><dd>${item.temporary_flag ? "1 / midlertidig" : "0 / endelig"}</dd></div>
        <div class="fact-item"><dt>physical_collection</dt><dd>${stats.collections} rad${stats.collections > 1 ? "er" : ""}</dd></div>
        <div class="fact-item"><dt>content_external_source</dt><dd>${stats.sources} kobling${stats.sources > 1 ? "er" : ""}</dd></div>
      </dl>
    </article>
  `;
}

function renderCollections(item) {
  const collectionsMarkup = item.collections.map((collection) => `
    <article class="collection-card">
      <div class="collection-head">
        <div>
          <h4>${collection.title}</h4>
          <p>${collection.format} · barcode ${collection.barcode}</p>
        </div>
        <span class="pill">sort ${collection.box_set_title_sort}</span>
      </div>

      <div class="collection-meta">
        <span class="pill">${collection.copies.length} kopi${collection.copies.length > 1 ? "er" : ""}</span>
        <span class="pill">${collection.discs.length} disk${collection.discs.length > 1 ? "er" : ""}</span>
        ${collection.box_set_barcode ? `<span class="pill">box set ${collection.box_set_barcode}</span>` : ""}
      </div>

      <div class="copy-list">
        ${collection.copies.map((copy) => `<span class="copy-chip">copy_id ${copy.copy_id} · ${copy.label}</span>`).join("")}
      </div>
      <p class="copy-meta">${collection.copies.map((copy) => copy.note).join(" · ")}</p>
    </article>
  `).join("");

  mainColumn.insertAdjacentHTML("beforeend", `
    <article class="section-card">
      <p class="eyebrow">Din samling</p>
      <h3>Utgaver og kopier</h3>
      <p>Dette tilsvarer content_in_physical_collection, physical_collection og physical_copy. Siden holder fokus på hvilke utgaver du faktisk eier, uten å vise rå primærnøkler overalt.</p>
      <div class="section-stack">${collectionsMarkup}</div>
    </article>
  `);
}

function renderDiscs(item) {
  const discMarkup = item.collections.map((collection) => `
    <div>
      <h4>${collection.title}</h4>
      <div class="section-stack">
        ${collection.discs.map((disc) => `
          <article class="disc-card">
            <div class="disc-head">
              <div>
                <h4>Disk ${disc.box_set_disc_order}</h4>
                <p>${disc.type_disc} · ${disc.format}</p>
              </div>
              <span class="pill">${disc.bonus_items.length} spor</span>
            </div>

            <div class="disc-meta">
              <span class="pill">disc_id ${disc.disc_id}</span>
              ${disc.related_content_id ? `<span class="pill">related ${disc.related_content_id}</span>` : ""}
            </div>

            <div class="bonus-list">
              ${disc.bonus_items.map((bonus) => `
                <div class="bonus-row">
                  <strong>${bonus.seq_no}. ${bonus.title}</strong>
                  <p>${bonus.item_type} · ${runtimeLabel(bonus.runtime_seconds)}</p>
                  <p>${bonus.notes}</p>
                </div>
              `).join("")}
            </div>
          </article>
        `).join("")}
      </div>
    </div>
  `).join("");

  mainColumn.insertAdjacentHTML("beforeend", `
    <article class="section-card">
      <p class="eyebrow">Disknivå</p>
      <h3>Disker og bonusinnhold</h3>
      <p>disc_in og disc_bonus_item får egne kort, slik at du kan vise både avspillingsformat, rekkefølge i bokssett og hvilket ekstramateriale som faktisk ligger på hver disk.</p>
      <div class="section-stack">${discMarkup}</div>
    </article>
  `);
}

function renderSidebar(item) {
  const stats = totals(item);
  const storageMarkup = flattenedStorage(item).map((entry) => `
    <article class="storage-row">
      <h4>${entry.label}</h4>
      <p>Posisjon ${entry.number_in_storage} / kapasitet ${entry.max_capacity}</p>
      <p>${entry.discLabel}</p>
    </article>
  `).join("");

  const sourceMarkup = item.sources.map((source) => `
    <article class="source-card">
      <h4>${source.source}</h4>
      <p>external_id: ${source.external_id}</p>
      <p>Hentet: ${source.fetched_at}</p>
      <p>${source.note}</p>
    </article>
  `).join("");

  sideColumn.innerHTML = `
    <article class="sidebar-card">
      <p class="eyebrow">Status</p>
      <h3>Kjappe tall</h3>
      <div class="sidebar-stats">
        <div class="stat-chip"><strong>${stats.copies}</strong><span>kopier</span></div>
        <div class="stat-chip"><strong>${stats.discs}</strong><span>disker</span></div>
        <div class="stat-chip"><strong>${stats.bonusItems}</strong><span>bonusspor</span></div>
      </div>
    </article>

    <article class="sidebar-card">
      <p class="eyebrow">Lagring</p>
      <h3>Hvor diskene ligger</h3>
      <p>Et eget sidepanel fungerer godt for disc_in_storage og storage, fordi denne informasjonen er nyttig, men sjelden hovedpoenget med siden.</p>
      <div class="section-stack">${storageMarkup}</div>
    </article>

    <article class="sidebar-card">
      <p class="eyebrow">Kilder</p>
      <h3>Eksterne ID-er og sync</h3>
      <p>content_external_source kan vises som små, selvstendige kort med external_id, sist hentet tidspunkt og kort beskrivelse av hva kilden brukes til.</p>
      <div class="section-stack">${sourceMarkup}</div>
    </article>
  `;
}

function render() {
  const filtered = filteredItems();
  const current = selectedItem(filtered);

  renderHero(current);
  renderRail(filtered);

  if (!current) {
    mainColumn.innerHTML = "";
    sideColumn.innerHTML = "";
    return;
  }

  renderOverview(current);
  renderCollections(current);
  renderDiscs(current);
  renderSidebar(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => {
  state.q = event.target.value.trim().toLowerCase();
  render();
});

document.getElementById("typeFilter").addEventListener("change", (event) => {
  state.type = event.target.value;
  render();
});

render();

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
    temporary_flag: false,
    tagline: "Ett verk i sentrum, mens samlingsdataene får bo i sin egen fane.",
    summary: "Denne varianten holder hovedflaten tett på en klassisk filmdetaljside: stor hero, synopsis, nøkkelfakta og kilder. Opplysninger om egne eksemplarer og digitale utgaver er flyttet ut i en egen fane, slik at siden ikke føles som en katalog på førsteblikk.",
    palette: ["#325d8f", "#142033"],
    genres: ["Eventyr", "Fantasy"],
    countries: ["New Zealand", "USA"],
    production: ["WingNut Films", "New Line Cinema"],
    sources: [
      { source: "tmdb", external_id: "120", note: "Basisdata, synopsis og premieredato." },
      { source: "invelos", external_id: "794043785727", note: "Produktdata for fysisk utgave." }
    ],
    cast: [
      "Elijah Wood som Frodo",
      "Ian McKellen som Gandalf",
      "Viggo Mortensen som Aragorn"
    ],
    owned: {
      physical: [
        {
          title: "Extended Edition Trilogy Box",
          format: "Blu-ray",
          barcode: "5051895415429",
          copies: [
            { label: "Eksemplar 1", location: "Hylle A", note: "Komplett bokssett." }
          ]
        },
        {
          title: "4K Steelbook",
          format: "4K UHD",
          barcode: "7333018023894",
          copies: [
            { label: "Eksemplar 1", location: "Skuff 2", note: "Kinoversjon i steelbook." }
          ]
        }
      ],
      digital: [
        { service: "Apple TV", quality: "4K Dolby Vision", ownership: "Kjøpt", note: "Digital kopi knyttet til privat bibliotek." },
        { service: "Plex", quality: "1080p", ownership: "Rippet fra egen disk", note: "Lokal privat streaming i hjemmenettverk." }
      ]
    }
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
    tagline: "Oversikt først, samling etterpå.",
    summary: "Her er detaljsiden holdt rolig og fokusert på filmdata. Samlingsfanen viser både fysisk eide utgaver og digitale steder du faktisk har filmen tilgjengelig.",
    palette: ["#805045", "#1d1421"],
    genres: ["Science fiction", "Thriller"],
    countries: ["USA"],
    production: ["The Ladd Company", "Warner Bros."],
    sources: [
      { source: "tmdb", external_id: "78", note: "Tittel, backdrop og premieredato." },
      { source: "invelos", external_id: "085391163420", note: "Barcode og fysisk produkt." }
    ],
    cast: [
      "Harrison Ford som Deckard",
      "Rutger Hauer som Roy Batty",
      "Sean Young som Rachael"
    ],
    owned: {
      physical: [
        {
          title: "Final Cut Collector's Edition",
          format: "Blu-ray",
          barcode: "085391163420",
          copies: [
            { label: "Eksemplar 1", location: "Hylle B", note: "Hovedeksemplar." },
            { label: "Eksemplar 2", location: "Arkivboks 1", note: "Reservekopi." }
          ]
        }
      ],
      digital: [
        { service: "Apple TV", quality: "HD", ownership: "Kjøpt", note: "Final Cut." }
      ]
    }
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
    tagline: "Samme struktur fungerer også for konserter og andre typer innhold.",
    summary: "Denne posten viser at samme detaljside kan brukes også når metadata fortsatt er midlertidige. Hovedflaten viser innholdet, mens samlingsfanen holder orden på fysisk og digital tilgjengelighet.",
    palette: ["#644db2", "#16142c"],
    genres: ["Konsert", "Musikk"],
    countries: ["Tsjekkia"],
    production: ["Eagle Rock Entertainment"],
    sources: [
      { source: "tvdb", external_id: "341794", note: "Foreløpig kilde, trenger verifisering." }
    ],
    cast: [
      "Hans Zimmer",
      "Johnny Marr",
      "Tina Guo"
    ],
    owned: {
      physical: [
        {
          title: "Standard Blu-ray",
          format: "Blu-ray",
          barcode: "5051300533472",
          copies: [
            { label: "Eksemplar 1", location: "Konsert-reol", note: "Standardutgave." }
          ]
        }
      ],
      digital: [
        { service: "Plex", quality: "1080p", ownership: "Rippet fra egen disk", note: "Tilgjengelig i privat bibliotek." }
      ]
    }
  }
];

const state = {
  q: "",
  selectedId: items[0].content_id,
  tab: "overview"
};

const heroSection = document.getElementById("heroSection");
const searchPanel = document.getElementById("searchPanel");
const tabBar = document.getElementById("tabBar");
const mainContent = document.getElementById("mainContent");
const sideContent = document.getElementById("sideContent");

function label(type) {
  return { movie: "Film", series: "Serie", concert: "Konsert" }[type] || type;
}

function formatDate(date) {
  return new Intl.DateTimeFormat("no-NO", { day: "numeric", month: "short", year: "numeric" }).format(new Date(date));
}

function totals(item) {
  const physicalEditions = item.owned.physical.length;
  const physicalCopies = item.owned.physical.reduce((sum, edition) => sum + edition.copies.length, 0);
  const digitalCopies = item.owned.digital.length;

  return { physicalEditions, physicalCopies, digitalCopies };
}

function filteredItems() {
  if (!state.q) return [];

  return items.filter((item) => {
    const haystack = [
      item.title,
      item.original_title,
      ...item.sources.flatMap((source) => [source.source, source.external_id]),
      ...item.owned.physical.flatMap((edition) => [edition.title, edition.barcode, ...edition.copies.map((copy) => copy.location)]),
      ...item.owned.digital.flatMap((entry) => [entry.service, entry.quality, entry.ownership])
    ].join(" ").toLowerCase();

    return haystack.includes(state.q);
  });
}

function selectedItem() {
  return items.find((item) => item.content_id === state.selectedId) ?? items[0];
}

function renderSearchResults(results) {
  if (!state.q) {
    searchPanel.classList.add("is-hidden");
    searchPanel.innerHTML = "";
    return;
  }

  searchPanel.classList.remove("is-hidden");

  if (!results.length) {
    searchPanel.innerHTML = `
      <div class="search-head">
        <div>
          <p class="eyebrow">Søkeresultat</p>
          <h2>Ingen treff</h2>
        </div>
      </div>
      <div class="empty-state">Prøv et annet søk.</div>
    `;
    return;
  }

  searchPanel.innerHTML = `
    <div class="search-head">
      <div>
        <p class="eyebrow">Søkeresultat</p>
        <h2>Velg tittel</h2>
      </div>
      <div class="result-chip">${results.length} treff</div>
    </div>
    <div class="search-result-list">
      ${results.map((item) => `
        <button class="search-result ${item.content_id === state.selectedId ? "is-active" : ""}" data-id="${item.content_id}">
          <div class="meta-row">
            <span class="pill">${label(item.content_type)}</span>
            <span class="pill">${item.first_release.slice(0, 4)}</span>
          </div>
          <h3>${item.title}</h3>
          <p>${item.tagline}</p>
        </button>
      `).join("")}
    </div>
  `;

  searchPanel.querySelectorAll("[data-id]").forEach((button) => {
    button.addEventListener("click", () => {
      state.selectedId = button.dataset.id;
      render();
    });
  });
}

function renderHero(item) {
  const stats = totals(item);

  heroSection.innerHTML = `
    <article class="hero-surface" style="--hero-start:${item.palette[0]}; --hero-end:${item.palette[1]};">
      <div class="hero-content">
        <div class="poster-card">
          <div class="poster-top">
            <span class="pill">${label(item.content_type)}</span>
            <span class="pill">${item.first_release.slice(0, 4)}</span>
          </div>
          <div>
            <div class="poster-title">${item.title}</div>
            <p class="poster-caption">${item.genres.join(" · ")}</p>
          </div>
        </div>

        <div class="hero-copy">
          <p class="eyebrow">Detaljside</p>
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
            <div class="stat-chip"><strong>${stats.physicalEditions}</strong><span>utgaver</span></div>
            <div class="stat-chip"><strong>${stats.physicalCopies}</strong><span>eksemplarer</span></div>
            <div class="stat-chip"><strong>${stats.digitalCopies}</strong><span>digitale</span></div>
            <div class="stat-chip"><strong>${item.sources.length}</strong><span>kilder</span></div>
          </div>
        </div>
      </div>
    </article>
  `;
}

function renderTabs() {
  const tabs = [
    { id: "overview", label: "Oversikt" },
    { id: "collection", label: "Min samling" }
  ];

  tabBar.innerHTML = tabs.map((tab) => `
    <button class="tab-button ${state.tab === tab.id ? "is-active" : ""}" data-tab="${tab.id}">
      ${tab.label}
    </button>
  `).join("");

  tabBar.querySelectorAll("[data-tab]").forEach((button) => {
    button.addEventListener("click", () => {
      state.tab = button.dataset.tab;
      renderContent(selectedItem());
      renderTabs();
    });
  });
}

function renderOverview(item) {
  mainContent.innerHTML = `
    <article class="main-card">
      <p class="eyebrow">Om tittelen</p>
      <h3>Filmfakta</h3>
      <p>Dette området holder seg tett på en vanlig filmdetaljside og unngår informasjon om hvilke kopier du eier.</p>
      <dl class="fact-grid">
        <div class="fact-item"><dt>content_id</dt><dd>${item.content_id}</dd></div>
        <div class="fact-item"><dt>type</dt><dd>${label(item.content_type)}</dd></div>
        <div class="fact-item"><dt>premiere</dt><dd>${formatDate(item.first_release)}</dd></div>
        <div class="fact-item"><dt>aldersgrense</dt><dd>${item.age_restriction} år</dd></div>
        <div class="fact-item"><dt>sjangre</dt><dd>${item.genres.join(", ")}</dd></div>
        <div class="fact-item"><dt>produksjon</dt><dd>${item.production.join(", ")}</dd></div>
      </dl>
    </article>

    <article class="main-card">
      <p class="eyebrow">Synopsis</p>
      <h3>Beskrivelse</h3>
      <p>${item.summary}</p>
    </article>

    <article class="main-card">
      <p class="eyebrow">Medvirkende</p>
      <h3>Eksempelinnhold</h3>
      <div class="stack">
        ${item.cast.map((entry) => `
          <div class="media-row">
            <h4>${entry}</h4>
            <p>Plassholder for cast-data fra API senere.</p>
          </div>
        `).join("")}
      </div>
    </article>
  `;

  sideContent.innerHTML = `
    <article class="side-card">
      <p class="eyebrow">Status</p>
      <h3>Rask oversikt</h3>
      <div class="stack">
        <div class="media-row"><h4>Watched flag</h4><p>${item.watched_flag ? "Sett" : "Ikke sett"}</p></div>
        <div class="media-row"><h4>Metadata-status</h4><p>${item.temporary_flag ? "Midlertidig" : "Ferdig"}</p></div>
        <div class="media-row"><h4>Land</h4><p>${item.countries.join(", ")}</p></div>
      </div>
    </article>

    <article class="side-card">
      <p class="eyebrow">Kilder</p>
      <h3>Eksterne koblinger</h3>
      <div class="stack">
        ${item.sources.map((source) => `
          <div class="source-card">
            <h4>${source.source}</h4>
            <div class="source-meta">
              <span class="pill">external_id ${source.external_id}</span>
            </div>
            <p>${source.note}</p>
          </div>
        `).join("")}
      </div>
    </article>
  `;
}

function renderCollection(item) {
  mainContent.innerHTML = `
    <article class="main-card">
      <p class="eyebrow">Fysiske eksemplarer</p>
      <h3>Det du eier på disk</h3>
      <p>Denne fanen samler fysisk eide utgaver og eksemplarer i én visning, uten å ta plass på hoveddetaljsiden.</p>
      <div class="stack">
        ${item.owned.physical.map((edition) => `
          <div class="ownership-card">
            <h4>${edition.title}</h4>
            <div class="ownership-meta">
              <span class="pill">${edition.format}</span>
              <span class="pill">barcode ${edition.barcode}</span>
              <span class="pill">${edition.copies.length} eksemplar${edition.copies.length > 1 ? "er" : ""}</span>
            </div>
            <div class="stack">
              ${edition.copies.map((copy) => `
                <div class="media-row">
                  <h4>${copy.label}</h4>
                  <p>${copy.location}</p>
                  <p>${copy.note}</p>
                </div>
              `).join("")}
            </div>
          </div>
        `).join("")}
      </div>
    </article>
  `;

  sideContent.innerHTML = `
    <article class="side-card">
      <p class="eyebrow">Digitale utgaver</p>
      <h3>Det du har tilgjengelig digitalt</h3>
      <div class="stack">
        ${item.owned.digital.map((entry) => `
          <div class="availability-card">
            <h4>${entry.service}</h4>
            <div class="availability-meta">
              <span class="pill">${entry.quality}</span>
              <span class="pill">${entry.ownership}</span>
            </div>
            <p>${entry.note}</p>
          </div>
        `).join("")}
      </div>
    </article>

    <article class="side-card">
      <p class="eyebrow">Hvorfor egen fane</p>
      <h3>UI-tanke</h3>
      <div class="stack">
        <div class="media-row">
          <h4>Hovedsiden føles mer som TMDB</h4>
          <p>Synopsis, metadata og kilder får være i fokus uten at samlingsdata tar over.</p>
        </div>
        <div class="media-row">
          <h4>Samlingsdata er fortsatt nær</h4>
          <p>Du bytter bare fane når du vil se hva du eier fysisk eller digitalt.</p>
        </div>
      </div>
    </article>
  `;
}

function renderContent(item) {
  if (state.tab === "collection") {
    renderCollection(item);
    return;
  }

  renderOverview(item);
}

function render() {
  const results = filteredItems();
  const current = selectedItem();

  renderSearchResults(results);
  renderHero(current);
  renderTabs();
  renderContent(current);
}

document.getElementById("searchInput").addEventListener("input", (event) => {
  state.q = event.target.value.trim().toLowerCase();
  const results = filteredItems();
  if (results.length) {
    state.selectedId = results[0].content_id;
  }
  render();
});

render();

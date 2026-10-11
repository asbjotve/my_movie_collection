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
    tagline: "Filmen i fokus, med roller og samling i egne lag under samme detaljside.",
    summary: "Denne varianten deler innholdet i tre tydelige faner. Første fane viser rene filmdetaljer, andre fane samler roller og besetning, og tredje fane samler det du eier fysisk eller har digitalt tilgjengelig.",
    palette: ["#325d8f", "#142033"],
    genres: ["Eventyr", "Fantasy"],
    countries: ["New Zealand", "USA"],
    production: ["WingNut Films", "New Line Cinema"],
    director: "Peter Jackson",
    writer: "Fran Walsh, Philippa Boyens, Peter Jackson",
    sources: [
      { source: "tmdb", external_id: "120", note: "Basisdata, synopsis og premieredato." },
      { source: "invelos", external_id: "794043785727", note: "Produktdata for fysisk utgave." }
    ],
    credits: {
      cast: [
        { name: "Elijah Wood", role: "Frodo", note: "Hovedrolle" },
        { name: "Ian McKellen", role: "Gandalf", note: "Støttende nøkkelrolle" },
        { name: "Viggo Mortensen", role: "Aragorn", note: "Del av ringens brorskap" },
        { name: "Sean Astin", role: "Samwise", note: "Nærmeste følgesvenn" }
      ],
      crew: [
        { name: "Peter Jackson", role: "Regissør", note: "Overordnet kreativ ledelse" },
        { name: "Andrew Lesnie", role: "Foto", note: "Visuell stil og lyssetting" },
        { name: "Howard Shore", role: "Musikk", note: "Original score" },
        { name: "Barrie M. Osborne", role: "Produsent", note: "Produksjonsansvar" }
      ]
    },
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
    tagline: "Tre faner gir tydelig skille mellom verket, menneskene bak og samlingen din.",
    summary: "Her er detaljsiden holdt filmorientert i første fane. Roller og besetning ligger i en egen seksjon, og samlingsfanen viser hvilke utgaver du faktisk har liggende fysisk eller digitalt.",
    palette: ["#805045", "#1d1421"],
    genres: ["Science fiction", "Thriller"],
    countries: ["USA"],
    production: ["The Ladd Company", "Warner Bros."],
    director: "Ridley Scott",
    writer: "Hampton Fancher, David Webb Peoples",
    sources: [
      { source: "tmdb", external_id: "78", note: "Tittel, backdrop og premieredato." },
      { source: "invelos", external_id: "085391163420", note: "Barcode og fysisk produkt." }
    ],
    credits: {
      cast: [
        { name: "Harrison Ford", role: "Deckard", note: "Hovedrolle" },
        { name: "Rutger Hauer", role: "Roy Batty", note: "Antagonist" },
        { name: "Sean Young", role: "Rachael", note: "Sentral birolle" },
        { name: "Edward James Olmos", role: "Gaff", note: "Tilbakevendende biperson" }
      ],
      crew: [
        { name: "Ridley Scott", role: "Regissør", note: "Overordnet visuell retning" },
        { name: "Jordan Cronenweth", role: "Foto", note: "Neo-noir uttrykk" },
        { name: "Vangelis", role: "Musikk", note: "Original score" },
        { name: "Michael Deeley", role: "Produsent", note: "Produksjonsansvar" }
      ]
    },
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
    tagline: "Samme løsning fungerer også når innholdet ikke er en vanlig spillefilm.",
    summary: "Også konserter kan få en tydelig oppdelt detaljside. Filmdetaljer-fanen kan brukes for verksinformasjon, roller og besetning kan romme artister og kreative bidrag, og samlingsfanen samler fysisk og digital tilgjengelighet.",
    palette: ["#644db2", "#16142c"],
    genres: ["Konsert", "Musikk"],
    countries: ["Tsjekkia"],
    production: ["Eagle Rock Entertainment"],
    director: "Tim Van Someren",
    writer: "Konsertproduksjon",
    sources: [
      { source: "tvdb", external_id: "341794", note: "Foreløpig kilde, trenger verifisering." }
    ],
    credits: {
      cast: [
        { name: "Hans Zimmer", role: "Hovedartist", note: "Frontfigur" },
        { name: "Johnny Marr", role: "Gitar", note: "Gjesteartist" },
        { name: "Tina Guo", role: "Cello", note: "Fremtredende innslag" },
        { name: "Junkie XL", role: "Gjesteartist", note: "Elektroniske elementer" }
      ],
      crew: [
        { name: "Tim Van Someren", role: "Regissør", note: "Scenisk filmatisering" },
        { name: "Hans Zimmer", role: "Musikalsk leder", note: "Overordnet kunstnerisk ansvar" },
        { name: "Jerry Bruckheimer", role: "Presentasjon", note: "Profilering av konserten" },
        { name: "Eagle Rock Entertainment", role: "Utgiver", note: "Distribusjon" }
      ]
    },
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
  tab: "details"
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
  const castCount = item.credits.cast.length;

  return { physicalEditions, physicalCopies, digitalCopies, castCount };
}

function filteredItems() {
  if (!state.q) return [];

  return items.filter((item) => {
    const haystack = [
      item.title,
      item.original_title,
      ...item.sources.flatMap((source) => [source.source, source.external_id]),
      ...item.credits.cast.flatMap((person) => [person.name, person.role]),
      ...item.credits.crew.flatMap((person) => [person.name, person.role]),
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
            <div class="stat-chip"><strong>${stats.castCount}</strong><span>roller</span></div>
            <div class="stat-chip"><strong>${stats.physicalEditions}</strong><span>utgaver</span></div>
            <div class="stat-chip"><strong>${stats.physicalCopies}</strong><span>eksemplarer</span></div>
            <div class="stat-chip"><strong>${stats.digitalCopies}</strong><span>digitale</span></div>
          </div>
        </div>
      </div>
    </article>
  `;
}

function renderTabs() {
  const tabs = [
    { id: "details", label: "Filmdetaljer" },
    { id: "credits", label: "Roller & besetning" },
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

function renderDetails(item) {
  mainContent.innerHTML = `
    <article class="main-card">
      <p class="eyebrow">Om tittelen</p>
      <h3>Filmdetaljer</h3>
      <p>Denne fanen holder seg tett på en vanlig detaljside og fokuserer på verket, ikke samlingen.</p>
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
  `;

  sideContent.innerHTML = `
    <article class="side-card">
      <p class="eyebrow">Nøkkelinfo</p>
      <h3>Kreative hovedroller</h3>
      <div class="stack">
        <div class="media-row"><h4>Regissør</h4><p>${item.director}</p></div>
        <div class="media-row"><h4>Manus</h4><p>${item.writer}</p></div>
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

function renderCredits(item) {
  mainContent.innerHTML = `
    <article class="main-card">
      <p class="eyebrow">Roller</p>
      <h3>Skuespillere og artister</h3>
      <div class="person-grid">
        ${item.credits.cast.map((person) => `
          <div class="person-card">
            <h4>${person.name}</h4>
            <div class="person-meta">
              <span class="pill">${person.role}</span>
            </div>
            <p>${person.note}</p>
          </div>
        `).join("")}
      </div>
    </article>
  `;

  sideContent.innerHTML = `
    <article class="side-card">
      <p class="eyebrow">Besetning</p>
      <h3>Bak kamera og produksjon</h3>
      <div class="stack">
        ${item.credits.crew.map((person) => `
          <div class="person-card">
            <h4>${person.name}</h4>
            <div class="person-meta">
              <span class="pill">${person.role}</span>
            </div>
            <p>${person.note}</p>
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
  `;
}

function renderContent(item) {
  if (state.tab === "credits") {
    renderCredits(item);
    return;
  }

  if (state.tab === "collection") {
    renderCollection(item);
    return;
  }

  renderDetails(item);
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

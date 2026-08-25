# website_template_example – versjonsoversikt

Dette dokumentet gir en kort oversikt over hva som skiller de ulike
versjonene av `frontend/public/website_template_example/`. Alle
versjoner kan nås fra oversiktssiden på `frontend/public/index.php`.

Versjon 1–13 er statiske HTML/CSS/JS-design-eksperimenter med hardkodet
demo-data (samme 2–3 titler, f.eks. «The Lord of the Rings: The
Fellowship of the Ring», «Blade Runner», «Hans Zimmer: Live in
Prague»), laget for å utforske ulike layout-/UX-konsepter for en
fremtidig detalj-/katalogside. Versjon 14 og utover er PHP og beveger
seg gradvis mot ekte databasekobling og en mer app-lignende struktur
med toppmeny.

## v1 – Bibliotek + detaljvisning
Én content-rad kan kobles til flere fysiske utgaver, men detaljvisningen
holder fokuset på tittelen først.

## v2 – Hero-stil detaljside
Stor hero og roligere underseksjoner gjør at siden minner mer om en
detaljside enn et register.

## v3 – Fast bibliotek til venstre
App-preget oppsett der biblioteket står fast til venstre og
detaljvisningen oppdateres til høyre.

## v4 – Stor toppflate, kortbasert resten
Toppflaten er viktigst: én valgt tittel får stort visuelt rom, mens
resten av informasjonen legges i mindre kort under.

## v5 – Lesbar katalogoversikt
Prioriterer en lesbar, tabell-/listepreget katalogoversikt fremfor
store visuelle kort.

## v6 – Faner i stedet for lange sider
Fokuserer på én valgt tittel og bytter detaljinnhold via faner i
stedet for lange sider.

## v7 – Filmplakat-orientert
Mer filmplakat-orientert førsteside der hovedinntrykket er viktigere
enn tabellstruktur.

## v8 – Arbeidsflate med faste filtre
Mer en arbeidsflate enn en publikumsside, med filtre alltid synlige
til venstre.

## v9 – Samlingsbilde før detaljer
Starter med et samlingsbilde/dashboard før valgt tittel får egen
detaljplass.

## v10 – Minimalistisk
Kutter ned på alt unødvendig og lar valgt tittel stå i sentrum – et
rolig, lettlest utgangspunkt for videre UI-arbeid.

## v11 – Databasestruktur synlig i detaljene
TMDB-lignende toppflate for selve tittelen, men den underliggende
databasestrukturen styrer detaljene lenger ned: én content-rad kan ha
flere fysiske utgaver, flere kopier per utgave, disker knyttet til
hver kopi og bonusinnhold per disk.

## v12 – Klassisk filmdetaljside + egen fane for eksemplarer
Holder hovedflaten tett på en klassisk filmdetaljside (hero, synopsis,
nøkkelfakta, kilder), mens opplysninger om egne eksemplarer/digitale
utgaver er flyttet til en egen fane.

## v13 – Tre faner (film / roller / samling)
Deler innholdet i tre tydelige faner: filmdetaljer, roller/besetning,
og det du eier fysisk eller har digitalt tilgjengelig.

## v14 – Første PHP-versjon, hardkodet «database-formet» data
Selvstendig, mørkt tema med søk/filter/detalj-modal. JS-`data`-arrayen
er hardkodet, men formet som om den kom fra en join av
`content` + `content_in_physical_collection` + `physical_collection` +
`content_external_source` – et forarbeid til v15.

## v15 – Første versjon med ekte databasekobling
Basert på v14, men henter nå data live fra `db_mediearkiv` via PHP/PDO:
- `config.php`: PDO-tilkobling via `MEDIA_DB_*`-miljøvariabler (phpdotenv).
- `api.php`: tre separate, lesbare SQL-spørringer (content, collections,
  sources), gruppert i PHP til samme JSON-form som v14 sin demo-data.
- `index.php`: henter data via `fetch('api.php')` i stedet for
  hardkodet array, med null-sikker visning av felter som kan mangle i
  ekte data (`runtime`, `age_restriction`, `first_release`).

## v16 – Ny struktur: toppmeny (uten database)
Bygget helt fra scratch (ikke basert på v14/v15). Sticky toppmeny med
fire punkter: **Mine filmer**, **Ønskeliste**, **Andre lister**,
**Administrering** – bytter synlig panel via JS uten reload. Ren
frontend-prototype med hardkodet demo-data, ingen databasekobling.
Inkluderer:
- En «Ikke innlogget»-indikator i toppmenyen, som forberedelse til
  fremtidig innlogging.
- Konfigurerbar tilgang per menypunkt via `$sectionAccess`-arrayen
  øverst i `index.php` (true/false per punkt = låst/åpen), med
  hengelås-ikon og forklarende merknadsbokser i panelene.
- En kommentarblokk øverst som viser nøyaktig hvor en fremtidig
  `require_login()`-sjekk bør legges inn.

## v17 – Toppmeny + ekte data + rutenett/liste + filtrering
Bygger videre på v16 sin toppmeny-struktur, men «Mine filmer» henter nå
ekte data fra databasen (samme `config.php`/`api.php`-mønster som v15,
i egne `v17`-filer, med `imdb_id` lagt til i API-responsen). Nytt i
denne versjonen:
- **Rutenett/liste-bytte**: en knapp lar deg bytte mellom kortvisning
  (cover-art-plassholder, som v15) og en enkel tabellvisning med kun
  **tittel, original tittel, årstall og IMDb-id** (lenket til imdb.com),
  uten cover-art – nyttig når samlingen er stor.
- **Tekstfiltrering** (samme mønster som v15): søkefelt (tittel/original
  tittel), type-chips (bygget dynamisk fra faktiske `content_type`-verdier
  i dataene) og en «Vis bare ikke-sett»-avkrysning. Filteret gjelder
  begge visningene (rutenett og liste).
- Ønskeliste/Andre lister/Administrering er fortsatt uendret
  demo-paneler fra v16, inkl. konfigurerbar lås-status.

## v18 – Første versjon med data via API (ikke direkte MySQL)
Identisk med v17 i utseende/funksjonalitet (toppmeny, rutenett/liste-
bytte, tekstfiltrering), men databasetilgangen er flyttet ut av PHP:
- Nytt FastAPI-endepunkt `GET /media/content`
  (`backend/app/routes/media_catalog_route.py` +
  `backend/app/services/media_catalog.py`) gjør nå selve SQL-
  spørringene mot `db_mediearkiv` (content + physical_collection +
  content_external_source), og returnerer samme datastruktur som
  v15/v17 sin `api.php` gjorde.
- v18 sin egen `api.php` gjør ingen SQL i det hele tatt – den er kun en
  tynn server-side proxy (`curl` mot `http://172.19.0.1:9500/media/content`)
  som sender JSON-responsen videre til nettleseren. Dette unngår CORS
  (alt serveres fortsatt fra samme origin) og krever ingen endring i
  Apache/edge-oppsettet.
- `imdb_id` hentes nå direkte fra `content.imdb_id`-kolonnen i stedet
  for via `content_external_source`, siden den kolonnen viste seg å
  allerede finnes og være delvis populert.
- Dette er bevisst starten på en større overgang: målet er at stadig
  mer av media-katalog-logikken (samlinger, kilder, roller m.m.) flyttes
  inn i dette API-et over tid, slik at PHP-sidene forblir tynne
  visningslag uten egen SQL/duplisert databaselogikk.

---

*Sist oppdatert i forbindelse med v18-arbeidet. Oppdater dette
dokumentet når nye versjoner legges til under
`frontend/public/website_template_example/`.*

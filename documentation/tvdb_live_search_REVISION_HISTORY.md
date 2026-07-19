# Revisjonshistorikk for `tvdb_ls`

Denne oversikten beskriver hovedforskjellene mellom hver revisjon og den
foregående revisjonen. `v1` er beskrevet som utgangspunktet.

## v1 – første TheTVDB live-søk

- Enkel Bootstrap-side for live-søk mot TheTVDB API v4.
- Frontend søker debounced etter minst to tegn og viser resultater direkte i
  en resultatliste.
- Backend støtter `action=search` og sender søket videre til TVDBs `/search`
  med `query` og `limit`.
- Resultater vises med plakat/placeholder, tittel, år, network, type-badge,
  TVDB-ID og forkortet beskrivelse.
- `config_tvdb.php` logger inn mot TVDB med `TVDB_API_KEY` og `TVDB_PIN` fra
  `.env`, cacher bearer-token per request og definerer `TVDB_TOKEN`.

## v2 – typefilter for film, serie eller begge

- UI-et fikk radiofilter for `Begge`, `Bare filmer` og `Bare serier`.
- Frontend sender valgt type som `type=both|movie|series` til `tvdb_api.php`.
- Backend legger `type` inn i TVDB-søket når brukeren velger `movie` eller
  `series`.
- Når `both` brukes, filtrerer frontend resultatene defensivt etter type ved
  behov.
- Søk kjøres automatisk på nytt når brukeren bytter typefilter og søketeksten
  allerede har minst to tegn.
- Revisjonen har mer synlig debugging, blant annet `display_errors=1` og
  logging av rå TVDB-respons i konsollen.

## v3 – kun eksplisitt film eller serie

- `both`-valget ble fjernet fra UI-et.
- Standardvalget ble endret til `TV-serier`.
- Backend validerer nå at `type` er enten `movie` eller `series`; andre verdier
  returnerer feil.
- Backend sender alltid `type` direkte til TVDB i søket, i stedet for å la
  type være valgfri.
- Frontend trenger ikke lenger ekstra lokal filtrering for `both`.
- `renderResults()` får valgt type som fallback hvis TVDB-resultatet mangler
  typefelt.

## v4 – detaljmodal og details-endepunkt

- Backend fikk nytt `action=details`.
- `details` validerer numerisk TVDB-ID og type, og kaller:
  - `/movies/{id}/extended?short=true` for filmer
  - `/series/{id}/extended?short=true` for serier
- API-logikken ble delt i tydeligere funksjoner: `handleSearch()`,
  `handleDetails()` og `tvdbRequest()`.
- Resultatrader ble klikkbare og åpner en Bootstrap-modal med detaljer.
- Detaljmodalen viser plakat, type, TVDB-ID, år, spilletid, land, språk,
  sjangere og beskrivelse når data finnes.
- Resultatteksten informerer nå om at brukeren kan klikke en rad for detaljer.
- Beskrivelsen i resultatlisten ble kortet ned mer aggressivt før detaljvisning.
- Revisjonsmappen inneholder også `v4/v3`, som er en arkivkopi av `v3`.

## Felles for alle revisjoner

- `config_tvdb.php` er uendret fra `v1` til `v4`.
- Alle revisjoner bruker TheTVDB v4 med bearer-token fra lokal backend, ikke
  direkte kall fra nettleseren til TVDB.

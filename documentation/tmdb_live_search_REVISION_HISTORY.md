# Revisjonshistorikk for `tmdb_ls`

Denne oversikten beskriver hovedforskjellene mellom hver revisjon og den
foregående revisjonen. `v1` er beskrevet som utgangspunktet.

## v1 – første TMDB-søk

- Enkel Bootstrap-side for TMDB-filmsøk.
- Søket går via lokal `api.php`, som kaller TMDBs `/search/movie`.
- Resultater vises som kort på hovedsiden med plakat, tittel, dato, rating og
  beskrivelse.
- `config.php` leser `TMDB_API_KEY` fra `.env` via Composer/autoload.
- Revisjonen inneholder også en enkel `index.php`-testfil og `test.php` for å
  verifisere `.env`/autoload-oppsett.

## v2 – film- og TV-søk med detaljvisning

- Søket ble utvidet fra kun film til både film og TV-serier.
- `api.php` fikk `action=search` og `action=details`.
- Film- og TV-resultater kombineres, merkes med `media_type` og sorteres etter
  popularitet.
- Detaljoppslag bruker `append_to_response=external_ids`, slik at IMDb-ID kan
  hentes ved valg av resultat.
- UI-et ble endret fra resultatkort på siden til dropdown-resultater under
  søkefeltet.
- Valgt element vises med større detaljkort, IMDb-lenke og TMDB-ID.
- `index.php` og `test.php` ble fjernet fra revisjonsmappen.

## v3 – årstallssøk og IMDb-status i dropdown

- Søket parser årstall i søketeksten, for eksempel `Titanic 1997`.
- Backend sender `year` for filmer og `first_air_date_year` for TV-serier til
  TMDB.
- Responsen inkluderer `search_year`, slik at UI-et kan vise at søket er
  filtrert på år.
- Backend henter IMDb-ID for hvert søkeresultat via et ekstra `external_ids`-
  kall.
- Dropdown-resultatene viser om IMDb-ID finnes eller mangler.
- En kort søketips-tekst ble lagt til i UI-et.
- `config.php` ble justert til å lese autoload og `.env` fra en annen relativ
  katalogdybde.

## v4 – fjerner dyre IMDb-oppslag fra søkeresultatene

- IMDb-ID hentes ikke lenger for hvert treff i søkeresultatet.
- Hjelpefunksjonen som gjorde ett ekstra `external_ids`-kall per resultat ble
  fjernet.
- Dropdownen viser igjen bare grunnleggende treffinformasjon, mens IMDb-ID
  hentes først ved detaljoppslag.
- Dette reduserer antall TMDB-kall per søk og gjør søket lettere.

## v5 – søk i modal og valgt resultat på hovedsiden

- UI-et ble bygget om til en hovedside med én tydelig søkeknapp og egen
  søkemodal.
- Søkemodalen får fokus automatisk når den åpnes og ryddes når den lukkes.
- Valgt treff vises først inne i modalen med full informasjon.
- Brukeren kan deretter legge valgt element til hovedsiden.
- Hovedsiden viser valgt film/serie som eget resultatkort med plakat, rating,
  IMDb-ID, TMDB-ID og beskrivelse.

## v6 – registreringsskjema for IMDb-/TMDB-ID

- Hovedsiden ble endret fra visning av valgt resultat til et enkelt
  registreringsskjema.
- Skjemaet har egne felt for `IMDB-ID` og `TMDB-ID`.
- Når brukeren klikker et søkeresultat, hentes detaljer direkte og feltene
  fylles automatisk.
- Modalen lukkes etter at ID-ene er overført til skjemaet.
- `api.php` ble refaktorert med tydeligere validering, typekontroll,
  JSON-parsekontroll og mer detaljerte feilmeldinger.
- `config.php` ble endret tilbake til samme relative katalogdybde som de tidlige
  revisjonene.
- En tom fil med navnet `scriptjs` dukker opp i revisjonen.

## v7 – infoside før overføring til skjema

- Klikk på søkeresultat fyller ikke lenger skjemaet umiddelbart.
- Resultatet åpnes først som en infoside inne i modalen med plakat, type,
  rating, år, TMDB-ID, IMDb-ID, IMDb-lenke og beskrivelse.
- Infosiden har en egen knapp for å overføre ID-er til skjemaet.
- Søk- og detaljfeil håndteres mer robust ved å lese tekstrespons og forsøke
  JSON-parsing før feilmeldingen vises.
- `live_search.php` fikk tilbake styling for valgt element, plakat og IMDb-lenke
  inne i modalen.
- Merk: `applyIdsToForm()` har TMDB-feltoverføring kommentert ut i denne
  revisjonen, så knappen overfører i praksis IMDb-ID, ikke TMDB-ID.

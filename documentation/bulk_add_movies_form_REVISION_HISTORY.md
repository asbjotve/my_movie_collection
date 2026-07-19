# Revisjonshistorikk for `multi_am_form`

Denne oversikten beskriver hovedforskjellene mellom hver revisjon og den
foregående revisjonen. `v1` er beskrevet som utgangspunktet.

## v1 – første komplette skjema

- Ett mørkt Bootstrap-skjema for enkeltfilmer og box-set.
- Dynamisk opprettelse av filmer, box-set, filmer i box-set og disker.
- Disker kunne registreres med type, format, rekkefølge, `storage_id` og
  plassnummer.
- Manglende diskrekkefølge i box-set ble beregnet automatisk før innsending.
- Skjemaet sendte data direkte til det daværende `bulk-add`-endepunktet med
  token fra `localStorage`.

## v2 – ny struktur og nytt payload-design

- Skjemaet ble bygget om til separate faner for singles og box-set.
- Singles fikk tabellvisning, standardformat og `default_copy_count`.
- Box-set-flyten ble endret til ordnede filmtitler med `inner_case_ean` og
  `treat_as_single`.
- Box-set fikk `format`, `box_set_barcode`, `copy_count` og en egen felles
  diskliste hvor en disk kan knyttes til en bestemt film.
- Lim-inn-liste og JSON-forhåndsvisning ble lagt til.
- Direkte innsending og de gamle feltene for fysisk lagringsplass ble fjernet;
  revisjonen fungerer hovedsakelig som payload-prototype.

## v3 – flere box-set i samme operasjon

- Den ene faste box-set-visningen ble erstattet med et accordion som kan
  inneholde flere box-set.
- Hvert box-set fikk egne filmer, disker, sammendrag og fjern-knapp.
- Box-set-payloaden ble endret til `kind: "box_sets_bulk"` med en
  `box_sets`-liste.
- Lim-inn-dialogen ble gjenbrukt mot det box-settet brukeren hadde valgt.
- Dynamisk innhold begynte å bli HTML-escaped mer konsekvent.

## v4 – redigering av bonusinnhold

- Egen modal for bonusinnhold på box-set-disker ble innført.
- Bonusposter fikk feltene `seq_no`, `title`, `item_type`,
  `runtime_seconds` og `notes`.
- Bonusinnhold ble lagret som JSON på den aktuelle diskraden og tatt med som
  `bonus_items` i payloaden.
- UI-et varsler dersom bonusinnhold legges på en disk som ikke har typen
  `bonus`.

## v5 – full diskeditor for singles

- Singles fikk en egen diskmodal i stedet for den tidligere
  plassholderknappen.
- Hver single kan nå ha flere feature-/bonusdisker med format, etikett og
  bonusinnhold.
- Bonusmodalen ble gjort felles for single-disker og box-set-disker.
- Single-payloaden fikk en faktisk `discs`-liste per rad.

## v6 – samlet forhåndsvisning og tydeligere diskkontekst

- Det ble lagt til en samlet forhåndsvisning med både singles og box-set under
  `kind: "bulk_add_preview"`.
- Bonusmodalen fikk dynamisk tittel og statusmerke som skiller mellom vanlig
  disk og bonusdisk.
- Tekster og knapper ble justert fra generelle «items» til en tydeligere
  spor-/diskmodell.

## v7 – UI-opprydding i disk- og bonusmodalene

- Single-diskmodalen fikk et tydeligere grått visuelt uttrykk, eget
  modaloppsett og mer presise knappetekster.
- Bonusmodalen ble forenklet tilbake til én felles «Bonus items»-visning.
- Intern JavaScript-struktur og navngivning ble ryddet uten vesentlig endring
  av payloadformatet.

## v8 – tryggere nøstede modaler

- Disk- og bonusmodalene fikk statisk backdrop.
- Når bonusmodalen åpnes over single-diskmodalen, fryses den underliggende
  modalen for å hindre feilklikk.
- Singles og box-set fikk igjen separate forhåndsvisningsknapper og separate
  payloadvisninger.
- Flere funksjoner og elementnavn ble standardisert mellom single- og
  box-set-flyten.

## v9 – språkstøtte og lagring av utkast

- Norsk bokmål og engelsk ble innført gjennom `lang/nb.php` og `lang/en.php`.
- Valgt språk lagres i PHP-session og kan byttes fra skjemaet.
- Synlige tekster ble flyttet til oversettelsesnøkler med helperne `tr()` og
  `h()`.
- Hele skjematilstanden, inkludert disker og bonusinnhold, lagres i
  `localStorage` og gjenopprettes etter side- eller språkbytte.
- En reset-knapp ble lagt til for å tømme både skjema og lagret utkast.
- Singles valideres slik at hver rad må inneholde minst én feature-disk før
  forhåndsvisning.

## v10 – TMDB-søk og eksterne ID-er

- TMDB-søk ble lagt til for både singles og filmer i box-set.
- Nye filer `api.php`, `config.php` og `script.js` håndterer TMDB-konfigurasjon,
  proxykall, søk og detaljoppslag.
- Valgt treff fyller IMDb-ID og en skjult `tmdb_id`, som deretter tas med i
  payloaden.
- Singles fikk format per rad i stedet for bare ett globalt format.
- `localStorage`-skjemaet ble oppgradert slik at format og TMDB-ID bevares.

## v11 – korrigert box-set-payload og stabile relasjoner

- Forhåndsvisningen av box-set sender igjen hele `box_sets`-listen; i `v10`
  ble bare `kind` sendt fra denne knappen.
- Diskens filmtilknytning lagres som `related_index`, ikke bare som vist
  tittel.
- Når en film fjernes, oppdateres indeksene og visningsteksten på tilknyttede
  disker.
- Diskrekkefølge nummereres på nytt når en disk fjernes.
- Håndtering av tomme og tekstlige `null`-verdier i disketiketter ble gjort
  mer robust.
- `localStorage`-nøkkelen ble oppgradert til `bulkAddState_v11`.

## v12 – etiketter på box-set-disker

- Box-set-disker fikk et eget valgfritt `label`-felt.
- Etiketten vises i disktabellen og i bonusmodalens konteksttekst.
- `label` følger nå box-set-disken gjennom payload, lagring i `localStorage`
  og gjenoppretting.
- Verdiene `null` og `NULL` normaliseres til manglende etikett.

## v13 – fysisk lagringsplass i nytt payloadformat

- Et globalt felt for `storage_id` ble lagt til og inkluderes i payloadene for
  både singles og box-set.
- Single-disker fikk `storage_slot_no` og `add_to_storage`.
- Box-set-disker fikk de samme lagringsfeltene i registreringsområdet og
  disktabellen.
- Lagringsfeltene tas med i payload, `localStorage` og gjenoppretting av
  utkast.
- Etter at en box-set-disk legges til, nullstilles lagringsplassnummer og
  avkrysningsfelt før neste disk registreres.

// tv_series_add_form v1.
// Two guided modes, both posting to the same backend endpoint via
// api.php's submit action:
//   - "tv_series_boxset": one series, its seasons/episodes, and discs
//     (the original mode).
//   - "mixed_boxset": one physical box mixing several series (each
//     with its own seasons/episodes) and/or standalone movies, with
//     discs referencing any mix of them. See
//     app/schemas/physical_collection_import.py's
//     MixedBoxsetImportPayload for the exact backend shape.
// Season/episode editing is shared between both modes via the
// generic renderSeasonBlocks()/addSeasonTo()/removeSeasonFrom()
// helpers below, parameterized by which seasons array + DOM container
// they operate on.

(function () {
  "use strict";

  // --- Mode toggle ----------------------------------------------------

  const formModeEl = document.getElementById("formMode");
  const singleModeSectionEl = document.getElementById("singleModeSection");
  const mixedModeSectionEl = document.getElementById("mixedModeSection");

  function currentMode() {
    return formModeEl.value === "mixed_boxset" ? "mixed_boxset" : "tv_series_boxset";
  }

  // Parses a quick-entry episode range spec like "1-6,9,12-14" into a
  // sorted, de-duplicated array of episode numbers. Used by the
  // per-disc "range quick-add" control below - entering 22 individual
  // episodes one at a time into a <select multiple> was the main
  // friction point reported after testing a 22-episode season.
  function parseEpisodeRangeSpec(spec) {
    const numbers = new Set();
    String(spec || "")
      .split(",")
      .map((part) => part.trim())
      .filter(Boolean)
      .forEach((part) => {
        const rangeMatch = part.match(/^(\d+)\s*-\s*(\d+)$/);
        if (rangeMatch) {
          let start = Number(rangeMatch[1]);
          let end = Number(rangeMatch[2]);
          if (start > end) [start, end] = [end, start];
          for (let n = start; n <= end; n += 1) numbers.add(n);
        } else if (/^\d+$/.test(part)) {
          numbers.add(Number(part));
        }
      });
    return Array.from(numbers).sort((a, b) => a - b);
  }

  function updateModeVisibility() {
    const mixed = currentMode() === "mixed_boxset";
    singleModeSectionEl.style.display = mixed ? "none" : "block";
    mixedModeSectionEl.style.display = mixed ? "block" : "none";
    // Lazy-init mixed mode with one empty series/disc so the shape is
    // clear the first time the user switches to it.
    if (mixed && mixedSeries.length === 0 && mixedMovies.length === 0) {
      addMixedSeries();
      addMixedDisc();
    }
  }

  formModeEl.addEventListener("change", updateModeVisibility);

  function h(s) {
    const div = document.createElement("div");
    div.textContent = s == null ? "" : String(s);
    return div.innerHTML;
  }

  // --- Shared season/episode editor -----------------------------------
  // Operates on a plain seasons array (season_number, title, air_date,
  // inner_case_ean, episodes:[{episode_number,title,runtime,
  // original_air_date}]) rendered into a given container element, used
  // both by single-mode's one series and by each series block in
  // mixed mode.

  function makeSeason(seasonNumber) {
    return { season_number: seasonNumber, title: "", air_date: "", inner_case_ean: "", episodes: [] };
  }

  // Resize season.episodes to the given count, keeping existing episode
  // numbers/data for the rows that remain (up to 20+ episodes per season
  // is common, so a per-row "add episode" click is impractical - see
  // user feedback). Title/runtime/air_date are left blank for now; the
  // plan is to fill these in from TVDB later rather than by hand.
  function setEpisodeCount(season, count) {
    const safeCount = Math.max(0, Math.floor(Number(count) || 0));
    if (safeCount > season.episodes.length) {
      for (let i = season.episodes.length; i < safeCount; i++) {
        season.episodes.push({
          episode_number: i + 1,
          title: "",
          runtime: "",
          original_air_date: "",
        });
      }
    } else if (safeCount < season.episodes.length) {
      season.episodes.length = safeCount;
    }
  }

  function addSeasonTo(seasonsArr) {
    const season = makeSeason(seasonsArr.length + 1);
    seasonsArr.push(season);
    setEpisodeCount(season, 1);
    return season;
  }

  function removeSeasonFrom(seasonsArr, season) {
    const idx = seasonsArr.indexOf(season);
    if (idx !== -1) seasonsArr.splice(idx, 1);
  }

  function renderSeasonBlocks(containerEl, noMsgEl, seasonsArr, onChange) {
    containerEl.innerHTML = "";
    noMsgEl.style.display = seasonsArr.length ? "none" : "block";

    seasonsArr.forEach((season) => {
      const block = document.createElement("div");
      block.className = "seasonBlock";
      block.innerHTML = `
        <div class="seasonHead">
          <h3>Sesong <span class="seasonNumberLabel">${h(season.season_number)}</span></h3>
          <button type="button" class="btn danger small" data-action="removeSeason">Fjern sesong</button>
        </div>
        <div class="grid cols4" style="margin-bottom:10px;">
          <div class="field">
            <label>Sesongnummer</label>
            <input type="number" min="0" class="seasonNumberInput" value="${h(season.season_number)}">
          </div>
          <div class="field">
            <label>Sesongtittel (valgfritt)</label>
            <input type="text" class="seasonTitleInput" value="${h(season.title)}" placeholder="f.eks. Season 1">
          </div>
          <div class="field">
            <label>Antall episoder</label>
            <input type="number" min="0" class="seasonEpisodeCountInput" value="${h(season.episodes.length)}">
          </div>
          <div class="field">
            <label>Egen EAN (valgfritt)</label>
            <input type="text" class="seasonInnerEanInput" value="${h(season.inner_case_ean)}" placeholder="ved samleboks med egen sesong-etui">
          </div>
        </div>
        <p class="muted" style="margin:0;">
          Episoder genereres automatisk (S${h(season.season_number)}E1 ... E${h(season.episodes.length)}).
          «Egen EAN» brukes bare hvis denne sesongen ligger i sitt eget etui inni en samleboks med flere sesonger.
        </p>
      `;

      block.querySelector(".seasonNumberInput").addEventListener("input", (e) => {
        season.season_number = e.target.value === "" ? "" : Number(e.target.value);
        block.querySelector(".seasonNumberLabel").textContent = season.season_number;
        onChange();
      });
      block.querySelector(".seasonTitleInput").addEventListener("input", (e) => {
        season.title = e.target.value;
      });
      block.querySelector(".seasonEpisodeCountInput").addEventListener("change", (e) => {
        setEpisodeCount(season, e.target.value);
        renderSeasonBlocks(containerEl, noMsgEl, seasonsArr, onChange);
        onChange();
      });
      block.querySelector(".seasonInnerEanInput").addEventListener("input", (e) => {
        season.inner_case_ean = e.target.value;
      });
      block.querySelector('[data-action="removeSeason"]').addEventListener("click", () => {
        removeSeasonFrom(seasonsArr, season);
        renderSeasonBlocks(containerEl, noMsgEl, seasonsArr, onChange);
        onChange();
      });

      containerEl.appendChild(block);
    });
  }

  // --- Single mode: seasons + discs ------------------------------------

  const seasons = []; // same shape as makeSeason()
  const discs = [];   // { id, order, format, label, season_id, storage_slot_no, add_to_storage, episode_refs: [{season_number, episode_number}] }
  let discSeq = 0;

  const seasonsContainer = document.getElementById("seasonsContainer");
  const noSeasonsMsg = document.getElementById("noSeasonsMsg");
  const discTableBody = document.getElementById("discTableBody");
  const noDiscsMsg = document.getElementById("noDiscsMsg");
  const autoDistributeSeasonSelect = document.getElementById("autoDistributeSeasonSelect");
  const autoDistributeBtn = document.getElementById("autoDistributeBtn");

  function renderSeasons() {
    renderSeasonBlocks(seasonsContainer, noSeasonsMsg, seasons, renderDiscEpisodeOptions);
    renderAutoDistributeSeasonOptions();
  }

  function renderAutoDistributeSeasonOptions() {
    if (!seasons.length) {
      autoDistributeSeasonSelect.innerHTML = '<option value="">(ingen sesonger lagt til)</option>';
      return;
    }
    autoDistributeSeasonSelect.innerHTML = seasons
      .map((season, idx) => `<option value="${idx}">Sesong ${h(season.season_number)}</option>`)
      .join("");
  }

  // Fordeler en sesongs episoder likt (i rekkefølge, nær-jevnt antall
  // pr. disk) på diskene som allerede er satt til den sesongen -
  // lagt til etter tilbakemelding om at det var tungvint å måtte
  // velge episoder manuelt én etter én for en sesong med 22 episoder.
  // Overskriver episodevalget på de berørte diskene.
  function autoDistributeSeasonEpisodes() {
    const season = seasons[Number(autoDistributeSeasonSelect.value)];
    if (!season || !season.episodes.length) return;

    const discsForSeason = discs.filter((d) => d.season_id === season).sort((a, b) => a.order - b.order);
    if (!discsForSeason.length) {
      alert("Ingen disker er satt til denne sesongen ennå - legg til disker og velg sesongen deres først.");
      return;
    }

    const episodes = season.episodes.slice().sort((a, b) => a.episode_number - b.episode_number);
    const discCount = discsForSeason.length;
    const base = Math.floor(episodes.length / discCount);
    const remainder = episodes.length % discCount;

    let cursor = 0;
    discsForSeason.forEach((disc, idx) => {
      const take = base + (idx < remainder ? 1 : 0);
      const slice = episodes.slice(cursor, cursor + take);
      cursor += take;
      disc.episode_refs = slice.map((ep) => ({
        season_number: season.season_number,
        episode_number: ep.episode_number,
      }));
    });

    renderDiscs();
  }

  autoDistributeBtn.addEventListener("click", autoDistributeSeasonEpisodes);

  function addSeason() {
    addSeasonTo(seasons);
    renderSeasons();
    renderDiscEpisodeOptions();
  }

  function addDisc() {
    discSeq += 1;
    discs.push({
      id: discSeq,
      order: discs.length + 1,
      format: "DVD",
      label: "",
      season_id: seasons.length ? seasons[0] : null,
      storage_slot_no: "",
      add_to_storage: true,
      episode_refs: [],
    });
    renderDiscs();
  }

  function removeDisc(id) {
    const idx = discs.findIndex((d) => d.id === id);
    if (idx !== -1) discs.splice(idx, 1);
    discs.forEach((d, i) => {
      d.order = i + 1;
    });
    renderDiscs();
  }

  function seasonOptionsHtml(selectedSeason) {
    if (!seasons.length) {
      return '<option value="">(ingen sesonger lagt til)</option>';
    }
    return seasons
      .map((season, idx) => {
        const label =
          `Sesong ${season.season_number}` +
          (season.inner_case_ean ? ` (eget etui, EAN ${season.inner_case_ean})` : "");
        const selected = season === selectedSeason ? " selected" : "";
        return `<option value="${idx}"${selected}>${h(label)}</option>`;
      })
      .join("");
  }

  function episodeOptionsHtml(season, selectedRefs) {
    if (!season) return "";
    return season.episodes
      .map((ep) => {
        const value = `${season.season_number}:${ep.episode_number}`;
        const label = `S${season.season_number}E${ep.episode_number}` + (ep.title ? ` - ${ep.title}` : "");
        const selected = selectedRefs.some(
          (r) => r.season_number === season.season_number && r.episode_number === ep.episode_number
        );
        return `<option value="${h(value)}"${selected ? " selected" : ""}>${h(label)}</option>`;
      })
      .join("");
  }

  function renderDiscEpisodeOptions() {
    // Seasons/episodes may have changed (renumbered, removed, count
    // changed) - make sure discs still point at a valid season and
    // drop episode_refs that no longer belong to it.
    discs.forEach((disc) => {
      if (!seasons.includes(disc.season_id)) {
        disc.season_id = seasons.length ? seasons[0] : null;
        disc.episode_refs = [];
      } else {
        const season = disc.season_id;
        disc.episode_refs = disc.episode_refs.filter((r) =>
          season.episodes.some(
            (ep) => ep.episode_number === r.episode_number && season.season_number === r.season_number
          )
        );
      }
    });
    renderAutoDistributeSeasonOptions();
    renderDiscs();
  }

  function renderDiscs() {
    discTableBody.innerHTML = "";
    noDiscsMsg.style.display = discs.length ? "none" : "block";

    discs.forEach((disc) => {
      const tr = document.createElement("tr");
      tr.className = "discRow";
      tr.innerHTML = `
        <td>${h(disc.order)}</td>
        <td>
          <select class="discFormatInput">
            <option value="DVD"${disc.format === "DVD" ? " selected" : ""}>DVD</option>
            <option value="Blu-ray"${disc.format === "Blu-ray" ? " selected" : ""}>Blu-ray</option>
            <option value="4K UHD"${disc.format === "4K UHD" ? " selected" : ""}>4K UHD</option>
          </select>
        </td>
        <td><input type="text" class="discLabelInput" value="${h(disc.label)}" placeholder="f.eks. Disk 1" style="width:120px;"></td>
        <td><select class="discSeasonInput">${seasonOptionsHtml(disc.season_id)}</select></td>
        <td><input type="number" min="0" class="discSlotInput" value="${h(disc.storage_slot_no)}" style="width:80px;"></td>
        <td><input type="checkbox" class="discAddToStorageInput" ${disc.add_to_storage ? "checked" : ""}></td>
        <td>
          <select multiple class="discEpisodesInput">${episodeOptionsHtml(disc.season_id, disc.episode_refs)}</select>
          <div style="margin-top:4px; display:flex; gap:4px;">
            <input type="text" class="discEpisodeRangeInput" placeholder="f.eks. 1-6,9" style="width:90px; font-size:12px;">
            <button type="button" class="btn small" data-action="addEpisodeRange" title="Legg til episodene fra feltet til venstre, uten å fjerne allerede valgte episoder">+ Legg til</button>
          </div>
        </td>
        <td><button type="button" class="btn danger small" data-action="removeDisc">Fjern</button></td>
      `;

      tr.querySelector(".discFormatInput").addEventListener("change", (e) => {
        disc.format = e.target.value;
      });
      tr.querySelector(".discLabelInput").addEventListener("input", (e) => {
        disc.label = e.target.value;
      });
      tr.querySelector(".discSeasonInput").addEventListener("change", (e) => {
        disc.season_id = seasons[Number(e.target.value)] || null;
        disc.episode_refs = []; // switching season - old picks no longer apply
        renderDiscs();
      });
      tr.querySelector(".discSlotInput").addEventListener("input", (e) => {
        disc.storage_slot_no = e.target.value === "" ? "" : Number(e.target.value);
      });
      tr.querySelector(".discAddToStorageInput").addEventListener("change", (e) => {
        disc.add_to_storage = e.target.checked;
      });
      tr.querySelector(".discEpisodesInput").addEventListener("change", (e) => {
        const selected = Array.from(e.target.selectedOptions).map((opt) => opt.value);
        disc.episode_refs = selected.map((v) => {
          const [seasonNumber, episodeNumber] = v.split(":").map(Number);
          return { season_number: seasonNumber, episode_number: episodeNumber };
        });
      });
      // Quick-add via a typed range (e.g. "1-6,9") instead of having
      // to ctrl/cmd-click every single episode in the multi-select -
      // merges into (doesn't replace) whatever's already selected.
      tr.querySelector('[data-action="addEpisodeRange"]').addEventListener("click", () => {
        const season = disc.season_id;
        if (!season) return;
        const input = tr.querySelector(".discEpisodeRangeInput");
        const wanted = parseEpisodeRangeSpec(input.value);
        wanted.forEach((episodeNumber) => {
          const exists = season.episodes.some((ep) => ep.episode_number === episodeNumber);
          const alreadyPicked = disc.episode_refs.some(
            (r) => r.season_number === season.season_number && r.episode_number === episodeNumber
          );
          if (exists && !alreadyPicked) {
            disc.episode_refs.push({ season_number: season.season_number, episode_number: episodeNumber });
          }
        });
        input.value = "";
        renderDiscs();
      });
      tr.querySelector('[data-action="removeDisc"]').addEventListener("click", () => {
        removeDisc(disc.id);
      });

      discTableBody.appendChild(tr);
    });
  }

  function buildSinglePayload() {
    return {
      kind: "tv_series_boxset",
      series: {
        title: document.getElementById("seriesTitle").value || null,
        imdb_id: document.getElementById("seriesImdb").value || null,
        tvdb_id: document.getElementById("seriesTvdb").value || null,
        content_type: "series",
      },
      box: buildBoxPayload(),
      seasons: seasons.map((s) => ({
        season_number: s.season_number,
        title: s.title || null,
        air_date: s.air_date || null,
        inner_case_ean: s.inner_case_ean || null,
        episodes: s.episodes.map((ep) => ({
          episode_number: ep.episode_number,
          title: ep.title || null,
          runtime: ep.runtime === "" ? null : ep.runtime,
          original_air_date: ep.original_air_date || null,
        })),
      })),
      discs: discs.map((d) => {
        const season = d.season_id;
        return {
          order: d.order,
          format: d.format,
          label: d.label || null,
          season_number: season ? season.season_number : null,
          inner_case_ean: season && season.inner_case_ean ? season.inner_case_ean : null,
          storage_slot_no: d.storage_slot_no === "" ? null : d.storage_slot_no,
          add_to_storage: d.add_to_storage,
          episode_refs: d.episode_refs,
        };
      }),
    };
  }

  // --- Shared box fields ------------------------------------------------

  function buildBoxPayload() {
    return {
      format: document.getElementById("boxFormat").value,
      box_set_barcode: document.getElementById("boxBarcode").value || null,
      storage_id: document.getElementById("storageId").value || null,
      copy_count: Number(document.getElementById("copyCount").value) || 1,
    };
  }

  // --- Mixed mode: multiple series, movies, discs ------------------------

  let mixedSeriesSeq = 0;
  let mixedMovieSeq = 0;
  let mixedDiscSeq = 0;
  const mixedSeries = []; // { id, title, imdb_id, tvdb_id, seasons: [...] }
  const mixedMovies = []; // { id, title, imdb_id, tmdb_id, tvdb_id, inner_case_ean }
  const mixedDiscs = [];  // { id, order, format, label, storage_slot_no, add_to_storage, content_refs: [{kind, id}], episode_refs: [{seriesId, season_number, episode_number}] }

  const mixedSeriesContainer = document.getElementById("mixedSeriesContainer");
  const noMixedSeriesMsg = document.getElementById("noMixedSeriesMsg");
  const mixedMoviesContainer = document.getElementById("mixedMoviesContainer");
  const noMixedMoviesMsg = document.getElementById("noMixedMoviesMsg");
  const mixedDiscTableBody = document.getElementById("mixedDiscTableBody");
  const noMixedDiscsMsg = document.getElementById("noMixedDiscsMsg");

  function addMixedSeries() {
    mixedSeriesSeq += 1;
    const series = { id: mixedSeriesSeq, title: "", imdb_id: "", tvdb_id: "", seasons: [] };
    mixedSeries.push(series);
    addSeasonTo(series.seasons);
    renderMixedSeries();
    renderMixedDiscs();
  }

  function removeMixedSeries(id) {
    const idx = mixedSeries.findIndex((s) => s.id === id);
    if (idx !== -1) mixedSeries.splice(idx, 1);
    renderMixedSeries();
    renderMixedDiscs();
  }

  function renderMixedSeries() {
    mixedSeriesContainer.innerHTML = "";
    noMixedSeriesMsg.style.display = mixedSeries.length ? "none" : "block";

    mixedSeries.forEach((series, idx) => {
      const block = document.createElement("div");
      block.className = "seasonBlock";
      block.innerHTML = `
        <div class="seasonHead">
          <h3>Serie ${idx + 1}</h3>
          <button type="button" class="btn danger small" data-action="removeSeries">Fjern serie</button>
        </div>
        <div class="grid cols2">
          <div class="field">
            <label>Tittel</label>
            <input type="text" class="seriesTitleInput" value="${h(series.title)}" placeholder="f.eks. Il Corleone">
          </div>
          <div class="field">
            <label>IMDb-ID</label>
            <input type="text" class="seriesImdbInput" value="${h(series.imdb_id)}" placeholder="tt...">
          </div>
          <div class="field">
            <label>TVDB-ID</label>
            <input type="text" class="seriesTvdbInput" value="${h(series.tvdb_id)}" placeholder="12345">
          </div>
        </div>
        <div class="actionsRow" style="margin-top:10px;">
          <button type="button" class="btn small" data-action="searchTvdb">Søk TVDB</button>
          <span class="muted seriesTvdbStatus"></span>
        </div>
        <div class="seriesTvdbResults" style="margin-top:8px;"></div>
        <div class="cardHead" style="padding:14px 0 0;">
          <h3 style="margin:0;font-size:14px;">Sesonger</h3>
          <button type="button" class="btn small" data-action="addSeason">+ Legg til sesong</button>
        </div>
        <div class="seriesSeasonsContainer" style="margin-top:10px;"></div>
        <p class="muted seriesNoSeasonsMsg">Ingen sesonger lagt til ennå.</p>
      `;

      block.querySelector(".seriesTitleInput").addEventListener("input", (e) => {
        series.title = e.target.value;
      });
      block.querySelector(".seriesImdbInput").addEventListener("input", (e) => {
        series.imdb_id = e.target.value;
      });
      block.querySelector(".seriesTvdbInput").addEventListener("input", (e) => {
        series.tvdb_id = e.target.value;
      });
      block.querySelector('[data-action="removeSeries"]').addEventListener("click", () => {
        removeMixedSeries(series.id);
      });

      const seasonsContainerEl = block.querySelector(".seriesSeasonsContainer");
      const noSeasonsMsgEl = block.querySelector(".seriesNoSeasonsMsg");
      const rerenderThisSeriesSeasons = () =>
        renderSeasonBlocks(seasonsContainerEl, noSeasonsMsgEl, series.seasons, renderMixedDiscs);
      rerenderThisSeriesSeasons();

      block.querySelector('[data-action="addSeason"]').addEventListener("click", () => {
        addSeasonTo(series.seasons);
        rerenderThisSeriesSeasons();
        renderMixedDiscs();
      });

      const titleInputEl = block.querySelector(".seriesTitleInput");
      const tvdbInputEl = block.querySelector(".seriesTvdbInput");
      const imdbInputEl = block.querySelector(".seriesImdbInput");
      const statusEl = block.querySelector(".seriesTvdbStatus");
      const resultsEl = block.querySelector(".seriesTvdbResults");
      block.querySelector('[data-action="searchTvdb"]').addEventListener("click", () => {
        searchTvdbFor(titleInputEl, tvdbInputEl, imdbInputEl, resultsEl, statusEl);
      });

      mixedSeriesContainer.appendChild(block);
    });
  }

  function addMixedMovie() {
    mixedMovieSeq += 1;
    mixedMovies.push({
      id: mixedMovieSeq,
      title: "",
      imdb_id: "",
      tmdb_id: "",
      tvdb_id: "",
      inner_case_ean: "",
    });
    renderMixedMovies();
    renderMixedDiscs();
  }

  function removeMixedMovie(id) {
    const idx = mixedMovies.findIndex((m) => m.id === id);
    if (idx !== -1) mixedMovies.splice(idx, 1);
    renderMixedMovies();
    renderMixedDiscs();
  }

  function renderMixedMovies() {
    mixedMoviesContainer.innerHTML = "";
    noMixedMoviesMsg.style.display = mixedMovies.length ? "none" : "block";

    mixedMovies.forEach((movie, idx) => {
      const block = document.createElement("div");
      block.className = "seasonBlock";
      block.innerHTML = `
        <div class="seasonHead">
          <h3>Film ${idx + 1}</h3>
          <button type="button" class="btn danger small" data-action="removeMovie">Fjern film</button>
        </div>
        <div class="grid cols4">
          <div class="field">
            <label>Tittel</label>
            <input type="text" class="movieTitleInput" value="${h(movie.title)}" placeholder="f.eks. Il Corleone - Il Ritorno">
          </div>
          <div class="field">
            <label>IMDb-ID</label>
            <input type="text" class="movieImdbInput" value="${h(movie.imdb_id)}" placeholder="tt...">
          </div>
          <div class="field">
            <label>TMDB-ID</label>
            <input type="text" class="movieTmdbInput" value="${h(movie.tmdb_id)}" placeholder="12345">
          </div>
          <div class="field">
            <label>Egen EAN (valgfritt)</label>
            <input type="text" class="movieInnerEanInput" value="${h(movie.inner_case_ean)}" placeholder="hvis filmen ligger i eget etui">
          </div>
        </div>
      `;

      block.querySelector(".movieTitleInput").addEventListener("input", (e) => {
        movie.title = e.target.value;
      });
      block.querySelector(".movieImdbInput").addEventListener("input", (e) => {
        movie.imdb_id = e.target.value;
      });
      block.querySelector(".movieTmdbInput").addEventListener("input", (e) => {
        movie.tmdb_id = e.target.value;
      });
      block.querySelector(".movieInnerEanInput").addEventListener("input", (e) => {
        movie.inner_case_ean = e.target.value;
      });
      block.querySelector('[data-action="removeMovie"]').addEventListener("click", () => {
        removeMixedMovie(movie.id);
      });

      mixedMoviesContainer.appendChild(block);
    });
  }

  function addMixedDisc() {
    mixedDiscSeq += 1;
    mixedDiscs.push({
      id: mixedDiscSeq,
      order: mixedDiscs.length + 1,
      format: "DVD",
      label: "",
      storage_slot_no: "",
      add_to_storage: true,
      content_refs: [], // {kind: "series"|"movie", id}
      episode_refs: [],  // {seriesId, season_number, episode_number}
    });
    renderMixedDiscs();
  }

  function removeMixedDisc(id) {
    const idx = mixedDiscs.findIndex((d) => d.id === id);
    if (idx !== -1) mixedDiscs.splice(idx, 1);
    mixedDiscs.forEach((d, i) => {
      d.order = i + 1;
    });
    renderMixedDiscs();
  }

  function contentOptionsHtml(disc) {
    const options = [];
    mixedSeries.forEach((series) => {
      const value = `series:${series.id}`;
      const label = "Serie: " + (series.title || "(uten tittel)");
      const selected = disc.content_refs.some((r) => r.kind === "series" && r.id === series.id);
      options.push(`<option value="${h(value)}"${selected ? " selected" : ""}>${h(label)}</option>`);
    });
    mixedMovies.forEach((movie) => {
      const value = `movie:${movie.id}`;
      const label = "Film: " + (movie.title || "(uten tittel)");
      const selected = disc.content_refs.some((r) => r.kind === "movie" && r.id === movie.id);
      options.push(`<option value="${h(value)}"${selected ? " selected" : ""}>${h(label)}</option>`);
    });
    if (!options.length) return '<option value="">(ingen serier/filmer lagt til)</option>';
    return options.join("");
  }

  function mixedEpisodeOptionsHtml(disc) {
    const options = [];
    mixedSeries.forEach((series) => {
      series.seasons.forEach((season) => {
        season.episodes.forEach((ep) => {
          const value = `${series.id}:${season.season_number}:${ep.episode_number}`;
          const label =
            `${series.title || "(uten tittel)"} - S${season.season_number}E${ep.episode_number}` +
            (ep.title ? ` - ${ep.title}` : "");
          const selected = disc.episode_refs.some(
            (r) =>
              r.seriesId === series.id &&
              r.season_number === season.season_number &&
              r.episode_number === ep.episode_number
          );
          options.push(`<option value="${h(value)}"${selected ? " selected" : ""}>${h(label)}</option>`);
        });
      });
    });
    if (!options.length) return '<option value="">(ingen episoder tilgjengelig)</option>';
    return options.join("");
  }

  function renderMixedDiscs() {
    // Seasons/series/movies may have changed - drop refs pointing at
    // things that no longer exist.
    mixedDiscs.forEach((disc) => {
      disc.content_refs = disc.content_refs.filter((r) =>
        r.kind === "series" ? mixedSeries.some((s) => s.id === r.id) : mixedMovies.some((m) => m.id === r.id)
      );
      disc.episode_refs = disc.episode_refs.filter((r) => {
        const series = mixedSeries.find((s) => s.id === r.seriesId);
        if (!series) return false;
        return series.seasons.some(
          (season) =>
            season.season_number === r.season_number &&
            season.episodes.some((ep) => ep.episode_number === r.episode_number)
        );
      });
    });

    mixedDiscTableBody.innerHTML = "";
    noMixedDiscsMsg.style.display = mixedDiscs.length ? "none" : "block";

    mixedDiscs.forEach((disc) => {
      const tr = document.createElement("tr");
      tr.className = "discRow";
      tr.innerHTML = `
        <td>${h(disc.order)}</td>
        <td>
          <select class="discFormatInput">
            <option value="DVD"${disc.format === "DVD" ? " selected" : ""}>DVD</option>
            <option value="Blu-ray"${disc.format === "Blu-ray" ? " selected" : ""}>Blu-ray</option>
            <option value="4K UHD"${disc.format === "4K UHD" ? " selected" : ""}>4K UHD</option>
          </select>
        </td>
        <td><input type="text" class="discLabelInput" value="${h(disc.label)}" placeholder="f.eks. Disk 1" style="width:120px;"></td>
        <td><input type="number" min="0" class="discSlotInput" value="${h(disc.storage_slot_no)}" style="width:80px;"></td>
        <td><input type="checkbox" class="discAddToStorageInput" ${disc.add_to_storage ? "checked" : ""}></td>
        <td><select multiple class="discContentInput">${contentOptionsHtml(disc)}</select></td>
        <td><select multiple class="discEpisodesInput">${mixedEpisodeOptionsHtml(disc)}</select></td>
        <td><button type="button" class="btn danger small" data-action="removeDisc">Fjern</button></td>
      `;

      tr.querySelector(".discFormatInput").addEventListener("change", (e) => {
        disc.format = e.target.value;
      });
      tr.querySelector(".discLabelInput").addEventListener("input", (e) => {
        disc.label = e.target.value;
      });
      tr.querySelector(".discSlotInput").addEventListener("input", (e) => {
        disc.storage_slot_no = e.target.value === "" ? "" : Number(e.target.value);
      });
      tr.querySelector(".discAddToStorageInput").addEventListener("change", (e) => {
        disc.add_to_storage = e.target.checked;
      });
      tr.querySelector(".discContentInput").addEventListener("change", (e) => {
        const selected = Array.from(e.target.selectedOptions).map((opt) => opt.value);
        disc.content_refs = selected.map((v) => {
          const [kind, id] = v.split(":");
          return { kind, id: Number(id) };
        });
      });
      tr.querySelector(".discEpisodesInput").addEventListener("change", (e) => {
        const selected = Array.from(e.target.selectedOptions).map((opt) => opt.value);
        disc.episode_refs = selected.map((v) => {
          const [seriesId, seasonNumber, episodeNumber] = v.split(":").map(Number);
          return { seriesId, season_number: seasonNumber, episode_number: episodeNumber };
        });
      });
      tr.querySelector('[data-action="removeDisc"]').addEventListener("click", () => {
        removeMixedDisc(disc.id);
      });

      mixedDiscTableBody.appendChild(tr);
    });
  }

  function buildMixedPayload() {
    return {
      kind: "mixed_boxset",
      box: buildBoxPayload(),
      series: mixedSeries.map((s) => ({
        title: s.title,
        imdb_id: s.imdb_id || null,
        tvdb_id: s.tvdb_id || null,
        seasons: s.seasons.map((season) => ({
          season_number: season.season_number,
          title: season.title || null,
          air_date: season.air_date || null,
          inner_case_ean: season.inner_case_ean || null,
          episodes: season.episodes.map((ep) => ({
            episode_number: ep.episode_number,
            title: ep.title || null,
            runtime: ep.runtime === "" ? null : ep.runtime,
            original_air_date: ep.original_air_date || null,
          })),
        })),
      })),
      movies: mixedMovies.map((m) => ({
        title: m.title,
        imdb_id: m.imdb_id || null,
        tmdb_id: m.tmdb_id || null,
        tvdb_id: m.tvdb_id || null,
        inner_case_ean: m.inner_case_ean || null,
      })),
      discs: mixedDiscs.map((d) => ({
        order: d.order,
        format: d.format,
        label: d.label || null,
        storage_slot_no: d.storage_slot_no === "" ? null : d.storage_slot_no,
        add_to_storage: d.add_to_storage,
        content_refs: d.content_refs.map((r) => {
          if (r.kind === "series") {
            return { kind: "series", series_index: mixedSeries.findIndex((s) => s.id === r.id) };
          }
          return { kind: "movie", movie_index: mixedMovies.findIndex((m) => m.id === r.id) };
        }),
        episode_refs: d.episode_refs.map((r) => ({
          series_index: mixedSeries.findIndex((s) => s.id === r.seriesId),
          season_number: r.season_number,
          episode_number: r.episode_number,
        })),
      })),
    };
  }

  function buildPayload() {
    return currentMode() === "mixed_boxset" ? buildMixedPayload() : buildSinglePayload();
  }

  // --- TVDB search --------------------------------------------------
  // Uses api.php's search_tvdb action (a thin proxy to TVDB v4's
  // /search endpoint). TVDB's search response already includes a
  // "remote_ids" array per result with the linked IMDb id (when TVDB
  // has one), so a single search fills in both tvdb_id and imdb_id -
  // no separate details lookup needed for this form. Generalized to
  // accept arbitrary target input/results/status elements so it can
  // be reused both for the single-mode series fields and for each
  // series block in mixed mode.
  const lightboxOverlayEl = document.getElementById("lightboxOverlay");
  const lightboxImgEl = document.getElementById("lightboxImg");

  function openLightbox(url) {
    lightboxImgEl.src = url;
    lightboxOverlayEl.classList.add("open");
  }

  function closeLightbox() {
    lightboxOverlayEl.classList.remove("open");
    lightboxImgEl.src = "";
  }

  lightboxOverlayEl.addEventListener("click", closeLightbox);

  function extractImdbId(remoteIds) {
    if (!Array.isArray(remoteIds)) return "";
    const match = remoteIds.find((r) => (r.sourceName || "").toUpperCase() === "IMDB");
    return match ? match.id : "";
  }

  async function searchTvdbFor(titleInputEl, tvdbInputEl, imdbInputEl, resultsEl, statusEl) {
    const query = titleInputEl.value.trim();
    if (!query) {
      statusEl.textContent = "Skriv inn en tittel først.";
      return;
    }

    statusEl.textContent = "Søker...";
    resultsEl.innerHTML = "";

    try {
      const res = await fetch(
        "api.php?action=search_tvdb&type=series&query=" + encodeURIComponent(query)
      );
      const data = await res.json();

      if (!res.ok) {
        statusEl.textContent = "Feilet: " + (data.error || res.status);
        return;
      }

      const results = Array.isArray(data.data) ? data.data : [];
      if (!results.length) {
        statusEl.textContent = "Ingen treff.";
        return;
      }

      statusEl.textContent = results.length + " treff:";
      results.slice(0, 10).forEach((item) => {
        const imdbId = extractImdbId(item.remote_ids);
        const row = document.createElement("div");
        row.className = "tvdbResultRow";
        const thumbUrl = item.thumbnail || item.image_url || "";
        const fullUrl = item.image_url || item.thumbnail || "";
        row.innerHTML = `
          ${thumbUrl ? `<img class="tvdbThumb" src="${h(thumbUrl)}" alt="" title="Klikk for å forstørre" style="width:46px;height:64px;object-fit:cover;border-radius:6px;flex:none;">` : `<div style="width:46px;height:64px;flex:none;background:var(--line);border-radius:6px;"></div>`}
          <div class="info" style="flex:1;">
            <strong>${h(item.name || "(uten tittel)")}</strong> ${h(item.year || "")}
            <span class="muted">tvdb_id: ${h(item.tvdb_id || "")}${imdbId ? " · imdb_id: " + h(imdbId) : " · ingen IMDb-kobling hos TVDB"}</span>
          </div>
          <button type="button" class="btn small">Velg</button>
        `;
        const thumbEl = row.querySelector(".tvdbThumb");
        if (thumbEl && fullUrl) {
          thumbEl.addEventListener("click", () => openLightbox(fullUrl));
        }
        row.querySelector("button").addEventListener("click", () => {
          titleInputEl.value = item.name || query;
          titleInputEl.dispatchEvent(new Event("input"));
          tvdbInputEl.value = item.tvdb_id || "";
          tvdbInputEl.dispatchEvent(new Event("input"));
          if (imdbId) {
            imdbInputEl.value = imdbId;
            imdbInputEl.dispatchEvent(new Event("input"));
          }
          resultsEl.innerHTML = "";
          statusEl.textContent = "Valgt: " + (item.name || query);
        });
        resultsEl.appendChild(row);
      });
    } catch (err) {
      statusEl.textContent = "Feilet: " + err.message;
    }
  }

  document.getElementById("searchTvdbBtn").addEventListener("click", () => {
    searchTvdbFor(
      document.getElementById("seriesTitle"),
      document.getElementById("seriesTvdb"),
      document.getElementById("seriesImdb"),
      tvdbResultsEl(),
      document.getElementById("tvdbSearchStatus")
    );
  });

  function tvdbResultsEl() {
    return document.getElementById("tvdbResults");
  }

  document.getElementById("addSeasonBtn").addEventListener("click", addSeason);
  document.getElementById("addDiscBtn").addEventListener("click", addDisc);
  document.getElementById("addMixedSeriesBtn").addEventListener("click", addMixedSeries);
  document.getElementById("addMixedMovieBtn").addEventListener("click", addMixedMovie);
  document.getElementById("addMixedDiscBtn").addEventListener("click", addMixedDisc);

  document.getElementById("previewBtn").addEventListener("click", () => {
    const payload = buildPayload();
    document.getElementById("payloadPreview").textContent = JSON.stringify(payload, null, 2);
  });

  document.getElementById("copyBtn").addEventListener("click", () => {
    const text = document.getElementById("payloadPreview").textContent;
    if (!text || text === "(ikke generert ennå)") return;
    navigator.clipboard.writeText(text).catch(() => {});
  });

  /**
   * Posts a payload object to the backend import endpoint via api.php,
   * updating the given button (disabled while in flight) and status
   * element with the result. Shared by both the "build from form" submit
   * button and the "paste raw JSON" submit button.
   */
  async function submitToBackend(payload, submitBtn, statusEl) {
    submitBtn.disabled = true;
    statusEl.textContent = "Sender inn...";
    statusEl.style.color = "";

    try {
      const res = await fetch("api.php?action=submit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => null);

      if (!res.ok) {
        const msg = (data && (data.error || data.detail)) || `Feil (HTTP ${res.status})`;
        statusEl.textContent = "Feil: " + (typeof msg === "string" ? msg : JSON.stringify(msg));
        statusEl.style.color = "var(--danger)";
        return;
      }

      statusEl.textContent = "Importert OK: " + JSON.stringify(data);
      statusEl.style.color = "var(--accent2)";
    } catch (err) {
      statusEl.textContent = "Nettverksfeil: " + err.message;
      statusEl.style.color = "var(--danger)";
    } finally {
      submitBtn.disabled = false;
    }
  }

  document.getElementById("submitBtn").addEventListener("click", () => {
    const payload = buildPayload();
    document.getElementById("payloadPreview").textContent = JSON.stringify(payload, null, 2);
    submitToBackend(
      payload,
      document.getElementById("submitBtn"),
      document.getElementById("submitStatus")
    );
  });

  document.getElementById("submitPasteBtn").addEventListener("click", () => {
    const statusEl = document.getElementById("pasteSubmitStatus");
    const raw = document.getElementById("pastePayloadInput").value;
    let payload;
    try {
      payload = JSON.parse(raw);
    } catch (err) {
      statusEl.textContent = "Ugyldig JSON: " + err.message;
      statusEl.style.color = "var(--danger)";
      return;
    }
    submitToBackend(payload, document.getElementById("submitPasteBtn"), statusEl);
  });

  // Start single mode with one season (with 1 episode) and one disc
  // pre-filled, to make the shape clearer.
  addSeason();
  addDisc();
  updateModeVisibility();
})();

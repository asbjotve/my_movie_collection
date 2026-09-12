// tv_series_add_form v1 - draft/prototype only.
// Manual entry + JSON payload preview, no backend submission yet.
// TVDB search (api.php's search_tvdb action) fills in title/tvdb_id/
// imdb_id for the series - see extractImdbId()/searchTvdb() below.

(function () {
  "use strict";

  let seasonSeq = 0;
  let discSeq = 0;

  const seasons = []; // { id, season_number, title, air_date, inner_case_ean, episodes: [{episode_number, title, runtime, original_air_date}] }
  const discs = [];   // { id, order, format, label, storage_slot_no, add_to_storage, episode_refs: [{season_number, episode_number}] }

  const seasonsContainer = document.getElementById("seasonsContainer");
  const noSeasonsMsg = document.getElementById("noSeasonsMsg");
  const discTableBody = document.getElementById("discTableBody");
  const noDiscsMsg = document.getElementById("noDiscsMsg");
  const tvdbResultsEl = document.getElementById("tvdbResults");
  const tvdbSearchStatusEl = document.getElementById("tvdbSearchStatus");

  function h(s) {
    const div = document.createElement("div");
    div.textContent = s == null ? "" : String(s);
    return div.innerHTML;
  }

  function addSeason() {
    seasonSeq += 1;
    const season = {
      id: seasonSeq,
      season_number: seasons.length + 1,
      title: "",
      air_date: "",
      inner_case_ean: "",
      episodes: [],
    };
    seasons.push(season);
    setEpisodeCount(season, 1);
    renderSeasons();
    renderDiscEpisodeOptions();
  }

  function removeSeason(id) {
    const idx = seasons.findIndex((s) => s.id === id);
    if (idx !== -1) seasons.splice(idx, 1);
    renderSeasons();
    renderDiscEpisodeOptions();
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

  function renderSeasons() {
    seasonsContainer.innerHTML = "";
    noSeasonsMsg.style.display = seasons.length ? "none" : "block";

    seasons.forEach((season) => {
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
        renderDiscEpisodeOptions();
      });
      block.querySelector(".seasonTitleInput").addEventListener("input", (e) => {
        season.title = e.target.value;
      });
      block.querySelector(".seasonEpisodeCountInput").addEventListener("change", (e) => {
        setEpisodeCount(season, e.target.value);
        renderSeasons();
        renderDiscEpisodeOptions();
      });
      block.querySelector(".seasonInnerEanInput").addEventListener("input", (e) => {
        season.inner_case_ean = e.target.value;
      });
      block.querySelector('[data-action="removeSeason"]').addEventListener("click", () => {
        removeSeason(season.id);
      });

      seasonsContainer.appendChild(block);
    });
  }

  function addDisc() {
    discSeq += 1;
    discs.push({
      id: discSeq,
      order: discs.length + 1,
      format: "DVD",
      label: "",
      season_id: seasons.length ? seasons[0].id : null,
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

  function seasonOptionsHtml(selectedSeasonId) {
    if (!seasons.length) {
      return '<option value="">(ingen sesonger lagt til)</option>';
    }
    return seasons
      .map((season) => {
        const label =
          `Sesong ${season.season_number}` +
          (season.inner_case_ean ? ` (eget etui, EAN ${season.inner_case_ean})` : "");
        const selected = season.id === selectedSeasonId ? " selected" : "";
        return `<option value="${h(season.id)}"${selected}>${h(label)}</option>`;
      })
      .join("");
  }

  function episodeOptionsHtml(seasonId, selectedRefs) {
    const season = seasons.find((s) => s.id === seasonId);
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
      if (!seasons.some((s) => s.id === disc.season_id)) {
        disc.season_id = seasons.length ? seasons[0].id : null;
        disc.episode_refs = [];
      } else {
        const season = seasons.find((s) => s.id === disc.season_id);
        disc.episode_refs = disc.episode_refs.filter((r) =>
          season.episodes.some(
            (ep) => ep.episode_number === r.episode_number && season.season_number === r.season_number
          )
        );
      }
    });
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
        <td><select multiple class="discEpisodesInput">${episodeOptionsHtml(disc.season_id, disc.episode_refs)}</select></td>
        <td><button type="button" class="btn danger small" data-action="removeDisc">Fjern</button></td>
      `;

      tr.querySelector(".discFormatInput").addEventListener("change", (e) => {
        disc.format = e.target.value;
      });
      tr.querySelector(".discLabelInput").addEventListener("input", (e) => {
        disc.label = e.target.value;
      });
      tr.querySelector(".discSeasonInput").addEventListener("change", (e) => {
        disc.season_id = Number(e.target.value);
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
        const season = seasons.find((s) => s.id === disc.season_id);
        const selected = Array.from(e.target.selectedOptions).map((opt) => opt.value);
        disc.episode_refs = selected.map((v) => {
          const [seasonNumber, episodeNumber] = v.split(":").map(Number);
          return { season_number: seasonNumber, episode_number: episodeNumber };
        });
      });
      tr.querySelector('[data-action="removeDisc"]').addEventListener("click", () => {
        removeDisc(disc.id);
      });

      discTableBody.appendChild(tr);
    });
  }

  function buildPayload() {
    return {
      kind: "tv_series_boxset",
      series: {
        title: document.getElementById("seriesTitle").value || null,
        imdb_id: document.getElementById("seriesImdb").value || null,
        tvdb_id: document.getElementById("seriesTvdb").value || null,
        content_type: "series",
      },
      box: {
        format: document.getElementById("boxFormat").value,
        box_set_barcode: document.getElementById("boxBarcode").value || null,
        storage_id: document.getElementById("storageId").value || null,
        copy_count: Number(document.getElementById("copyCount").value) || 1,
      },
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
        const season = seasons.find((s) => s.id === d.season_id);
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

  // --- TVDB search --------------------------------------------------
  // Uses api.php's search_tvdb action (a thin proxy to TVDB v4's
  // /search endpoint). TVDB's search response already includes a
  // "remote_ids" array per result with the linked IMDb id (when TVDB
  // has one), so a single search fills in both tvdb_id and imdb_id -
  // no separate details lookup needed for this form.
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

  async function searchTvdb() {
    const query = document.getElementById("seriesTitle").value.trim();
    if (!query) {
      tvdbSearchStatusEl.textContent = "Skriv inn en tittel først.";
      return;
    }

    tvdbSearchStatusEl.textContent = "Søker...";
    tvdbResultsEl.innerHTML = "";

    try {
      const res = await fetch(
        "api.php?action=search_tvdb&type=series&query=" + encodeURIComponent(query)
      );
      const data = await res.json();

      if (!res.ok) {
        tvdbSearchStatusEl.textContent = "Feilet: " + (data.error || res.status);
        return;
      }

      const results = Array.isArray(data.data) ? data.data : [];
      if (!results.length) {
        tvdbSearchStatusEl.textContent = "Ingen treff.";
        return;
      }

      tvdbSearchStatusEl.textContent = results.length + " treff:";
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
          document.getElementById("seriesTitle").value = item.name || query;
          document.getElementById("seriesTvdb").value = item.tvdb_id || "";
          if (imdbId) {
            document.getElementById("seriesImdb").value = imdbId;
          }
          tvdbResultsEl.innerHTML = "";
          tvdbSearchStatusEl.textContent = "Valgt: " + (item.name || query);
        });
        tvdbResultsEl.appendChild(row);
      });
    } catch (err) {
      tvdbSearchStatusEl.textContent = "Feilet: " + err.message;
    }
  }

  document.getElementById("searchTvdbBtn").addEventListener("click", searchTvdb);

  document.getElementById("addSeasonBtn").addEventListener("click", addSeason);
  document.getElementById("addDiscBtn").addEventListener("click", addDisc);

  document.getElementById("previewBtn").addEventListener("click", () => {
    const payload = buildPayload();
    document.getElementById("payloadPreview").textContent = JSON.stringify(payload, null, 2);
  });

  document.getElementById("copyBtn").addEventListener("click", () => {
    const text = document.getElementById("payloadPreview").textContent;
    if (!text || text === "(ikke generert ennå)") return;
    navigator.clipboard.writeText(text).catch(() => {});
  });

  // Start with one season (with 1 episode) and one disc pre-filled, to
  // make the shape clearer.
  addSeason();
  addDisc();
})();

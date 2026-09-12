// tv_series_add_form v1 - draft/prototype only.
// Manual entry + JSON payload preview, no backend submission yet.

(function () {
  "use strict";

  let seasonSeq = 0;
  let discSeq = 0;

  const seasons = []; // { id, season_number, title, air_date, episodes: [{id, episode_number, title, runtime, original_air_date}] }
  const discs = [];   // { id, order, format, label, storage_slot_no, add_to_storage, episode_refs: [{season_number, episode_number}] }

  const seasonsContainer = document.getElementById("seasonsContainer");
  const noSeasonsMsg = document.getElementById("noSeasonsMsg");
  const discTableBody = document.getElementById("discTableBody");
  const noDiscsMsg = document.getElementById("noDiscsMsg");

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
      episodes: [],
    };
    seasons.push(season);
    renderSeasons();
    renderDiscEpisodeOptions();
  }

  function removeSeason(id) {
    const idx = seasons.findIndex((s) => s.id === id);
    if (idx !== -1) seasons.splice(idx, 1);
    renderSeasons();
    renderDiscEpisodeOptions();
  }

  function addEpisode(seasonId) {
    const season = seasons.find((s) => s.id === seasonId);
    if (!season) return;
    season.episodes.push({
      episode_number: season.episodes.length + 1,
      title: "",
      runtime: "",
      original_air_date: "",
    });
    renderSeasons();
    renderDiscEpisodeOptions();
  }

  function removeEpisode(seasonId, epIdx) {
    const season = seasons.find((s) => s.id === seasonId);
    if (!season) return;
    season.episodes.splice(epIdx, 1);
    renderSeasons();
    renderDiscEpisodeOptions();
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
        <div class="grid cols2" style="margin-bottom:10px;">
          <div class="field">
            <label>Sesongnummer</label>
            <input type="number" min="0" class="seasonNumberInput" value="${h(season.season_number)}">
          </div>
          <div class="field">
            <label>Sesongtittel (valgfritt)</label>
            <input type="text" class="seasonTitleInput" value="${h(season.title)}" placeholder="f.eks. Season 1">
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Ep. nr</th>
              <th>Tittel</th>
              <th>Varighet (min)</th>
              <th>Opprinnelig sendedato</th>
              <th></th>
            </tr>
          </thead>
          <tbody class="episodeTableBody"></tbody>
        </table>
        <button type="button" class="btn small" data-action="addEpisode" style="margin-top:8px;">+ Legg til episode</button>
      `;

      const epBody = block.querySelector(".episodeTableBody");
      season.episodes.forEach((ep, epIdx) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td><input type="number" min="0" class="epNumberInput" value="${h(ep.episode_number)}" style="width:70px;"></td>
          <td><input type="text" class="epTitleInput" value="${h(ep.title)}" placeholder="Episodetittel"></td>
          <td><input type="number" min="0" class="epRuntimeInput" value="${h(ep.runtime)}" style="width:80px;"></td>
          <td><input type="date" class="epAirDateInput" value="${h(ep.original_air_date)}"></td>
          <td><button type="button" class="btn danger small" data-action="removeEpisode">Fjern</button></td>
        `;
        tr.querySelector(".epNumberInput").addEventListener("input", (e) => {
          ep.episode_number = e.target.value === "" ? "" : Number(e.target.value);
          renderDiscEpisodeOptions();
        });
        tr.querySelector(".epTitleInput").addEventListener("input", (e) => {
          ep.title = e.target.value;
          renderDiscEpisodeOptions();
        });
        tr.querySelector(".epRuntimeInput").addEventListener("input", (e) => {
          ep.runtime = e.target.value === "" ? "" : Number(e.target.value);
        });
        tr.querySelector(".epAirDateInput").addEventListener("input", (e) => {
          ep.original_air_date = e.target.value;
        });
        tr.querySelector('[data-action="removeEpisode"]').addEventListener("click", () => {
          removeEpisode(season.id, epIdx);
        });
        epBody.appendChild(tr);
      });

      block.querySelector(".seasonNumberInput").addEventListener("input", (e) => {
        season.season_number = e.target.value === "" ? "" : Number(e.target.value);
        block.querySelector(".seasonNumberLabel").textContent = season.season_number;
        renderDiscEpisodeOptions();
      });
      block.querySelector(".seasonTitleInput").addEventListener("input", (e) => {
        season.title = e.target.value;
      });
      block.querySelector('[data-action="removeSeason"]').addEventListener("click", () => {
        removeSeason(season.id);
      });
      block.querySelector('[data-action="addEpisode"]').addEventListener("click", () => {
        addEpisode(season.id);
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

  function episodeOptionsHtml(selectedRefs) {
    const options = [];
    seasons.forEach((season) => {
      season.episodes.forEach((ep) => {
        const value = `${season.season_number}:${ep.episode_number}`;
        const label = `S${season.season_number}E${ep.episode_number}` + (ep.title ? ` - ${ep.title}` : "");
        const selected = selectedRefs.some(
          (r) => r.season_number === season.season_number && r.episode_number === ep.episode_number
        );
        options.push(
          `<option value="${h(value)}"${selected ? " selected" : ""}>${h(label)}</option>`
        );
      });
    });
    return options.join("");
  }

  function renderDiscEpisodeOptions() {
    // Re-render discs so the episode multi-select reflects current seasons/episodes.
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
        <td><input type="number" min="0" class="discSlotInput" value="${h(disc.storage_slot_no)}" style="width:80px;"></td>
        <td><input type="checkbox" class="discAddToStorageInput" ${disc.add_to_storage ? "checked" : ""}></td>
        <td><select multiple class="discEpisodesInput">${episodeOptionsHtml(disc.episode_refs)}</select></td>
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
      tr.querySelector(".discEpisodesInput").addEventListener("change", (e) => {
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
        episodes: s.episodes.map((ep) => ({
          episode_number: ep.episode_number,
          title: ep.title || null,
          runtime: ep.runtime === "" ? null : ep.runtime,
          original_air_date: ep.original_air_date || null,
        })),
      })),
      discs: discs.map((d) => ({
        order: d.order,
        format: d.format,
        label: d.label || null,
        storage_slot_no: d.storage_slot_no === "" ? null : d.storage_slot_no,
        add_to_storage: d.add_to_storage,
        episode_refs: d.episode_refs,
      })),
    };
  }

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

  // Start with one season and one disc pre-filled, to make the shape clearer.
  addSeason();
  addEpisode(1);
  addDisc();
})();

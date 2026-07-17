<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk add – Singles & Box sets (multi) + Discs + Bonus items</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .table td, .table th { vertical-align: middle; }
    .btn-xs { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .4rem; --bs-btn-font-size: .75rem; }
    .muted { color: var(--bs-secondary-color); }
    .nowrap { white-space: nowrap; }
  </style>
</head>

<body class="bg-light">
  <div class="container py-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0">Bulk add</h1>
      <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#help">
        Help
      </button>
    </div>

    <div class="collapse mb-3" id="help">
      <div class="card card-body">
        <ul class="mb-0">
          <li><strong>Discs</strong> are stored per row (single release row, or a box set disc row) as JSON in this prototype.</li>
          <li><strong>Bonus items</strong> are stored per disc and correspond to <span class="mono">disc_bonus_item</span>.</li>
          <li>Intended semantics: <span class="mono">type_disc=feature</span> for main movie discs, <span class="mono">bonus</span> for extras.</li>
        </ul>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="releaseTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
          Single releases
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="box-tab" data-bs-toggle="tab" data-bs-target="#box" type="button" role="tab" aria-controls="box" aria-selected="false">
          Box sets (multi)
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white p-3 rounded-bottom">

      <!-- =========================
           SINGLE RELEASES
      ========================== -->
      <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label" for="singleFormat">Format</label>
            <select class="form-select" id="singleFormat">
              <option value="DVD">DVD</option>
              <option value="BD" selected>Blu-ray</option>
              <option value="UHD">4K UHD</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="singleCopyCount">Default copy count</label>
            <input type="number" class="form-control" id="singleCopyCount" value="1" min="1" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="singleQuickImport">Quick add (title)</label>
            <div class="input-group">
              <input class="form-control" id="singleQuickImport" placeholder="e.g. Hidalgo" />
              <button class="btn btn-outline-primary" type="button" id="btnSingleAddFromText">Add</button>
            </div>
            <div class="form-text">Adds a new empty row with the title filled in.</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">Titles</h2>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" type="button" id="btnSingleAddRow">Add row</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnPreviewAll">Preview payload</button>
            <button class="btn btn-sm btn-success" type="button" disabled>Submit</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="singleTable">
            <thead class="table-light">
              <tr>
                <th style="min-width: 240px;">Title</th>
                <th style="min-width: 170px;">Barcode (EAN-13)</th>
                <th style="min-width: 180px;">IMDb ID (optional)</th>
                <th style="width: 230px;">Discs (feature/bonus)</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="alert alert-info">
          Click <strong>Discs</strong> to add feature/bonus discs for a single release, and edit bonus-items per disc.
        </div>
      </div>

      <!-- =========================
           BOX SETS (MULTI)
      ========================== -->
      <div class="tab-pane fade" id="box" role="tabpanel" aria-labelledby="box-tab">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h2 class="h5 mb-1">Box sets</h2>
            <div class="text-muted small">Add multiple box sets. Bonus items can be edited per disc.</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" type="button" id="btnAddBoxSet">Add box set</button>
            <button class="btn btn-outline-secondary" type="button" id="btnPreviewAllBoxSets">Preview box sets</button>
            <button class="btn btn-success" type="button" disabled>Submit all</button>
          </div>
        </div>

        <div class="accordion" id="boxSetsAccordion"></div>

      </div>

    </div>

    <!-- =========================
         MODALS
    ========================== -->

    <!-- Paste list modal -->
    <div class="modal fade" id="pasteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Paste movie list</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label" for="boxPasteArea">One movie per line</label>
            <textarea class="form-control" id="boxPasteArea" rows="8" placeholder="Fellowship of the Ring&#10;The Two Towers&#10;The Return of the King"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="button" id="btnBoxPasteApply" data-bs-dismiss="modal">Add</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Disc editor modal (used for singles) -->
    <div class="modal fade" id="discModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1">Discs for single release</h5>
              <div class="small muted" id="discModalSubtitle">—</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">
                Add <span class="mono">feature</span> and/or <span class="mono">bonus</span> discs. Bonus items can be edited per disc.
              </div>
              <button class="btn btn-sm btn-primary" type="button" id="btnAddDiscEditorRow">Add disc</button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle" id="discEditorTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 120px;">Type</th>
                    <th style="width: 140px;">Format</th>
                    <th>Label (optional)</th>
                    <th style="width: 220px;">Bonus items…</th>
                    <th style="width: 60px;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="button" id="btnSaveDiscsForSingle" data-bs-dismiss="modal">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bonus items editor modal (used for both singles + box-set disc rows) -->
    <div class="modal fade" id="bonusModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div class="d-flex align-items-start gap-2 w-100">
              <div>
                <h5 class="modal-title mb-1" id="bonusModalTitle">Bonus disc contents</h5>
                <div class="small muted" id="bonusModalSubtitle">—</div>
              </div>
              <div class="ms-auto">
                <span class="badge text-bg-secondary" id="bonusModalBadge">DISC</span>
              </div>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-warning py-2 d-none" id="bonusNotBonusAlert">
              This disc is not marked as <span class="mono">bonus</span>. You can still add tracks, but it might be inconsistent.
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">
                Stored as: <span class="mono">(disc_id, seq_no)</span> in <span class="mono">disc_bonus_item</span>.
              </div>
              <button class="btn btn-sm btn-primary" type="button" id="btnAddBonusItemRow">Add track</button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle" id="bonusItemsTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 90px;">Seq</th>
                    <th style="min-width: 280px;">Title</th>
                    <th style="width: 180px;">Type</th>
                    <th style="width: 170px;">Runtime (sec)</th>
                    <th style="min-width: 260px;">Notes</th>
                    <th style="width: 60px;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="button" id="btnSaveBonusItems" data-bs-dismiss="modal">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Preview payload</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <pre class="bg-light border rounded p-3 mb-0" style="max-height: 70vh; overflow: auto;" id="previewPre"></pre>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // =========================================================================
    // Helpers
    // =========================================================================
    function el(html) {
      const t = document.createElement('template');
      t.innerHTML = html.trim();
      return t.content.firstChild;
    }

    function escapeHtml(s) {
      return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function showPreview(obj) {
      document.getElementById('previewPre').textContent = JSON.stringify(obj, null, 2);
      new bootstrap.Modal(document.getElementById('previewModal')).show();
    }

    function getJsonAttr(node, attrName, fallback) {
      const raw = node.getAttribute(attrName);
      if (!raw) return fallback;
      try { return JSON.parse(raw); } catch { return fallback; }
    }

    function setJsonAttr(node, attrName, value) {
      node.setAttribute(attrName, JSON.stringify(value ?? null));
    }

    // =========================================================================
    // Bonus modal (shared)
    // =========================================================================
    const bonusModalEl = document.getElementById('bonusModal');
    const bonusItemsTbody = document.querySelector('#bonusItemsTable tbody');
    const bonusNotBonusAlert = document.getElementById('bonusNotBonusAlert');
    const bonusModalSubtitle = document.getElementById('bonusModalSubtitle');
    const bonusModalTitle = document.getElementById('bonusModalTitle');
    const bonusModalBadge = document.getElementById('bonusModalBadge');

    let bonusTarget = null; // { node: <tr> }

    function clearBonusTable() { bonusItemsTbody.innerHTML = ''; }

    function addBonusItemEditorRow({seq_no="", title="", item_type="featurette", runtime_seconds="", notes=""} = {}) {
      const row = el(`
        <tr>
          <td><input class="form-control form-control-sm" name="seq_no" type="number" min="1" placeholder="1" value="${escapeHtml(seq_no)}"></td>
          <td><input class="form-control form-control-sm" name="title" placeholder="Title" value="${escapeHtml(title)}"></td>
          <td>
            <select class="form-select form-select-sm" name="item_type">
              <option value="featurette">featurette</option>
              <option value="documentary">documentary</option>
              <option value="deleted_scene">deleted_scene</option>
              <option value="trailer">trailer</option>
              <option value="interview">interview</option>
              <option value="commentary">commentary</option>
              <option value="short">short</option>
              <option value="other">other</option>
            </select>
          </td>
          <td><input class="form-control form-control-sm" name="runtime_seconds" type="number" min="0" placeholder="e.g. 600" value="${escapeHtml(runtime_seconds)}"></td>
          <td><input class="form-control form-control-sm" name="notes" placeholder="optional" value="${escapeHtml(notes)}"></td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove track">✕</button></td>
        </tr>
      `);
      row.querySelector('select[name="item_type"]').value = item_type || 'featurette';
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      bonusItemsTbody.appendChild(row);
    }

    function openBonusEditor({targetRow, discType, subtitle}) {
      bonusTarget = { node: targetRow };

      bonusModalSubtitle.textContent = subtitle;

      const isBonus = (discType === 'bonus');
      bonusModalTitle.textContent = isBonus ? 'Bonus disc contents (track list)' : 'Disc contents (track list)';
      bonusModalBadge.textContent = isBonus ? 'BONUS DISC' : 'DISC';
      bonusModalBadge.className = 'badge ' + (isBonus ? 'text-bg-warning' : 'text-bg-secondary');

      const header = bonusModalEl.querySelector('.modal-header');
      header.classList.toggle('bg-warning-subtle', isBonus);

      bonusNotBonusAlert.classList.toggle('d-none', isBonus);

      clearBonusTable();
      const existing = getJsonAttr(targetRow, 'data-bonus-items', []);
      (existing || []).forEach(item => addBonusItemEditorRow(item));
      if (!existing || existing.length === 0) addBonusItemEditorRow({seq_no: 1});

      new bootstrap.Modal(bonusModalEl).show();
    }

    document.getElementById('btnAddBonusItemRow').addEventListener('click', () => {
      const seqs = [...bonusItemsTbody.querySelectorAll('input[name="seq_no"]')]
        .map(x => Number(x.value))
        .filter(n => Number.isFinite(n) && n > 0);
      const next = seqs.length ? Math.max(...seqs) + 1 : 1;
      addBonusItemEditorRow({seq_no: next});
    });

    document.getElementById('btnSaveBonusItems').addEventListener('click', () => {
      if (!bonusTarget?.node) return;

      const items = [...bonusItemsTbody.querySelectorAll('tr')].map(tr => {
        const seq = Number(tr.querySelector('input[name="seq_no"]').value || 0);
        const title = tr.querySelector('input[name="title"]').value.trim();
        const item_type = tr.querySelector('select[name="item_type"]').value;
        const runtime_seconds_raw = tr.querySelector('input[name="runtime_seconds"]').value;
        const runtime_seconds = runtime_seconds_raw.trim() === "" ? null : Number(runtime_seconds_raw);
        const notes = tr.querySelector('input[name="notes"]').value.trim();
        return { seq_no: seq || null, title, item_type, runtime_seconds, notes: notes || null };
      });

      const cleaned = items.filter(x => (x.title && x.title.trim()) || x.seq_no);
      cleaned.sort((a, b) => (a.seq_no ?? 0) - (b.seq_no ?? 0));
      setJsonAttr(bonusTarget.node, 'data-bonus-items', cleaned);

      const btn = bonusTarget.node.querySelector('button[data-action="edit-bonus"]');
      if (btn) btn.textContent = cleaned.length ? `Edit (${cleaned.length})` : 'Edit';

      bonusTarget = null;
      clearBonusTable();
    });

    // =========================================================================
    // Singles (with disc modal)
    // =========================================================================
    const singleTbody = document.querySelector('#singleTable tbody');

    const discEditorTbody = document.querySelector('#discEditorTable tbody');
    const discModalSubtitle = document.getElementById('discModalSubtitle');
    let discModalTargetSingleRow = null;

    function clearDiscEditorTable() { discEditorTbody.innerHTML = ''; }

    function updateSingleDiscButtonLabel(singleRow) {
      const discs = getJsonAttr(singleRow, 'data-discs', []);
      const btn = singleRow.querySelector('button[data-action="edit-discs"]');
      if (!btn) return;
      const n = Array.isArray(discs) ? discs.length : 0;
      btn.textContent = n ? `Discs (${n})` : 'Discs';
    }

    function addDiscEditorRow({type_disc="feature", format="BD", label="", bonus_items=[]} = {}) {
      const row = el(`
        <tr data-bonus-items="[]">
          <td>
            <select class="form-select form-select-sm" name="type_disc">
              <option value="feature">feature</option>
              <option value="bonus">bonus</option>
            </select>
          </td>
          <td>
            <select class="form-select form-select-sm" name="format">
              <option value="DVD">DVD</option>
              <option value="BD" selected>BD</option>
              <option value="UHD">UHD</option>
            </select>
          </td>
          <td><input class="form-control form-control-sm" name="label" placeholder="optional label" value="${escapeHtml(label)}"></td>
          <td class="nowrap">
            <button class="btn btn-xs btn-outline-secondary" type="button" data-action="edit-bonus">Edit</button>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove disc">✕</button></td>
        </tr>
      `);

      row.querySelector('select[name="type_disc"]').value = type_disc || 'feature';
      row.querySelector('select[name="format"]').value = format || 'BD';
      setJsonAttr(row, 'data-bonus-items', bonus_items || []);

      const updateBonusLabel = () => {
        const items = getJsonAttr(row, 'data-bonus-items', []);
        const btn = row.querySelector('button[data-action="edit-bonus"]');
        btn.textContent = (items && items.length) ? `Edit (${items.length})` : 'Edit';
      };
      updateBonusLabel();

      row.querySelector('button[data-action="edit-bonus"]').addEventListener('click', () => {
        const discType = row.querySelector('select[name="type_disc"]').value;
        const discFormat = row.querySelector('select[name="format"]').value;
        const discLabel = row.querySelector('input[name="label"]').value.trim();
        openBonusEditor({
          targetRow: row,
          discType,
          subtitle: `Single disc · type=${discType} · format=${discFormat}` + (discLabel ? ` · label="${discLabel}"` : '')
        });
      });

      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      discEditorTbody.appendChild(row);
    }

    function openDiscModalForSingleRow(singleRow) {
      discModalTargetSingleRow = singleRow;
      clearDiscEditorTable();

      const title = singleRow.querySelector('input[name="title"]').value.trim() || '(untitled)';
      const barcode = singleRow.querySelector('input[name="barcode"]').value.trim() || '(no barcode)';
      discModalSubtitle.textContent = `${title} · EAN ${barcode}`;

      const existingDiscs = getJsonAttr(singleRow, 'data-discs', []);
      (existingDiscs || []).forEach(d => addDiscEditorRow(d));

      if (!existingDiscs || existingDiscs.length === 0) {
        addDiscEditorRow({type_disc: 'feature', format: document.getElementById('singleFormat').value});
      }

      new bootstrap.Modal(document.getElementById('discModal')).show();
    }

    document.getElementById('btnAddDiscEditorRow').addEventListener('click', () => {
      addDiscEditorRow({type_disc: 'feature', format: document.getElementById('singleFormat').value});
    });

    document.getElementById('btnSaveDiscsForSingle').addEventListener('click', () => {
      if (!discModalTargetSingleRow) return;

      const discs = [...discEditorTbody.querySelectorAll('tr')].map(tr => {
        const type_disc = tr.querySelector('select[name="type_disc"]').value;
        const format = tr.querySelector('select[name="format"]').value;
        const label = tr.querySelector('input[name="label"]').value.trim();
        const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);
        return { type_disc, format, label: label || null, bonus_items };
      });

      setJsonAttr(discModalTargetSingleRow, 'data-discs', discs);
      updateSingleDiscButtonLabel(discModalTargetSingleRow);

      discModalTargetSingleRow = null;
      clearDiscEditorTable();
    });

    function addSingleRow({title = "", barcode = "", imdb = ""} = {}) {
      const row = el(`
        <tr data-discs="[]">
          <td><input class="form-control form-control-sm" name="title" placeholder="Title" value="${escapeHtml(title)}"></td>
          <td><input class="form-control form-control-sm" name="barcode" placeholder="EAN-13" value="${escapeHtml(barcode)}"></td>
          <td><input class="form-control form-control-sm" name="imdb" placeholder="tt1234567" value="${escapeHtml(imdb)}"></td>
          <td class="nowrap">
            <button class="btn btn-xs btn-outline-primary" type="button" data-action="edit-discs">Discs</button>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove row">✕</button></td>
        </tr>
      `);

      row.querySelector('button[data-action="edit-discs"]').addEventListener('click', () => openDiscModalForSingleRow(row));
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());

      singleTbody.appendChild(row);
      updateSingleDiscButtonLabel(row);
    }

    function readSinglesForm() {
      const rows = [...singleTbody.querySelectorAll('tr')].map(tr => {
        const title = tr.querySelector('input[name="title"]').value.trim();
        const barcode = tr.querySelector('input[name="barcode"]').value.trim();
        const imdb = tr.querySelector('input[name="imdb"]').value.trim();
        const discs = getJsonAttr(tr, 'data-discs', []);
        return { title, barcode, imdb_id: imdb || null, discs };
      });

      return {
        kind: "singles",
        format: document.getElementById('singleFormat').value,
        default_copy_count: Number(document.getElementById('singleCopyCount').value || 1),
        rows
      };
    }

    document.getElementById('btnSingleAddRow').addEventListener('click', () => addSingleRow());
    document.getElementById('btnSingleAddFromText').addEventListener('click', () => {
      const v = document.getElementById('singleQuickImport').value.trim();
      if (v) addSingleRow({title: v});
      document.getElementById('singleQuickImport').value = "";
    });

    // =========================================================================
    // Box sets (multi) (with bonus items per disc)
    // =========================================================================
    const accordion = document.getElementById('boxSetsAccordion');
    let boxSetSeq = 0;
    let pasteTargetBoxSetId = null;

    function renumberOrders(tbody) {
      [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
        const c = tr.querySelector('[data-order]');
        if (c) c.textContent = i + 1;
      });
    }

    function refreshRelatedDropdown(boxSetRoot) {
      const titles = [...boxSetRoot.querySelectorAll('tbody[data-role="movies"] tr')]
        .map(tr => tr.querySelector('input[name="title"]').value.trim());

      const sel = boxSetRoot.querySelector('select[name="disc_related"]');
      sel.innerHTML =
        '<option value="">(whole box)</option>' +
        titles.map((t, i) => `<option value="${i}">${escapeHtml(t || `(untitled #${i+1})`)}</option>`).join('');
    }

    function addMovieRowBox(boxSetRoot, {title="", imdb="", innerEan="", treatAsSingle=null} = {}) {
      if (treatAsSingle === null) treatAsSingle = Boolean(innerEan && innerEan.trim());

      const tbody = boxSetRoot.querySelector('tbody[data-role="movies"]');
      const row = el(`
        <tr>
          <td data-order class="text-muted small">-</td>
          <td><input class="form-control form-control-sm" name="title" placeholder="Title" value="${escapeHtml(title)}"></td>
          <td><input class="form-control form-control-sm" name="imdb" placeholder="tt1234567" value="${escapeHtml(imdb)}"></td>
          <td><input class="form-control form-control-sm" name="inner_ean" placeholder="EAN-13" value="${escapeHtml(innerEan)}"></td>
          <td>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="treat_as_single" ${treatAsSingle ? "checked" : ""}>
              <label class="form-check-label small text-muted">Create single</label>
            </div>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove movie">✕</button></td>
        </tr>
      `);

      const titleInput = row.querySelector('input[name="title"]');
      const innerEanInput = row.querySelector('input[name="inner_ean"]');
      const treatCb = row.querySelector('input[name="treat_as_single"]');

      row.querySelector('.btn-outline-danger').addEventListener('click', () => {
        row.remove();
        renumberOrders(tbody);
        refreshRelatedDropdown(boxSetRoot);
      });

      titleInput.addEventListener('input', () => refreshRelatedDropdown(boxSetRoot));
      innerEanInput.addEventListener('input', () => { if (innerEanInput.value.trim()) treatCb.checked = true; });

      tbody.appendChild(row);
      renumberOrders(tbody);
      refreshRelatedDropdown(boxSetRoot);
    }

    function addDiscRowBox(boxSetRoot, {typeDisc="feature", format="BD", relatedTitle=""} = {}) {
      const tbody = boxSetRoot.querySelector('tbody[data-role="discs"]');
      const order = tbody.querySelectorAll('tr').length + 1;

      const row = el(`
        <tr data-bonus-items="[]">
          <td class="text-muted small">${order}</td>
          <td><span class="badge text-bg-${typeDisc === 'bonus' ? 'warning' : 'primary'}" data-role="disc-type">${escapeHtml(typeDisc)}</span></td>
          <td data-role="disc-format">${escapeHtml(format)}</td>
          <td data-role="disc-related">${escapeHtml(relatedTitle)}</td>
          <td class="nowrap">
            <button class="btn btn-xs btn-outline-secondary" type="button" data-action="edit-bonus">Edit</button>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove disc">✕</button></td>
        </tr>
      `);

      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());

      row.querySelector('button[data-action="edit-bonus"]').addEventListener('click', () => {
        const discType = row.querySelector('[data-role="disc-type"]').textContent.trim();
        const discFormat = row.querySelector('[data-role="disc-format"]').textContent.trim();
        const rel = row.querySelector('[data-role="disc-related"]').textContent.trim();
        const subtitle = `Box disc #${order} · type=${discType} · format=${discFormat}` + (rel ? ` · related="${rel}"` : ' · related=(whole box)');
        openBonusEditor({ targetRow: row, discType, subtitle });
      });

      tbody.appendChild(row);

      const btn = row.querySelector('button[data-action="edit-bonus"]');
      btn.textContent = 'Edit';
    }

    function createBoxSetCard() {
      boxSetSeq += 1;
      const boxSetId = `boxset_${boxSetSeq}`;
      const collapseId = `${boxSetId}_collapse`;
      const headingId = `${boxSetId}_heading`;

      const card = el(`
        <div class="accordion-item" data-boxset-id="${boxSetId}">
          <h2 class="accordion-header" id="${headingId}">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
              <span class="me-2">Box set #${boxSetSeq}</span>
              <span class="badge text-bg-light border text-muted ms-auto" data-role="summary">Not set</span>
            </button>
          </h2>
          <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headingId}" data-bs-parent="#boxSetsAccordion">
            <div class="accordion-body">

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label">Format</label>
                  <select class="form-select" name="box_format">
                    <option value="DVD">DVD</option>
                    <option value="BD" selected>Blu-ray</option>
                    <option value="UHD">4K UHD</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Box-set barcode (EAN-13)</label>
                  <input class="form-control" name="box_barcode" placeholder="13 digits" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Copy count</label>
                  <input type="number" class="form-control" name="box_copy_count" value="1" min="1" />
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h6 mb-0">Movies in box (ordered)</h3>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-primary" type="button" data-action="add-movie">Add movie</button>
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-action="paste-movies" data-bs-toggle="modal" data-bs-target="#pasteModal">Paste list</button>
                </div>
              </div>

              <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 80px;">Order</th>
                      <th style="min-width: 260px;">Title</th>
                      <th style="min-width: 180px;">IMDb ID</th>
                      <th style="min-width: 170px;">Inner-case EAN</th>
                      <th style="width: 180px;">Treat as single?</th>
                      <th style="width: 60px;"></th>
                    </tr>
                  </thead>
                  <tbody data-role="movies"></tbody>
                </table>
              </div>

              <div class="card mb-3">
                <div class="card-header">Discs in this box (for copy_id = 1)</div>
                <div class="card-body">
                  <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-3">
                      <label class="form-label">Disc type</label>
                      <select class="form-select" name="disc_type">
                        <option value="feature" selected>feature</option>
                        <option value="bonus">bonus</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Disc format</label>
                      <select class="form-select" name="disc_format">
                        <option value="DVD">DVD</option>
                        <option value="BD" selected>BD</option>
                        <option value="UHD">UHD</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Relates to movie (optional)</label>
                      <select class="form-select" name="disc_related">
                        <option value="">(whole box)</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <button class="btn btn-primary w-100" type="button" data-action="add-disc">Add disc</button>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th style="width: 80px;">Order</th>
                          <th style="width: 120px;">Type</th>
                          <th style="width: 120px;">Format</th>
                          <th>Related movie</th>
                          <th style="width: 200px;">Bonus items…</th>
                          <th style="width: 60px;"></th>
                        </tr>
                      </thead>
                      <tbody data-role="discs"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end">
                <button class="btn btn-outline-danger" type="button" data-action="remove-boxset">Remove this box set</button>
              </div>

            </div>
          </div>
        </div>
      `);

      const root = card;

      function updateSummary() {
        const barcode = root.querySelector('input[name="box_barcode"]').value.trim();
        const movieCount = root.querySelectorAll('tbody[data-role="movies"] tr').length;
        const discCount = root.querySelectorAll('tbody[data-role="discs"] tr').length;
        const badge = root.querySelector('[data-role="summary"]');

        const parts = [];
        parts.push(barcode ? `EAN ${barcode}` : 'No EAN');
        parts.push(`${movieCount} movies`);
        parts.push(`${discCount} discs`);
        badge.textContent = parts.join(' · ');
      }

      root.querySelector('[data-action="add-movie"]').addEventListener('click', () => {
        addMovieRowBox(root);
        updateSummary();
      });

      root.querySelector('[data-action="paste-movies"]').addEventListener('click', () => {
        pasteTargetBoxSetId = boxSetId;
      });

      root.querySelector('[data-action="add-disc"]').addEventListener('click', () => {
        const typeDisc = root.querySelector('select[name="disc_type"]').value;
        const format = root.querySelector('select[name="disc_format"]').value;
        const relatedIdx = root.querySelector('select[name="disc_related"]').value;

        let relatedTitle = "";
        if (relatedIdx !== "") {
          const rows = root.querySelectorAll('tbody[data-role="movies"] tr');
          const tr = rows[Number(relatedIdx)];
          relatedTitle = tr ? tr.querySelector('input[name="title"]').value.trim() : "";
        }

        addDiscRowBox(root, {typeDisc, format, relatedTitle});
        updateSummary();
      });

      root.querySelector('[data-action="remove-boxset"]').addEventListener('click', () => root.remove());
      root.querySelector('input[name="box_barcode"]').addEventListener('input', updateSummary);

      addMovieRowBox(root);
      refreshRelatedDropdown(root);
      updateSummary();

      return card;
    }

    function readAllBoxSets() {
      const cards = [...accordion.querySelectorAll('[data-boxset-id]')];
      return cards.map((root, idx) => {
        const format = root.querySelector('select[name="box_format"]').value;
        const boxBarcode = root.querySelector('input[name="box_barcode"]').value.trim() || null;
        const copyCount = Number(root.querySelector('input[name="box_copy_count"]').value || 1);

        const movies = [...root.querySelectorAll('tbody[data-role="movies"] tr')].map((tr, i) => {
          const title = tr.querySelector('input[name="title"]').value.trim();
          const imdb = tr.querySelector('input[name="imdb"]').value.trim();
          const innerEan = tr.querySelector('input[name="inner_ean"]').value.trim();
          const treatAsSingle = tr.querySelector('input[name="treat_as_single"]').checked;
          return { order: i + 1, title, imdb_id: imdb || null, inner_case_ean: innerEan || null, treat_as_single: treatAsSingle };
        });

        const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => {
          const type_disc = tr.querySelector('[data-role="disc-type"]')?.textContent?.trim() || null;
          const format = tr.querySelector('[data-role="disc-format"]')?.textContent?.trim() || null;
          const related_title = tr.querySelector('[data-role="disc-related"]')?.textContent?.trim() || null;
          const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);
          return { order: Number(tr.children[0].textContent.trim()), type_disc, format, related_title: related_title || null, bonus_items };
        });

        return { box_set_index: idx + 1, format, box_set_barcode: boxBarcode, copy_count: copyCount, movies, discs };
      });
    }

    // =========================================================================
    // Buttons
    // =========================================================================
    document.getElementById('btnAddBoxSet').addEventListener('click', () => {
      accordion.appendChild(createBoxSetCard());
    });

    document.getElementById('btnPreviewAllBoxSets').addEventListener('click', () => {
      showPreview({ kind: "box_sets_bulk", box_sets: readAllBoxSets() });
    });

    // Paste apply
    document.getElementById('btnBoxPasteApply').addEventListener('click', () => {
      const lines = document.getElementById('boxPasteArea').value
        .split('\n')
        .map(x => x.trim())
        .filter(Boolean);

      document.getElementById('boxPasteArea').value = "";

      if (!pasteTargetBoxSetId) return;
      const target = accordion.querySelector(`[data-boxset-id="${pasteTargetBoxSetId}"]`);
      if (!target) return;

      lines.forEach(t => addMovieRowBox(target, {title: t}));
      pasteTargetBoxSetId = null;
    });

    document.getElementById('btnPreviewAll').addEventListener('click', () => {
      showPreview({
        kind: "bulk_add_preview",
        singles: readSinglesForm(),
        box_sets: readAllBoxSets()
      });
    });

    // =========================================================================
    // Init
    // =========================================================================
    addSingleRow();
    accordion.appendChild(createBoxSetCard());
  </script>
</body>
</html>

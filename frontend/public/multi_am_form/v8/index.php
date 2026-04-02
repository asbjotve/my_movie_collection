<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk add – Singles & Box sets + Discs + Bonus items</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .table td, .table th { vertical-align: middle; }
    .btn-xs { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .4rem; --bs-btn-font-size: .75rem; }
    .muted { color: var(--bs-secondary-color); }
    .nowrap { white-space: nowrap; }

    /* Make "Discs for single release" modal visually distinct (gray) */
    #discModal .modal-header { background: var(--bs-secondary-bg); }
    #discModal .modal-body { background: var(--bs-tertiary-bg); }
    #discModal .modal-footer { background: var(--bs-secondary-bg); }
    #discModal .modal-content { border: 2px solid rgba(0,0,0,.25); }

    /* Borders for both modals */
    #bonusModal .modal-content { border: 2px solid rgba(0,0,0,.25); }

    /* Optional: slightly de-emphasize the primary action in disc modal */
    #discModal .btn.btn-success { background-color: var(--bs-secondary); border-color: var(--bs-secondary); }
    #discModal .btn.btn-success:hover { background-color: #5c636a; border-color: #565e64; }
    #discModal .btn.btn-success:focus { box-shadow: 0 0 0 .25rem rgba(108,117,125,.35); }

    /* When we "freeze" the disc modal under the bonus modal */
    #discModal .modal-content.is-frozen {
      opacity: .55;
      filter: grayscale(25%);
      pointer-events: none; /* extra safety */
    }
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
          <li>Nested modals: when Bonus-items is open, the Discs modal becomes temporarily disabled to prevent misclicks.</li>
        </ul>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="releaseTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
          Single releases
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="box-tab" data-bs-toggle="tab" data-bs-target="#box" type="button" role="tab">
          Box sets (multi)
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white p-3 rounded-bottom">

      <!-- SINGLE RELEASES -->
      <div class="tab-pane fade show active" id="single" role="tabpanel">

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
                <th style="min-width: 180px;">IMDb ID</th>
                <th style="width: 230px;">Discs</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div>

      <!-- BOX SETS -->
      <div class="tab-pane fade" id="box" role="tabpanel">

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

    <!-- Paste list modal -->
    <div class="modal fade" id="pasteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content border-2">
          <div class="modal-header">
            <h5 class="modal-title">Paste movie list</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label" for="boxPasteArea">One movie per line</label>
            <textarea class="form-control" id="boxPasteArea" rows="8"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="button" id="btnBoxPasteApply" data-bs-dismiss="modal">Add</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Disc modal -->
    <div class="modal fade" id="discModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1">Discs for single release</h5>
              <div class="small muted" id="discModalSubtitle">—</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">Add feature/bonus discs. Edit bonus items per disc.</div>
              <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddDiscEditorRow">Add disc</button>
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
            <button class="btn btn-success" type="button" id="btnSaveDiscsForSingle" data-bs-dismiss="modal">Save discs</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bonus modal -->
    <div class="modal fade" id="bonusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1">Bonus items</h5>
              <div class="small muted" id="bonusModalSubtitle">—</div>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-warning py-2 d-none" id="bonusNotBonusAlert">
              This disc is not marked as <span class="mono">bonus</span>. You can still add items.
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">
                Stored as: <span class="mono">(disc_id, seq_no)</span> in <span class="mono">disc_bonus_item</span>.
              </div>
              <button class="btn btn-sm btn-primary" type="button" id="btnAddBonusItemRow">Add item</button>
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
            <button class="btn btn-success" type="button" id="btnSaveBonusItems" data-bs-dismiss="modal">Save items</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content border-2">
          <div class="modal-header">
            <h5 class="modal-title">Preview payload</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <pre class="bg-light border rounded p-3 mb-0" style="max-height: 70vh; overflow: auto;" id="previewPre"></pre>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // ========= helpers =========
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

    // ========= freeze/unfreeze disc modal when bonus modal is open =========
    const discModalEl = document.getElementById('discModal');
    const bonusModalEl = document.getElementById('bonusModal');

    function isModalShown(modalEl) {
      return modalEl.classList.contains('show');
    }

    function freezeDiscModalIfOpen() {
      if (!isModalShown(discModalEl)) return;
      const content = discModalEl.querySelector('.modal-content');
      content.classList.add('is-frozen');

      // inert is supported in modern browsers; fallback is pointer-events none above
      try { content.inert = true; } catch {}
      content.setAttribute('aria-hidden', 'true');
    }

    function unfreezeDiscModalIfOpen() {
      const content = discModalEl.querySelector('.modal-content');
      content.classList.remove('is-frozen');
      try { content.inert = false; } catch {}
      content.removeAttribute('aria-hidden');
    }

    bonusModalEl.addEventListener('shown.bs.modal', freezeDiscModalIfOpen);
    bonusModalEl.addEventListener('hidden.bs.modal', unfreezeDiscModalIfOpen);

    // ========= bonus modal =========
    const bonusItemsTbody = document.querySelector('#bonusItemsTable tbody');
    const bonusNotBonusAlert = document.getElementById('bonusNotBonusAlert');
    const bonusModalSubtitle = document.getElementById('bonusModalSubtitle');
    let bonusTarget = null;

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
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove item">✕</button></td>
        </tr>
      `);
      row.querySelector('select[name="item_type"]').value = item_type || 'featurette';
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      bonusItemsTbody.appendChild(row);
    }

    function openBonusEditor({targetRow, discType, subtitle}) {
      bonusTarget = { node: targetRow };
      bonusModalSubtitle.textContent = subtitle;
      bonusNotBonusAlert.classList.toggle('d-none', discType === 'bonus');

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

    // ========= singles + disc modal =========
    const singleTbody = document.querySelector('#singleTable tbody');
    const discEditorTbody = document.querySelector('#discEditorTable tbody');
    const discModalSubtitle = document.getElementById('discModalSubtitle');
    let discModalTargetSingleRow = null;

    function clearDiscEditorTable() { discEditorTbody.innerHTML = ''; }

    function updateSingleDiscButtonLabel(singleRow) {
      const discs = getJsonAttr(singleRow, 'data-discs', []);
      const btn = singleRow.querySelector('button[data-action="edit-discs"]');
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
        row.querySelector('button[data-action="edit-bonus"]').textContent = (items && items.length) ? `Edit (${items.length})` : 'Edit';
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

      new bootstrap.Modal(discModalEl).show();
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

    function addSingleRow({title="", barcode="", imdb=""} = {}) {
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
      const rows = [...singleTbody.querySelectorAll('tr')].map(tr => ({
        title: tr.querySelector('input[name="title"]').value.trim(),
        barcode: tr.querySelector('input[name="barcode"]').value.trim(),
        imdb_id: tr.querySelector('input[name="imdb"]').value.trim() || null,
        discs: getJsonAttr(tr, 'data-discs', [])
      }));
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

    // ========= box sets (minimal, preview only) =========
    const accordion = document.getElementById('boxSetsAccordion');
    document.getElementById('btnAddBoxSet').addEventListener('click', () => {
      // placeholder (can bring back full multi-boxset builder if you want)
      const item = el(`<div class="alert alert-secondary mb-2">Box-set builder omitted in this snippet for brevity.</div>`);
      accordion.appendChild(item);
    });

    document.getElementById('btnPreviewAllBoxSets').addEventListener('click', () => {
      showPreview({ kind: "box_sets_bulk", box_sets: [] });
    });

    document.getElementById('btnPreviewAll').addEventListener('click', () => {
      showPreview({ kind: "bulk_add_preview", singles: readSinglesForm() });
    });

    // init
    addSingleRow();
  </script>
</body>
</html>

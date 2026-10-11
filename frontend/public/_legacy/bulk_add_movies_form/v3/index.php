<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk add – Singles & Box sets (multi)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .table td, .table th { vertical-align: middle; }
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
          <li><strong>Singles</strong>: each row is one product with its own <span class="mono">barcode</span>.</li>
          <li><strong>Box sets</strong>: each box set is one product with <span class="mono">box_set_barcode</span> and multiple movies in order.</li>
          <li><strong>Inner-case EAN</strong> (box sets): optional; can be used to also treat a movie as a single in your library.</li>
          <li><strong>Discs</strong>: use <span class="mono">type_disc=feature</span> for main movie discs, <span class="mono">bonus</span> for extras.</li>
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
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnSinglePreview">Preview payload</button>
            <button class="btn btn-sm btn-success" type="button" id="btnSingleSubmit" disabled>Submit</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="singleTable">
            <thead class="table-light">
              <tr>
                <th style="min-width: 240px;">Title</th>
                <th style="min-width: 170px;">Barcode (EAN-13)</th>
                <th style="min-width: 180px;">IMDb ID (optional)</th>
                <th style="width: 160px;">Discs…</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody>
              <!-- JS rows -->
            </tbody>
          </table>
        </div>

        <div class="alert alert-info">
          Tip: For “single + bonus disc”, add 2 discs: one <span class="mono">feature</span> and one <span class="mono">bonus</span>.
          (Disc editor is a placeholder in this prototype.)
        </div>

        <div class="form-text">
          (Submit is disabled in this pure-HTML prototype.)
        </div>
      </div>

      <!-- =========================
           BOX SETS (MULTI)
      ========================== -->
      <div class="tab-pane fade" id="box" role="tabpanel" aria-labelledby="box-tab">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h2 class="h5 mb-1">Box sets</h2>
            <div class="text-muted small">Add multiple box sets, then preview or submit all at once.</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" type="button" id="btnAddBoxSet">Add box set</button>
            <button class="btn btn-outline-secondary" type="button" id="btnPreviewAllBoxSets">Preview all</button>
            <button class="btn btn-success" type="button" id="btnSubmitAllBoxSets" disabled>Submit all</button>
          </div>
        </div>

        <div class="accordion" id="boxSetsAccordion">
          <!-- JS will insert box-set cards here -->
        </div>

        <div class="form-text mt-3">
          (Submit is disabled in this pure-HTML prototype.)
        </div>

      </div>

    </div>

    <!-- Paste list modal (reused; target box-set is stored in JS) -->
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

    // =========================================================================
    // Singles
    // =========================================================================
    const singleTbody = document.querySelector('#singleTable tbody');

    function addSingleRow({title = "", barcode = "", imdb = ""} = {}) {
      const row = el(`
        <tr>
          <td><input class="form-control form-control-sm" name="title" placeholder="Title" value="${escapeHtml(title)}"></td>
          <td><input class="form-control form-control-sm" name="barcode" placeholder="EAN-13" value="${escapeHtml(barcode)}"></td>
          <td><input class="form-control form-control-sm" name="imdb" placeholder="tt1234567" value="${escapeHtml(imdb)}"></td>
          <td><button class="btn btn-sm btn-outline-secondary" type="button" title="Disc editor not implemented">Edit</button></td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove row">✕</button></td>
        </tr>
      `);
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      singleTbody.appendChild(row);
    }

    function readSinglesForm() {
      const rows = [...singleTbody.querySelectorAll('tr')].map(tr => {
        const title = tr.querySelector('input[name="title"]').value.trim();
        const barcode = tr.querySelector('input[name="barcode"]').value.trim();
        const imdb = tr.querySelector('input[name="imdb"]').value.trim();
        return { title, barcode, imdb_id: imdb || null };
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
    document.getElementById('btnSinglePreview').addEventListener('click', () => showPreview(readSinglesForm()));

    // =========================================================================
    // Box sets (multi)
    // =========================================================================
    const accordion = document.getElementById('boxSetsAccordion');
    let boxSetSeq = 0;

    // Paste modal target tracking
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

    function addMovieRow(boxSetRoot, {title="", imdb="", innerEan="", treatAsSingle=null} = {}) {
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
      innerEanInput.addEventListener('input', () => {
        if (innerEanInput.value.trim()) treatCb.checked = true;
      });

      tbody.appendChild(row);
      renumberOrders(tbody);
      refreshRelatedDropdown(boxSetRoot);
    }

    function addDiscRow(boxSetRoot, {typeDisc="feature", format="BD", relatedTitle=""} = {}) {
      const tbody = boxSetRoot.querySelector('tbody[data-role="discs"]');
      const order = tbody.querySelectorAll('tr').length + 1;
      const row = el(`
        <tr>
          <td class="text-muted small">${order}</td>
          <td><span class="badge text-bg-${typeDisc === 'bonus' ? 'warning' : 'primary'}">${escapeHtml(typeDisc)}</span></td>
          <td>${escapeHtml(format)}</td>
          <td>${relatedTitle ? escapeHtml(relatedTitle) : '<span class="text-muted">(whole box)</span>'}</td>
          <td><button class="btn btn-sm btn-outline-secondary" type="button" title="Bonus-item editor not implemented">Edit</button></td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove disc">✕</button></td>
        </tr>
      `);
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      tbody.appendChild(row);
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

              <div class="alert alert-secondary py-2">
                <div class="small">
                  <strong>Inner-case EAN:</strong> optional. If you fill it in, you can also create a “single” for that movie in your library.
                </div>
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
                      <div class="form-text">Empty = box-level bonus disc.</div>
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

                  <div class="form-text mt-2">
                    Bonus items are stored per disc (<span class="mono">disc_bonus_item</span>).
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

      // Summary badge updater
      function updateSummary() {
        const barcode = root.querySelector('input[name="box_barcode"]').value.trim();
        const movieCount = root.querySelectorAll('tbody[data-role="movies"] tr').length;
        const discCount = root.querySelectorAll('tbody[data-role="discs"] tr').length;
        const badge = root.querySelector('[data-role="summary"]');

        const parts = [];
        parts.push(barcode ? `EAN ${barcode}` : 'No EAN');
        parts.push(`${movieCount} movie${movieCount === 1 ? '' : 's'}`);
        parts.push(`${discCount} disc${discCount === 1 ? '' : 's'}`);
        badge.textContent = parts.join(' · ');
      }

      // actions
      root.querySelector('[data-action="add-movie"]').addEventListener('click', () => {
        addMovieRow(root);
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

        addDiscRow(root, {typeDisc, format, relatedTitle});
        updateSummary();
      });

      root.querySelector('[data-action="remove-boxset"]').addEventListener('click', () => root.remove());

      root.querySelector('input[name="box_barcode"]').addEventListener('input', updateSummary);
      root.querySelector('select[name="box_format"]').addEventListener('change', updateSummary);

      // initial
      addMovieRow(root);
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
          return {
            order: i + 1,
            title,
            imdb_id: imdb || null,
            inner_case_ean: innerEan || null,
            treat_as_single: treatAsSingle
          };
        });

        const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => {
          return {
            order: Number(tr.children[0].textContent.trim()),
            type_disc: tr.querySelector('.badge')?.textContent?.trim() || null,
            format: tr.children[2].textContent.trim(),
            related_title: tr.children[3].textContent.trim()
          };
        });

        return {
          box_set_index: idx + 1,
          format,
          box_set_barcode: boxBarcode,
          copy_count: copyCount,
          movies,
          discs
        };
      });
    }

    document.getElementById('btnAddBoxSet').addEventListener('click', () => {
      accordion.appendChild(createBoxSetCard());
    });

    document.getElementById('btnPreviewAllBoxSets').addEventListener('click', () => {
      showPreview({ kind: "box_sets_bulk", box_sets: readAllBoxSets() });
    });

    // Paste list apply (to the targeted box set)
    document.getElementById('btnBoxPasteApply').addEventListener('click', () => {
      const lines = document.getElementById('boxPasteArea').value
        .split('\n')
        .map(x => x.trim())
        .filter(Boolean);

      document.getElementById('boxPasteArea').value = "";

      if (!pasteTargetBoxSetId) return;
      const target = accordion.querySelector(`[data-boxset-id="${pasteTargetBoxSetId}"]`);
      if (!target) return;

      lines.forEach(t => addMovieRow(target, {title: t}));
      pasteTargetBoxSetId = null;
    });

    // initial state
    addSingleRow();
    accordion.appendChild(createBoxSetCard());
  </script>
</body>
</html>

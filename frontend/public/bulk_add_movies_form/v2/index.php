<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk add – Movies & Box sets</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
          <li><strong>Single releases</strong>: each row is one product with its own barcode.</li>
          <li><strong>Box set</strong>: one product (box_set_barcode) containing multiple movies in order.</li>
          <li><strong>Inner-case EAN</strong> (box sets): optional. If provided, the system can also treat each movie as a “single” in your library.</li>
          <li><strong>Discs</strong>: use <code>type_disc=feature</code> for main movie discs, <code>bonus</code> for extras.</li>
          <li><strong>Bonus items</strong>: optionally list what’s on a bonus disc.</li>
        </ul>
      </div>
    </div>

    <!-- Release type -->
    <ul class="nav nav-tabs" id="releaseTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
          Single releases
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="box-tab" data-bs-toggle="tab" data-bs-target="#box" type="button" role="tab">
          Box set
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white p-3 rounded-bottom">

      <!-- =========================
           SINGLE RELEASES
      ========================== -->
      <div class="tab-pane fade show active" id="single" role="tabpanel">

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Format</label>
            <select class="form-select" id="singleFormat">
              <option value="DVD">DVD</option>
              <option value="BD">Blu-ray</option>
              <option value="UHD">4K UHD</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Default copy count</label>
            <input type="number" class="form-control" id="singleCopyCount" value="1" min="1" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Quick add (title)</label>
            <div class="input-group">
              <input class="form-control" id="singleQuickImport" placeholder="e.g. Hidalgo" />
              <button class="btn btn-outline-primary" type="button" id="btnSingleAddFromText">Add</button>
            </div>
            <div class="form-text">Adds a new empty row with the title filled in.</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">Titles</h2>
          <button class="btn btn-sm btn-primary" type="button" id="btnSingleAddRow">Add row</button>
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
          Tip: For “single + bonus disc”, add 2 discs: one <code>feature</code> and one <code>bonus</code>.
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-success" type="button" id="btnSingleSubmit" disabled>Submit singles</button>
          <button class="btn btn-outline-secondary" type="button" id="btnSinglePreview">Preview payload</button>
        </div>
        <div class="form-text mt-2">
          (Submit is disabled in this pure-HTML prototype.)
        </div>
      </div>

      <!-- =========================
           BOX SET
      ========================== -->
      <div class="tab-pane fade" id="box" role="tabpanel">

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Format</label>
            <select class="form-select" id="boxFormat">
              <option value="DVD">DVD</option>
              <option value="BD">Blu-ray</option>
              <option value="UHD">4K UHD</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Box-set barcode (EAN-13)</label>
            <input class="form-control" id="boxBarcode" placeholder="13 digits" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Copy count</label>
            <input type="number" class="form-control" id="boxCopyCount" value="1" min="1" />
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0">Movies in box (ordered)</h2>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" type="button" id="btnBoxAddRow">Add movie</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnBoxPasteList" data-bs-toggle="modal" data-bs-target="#pasteModal">
              Paste list
            </button>
          </div>
        </div>

        <div class="table-responsive mb-2">
          <table class="table table-sm align-middle" id="boxTitlesTable">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">Order</th>
                <th style="min-width: 260px;">Title</th>
                <th style="min-width: 180px;">IMDb ID (optional)</th>
                <th style="min-width: 170px;">Inner-case EAN (optional)</th>
                <th style="width: 180px;">Treat as single?</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody>
              <!-- JS rows -->
            </tbody>
          </table>
        </div>

        <div class="alert alert-secondary py-2">
          <div class="small">
            <strong>Inner-case EAN:</strong> If you enter an EAN for a movie inside the box, you can also have it treated as its own “single” in your library (no DDL changes required; backend can create a single <code>physical_collection</code> row).
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            Discs in this box (for copy_id = 1)
          </div>
          <div class="card-body">
            <div class="row g-2 align-items-end mb-2">
              <div class="col-md-3">
                <label class="form-label">Disc type</label>
                <select class="form-select" id="boxDiscType">
                  <option value="feature">feature</option>
                  <option value="bonus">bonus</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Disc format</label>
                <select class="form-select" id="boxDiscFormat">
                  <option value="DVD">DVD</option>
                  <option value="BD" selected>BD</option>
                  <option value="UHD">UHD</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Relates to movie (optional)</label>
                <select class="form-select" id="boxDiscRelated">
                  <option value="">(whole box)</option>
                  <!-- JS will populate with titles in box -->
                </select>
                <div class="form-text">Set empty for “box-level” bonus disc.</div>
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary w-100" type="button" id="btnBoxAddDisc">Add disc</button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0" id="boxDiscsTable">
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
                <tbody>
                  <!-- JS rows -->
                </tbody>
              </table>
            </div>

            <div class="form-text mt-2">
              Bonus items are stored per disc (table <code>disc_bonus_item</code>).
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-success" type="button" id="btnBoxSubmit" disabled>Submit box set</button>
          <button class="btn btn-outline-secondary" type="button" id="btnBoxPreview">Preview payload</button>
        </div>
        <div class="form-text mt-2">
          (Submit is disabled in this pure-HTML prototype.)
        </div>
      </div>

    </div>

    <!-- Paste list modal -->
    <div class="modal fade" id="pasteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Paste movie list</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">One movie per line</label>
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
    // Minimal helpers (skeleton). No backend wiring included.

    const singleTbody = document.querySelector('#singleTable tbody');
    const boxTitlesTbody = document.querySelector('#boxTitlesTable tbody');
    const boxDiscsTbody = document.querySelector('#boxDiscsTable tbody');
    const boxDiscRelated = document.getElementById('boxDiscRelated');

    function el(html) {
      const t = document.createElement('template');
      t.innerHTML = html.trim();
      return t.content.firstChild;
    }

    // -------------------------
    // SINGLE ROWS
    // -------------------------
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

    // -------------------------
    // BOX SET TITLE ROWS (now includes inner-case EAN + treat-as-single)
    // -------------------------
    function renumberBoxTitleOrder() {
      [...boxTitlesTbody.querySelectorAll('tr')].forEach((tr, i) => {
        tr.querySelector('[data-order]').textContent = i + 1;
      });
      refreshBoxRelatedDropdown();
    }

    function refreshBoxRelatedDropdown() {
      const titles = [...boxTitlesTbody.querySelectorAll('tr')]
        .map(tr => tr.querySelector('input[name="title"]').value.trim());

      boxDiscRelated.innerHTML =
        '<option value="">(whole box)</option>' +
        titles
          .map((t, i) => t ? `<option value="${i}">${escapeHtml(t)}</option>` : `<option value="${i}">(untitled #${i+1})</option>`)
          .join('');
    }

    function addBoxTitleRow({title = "", imdb = "", innerEan = "", treatAsSingle = null} = {}) {
      // If treatAsSingle isn't explicitly set, default to:
      // - true when innerEan is present
      // - false otherwise
      if (treatAsSingle === null) treatAsSingle = Boolean(innerEan && innerEan.trim());

      const row = el(`
        <tr>
          <td data-order class="text-muted small">-</td>
          <td><input class="form-control form-control-sm" name="title" placeholder="Title" value="${escapeHtml(title)}"></td>
          <td><input class="form-control form-control-sm" name="imdb" placeholder="tt1234567" value="${escapeHtml(imdb)}"></td>
          <td><input class="form-control form-control-sm" name="inner_ean" placeholder="EAN-13" value="${escapeHtml(innerEan)}"></td>
          <td>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="treat_as_single" ${treatAsSingle ? 'checked' : ''}>
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
        renumberBoxTitleOrder();
      });

      titleInput.addEventListener('input', refreshBoxRelatedDropdown);

      // UX: if user types an inner EAN, auto-check "Create single".
      innerEanInput.addEventListener('input', () => {
        if (innerEanInput.value.trim()) treatCb.checked = true;
      });

      boxTitlesTbody.appendChild(row);
      renumberBoxTitleOrder();
    }

    // -------------------------
    // BOX SET DISC ROWS
    // -------------------------
    function addBoxDiscRow({typeDisc="feature", format="BD", relatedTitle=""} = {}) {
      const order = boxDiscsTbody.querySelectorAll('tr').length + 1;
      const row = el(`
        <tr>
          <td class="text-muted small">${order}</td>
          <td><span class="badge text-bg-${typeDisc === 'bonus' ? 'warning' : 'primary'}">${typeDisc}</span></td>
          <td>${escapeHtml(format)}</td>
          <td>${relatedTitle ? escapeHtml(relatedTitle) : '<span class="text-muted">(whole box)</span>'}</td>
          <td><button class="btn btn-sm btn-outline-secondary" type="button" title="Bonus-item editor not implemented">Edit</button></td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Remove disc">✕</button></td>
        </tr>
      `);
      row.querySelector('.btn-outline-danger').addEventListener('click', () => row.remove());
      boxDiscsTbody.appendChild(row);
    }

    // -------------------------
    // PREVIEW
    // -------------------------
    function showPreview(obj) {
      document.getElementById('previewPre').textContent = JSON.stringify(obj, null, 2);
      const m = new bootstrap.Modal(document.getElementById('previewModal'));
      m.show();
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

    function readBoxForm() {
      const movies = [...boxTitlesTbody.querySelectorAll('tr')].map((tr, idx) => {
        const title = tr.querySelector('input[name="title"]').value.trim();
        const imdb = tr.querySelector('input[name="imdb"]').value.trim();
        const innerEan = tr.querySelector('input[name="inner_ean"]').value.trim();
        const treatAsSingle = tr.querySelector('input[name="treat_as_single"]').checked;
        return {
          order: idx + 1,
          title,
          imdb_id: imdb || null,
          inner_case_ean: innerEan || null,
          treat_as_single: treatAsSingle
        };
      });

      const discs = [...boxDiscsTbody.querySelectorAll('tr')].map(tr => {
        return {
          order: tr.children[0].textContent.trim(),
          type_disc: tr.querySelector('.badge')?.textContent?.trim() || null,
          format: tr.children[2].textContent.trim(),
          related_title: tr.children[3].textContent.trim()
        };
      });

      return {
        kind: "box_set",
        format: document.getElementById('boxFormat').value,
        box_set_barcode: document.getElementById('boxBarcode').value.trim() || null,
        copy_count: Number(document.getElementById('boxCopyCount').value || 1),
        movies,
        discs
      };
    }

    // Basic escaping to avoid breaking HTML when inserting titles via JS
    function escapeHtml(s) {
      return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    // -------------------------
    // Wire up buttons
    // -------------------------
    document.getElementById('btnSingleAddRow').addEventListener('click', () => addSingleRow());
    document.getElementById('btnSingleAddFromText').addEventListener('click', () => {
      const v = document.getElementById('singleQuickImport').value.trim();
      if (v) addSingleRow({title: v});
      document.getElementById('singleQuickImport').value = "";
    });

    document.getElementById('btnBoxAddRow').addEventListener('click', () => addBoxTitleRow());
    document.getElementById('btnBoxPasteApply').addEventListener('click', () => {
      const lines = document.getElementById('boxPasteArea').value
        .split('\n')
        .map(x => x.trim())
        .filter(Boolean);

      lines.forEach(t => addBoxTitleRow({title: t}));
      document.getElementById('boxPasteArea').value = "";
    });

    document.getElementById('btnBoxAddDisc').addEventListener('click', () => {
      const typeDisc = document.getElementById('boxDiscType').value;
      const format = document.getElementById('boxDiscFormat').value;
      const relatedIdx = document.getElementById('boxDiscRelated').value;

      let relatedTitle = "";
      if (relatedIdx !== "") {
        const tr = boxTitlesTbody.querySelectorAll('tr')[Number(relatedIdx)];
        relatedTitle = tr ? tr.querySelector('input[name="title"]').value.trim() : "";
      }
      addBoxDiscRow({typeDisc, format, relatedTitle});
    });

    document.getElementById('btnSinglePreview').addEventListener('click', () => {
      showPreview(readSinglesForm());
    });

    document.getElementById('btnBoxPreview').addEventListener('click', () => {
      showPreview(readBoxForm());
    });

    // Initial rows
    addSingleRow();
    addBoxTitleRow();

  </script>
</body>
</html>

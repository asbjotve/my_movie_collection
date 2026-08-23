<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/lang.php';

$lang = bamf_current_lang();
$t = bamf_load_translations($lang);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(tr($t, 'page.title')) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .table td, .table th { vertical-align: middle; }
    .btn-xs { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .4rem; --bs-btn-font-size: .75rem; }
    .muted { color: var(--bs-secondary-color); }
    .nowrap { white-space: nowrap; }

    #discModal .modal-header { background: var(--bs-secondary-bg); }
    #discModal .modal-body { background: var(--bs-tertiary-bg); }
    #discModal .modal-footer { background: var(--bs-secondary-bg); }
    #discModal .modal-content { border: 2px solid rgba(0,0,0,.25); }

    #bonusModal .modal-content { border: 2px solid rgba(0,0,0,.25); }
    #pasteModal .modal-content { border: 2px solid rgba(0,0,0,.25); }
    #previewModal .modal-content { border: 2px solid rgba(0,0,0,.25); }
    #searchModal .modal-content { border: 2px solid rgba(0,0,0,.25); }

    #discModal .modal-content.is-frozen {
      opacity: .55;
      filter: grayscale(25%);
      pointer-events: none;
    }

    .dropdown-results {
      border: 1px solid rgba(0,0,0,.1);
      border-radius: .5rem;
      overflow: hidden;
      display: none;
      max-height: 320px;
      overflow-y: auto;
      background: #fff;
    }
    .dropdown-results.show { display: block; }
    .dropdown-item-custom {
      display: flex;
      gap: 12px;
      padding: 10px;
      cursor: pointer;
      border-bottom: 1px solid rgba(0,0,0,.06);
    }
    .dropdown-item-custom:hover { background: rgba(0,0,0,.03); }
    .dropdown-poster {
      width: 48px;
      height: 72px;
      object-fit: cover;
      border-radius: 6px;
      flex: 0 0 auto;
    }
    .dropdown-no-poster {
      width: 48px;
      height: 72px;
      border-radius: 6px;
      background: #e9ecef;
      display: grid;
      place-items: center;
      font-size: 11px;
      color: #6c757d;
      flex: 0 0 auto;
      text-align: center;
      padding: 4px;
    }
    .dropdown-info { flex: 1; min-width: 0; }
    .dropdown-title { font-weight: 600; display: flex; gap: 8px; align-items: center; }
    .dropdown-meta { color: #6c757d; font-size: 13px; margin-top: 2px; }
    .media-badge { font-size: 11px; padding: .25em .5em; border-radius: 999px; }
    .badge-tv { background: #6f42c1; color: #fff; }
    .badge-movie { background: #0d6efd; color: #fff; }
    .bg-purple { background: #6f42c1 !important; color: #fff !important; }
  </style>
</head>

<body class="bg-light">
  <div class="container py-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0"><?= h(tr($t, 'page.h1')) ?></h1>

      <div class="d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2 mb-0">
          <label class="form-label mb-0 small text-muted" for="langSelect"><?= h(tr($t, 'lang.label')) ?></label>
          <select class="form-select form-select-sm" id="langSelect" name="lang" onchange="this.form.submit()">
            <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>><?= h(tr($t, 'lang.en')) ?></option>
            <option value="nb" <?= $lang === 'nb' ? 'selected' : '' ?>><?= h(tr($t, 'lang.nb')) ?></option>
          </select>
          <noscript>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= h(tr($t, 'btn.apply')) ?></button>
          </noscript>
        </form>

        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#help">
          <?= h(tr($t, 'btn.help')) ?>
        </button>

        <button class="btn btn-outline-danger btn-sm" type="button" id="btnResetAll">
          <?= h(tr($t, 'btn.reset')) ?>
        </button>
      </div>
    </div>

    <div class="collapse mb-3" id="help">
      <div class="card card-body">
        <ul class="mb-0">
          <li><?= tr($t, 'help.li1_html') ?></li>
          <li><?= tr($t, 'help.li2_html') ?></li>
          <li><?= tr($t, 'help.li3_html') ?></li>
          <li><?= tr($t, 'help.li4_html') ?></li>
        </ul>
      </div>
    </div>

    <ul class="nav nav-tabs" id="releaseTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
          <?= h(tr($t, 'tab.singles')) ?>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="box-tab" data-bs-toggle="tab" data-bs-target="#box" type="button" role="tab" aria-controls="box" aria-selected="false">
          <?= h(tr($t, 'tab.boxsets')) ?>
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white p-3 rounded-bottom">

      <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label" for="singleFormat"><?= h(tr($t, 'label.default_format')) ?></label>
            <select class="form-select" id="singleFormat">
              <option value="DVD"><?= h(tr($t, 'format.dvd_short')) ?></option>
              <option value="BD" selected><?= h(tr($t, 'format.bd_short')) ?></option>
              <option value="UHD"><?= h(tr($t, 'format.uhd_short')) ?></option>
            </select>
            <div class="form-text"><?= h(tr($t, 'hint.default_format')) ?></div>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="singleCopyCount"><?= h(tr($t, 'label.default_copy_count')) ?></label>
            <input type="number" class="form-control" id="singleCopyCount" value="1" min="1" />
          </div>

          <div class="col-md-4">
            <label class="form-label" for="singleQuickImport"><?= h(tr($t, 'label.quick_add_title')) ?></label>
            <div class="input-group">
              <input class="form-control" id="singleQuickImport" placeholder="<?= h(tr($t, 'ph.quick_add_title')) ?>" />
              <button class="btn btn-outline-primary" type="button" id="btnSingleAddFromText"><?= h(tr($t, 'btn.add')) ?></button>
            </div>
            <div class="form-text"><?= h(tr($t, 'help.quick_add')) ?></div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 mb-0"><?= h(tr($t, 'section.titles')) ?></h2>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" type="button" id="btnSingleAddRow"><?= h(tr($t, 'btn.add_row')) ?></button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnSinglePreview"><?= h(tr($t, 'btn.preview_singles')) ?></button>
            <button class="btn btn-sm btn-success" type="button" disabled><?= h(tr($t, 'btn.submit_singles')) ?></button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="singleTable">
            <thead class="table-light">
              <tr>
                <th style="min-width: 240px;"><?= h(tr($t, 'col.title')) ?></th>
                <th style="width: 110px;"><?= h(tr($t, 'col.format')) ?></th>
                <th style="min-width: 170px;"><?= h(tr($t, 'col.barcode')) ?></th>
                <th style="min-width: 240px;"><?= h(tr($t, 'col.imdb')) ?></th>
                <th style="width: 230px;"><?= h(tr($t, 'col.discs')) ?></th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="alert alert-info">
          <?= tr($t, 'hint.discs_html') ?>
        </div>
      </div>

      <div class="tab-pane fade" id="box" role="tabpanel" aria-labelledby="box-tab">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h2 class="h5 mb-1"><?= h(tr($t, 'section.boxsets')) ?></h2>
            <div class="text-muted small"><?= h(tr($t, 'hint.boxsets')) ?></div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" type="button" id="btnAddBoxSet"><?= h(tr($t, 'btn.add_box_set')) ?></button>
            <button class="btn btn-outline-secondary" type="button" id="btnPreviewAllBoxSets"><?= h(tr($t, 'btn.preview_box_sets')) ?></button>
            <button class="btn btn-success" type="button" disabled><?= h(tr($t, 'btn.submit_box_sets')) ?></button>
          </div>
        </div>

        <div class="accordion" id="boxSetsAccordion"></div>
      </div>

    </div>

    <div class="modal fade" id="pasteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?= h(tr($t, 'modal.paste.title')) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(tr($t, 'btn.close')) ?>"></button>
          </div>
          <div class="modal-body">
            <label class="form-label" for="boxPasteArea"><?= h(tr($t, 'modal.paste.label')) ?></label>
            <textarea class="form-control" id="boxPasteArea" rows="8" placeholder="<?= h(tr($t, 'modal.paste.placeholder')) ?>"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal"><?= h(tr($t, 'btn.cancel')) ?></button>
            <button class="btn btn-primary" type="button" id="btnBoxPasteApply" data-bs-dismiss="modal"><?= h(tr($t, 'btn.add')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="discModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1"><?= h(tr($t, 'modal.discs.title')) ?></h5>
              <div class="small muted" id="discModalSubtitle">—</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(tr($t, 'btn.close')) ?>"></button>
          </div>

          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">
                <?= tr($t, 'modal.discs.hint_html') ?>
              </div>
              <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddDiscEditorRow"><?= h(tr($t, 'btn.add_disc')) ?></button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle" id="discEditorTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 120px;"><?= h(tr($t, 'col.type')) ?></th>
                    <th style="width: 140px;"><?= h(tr($t, 'col.format')) ?></th>
                    <th><?= h(tr($t, 'col.label')) ?></th>
                    <th style="width: 220px;"><?= h(tr($t, 'col.bonus_items')) ?></th>
                    <th style="width: 60px;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal"><?= h(tr($t, 'btn.cancel')) ?></button>
            <button class="btn btn-success" type="button" id="btnSaveDiscsForSingle" data-bs-dismiss="modal"><?= h(tr($t, 'btn.save_discs')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="bonusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title mb-1"><?= h(tr($t, 'modal.bonus.title')) ?></h5>
              <div class="small muted" id="bonusModalSubtitle">—</div>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="<?= h(tr($t, 'btn.close')) ?>"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-warning py-2 d-none" id="bonusNotBonusAlert">
              <?= tr($t, 'modal.bonus.warn_not_bonus_html') ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small muted">
                <?= tr($t, 'modal.bonus.stored_as_html') ?>
              </div>
              <button class="btn btn-sm btn-primary" type="button" id="btnAddBonusItemRow"><?= h(tr($t, 'btn.add_item')) ?></button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle" id="bonusItemsTable">
                <thead class="table-light">
                  <tr>
                    <th style="width: 90px;"><?= h(tr($t, 'col.seq')) ?></th>
                    <th style="min-width: 280px;"><?= h(tr($t, 'col.title')) ?></th>
                    <th style="width: 180px;"><?= h(tr($t, 'col.type')) ?></th>
                    <th style="width: 170px;"><?= h(tr($t, 'col.runtime')) ?></th>
                    <th style="min-width: 260px;"><?= h(tr($t, 'col.notes')) ?></th>
                    <th style="width: 60px;"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal"><?= h(tr($t, 'btn.cancel')) ?></button>
            <button class="btn btn-success" type="button" id="btnSaveBonusItems" data-bs-dismiss="modal"><?= h(tr($t, 'btn.save_items')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><?= h(tr($t, 'modal.preview.title')) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(tr($t, 'btn.close')) ?>"></button>
          </div>
          <div class="modal-body">
            <pre class="bg-light border rounded p-3 mb-0" style="max-height: 70vh; overflow: auto;" id="previewPre"></pre>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal"><?= h(tr($t, 'btn.close')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title" id="searchModalLabel">🔍 Søk etter filmer og TV-serier</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Lukk"></button>
          </div>

          <div class="modal-body">
            <div class="search-wrapper">
              <div class="input-group input-group-lg">
                <span class="input-group-text">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                  </svg>
                </span>

                <input type="text" id="searchInput" class="form-control" placeholder="Søk etter filmer eller TV-serier..." autocomplete="off">

                <span class="input-group-text" id="loadingSpinner" style="display: none;">
                  <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Søker...</span>
                  </div>
                </span>
              </div>

              <div class="form-text mt-1">
                💡 Tips: Søk med årstall, f.eks. "Titanic 1997" eller "Breaking Bad 2008"
              </div>

              <div id="dropdownResults" class="dropdown-results mt-2"></div>
            </div>

            <div id="searchStatus" class="text-center mt-2 text-muted" style="min-height: 24px;"></div>
            <div id="selectedItem" class="mt-3"></div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const I18N = <?= json_encode(bamf_flatten_for_js($t), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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
      bootstrap.Modal.getOrCreateInstance(document.getElementById('previewModal')).show();
    }

    function getJsonAttr(node, attrName, fallback) {
      const raw = node.getAttribute(attrName);
      if (!raw) return fallback;
      try { return JSON.parse(raw); } catch { return fallback; }
    }

    function setJsonAttr(node, attrName, value) {
      node.setAttribute(attrName, JSON.stringify(value ?? null));
    }

    function fmt(key, vars) {
      let s = String(I18N[key] ?? key);
      if (vars) for (const [k, v] of Object.entries(vars)) s = s.replaceAll(`{${k}}`, String(v));
      return s;
    }

    const discModalEl = document.getElementById('discModal');
    const bonusModalEl = document.getElementById('bonusModal');

    function isModalShown(modalEl) { return modalEl.classList.contains('show'); }

    function freezeDiscModalIfOpen() {
      if (!isModalShown(discModalEl)) return;
      const content = discModalEl.querySelector('.modal-content');
      content.classList.add('is-frozen');
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

    const bonusItemsTbody = document.querySelector('#bonusItemsTable tbody');
    const bonusNotBonusAlert = document.getElementById('bonusNotBonusAlert');
    const bonusModalSubtitle = document.getElementById('bonusModalSubtitle');
    let bonusTarget = null;

    function clearBonusTable() { bonusItemsTbody.innerHTML = ''; }

    function addBonusItemEditorRow({seq_no="", title="", item_type="featurette", runtime_seconds="", notes=""} = {}) {
      const row = el(`
        <tr>
          <td><input class="form-control form-control-sm" name="seq_no" type="number" min="1" placeholder="1" value="${escapeHtml(seq_no)}"></td>
          <td><input class="form-control form-control-sm" name="title" placeholder="${escapeHtml(fmt('ph.title'))}" value="${escapeHtml(title)}"></td>
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
          <td><input class="form-control form-control-sm" name="runtime_seconds" type="number" min="0" placeholder="${escapeHtml(fmt('ph.runtime'))}" value="${escapeHtml(runtime_seconds)}"></td>
          <td><input class="form-control form-control-sm" name="notes" placeholder="${escapeHtml(fmt('ph.optional'))}" value="${escapeHtml(notes)}"></td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="${escapeHtml(fmt('aria.remove_item'))}">✕</button></td>
        </tr>
      `);
      row.querySelector('select[name="item_type"]').value = item_type || 'featurette';
      row.querySelector('.btn-outline-danger').addEventListener('click', () => { row.remove(); scheduleSave(); });
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

      bootstrap.Modal.getOrCreateInstance(bonusModalEl).show();
    }

    document.getElementById('btnAddBonusItemRow').addEventListener('click', () => {
      const seqs = [...bonusItemsTbody.querySelectorAll('input[name="seq_no"]')]
        .map(x => Number(x.value))
        .filter(n => Number.isFinite(n) && n > 0);
      const next = seqs.length ? Math.max(...seqs) + 1 : 1;
      addBonusItemEditorRow({seq_no: next});
      scheduleSave();
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
      if (btn) btn.textContent = cleaned.length ? fmt('btn.edit_count', {n: cleaned.length}) : fmt('btn.edit');

      bonusTarget = null;
      clearBonusTable();
      scheduleSave();
    });

    const singleTbody = document.querySelector('#singleTable tbody');
    const discEditorTbody = document.querySelector('#discEditorTable tbody');
    const discModalSubtitle = document.getElementById('discModalSubtitle');
    let discModalTargetSingleRow = null;

    function clearDiscEditorTable() { discEditorTbody.innerHTML = ''; }

    function updateSingleDiscButtonLabel(singleRow) {
      const discs = getJsonAttr(singleRow, 'data-discs', []);
      const btn = singleRow.querySelector('button[data-action="edit-discs"]');
      const n = Array.isArray(discs) ? discs.length : 0;
      btn.textContent = n ? fmt('btn.discs_count', {n}) : fmt('btn.discs');
    }

    function addDiscEditorRow({type_disc="feature", format="BD", label="", bonus_items=[]} = {}) {
      const safeLabel = (label === null || label === undefined || label === 'null') ? '' : String(label);

      const row = el(`
        <tr data-bonus-items="[]">
          <td>
            <select class="form-select form-select-sm" name="type_disc">
              <option value="feature">${escapeHtml(fmt('disc.feature'))}</option>
              <option value="bonus">${escapeHtml(fmt('disc.bonus'))}</option>
            </select>
          </td>
          <td>
            <select class="form-select form-select-sm" name="format">
              <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
              <option value="BD">${escapeHtml(fmt('format.bd_short'))}</option>
              <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
            </select>
          </td>
          <td><input class="form-control form-control-sm" name="label" placeholder="${escapeHtml(fmt('ph.optional_label'))}" value="${escapeHtml(safeLabel)}"></td>
          <td class="nowrap">
            <button class="btn btn-xs btn-outline-secondary" type="button" data-action="edit-bonus">${escapeHtml(fmt('btn.edit'))}</button>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="${escapeHtml(fmt('aria.remove_disc'))}">✕</button></td>
        </tr>
      `);

      row.querySelector('select[name="type_disc"]').value = type_disc || 'feature';
      row.querySelector('select[name="format"]').value = format || 'BD';
      setJsonAttr(row, 'data-bonus-items', bonus_items || []);

      row.querySelector('button[data-action="edit-bonus"]').addEventListener('click', () => {
        const discType = row.querySelector('select[name="type_disc"]').value;
        const discFormat = row.querySelector('select[name="format"]').value;
        const discLabel = row.querySelector('input[name="label"]').value.trim();
        openBonusEditor({
          targetRow: row,
          discType,
          subtitle: fmt('subtitle.single_disc', {discType, discFormat}) + (discLabel ? ` · ${fmt('label.label')}="${discLabel}"` : '')
        });
      });

      row.querySelector('.btn-outline-danger').addEventListener('click', () => { row.remove(); scheduleSave(); });

      discEditorTbody.appendChild(row);
    }

    function openDiscModalForSingleRow(singleRow) {
      discModalTargetSingleRow = singleRow;
      clearDiscEditorTable();

      const title = singleRow.querySelector('input[name="title"]').value.trim() || fmt('word.untitled');
      const barcode = singleRow.querySelector('input[name="barcode"]').value.trim() || fmt('word.no_barcode');
      discModalSubtitle.textContent = `${title} · EAN ${barcode}`;

      const existingDiscs = getJsonAttr(singleRow, 'data-discs', []);
      (existingDiscs || []).forEach(d => addDiscEditorRow(d));
      if (!existingDiscs || existingDiscs.length === 0) {
        const rowFormat = singleRow.querySelector('select[name="single_row_format"]').value;
        addDiscEditorRow({type_disc: 'feature', format: rowFormat});
      }

      bootstrap.Modal.getOrCreateInstance(discModalEl).show();
    }

    document.getElementById('btnAddDiscEditorRow').addEventListener('click', () => {
      const rowFormat = discModalTargetSingleRow
        ? discModalTargetSingleRow.querySelector('select[name="single_row_format"]').value
        : document.getElementById('singleFormat').value;
      addDiscEditorRow({type_disc: 'feature', format: rowFormat});
      scheduleSave();
    });

    document.getElementById('btnSaveDiscsForSingle').addEventListener('click', () => {
      if (!discModalTargetSingleRow) return;

      const discs = [...discEditorTbody.querySelectorAll('tr')].map(tr => {
        const type_disc = tr.querySelector('select[name="type_disc"]').value;
        const format = tr.querySelector('select[name="format"]').value;

        let label = tr.querySelector('input[name="label"]').value;
        label = (label ?? '').trim();
        if (label === 'null' || label === 'NULL') label = '';

        const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);
        return { type_disc, format, label: label || null, bonus_items };
      });

      setJsonAttr(discModalTargetSingleRow, 'data-discs', discs);
      updateSingleDiscButtonLabel(discModalTargetSingleRow);

      discModalTargetSingleRow = null;
      clearDiscEditorTable();
      scheduleSave();
    });

    function addSingleRow({title = "", format = null, barcode = "", imdb = "", tmdb = ""} = {}) {
      const defaultFormat = format || document.getElementById('singleFormat').value;

      const row = el(`
        <tr data-discs="[]">
          <td><input class="form-control form-control-sm" name="title" placeholder="${escapeHtml(fmt('ph.title'))}" value="${escapeHtml(title)}"></td>

          <td>
            <select class="form-select form-select-sm" name="single_row_format">
              <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
              <option value="BD">${escapeHtml(fmt('format.bd_short'))}</option>
              <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
            </select>
          </td>

          <td><input class="form-control form-control-sm" name="barcode" placeholder="${escapeHtml(fmt('ph.ean13'))}" value="${escapeHtml(barcode)}"></td>

          <td>
            <div class="input-group input-group-sm">
              <input class="form-control form-control-sm" name="imdb" placeholder="${escapeHtml(fmt('ph.imdb'))}" value="${escapeHtml(imdb)}">
              <button
                class="btn btn-outline-primary"
                type="button"
                data-action="open-search"
                data-bs-toggle="modal"
                data-bs-target="#searchModal"
                title="${escapeHtml(fmt('btn.search_tmdb'))}"
              >🔍</button>
            </div>

            <input type="hidden" name="tmdb_id" value="${escapeHtml(tmdb)}">
          </td>

          <td class="nowrap">
            <button class="btn btn-xs btn-outline-primary" type="button" data-action="edit-discs">${escapeHtml(fmt('btn.discs'))}</button>
          </td>

          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="${escapeHtml(fmt('aria.remove_row'))}">✕</button></td>
        </tr>
      `);

      row.querySelector('select[name="single_row_format"]').value = defaultFormat;
      row.querySelector('select[name="single_row_format"]').addEventListener('change', scheduleSave);

      row.querySelector('button[data-action="edit-discs"]').addEventListener('click', () => openDiscModalForSingleRow(row));
      row.querySelector('.btn-outline-danger').addEventListener('click', () => { row.remove(); scheduleSave(); });

      row.querySelector('button[data-action="open-search"]').addEventListener('click', () => {
        window.__bulkActiveImdbInput = row.querySelector('input[name="imdb"]');
        window.__bulkActiveTmdbInput = row.querySelector('input[name="tmdb_id"]');
      });

      singleTbody.appendChild(row);
      updateSingleDiscButtonLabel(row);
      scheduleSave();
    }

    function readSinglesForm() {
      const rows = [...singleTbody.querySelectorAll('tr')].map(tr => {
        const title = tr.querySelector('input[name="title"]').value.trim();
        const format = tr.querySelector('select[name="single_row_format"]').value;
        const barcode = tr.querySelector('input[name="barcode"]').value.trim();
        const imdb = tr.querySelector('input[name="imdb"]').value.trim();
        const tmdb = tr.querySelector('input[name="tmdb_id"]').value.trim();
        const discs = getJsonAttr(tr, 'data-discs', []);
        return { title, format, barcode, imdb_id: imdb || null, tmdb_id: tmdb || null, discs };
      });

      return {
        kind: "singles",
        default_copy_count: Number(document.getElementById('singleCopyCount').value || 1),
        rows
      };
    }

    function validateSinglesHaveFeatureDisc() {
      const rows = [...singleTbody.querySelectorAll('tr')];
      const bad = [];

      rows.forEach((tr, idx) => {
        const title = tr.querySelector('input[name="title"]')?.value?.trim() || `Row ${idx + 1}`;
        const discs = getJsonAttr(tr, 'data-discs', []);
        const hasFeature = Array.isArray(discs) && discs.some(d => d && d.type_disc === 'feature');
        if (!hasFeature) bad.push(title);
      });

      if (bad.length) {
        alert(fmt('err.missing_feature_disc') + "\n\n- " + bad.join("\n- "));
        return false;
      }
      return true;
    }

    document.getElementById('btnSingleAddRow').addEventListener('click', () => addSingleRow());
    document.getElementById('btnSingleAddFromText').addEventListener('click', () => {
      const v = document.getElementById('singleQuickImport').value.trim();
      if (v) addSingleRow({title: v});
      document.getElementById('singleQuickImport').value = "";
      scheduleSave();
    });

    document.getElementById('btnSinglePreview').addEventListener('click', () => {
      if (!validateSinglesHaveFeatureDisc()) return;
      showPreview(readSinglesForm());
    });

    const accordion = document.getElementById('boxSetsAccordion');
    let boxSetSeq = 0;
    let pasteTargetBoxSetId = null;

    function renumberBoxOrders(tbody) {
      [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
        const c = tr.querySelector('[data-order]');
        if (c) c.textContent = i + 1;
      });
    }

    function renumberBoxDiscOrders(boxSetRoot) {
      const tbody = boxSetRoot.querySelector('tbody[data-role="discs"]');
      [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
        tr.children[0].textContent = i + 1;
      });
    }

    function refreshBoxDiscRelatedLabels(boxSetRoot) {
      const movieRows = [...boxSetRoot.querySelectorAll('tbody[data-role="movies"] tr')];

      [...boxSetRoot.querySelectorAll('tbody[data-role="discs"] tr')].forEach(tr => {
        const raw = tr.getAttribute('data-related-index');
        let label = '';

        if (raw !== null && raw !== '') {
          const idx = Number(raw);
          const movieRow = movieRows[idx];
          label = movieRow ? movieRow.querySelector('input[name="title"]').value.trim() : '';
        }

        const cell = tr.querySelector('[data-role="disc-related"]');
        if (cell) cell.textContent = label;
      });
    }

    function refreshBoxRelatedDropdown(boxSetRoot) {
      const titles = [...boxSetRoot.querySelectorAll('tbody[data-role="movies"] tr')]
        .map(tr => tr.querySelector('input[name="title"]').value.trim());

      const sel = boxSetRoot.querySelector('select[name="disc_related"]');
      sel.innerHTML =
        '<option value="">' + escapeHtml(fmt('word.whole_box')) + '</option>' +
        titles.map((t, i) => `<option value="${i}">${escapeHtml(t || fmt('fmt.untitled_n', {n: i+1}))}</option>`).join('');

      refreshBoxDiscRelatedLabels(boxSetRoot);
    }

    function addBoxTitleRow(boxSetRoot, {title="", imdb="", tmdb="", innerEan="", treatAsSingle=null} = {}) {
      if (treatAsSingle === null) treatAsSingle = Boolean(innerEan && innerEan.trim());

      const tbody = boxSetRoot.querySelector('tbody[data-role="movies"]');
      const row = el(`
        <tr>
          <td data-order class="text-muted small">-</td>

          <td>
            <input class="form-control form-control-sm" name="title"
                   placeholder="${escapeHtml(fmt('ph.title'))}"
                   value="${escapeHtml(title)}">
          </td>

          <td>
            <div class="input-group input-group-sm">
              <input class="form-control form-control-sm" name="imdb"
                     placeholder="${escapeHtml(fmt('ph.imdb'))}"
                     value="${escapeHtml(imdb)}">
              <button
                class="btn btn-outline-primary"
                type="button"
                data-action="open-search"
                data-bs-toggle="modal"
                data-bs-target="#searchModal"
                title="${escapeHtml(fmt('btn.search_tmdb'))}"
              >🔍</button>
            </div>

            <input type="hidden" name="tmdb_id" value="${escapeHtml(tmdb)}">
          </td>

          <td>
            <input class="form-control form-control-sm" name="inner_ean"
                   placeholder="${escapeHtml(fmt('ph.ean13'))}"
                   value="${escapeHtml(innerEan)}">
          </td>

          <td>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="treat_as_single" ${treatAsSingle ? "checked" : ""}>
              <label class="form-check-label small text-muted">${escapeHtml(fmt('label.create_single'))}</label>
            </div>
          </td>

          <td>
            <button class="btn btn-sm btn-outline-danger" type="button"
                    aria-label="${escapeHtml(fmt('aria.remove_movie'))}">✕</button>
          </td>
        </tr>
      `);

      const titleInput = row.querySelector('input[name="title"]');
      const imdbInput = row.querySelector('input[name="imdb"]');
      const tmdbInput = row.querySelector('input[name="tmdb_id"]');
      const innerEanInput = row.querySelector('input[name="inner_ean"]');
      const treatCb = row.querySelector('input[name="treat_as_single"]');

      row.querySelector('button[data-action="open-search"]').addEventListener('click', () => {
        window.__bulkActiveImdbInput = imdbInput;
        window.__bulkActiveTmdbInput = tmdbInput;
      });

      row.querySelector('.btn-outline-danger').addEventListener('click', () => {
        const removedIndex = [...tbody.querySelectorAll('tr')].indexOf(row);
        row.remove();

        [...boxSetRoot.querySelectorAll('tbody[data-role="discs"] tr')].forEach(discTr => {
          const raw = discTr.getAttribute('data-related-index');
          if (raw === null || raw === '') return;

          let idx = Number(raw);
          if (!Number.isFinite(idx)) {
            discTr.setAttribute('data-related-index', '');
            return;
          }

          if (idx === removedIndex) {
            discTr.setAttribute('data-related-index', '');
          } else if (idx > removedIndex) {
            discTr.setAttribute('data-related-index', String(idx - 1));
          }
        });

        renumberBoxOrders(tbody);
        refreshBoxRelatedDropdown(boxSetRoot);
        scheduleSave();
      });

      titleInput.addEventListener('input', () => { refreshBoxRelatedDropdown(boxSetRoot); scheduleSave(); });
      imdbInput.addEventListener('input', scheduleSave);
      innerEanInput.addEventListener('input', () => { if (innerEanInput.value.trim()) treatCb.checked = true; scheduleSave(); });
      treatCb.addEventListener('change', scheduleSave);

      tbody.appendChild(row);
      renumberBoxOrders(tbody);
      refreshBoxRelatedDropdown(boxSetRoot);
      scheduleSave();
    }

    function addBoxDiscRow(boxSetRoot, {typeDisc="feature", format="BD", label="", relatedIndex="", relatedTitle="", bonus_items=[]} = {}) {
      const safeLabel = (label === null || label === undefined || label === 'null') ? '' : String(label);
      const tbody = boxSetRoot.querySelector('tbody[data-role="discs"]');
      const order = tbody.querySelectorAll('tr').length + 1;

      const row = el(`
        <tr data-bonus-items="[]" data-related-index="${escapeHtml(relatedIndex)}">
          <td class="text-muted small">${order}</td>
          <td><span class="badge text-bg-${typeDisc === 'bonus' ? 'warning' : 'primary'}" data-role="disc-type">${escapeHtml(typeDisc)}</span></td>
          <td data-role="disc-format">${escapeHtml(format)}</td>
          <td data-role="disc-label">${escapeHtml(safeLabel)}</td>
          <td data-role="disc-related">${escapeHtml(relatedTitle)}</td>
          <td class="nowrap">
            <button class="btn btn-xs btn-outline-secondary" type="button" data-action="edit-bonus">${escapeHtml(fmt('btn.edit'))}</button>
          </td>
          <td><button class="btn btn-sm btn-outline-danger" type="button" aria-label="${escapeHtml(fmt('aria.remove_disc'))}">✕</button></td>
        </tr>
      `);

      setJsonAttr(row, 'data-bonus-items', bonus_items || []);

      row.querySelector('.btn-outline-danger').addEventListener('click', () => {
        row.remove();
        renumberBoxDiscOrders(boxSetRoot);
        scheduleSave();
      });

      row.querySelector('button[data-action="edit-bonus"]').addEventListener('click', () => {
        const currentOrder = row.children[0].textContent.trim();
        const discType = row.querySelector('[data-role="disc-type"]').textContent.trim();
        const discFormat = row.querySelector('[data-role="disc-format"]').textContent.trim();
        const discLabel = row.querySelector('[data-role="disc-label"]').textContent.trim();
        const rel = row.querySelector('[data-role="disc-related"]').textContent.trim();
        const subtitleBase = fmt('subtitle.box_disc', {n: currentOrder, discType, discFormat, rel: rel || fmt('word.whole_box_plain')});
        const subtitle = discLabel ? `${subtitleBase} · ${fmt('label.label')}="${discLabel}"` : subtitleBase;
        openBonusEditor({ targetRow: row, discType, subtitle });
      });

      tbody.appendChild(row);

      const btn = row.querySelector('button[data-action="edit-bonus"]');
      const n = (bonus_items || []).length;
      btn.textContent = n ? fmt('btn.edit_count', {n}) : fmt('btn.edit');

      scheduleSave();
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
              <span class="me-2">${escapeHtml(fmt('fmt.box_set_n', {n: boxSetSeq}))}</span>
              <span class="badge text-bg-light border text-muted ms-auto" data-role="summary">${escapeHtml(fmt('word.not_set'))}</span>
            </button>
          </h2>
          <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headingId}" data-bs-parent="#boxSetsAccordion">
            <div class="accordion-body">

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label">${escapeHtml(fmt('label.format'))}</label>
                  <select class="form-select" name="box_format">
                    <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
                    <option value="BD" selected>${escapeHtml(fmt('format.bd_short'))}</option>
                    <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">${escapeHtml(fmt('label.box_barcode'))}</label>
                  <input class="form-control" name="box_barcode" placeholder="${escapeHtml(fmt('ph.box_barcode'))}" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">${escapeHtml(fmt('label.copy_count'))}</label>
                  <input type="number" class="form-control" name="box_copy_count" value="1" min="1" />
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h6 mb-0">${escapeHtml(fmt('section.movies_in_box'))}</h3>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-primary" type="button" data-action="add-movie">${escapeHtml(fmt('btn.add_movie'))}</button>
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-action="paste-movies" data-bs-toggle="modal" data-bs-target="#pasteModal">${escapeHtml(fmt('btn.paste_list'))}</button>
                </div>
              </div>

              <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 80px;">${escapeHtml(fmt('col.order'))}</th>
                      <th style="min-width: 260px;">${escapeHtml(fmt('col.title'))}</th>
                      <th style="min-width: 220px;">${escapeHtml(fmt('col.imdb'))}</th>
                      <th style="min-width: 170px;">${escapeHtml(fmt('col.inner_ean'))}</th>
                      <th style="width: 180px;">${escapeHtml(fmt('col.treat_as_single'))}</th>
                      <th style="width: 60px;"></th>
                    </tr>
                  </thead>
                  <tbody data-role="movies"></tbody>
                </table>
              </div>

              <div class="card mb-3">
                <div class="card-header">${escapeHtml(fmt('section.discs_in_box'))}</div>
                <div class="card-body">
                  <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-2">
                      <label class="form-label">${escapeHtml(fmt('label.disc_type'))}</label>
                      <select class="form-select" name="disc_type">
                        <option value="feature" selected>feature</option>
                        <option value="bonus">bonus</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label">${escapeHtml(fmt('label.disc_format'))}</label>
                      <select class="form-select" name="disc_format">
                        <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
                        <option value="BD" selected>${escapeHtml(fmt('format.bd_short'))}</option>
                        <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label"><?= h(tr($t, 'col.label')) ?></label>
                      <input class="form-control" name="disc_label" placeholder="<?= h(tr($t, 'ph.optional_label')) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">${escapeHtml(fmt('label.related_movie'))}</label>
                      <select class="form-select" name="disc_related">
                        <option value="">${escapeHtml(fmt('word.whole_box'))}</option>
                      </select>
                      <div class="form-text">${escapeHtml(fmt('hint.related_movie'))}</div>
                    </div>
                    <div class="col-md-2">
                      <button class="btn btn-primary w-100" type="button" data-action="add-disc">${escapeHtml(fmt('btn.add_disc'))}</button>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th style="width: 80px;">${escapeHtml(fmt('col.order'))}</th>
                          <th style="width: 120px;">${escapeHtml(fmt('col.type'))}</th>
                          <th style="width: 120px;">${escapeHtml(fmt('col.format'))}</th>
                          <th style="min-width: 180px;"><?= h(tr($t, 'col.label')) ?></th>
                          <th>${escapeHtml(fmt('col.related_movie'))}</th>
                          <th style="width: 200px;">${escapeHtml(fmt('col.bonus_items'))}</th>
                          <th style="width: 60px;"></th>
                        </tr>
                      </thead>
                      <tbody data-role="discs"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end">
                <button class="btn btn-outline-danger" type="button" data-action="remove-boxset">${escapeHtml(fmt('btn.remove_boxset'))}</button>
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
        parts.push(barcode ? `EAN ${barcode}` : fmt('word.no_ean'));
        parts.push(fmt('fmt.movies_count', {n: movieCount}));
        parts.push(fmt('fmt.discs_count', {n: discCount}));
        badge.textContent = parts.join(' · ');
      }

      root.querySelector('[data-action="add-movie"]').addEventListener('click', () => {
        addBoxTitleRow(root);
        updateSummary();
        scheduleSave();
      });

      root.querySelector('[data-action="paste-movies"]').addEventListener('click', () => {
        pasteTargetBoxSetId = boxSetId;
      });

      root.querySelector('[data-action="add-disc"]').addEventListener('click', () => {
        const typeDisc = root.querySelector('select[name="disc_type"]').value;
        const format = root.querySelector('select[name="disc_format"]').value;
        let label = root.querySelector('input[name="disc_label"]').value.trim();
        if (label === 'null' || label === 'NULL') label = '';
        const relatedIdx = root.querySelector('select[name="disc_related"]').value;

        let relatedTitle = "";
        if (relatedIdx !== "") {
          const rows = root.querySelectorAll('tbody[data-role="movies"] tr');
          const tr = rows[Number(relatedIdx)];
          relatedTitle = tr ? tr.querySelector('input[name="title"]').value.trim() : "";
        }

        addBoxDiscRow(root, {
          typeDisc,
          format,
          label,
          relatedIndex: relatedIdx,
          relatedTitle
        });

        root.querySelector('input[name="disc_label"]').value = "";
        updateSummary();
        scheduleSave();
      });

      root.querySelector('[data-action="remove-boxset"]').addEventListener('click', () => { root.remove(); scheduleSave(); });
      root.querySelector('input[name="box_barcode"]').addEventListener('input', () => { updateSummary(); scheduleSave(); });
      root.querySelector('select[name="box_format"]').addEventListener('change', scheduleSave);
      root.querySelector('input[name="box_copy_count"]').addEventListener('input', scheduleSave);
      root.querySelector('input[name="disc_label"]').addEventListener('input', scheduleSave);

      addBoxTitleRow(root);
      refreshBoxRelatedDropdown(root);
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
          const tmdb = tr.querySelector('input[name="tmdb_id"]').value.trim();
          const innerEan = tr.querySelector('input[name="inner_ean"]').value.trim();
          const treatAsSingle = tr.querySelector('input[name="treat_as_single"]').checked;
          return {
            order: i + 1,
            title,
            imdb_id: imdb || null,
            tmdb_id: tmdb || null,
            inner_case_ean: innerEan || null,
            treat_as_single: treatAsSingle
          };
        });

        const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => {
          const type_disc = tr.querySelector('[data-role="disc-type"]')?.textContent?.trim() || null;
          const format = tr.querySelector('[data-role="disc-format"]')?.textContent?.trim() || null;
          const label_raw = tr.querySelector('[data-role="disc-label"]')?.textContent ?? '';
          const label = label_raw.trim() || null;
          const related_title = tr.querySelector('[data-role="disc-related"]')?.textContent?.trim() || null;
          const related_index_raw = tr.getAttribute('data-related-index');
          const related_index = related_index_raw === "" || related_index_raw == null ? null : Number(related_index_raw);
          const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);

          return {
            order: Number(tr.children[0].textContent.trim()),
            type_disc,
            format,
            label,
            related_index,
            related_title: related_title || null,
            bonus_items
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
      scheduleSave();
    });

    document.getElementById('btnPreviewAllBoxSets').addEventListener('click', () => {
      const payload = { kind: "box_sets_bulk", box_sets: readAllBoxSets() };
      console.log(payload);
      showPreview(payload);
    });

    document.getElementById('btnBoxPasteApply').addEventListener('click', () => {
      const lines = document.getElementById('boxPasteArea').value
        .split('\n')
        .map(x => x.trim())
        .filter(Boolean);

      document.getElementById('boxPasteArea').value = "";

      if (!pasteTargetBoxSetId) return;
      const target = accordion.querySelector(`[data-boxset-id="${pasteTargetBoxSetId}"]`);
      if (!target) return;

      lines.forEach(t => addBoxTitleRow(target, {title: t}));
      pasteTargetBoxSetId = null;
      scheduleSave();
    });

    const STORAGE_KEY = 'bulkAddState_v11';
    let saveTimer = null;

    function scheduleSave() {
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeState()));
        } catch {}
      }, 250);
    }

    function serializeState() {
      const singles = {
        default_format: document.getElementById('singleFormat').value,
        default_copy_count: Number(document.getElementById('singleCopyCount').value || 1),
        quick_title: document.getElementById('singleQuickImport').value || '',
        rows: [...singleTbody.querySelectorAll('tr')].map(tr => ({
          title: tr.querySelector('input[name="title"]')?.value ?? '',
          format: tr.querySelector('select[name="single_row_format"]')?.value ?? 'BD',
          barcode: tr.querySelector('input[name="barcode"]')?.value ?? '',
          imdb: tr.querySelector('input[name="imdb"]')?.value ?? '',
          tmdb: tr.querySelector('input[name="tmdb_id"]')?.value ?? '',
          discs: getJsonAttr(tr, 'data-discs', []),
        })),
      };

      const boxSets = [...accordion.querySelectorAll('[data-boxset-id]')].map(root => {
        const movies = [...root.querySelectorAll('tbody[data-role="movies"] tr')].map(tr => ({
          title: tr.querySelector('input[name="title"]')?.value ?? '',
          imdb: tr.querySelector('input[name="imdb"]')?.value ?? '',
          tmdb: tr.querySelector('input[name="tmdb_id"]')?.value ?? '',
          inner_ean: tr.querySelector('input[name="inner_ean"]')?.value ?? '',
          treat_as_single: !!tr.querySelector('input[name="treat_as_single"]')?.checked,
        }));

        const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => ({
          typeDisc: tr.querySelector('[data-role="disc-type"]')?.textContent?.trim() ?? 'feature',
          format: tr.querySelector('[data-role="disc-format"]')?.textContent?.trim() ?? 'BD',
          label: tr.querySelector('[data-role="disc-label"]')?.textContent?.trim() ?? '',
          relatedIndex: tr.getAttribute('data-related-index') ?? '',
          relatedTitle: tr.querySelector('[data-role="disc-related"]')?.textContent?.trim() ?? '',
          bonus_items: getJsonAttr(tr, 'data-bonus-items', []),
        }));

        return {
          box_format: root.querySelector('select[name="box_format"]')?.value ?? 'BD',
          box_barcode: root.querySelector('input[name="box_barcode"]')?.value ?? '',
          box_copy_count: Number(root.querySelector('input[name="box_copy_count"]')?.value || 1),
          movies,
          discs,
        };
      });

      return { singles, boxSets };
    }

    function restoreState(state) {
      if (!state) return;

      if (state.singles?.default_format) document.getElementById('singleFormat').value = state.singles.default_format;
      if (state.singles?.default_copy_count) document.getElementById('singleCopyCount').value = String(state.singles.default_copy_count);
      if (typeof state.singles?.quick_title === 'string') document.getElementById('singleQuickImport').value = state.singles.quick_title;

      singleTbody.innerHTML = '';
      (state.singles?.rows || []).forEach(r => {
        addSingleRow({ title: r.title, format: r.format, barcode: r.barcode, imdb: r.imdb, tmdb: r.tmdb });
        const trEl = singleTbody.lastElementChild;
        if (trEl) {
          setJsonAttr(trEl, 'data-discs', Array.isArray(r.discs) ? r.discs : []);
          updateSingleDiscButtonLabel(trEl);
        }
      });
      if ((state.singles?.rows || []).length === 0) addSingleRow();

      accordion.innerHTML = '';
      boxSetSeq = 0;
      (state.boxSets || []).forEach(bs => {
        const card = createBoxSetCard();
        accordion.appendChild(card);
        const root = card;

        root.querySelector('select[name="box_format"]').value = bs.box_format || 'BD';
        root.querySelector('input[name="box_barcode"]').value = bs.box_barcode || '';
        root.querySelector('input[name="box_copy_count"]').value = String(bs.box_copy_count || 1);

        const moviesTbody = root.querySelector('tbody[data-role="movies"]');
        moviesTbody.innerHTML = '';
        (bs.movies || []).forEach(m => addBoxTitleRow(root, m));
        if ((bs.movies || []).length === 0) addBoxTitleRow(root);

        const discsTbody = root.querySelector('tbody[data-role="discs"]');
        discsTbody.innerHTML = '';
        (bs.discs || []).forEach(d => {
          addBoxDiscRow(root, {
            typeDisc: d.typeDisc,
            format: d.format,
            label: d.label ?? '',
            relatedIndex: d.relatedIndex ?? '',
            relatedTitle: d.relatedTitle ?? '',
            bonus_items: d.bonus_items ?? []
          });
          const trEl = discsTbody.lastElementChild;
          if (trEl) setJsonAttr(trEl, 'data-bonus-items', Array.isArray(d.bonus_items) ? d.bonus_items : []);
        });

        refreshBoxRelatedDropdown(root);
        root.querySelector('input[name="box_barcode"]').dispatchEvent(new Event('input', { bubbles: true }));
      });

      if ((state.boxSets || []).length === 0) accordion.appendChild(createBoxSetCard());
    }

    document.addEventListener('input', scheduleSave, true);
    document.addEventListener('change', scheduleSave, true);

    document.getElementById('btnResetAll').addEventListener('click', () => {
      if (!confirm(fmt('confirm.reset'))) return;
      try { localStorage.removeItem(STORAGE_KEY); } catch {}
      location.reload();
    });

    addSingleRow();
    accordion.appendChild(createBoxSetCard());

    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) restoreState(JSON.parse(raw));
    } catch {}
  </script>

  <script src="script.js"></script>
</body>
</html>

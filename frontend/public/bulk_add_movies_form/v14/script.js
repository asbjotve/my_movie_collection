const API_ENDPOINT = 'api.php';
const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';
const I18N = window.BAMF_I18N || {};
const DEFAULT_STORAGE_ID = window.BAMF_DEFAULT_STORAGE_ID || '';
const STORAGE_KEY = 'bulkAddState_v14';

let searchTimeout;
let tvdbSearchTimeout;
let saveTimer = null;
let bonusTarget = null;
let discModalTargetSingleRow = null;
let pasteTargetBoxSetId = null;
let boxSetSeq = 0;

window.__bulkActiveImdbInput = window.__bulkActiveImdbInput || null;
window.__bulkActiveTmdbInput = window.__bulkActiveTmdbInput || null;
window.__bulkActiveTvdbInput = window.__bulkActiveTvdbInput || null;

const boxSetsContainer = document.getElementById('boxSetsContainer');
const singleTbody = document.querySelector('#singleTable tbody');
const discEditorTbody = document.querySelector('#discEditorTable tbody');
const bonusItemsTbody = document.querySelector('#bonusItemsTable tbody');
const discModalSubtitle = document.getElementById('discModalSubtitle');
const bonusModalSubtitle = document.getElementById('bonusModalSubtitle');
const bonusNotBonusAlert = document.getElementById('bonusNotBonusAlert');
const previewPre = document.getElementById('previewPre');
const apiStatusTag = document.getElementById('apiStatusTag');
const apiStatusText = document.getElementById('apiStatusText');
const apiStatusPre = document.getElementById('apiStatusPre');
const searchInput = document.getElementById('searchInput');
const dropdownResults = document.getElementById('dropdownResults');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus = document.getElementById('searchStatus');
const searchModal = document.getElementById('searchModal');
const selectedItemContainer = document.getElementById('selectedItem');
const tvdbSearchInput = document.getElementById('tvdbSearchInput');
const tvdbDropdownResults = document.getElementById('tvdbDropdownResults');
const tvdbLoadingSpinner = document.getElementById('tvdbLoadingSpinner');
const tvdbSearchStatus = document.getElementById('tvdbSearchStatus');
const tvdbSearchModal = document.getElementById('tvdbSearchModal');

function el(html) {
  const template = document.createElement('template');
  template.innerHTML = html.trim();
  return template.content.firstChild;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
  return String(value ?? '')
    .replaceAll('\\', '\\\\')
    .replaceAll("'", "\\'")
    .replaceAll('\n', ' ')
    .replaceAll('\r', ' ');
}

function fmt(key, vars) {
  let text = String(I18N[key] ?? key);
  if (vars) {
    for (const [k, v] of Object.entries(vars)) {
      text = text.replaceAll(`{${k}}`, String(v));
    }
  }
  return text;
}

function getJsonAttr(node, attrName, fallback) {
  const raw = node.getAttribute(attrName);
  if (!raw) return fallback;
  try {
    return JSON.parse(raw);
  } catch {
    return fallback;
  }
}

function setJsonAttr(node, attrName, value) {
  node.setAttribute(attrName, JSON.stringify(value ?? null));
}

function getStorageId() {
  return document.getElementById('storageId').value.trim() || DEFAULT_STORAGE_ID;
}

function showPreview(obj) {
  previewPre.textContent = JSON.stringify(obj, null, 2);
  openModal('previewModal');
}

function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('active');
  modal.setAttribute('aria-hidden', 'false');

  if (id === 'searchModal') {
    setTimeout(() => searchInput.focus(), 0);
  }
  if (id === 'tvdbSearchModal') {
    setTimeout(() => tvdbSearchInput.focus(), 0);
  }
}

function closeModal(modalOrId) {
  const modal = typeof modalOrId === 'string' ? document.getElementById(modalOrId) : modalOrId;
  if (!modal) return;
  modal.classList.remove('active');
  modal.setAttribute('aria-hidden', 'true');

  if (modal.id === 'searchModal') {
    searchInput.value = '';
    dropdownResults.innerHTML = '';
    selectedItemContainer.innerHTML = '';
    searchStatus.textContent = fmt('status.search_short');
    loadingSpinner.classList.add('hidden');
  }
  if (modal.id === 'tvdbSearchModal') {
    tvdbSearchInput.value = '';
    tvdbDropdownResults.innerHTML = '';
    tvdbSearchStatus.textContent = fmt('status.search_short');
    tvdbLoadingSpinner.classList.add('hidden');
    const movieRadio = document.querySelector('input[name="tvdbSearchType"][value="movie"]');
    if (movieRadio) movieRadio.checked = true;
  }
}

function trimToNull(value) {
  const trimmed = String(value ?? '').trim();
  return trimmed === '' ? null : trimmed;
}

function setStatus(kind, text, payload) {
  apiStatusTag.className = 'tag';
  if (kind === 'success') apiStatusTag.classList.add('good');
  if (kind === 'error') apiStatusTag.classList.add('bad');
  if (kind === 'sending') apiStatusTag.classList.add('warn');

  apiStatusTag.textContent =
    kind === 'success' ? fmt('status.success') :
    kind === 'error' ? fmt('status.error') :
    kind === 'sending' ? fmt('status.sending') : '…';

  apiStatusText.textContent = text;
  apiStatusPre.textContent = JSON.stringify(payload ?? {}, null, 2);
}

/**
 * Pydantic 422-feil kommer som en liste av objekter (loc/msg/type), ikke en
 * enkel streng - gjør dem lesbare i stedet for at UI viser "[object Object]".
 */
function formatErrorDetail(data) {
  if (!data) return null;
  if (typeof data.detail === 'string') return data.detail;
  if (Array.isArray(data.detail)) {
    return data.detail
      .map(item => {
        const field = Array.isArray(item.loc) ? item.loc.filter(p => typeof p !== 'number').join(' → ') : '';
        return field ? `${field}: ${item.msg}` : item.msg;
      })
      .join('\n');
  }
  if (typeof data.error === 'string') return data.error;
  if (typeof data.raw === 'string') return data.raw;
  return null;
}

async function submitPayload(payload, submitButton) {
  const previousLabel = submitButton.textContent;
  submitButton.disabled = true;
  setStatus('sending', fmt('status.sending'), payload);

  try {
    const response = await fetch(`${API_ENDPOINT}?action=submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const text = await response.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch {
      data = { raw: text || fmt('text.response_empty') };
    }

    if (!response.ok) {
      const message = formatErrorDetail(data) || `${fmt('status.error')} (${response.status})`;
      setStatus('error', message, data);
      return;
    }

    const message = data?.status === 'ok'
      ? `${fmt('status.success')} (${data.kind || payload.kind})`
      : fmt('status.success');
    setStatus('success', message, data);
  } catch (error) {
    setStatus('error', error.message || fmt('status.error'), { error: error.message || String(error) });
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = previousLabel;
  }
}

function scheduleSave() {
  if (saveTimer) clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeState()));
    } catch {}
  }, 250);
}

function clearBonusTable() {
  bonusItemsTbody.innerHTML = '';
}

function addBonusItemEditorRow({ seq_no = '', title = '', item_type = 'featurette', runtime_seconds = '', notes = '' } = {}) {
  const row = el(`
    <tr>
      <td><input name="seq_no" type="number" min="1" placeholder="1" value="${escapeHtml(seq_no)}"></td>
      <td><input name="title" placeholder="${escapeHtml(fmt('ph.title'))}" value="${escapeHtml(title)}"></td>
      <td>
        <select name="item_type">
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
      <td><input name="runtime_seconds" type="number" min="0" placeholder="${escapeHtml(fmt('ph.runtime'))}" value="${escapeHtml(runtime_seconds)}"></td>
      <td><input name="notes" placeholder="${escapeHtml(fmt('ph.optional'))}" value="${escapeHtml(notes)}"></td>
      <td><button class="btn btnDanger btnTiny" type="button" aria-label="${escapeHtml(fmt('aria.remove_item'))}">✕</button></td>
    </tr>
  `);

  row.querySelector('select[name="item_type"]').value = item_type || 'featurette';
  row.querySelector('button').addEventListener('click', () => {
    row.remove();
    scheduleSave();
  });

  bonusItemsTbody.appendChild(row);
}

function openBonusEditor({ targetRow, discType, subtitle }) {
  bonusTarget = { node: targetRow };
  bonusModalSubtitle.textContent = subtitle;
  bonusNotBonusAlert.classList.toggle('hidden', discType === 'bonus');

  clearBonusTable();
  const existing = getJsonAttr(targetRow, 'data-bonus-items', []);
  (existing || []).forEach(item => addBonusItemEditorRow(item));
  if (!existing || existing.length === 0) addBonusItemEditorRow({ seq_no: 1 });

  openModal('bonusModal');
}

function updateSingleDiscButtonLabel(singleRow) {
  const discs = getJsonAttr(singleRow, 'data-discs', []);
  const button = singleRow.querySelector('button[data-action="edit-discs"]');
  const count = Array.isArray(discs) ? discs.length : 0;
  button.textContent = count ? fmt('btn.discs_count', { n: count }) : fmt('btn.discs');
}

function clearDiscEditorTable() {
  discEditorTbody.innerHTML = '';
}

function addDiscEditorRow({
  type_disc = 'feature',
  format = 'BD',
  label = '',
  bonus_items = [],
  storage_slot_no = '',
  add_to_storage = false,
} = {}) {
  const safeLabel = label === null || label === undefined || label === 'null' ? '' : String(label);
  const safeStorageSlotNo = storage_slot_no === null || storage_slot_no === undefined ? '' : String(storage_slot_no);

  const row = el(`
    <tr data-bonus-items="[]">
      <td>
        <select name="type_disc">
          <option value="feature">${escapeHtml(fmt('disc.feature'))}</option>
          <option value="bonus">${escapeHtml(fmt('disc.bonus'))}</option>
        </select>
      </td>
      <td>
        <select name="format">
          <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
          <option value="BD">${escapeHtml(fmt('format.bd_short'))}</option>
          <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
        </select>
      </td>
      <td><input name="label" placeholder="${escapeHtml(fmt('ph.optional_label'))}" value="${escapeHtml(safeLabel)}"></td>
      <td><input name="storage_slot_no" type="number" min="1" value="${escapeHtml(safeStorageSlotNo)}"></td>
      <td><label class="checkLine"><input name="add_to_storage" type="checkbox" ${add_to_storage ? 'checked' : ''}><span>${escapeHtml(fmt('word.yes'))}</span></label></td>
      <td><button class="btn btnTiny" type="button" data-action="edit-bonus">${escapeHtml(fmt('btn.edit'))}</button></td>
      <td><button class="btn btnDanger btnTiny" type="button" aria-label="${escapeHtml(fmt('aria.remove_disc'))}">✕</button></td>
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
      subtitle: fmt('subtitle.single_disc', { discType, discFormat }) + (discLabel ? ` · ${fmt('label.label')}="${discLabel}"` : ''),
    });
  });

  row.querySelector('button[aria-label]').addEventListener('click', () => {
    row.remove();
    scheduleSave();
  });

  discEditorTbody.appendChild(row);
}

function openDiscModalForSingleRow(singleRow) {
  discModalTargetSingleRow = singleRow;
  clearDiscEditorTable();

  const title = singleRow.querySelector('input[name="title"]').value.trim() || fmt('word.untitled');
  const barcode = singleRow.querySelector('input[name="barcode"]').value.trim() || fmt('word.no_barcode');
  discModalSubtitle.textContent = `${title} · EAN ${barcode}`;

  const existingDiscs = getJsonAttr(singleRow, 'data-discs', []);
  (existingDiscs || []).forEach(disc => addDiscEditorRow(disc));
  if (!existingDiscs || existingDiscs.length === 0) {
    const rowFormat = singleRow.querySelector('select[name="single_row_format"]').value;
    addDiscEditorRow({ type_disc: 'feature', format: rowFormat });
  }

  openModal('discModal');
}

function addSingleRow({ title = '', format = null, barcode = '', imdb = '', tmdb = '', tvdb = '' } = {}) {
  const defaultFormat = format || document.getElementById('singleFormat').value;

  const row = el(`
    <tr data-discs="[]">
      <td><input name="title" placeholder="${escapeHtml(fmt('ph.title'))}" value="${escapeHtml(title)}"></td>
      <td>
        <select name="single_row_format">
          <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
          <option value="BD">${escapeHtml(fmt('format.bd_short'))}</option>
          <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
        </select>
      </td>
      <td><input name="barcode" placeholder="${escapeHtml(fmt('ph.ean13'))}" value="${escapeHtml(barcode)}"></td>
      <td>
        <div class="inlineInput">
          <input name="imdb" placeholder="${escapeHtml(fmt('ph.imdb'))}" value="${escapeHtml(imdb)}">
          <button class="btn btnPrimary btnIcon" type="button" data-action="open-search" title="${escapeHtml(fmt('btn.search_tmdb'))}">🔍</button>
        </div>
        <div class="inlineInput">
          <input name="tvdb_id" placeholder="${escapeHtml(fmt('ph.tvdb'))}" value="${escapeHtml(tvdb)}">
          <button class="btn btnPrimary btnIcon" type="button" data-action="open-search-tvdb" title="${escapeHtml(fmt('btn.search_tvdb'))}">🔍</button>
        </div>
        <input type="hidden" name="tmdb_id" value="${escapeHtml(tmdb)}">
      </td>
      <td><button class="btn btnTiny" type="button" data-action="edit-discs">${escapeHtml(fmt('btn.discs'))}</button></td>
      <td><button class="btn btnDanger btnTiny" type="button" aria-label="${escapeHtml(fmt('aria.remove_row'))}">✕</button></td>
    </tr>
  `);

  row.querySelector('select[name="single_row_format"]').value = defaultFormat;
  row.querySelector('select[name="single_row_format"]').addEventListener('change', scheduleSave);
  row.querySelector('button[data-action="edit-discs"]').addEventListener('click', () => openDiscModalForSingleRow(row));
  row.querySelector('button[aria-label]').addEventListener('click', () => {
    row.remove();
    scheduleSave();
  });
  row.querySelector('button[data-action="open-search"]').addEventListener('click', () => {
    window.__bulkActiveImdbInput = row.querySelector('input[name="imdb"]');
    window.__bulkActiveTmdbInput = row.querySelector('input[name="tmdb_id"]');
    openModal('searchModal');
  });
  row.querySelector('button[data-action="open-search-tvdb"]').addEventListener('click', () => {
    window.__bulkActiveTvdbInput = row.querySelector('input[name="tvdb_id"]');
    openModal('tvdbSearchModal');
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
    const tvdb = tr.querySelector('input[name="tvdb_id"]').value.trim();
    const discs = getJsonAttr(tr, 'data-discs', []);
    return { title, format, barcode, imdb_id: imdb || null, tmdb_id: tmdb || null, tvdb_id: tvdb || null, discs };
  });

  return {
    kind: 'singles',
    storage_id: getStorageId(),
    default_copy_count: Number(document.getElementById('singleCopyCount').value || 1),
    rows,
  };
}

function validateSinglesHaveFeatureDisc() {
  const rows = [...singleTbody.querySelectorAll('tr')];
  const bad = [];

  rows.forEach((tr, idx) => {
    const title = tr.querySelector('input[name="title"]')?.value?.trim() || `${fmt('word.row')} ${idx + 1}`;
    const discs = getJsonAttr(tr, 'data-discs', []);
    const hasFeature = Array.isArray(discs) && discs.some(disc => disc && disc.type_disc === 'feature');
    if (!hasFeature) bad.push(title);
  });

  if (bad.length) {
    alert(fmt('err.missing_feature_disc') + '\n\n- ' + bad.join('\n- '));
    return false;
  }
  return true;
}

function renumberBoxOrders(tbody) {
  [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
    const cell = tr.querySelector('[data-order]');
    if (cell) cell.textContent = i + 1;
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

  const select = boxSetRoot.querySelector('select[name="disc_related"]');
  select.innerHTML =
    `<option value="">${escapeHtml(fmt('word.whole_box'))}</option>` +
    titles.map((title, i) => `<option value="${i}">${escapeHtml(title || fmt('fmt.untitled_n', { n: i + 1 }))}</option>`).join('');

  refreshBoxDiscRelatedLabels(boxSetRoot);
}

function addBoxTitleRow(boxSetRoot, { title = '', imdb = '', tmdb = '', tvdb = '', inner_ean = '', treat_as_single = null } = {}) {
  if (treat_as_single === null) treat_as_single = Boolean(inner_ean && inner_ean.trim());

  const tbody = boxSetRoot.querySelector('tbody[data-role="movies"]');
  const row = el(`
    <tr>
      <td data-order class="muted">-</td>
      <td><input name="title" placeholder="${escapeHtml(fmt('ph.title'))}" value="${escapeHtml(title)}"></td>
      <td>
        <div class="inlineInput">
          <input name="imdb" placeholder="${escapeHtml(fmt('ph.imdb'))}" value="${escapeHtml(imdb)}">
          <button class="btn btnPrimary btnIcon" type="button" data-action="open-search" title="${escapeHtml(fmt('btn.search_tmdb'))}">🔍</button>
        </div>
        <div class="inlineInput">
          <input name="tvdb_id" placeholder="${escapeHtml(fmt('ph.tvdb'))}" value="${escapeHtml(tvdb)}">
          <button class="btn btnPrimary btnIcon" type="button" data-action="open-search-tvdb" title="${escapeHtml(fmt('btn.search_tvdb'))}">🔍</button>
        </div>
        <input type="hidden" name="tmdb_id" value="${escapeHtml(tmdb)}">
      </td>
      <td><input name="inner_ean" placeholder="${escapeHtml(fmt('ph.ean13'))}" value="${escapeHtml(inner_ean)}"></td>
      <td><label class="checkLine"><input type="checkbox" name="treat_as_single" ${treat_as_single ? 'checked' : ''}><span>${escapeHtml(fmt('label.create_single'))}</span></label></td>
      <td><button class="btn btnDanger btnTiny" type="button" aria-label="${escapeHtml(fmt('aria.remove_movie'))}">✕</button></td>
    </tr>
  `);

  const titleInput = row.querySelector('input[name="title"]');
  const imdbInput = row.querySelector('input[name="imdb"]');
  const tmdbInput = row.querySelector('input[name="tmdb_id"]');
  const tvdbInput = row.querySelector('input[name="tvdb_id"]');
  const innerEanInput = row.querySelector('input[name="inner_ean"]');
  const treatCb = row.querySelector('input[name="treat_as_single"]');

  row.querySelector('button[data-action="open-search"]').addEventListener('click', () => {
    window.__bulkActiveImdbInput = imdbInput;
    window.__bulkActiveTmdbInput = tmdbInput;
    openModal('searchModal');
  });
  row.querySelector('button[data-action="open-search-tvdb"]').addEventListener('click', () => {
    window.__bulkActiveTvdbInput = tvdbInput;
    openModal('tvdbSearchModal');
  });

  row.querySelector('button[aria-label]').addEventListener('click', () => {
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
    updateBoxSetSummary(boxSetRoot);
    scheduleSave();
  });

  titleInput.addEventListener('input', () => {
    refreshBoxRelatedDropdown(boxSetRoot);
    scheduleSave();
  });
  imdbInput.addEventListener('input', scheduleSave);
  innerEanInput.addEventListener('input', () => {
    if (innerEanInput.value.trim()) treatCb.checked = true;
    scheduleSave();
  });
  treatCb.addEventListener('change', scheduleSave);

  tbody.appendChild(row);
  renumberBoxOrders(tbody);
  refreshBoxRelatedDropdown(boxSetRoot);
  updateBoxSetSummary(boxSetRoot);
  scheduleSave();
}

function addBoxDiscRow(boxSetRoot, {
  typeDisc = 'feature',
  format = 'BD',
  label = '',
  relatedIndex = '',
  relatedTitle = '',
  bonus_items = [],
  storage_slot_no = '',
  add_to_storage = false,
} = {}) {
  const safeLabel = label === null || label === undefined || label === 'null' ? '' : String(label);
  const safeStorageSlotNo = storage_slot_no === null || storage_slot_no === undefined ? '' : String(storage_slot_no);
  const tbody = boxSetRoot.querySelector('tbody[data-role="discs"]');
  const order = tbody.querySelectorAll('tr').length + 1;

  const row = el(`
    <tr data-bonus-items="[]" data-related-index="${escapeHtml(relatedIndex)}" data-storage-slot-no="${escapeHtml(safeStorageSlotNo)}" data-add-to-storage="${add_to_storage ? '1' : '0'}">
      <td class="muted">${order}</td>
      <td><span class="tag ${typeDisc === 'bonus' ? 'warn' : ''}" data-role="disc-type">${escapeHtml(typeDisc)}</span></td>
      <td data-role="disc-format">${escapeHtml(format)}</td>
      <td data-role="disc-label">${escapeHtml(safeLabel)}</td>
      <td data-role="disc-storage-slot">${escapeHtml(safeStorageSlotNo)}</td>
      <td data-role="disc-add-to-storage">${add_to_storage ? escapeHtml(fmt('word.yes')) : escapeHtml(fmt('word.no'))}</td>
      <td data-role="disc-related">${escapeHtml(relatedTitle)}</td>
      <td><button class="btn btnTiny" type="button" data-action="edit-bonus">${escapeHtml(fmt('btn.edit'))}</button></td>
      <td><button class="btn btnDanger btnTiny" type="button" aria-label="${escapeHtml(fmt('aria.remove_disc'))}">✕</button></td>
    </tr>
  `);

  setJsonAttr(row, 'data-bonus-items', bonus_items || []);

  row.querySelector('button[aria-label]').addEventListener('click', () => {
    row.remove();
    renumberBoxDiscOrders(boxSetRoot);
    updateBoxSetSummary(boxSetRoot);
    scheduleSave();
  });

  row.querySelector('button[data-action="edit-bonus"]').addEventListener('click', () => {
    const currentOrder = row.children[0].textContent.trim();
    const discType = row.querySelector('[data-role="disc-type"]').textContent.trim();
    const discFormat = row.querySelector('[data-role="disc-format"]').textContent.trim();
    const discLabel = row.querySelector('[data-role="disc-label"]').textContent.trim();
    const rel = row.querySelector('[data-role="disc-related"]').textContent.trim();
    const subtitleBase = fmt('subtitle.box_disc', {
      n: currentOrder,
      discType,
      discFormat,
      rel: rel || fmt('word.whole_box_plain'),
    });
    const subtitle = discLabel ? `${subtitleBase} · ${fmt('label.label')}="${discLabel}"` : subtitleBase;
    openBonusEditor({ targetRow: row, discType, subtitle });
  });

  tbody.appendChild(row);

  const bonusButton = row.querySelector('button[data-action="edit-bonus"]');
  const count = (bonus_items || []).length;
  bonusButton.textContent = count ? fmt('btn.edit_count', { n: count }) : fmt('btn.edit');

  updateBoxSetSummary(boxSetRoot);
  scheduleSave();
}

function updateBoxSetSummary(root) {
  const barcode = root.querySelector('input[name="box_barcode"]').value.trim();
  const movieCount = root.querySelectorAll('tbody[data-role="movies"] tr').length;
  const discCount = root.querySelectorAll('tbody[data-role="discs"] tr').length;
  const badge = root.querySelector('[data-role="summary"]');
  const parts = [];
  parts.push(barcode ? `EAN ${barcode}` : fmt('word.no_ean'));
  parts.push(fmt('fmt.movies_count', { n: movieCount }));
  parts.push(fmt('fmt.discs_count', { n: discCount }));
  badge.textContent = parts.join(' · ');
}

function createBoxSetCard() {
  boxSetSeq += 1;
  const boxSetId = `boxset_${boxSetSeq}`;

  const card = el(`
    <div class="card boxSetCard" data-boxset-id="${boxSetId}">
      <div class="boxSetHeader">
        <div>
          <h3>${escapeHtml(fmt('fmt.box_set_n', { n: boxSetSeq }))}</h3>
          <div class="summaryLine"><span class="tag" data-role="summary">${escapeHtml(fmt('word.not_set'))}</span></div>
        </div>
        <button class="btn btnDanger" type="button" data-action="remove-boxset">${escapeHtml(fmt('btn.remove_boxset'))}</button>
      </div>

      <div class="formGrid" style="margin-bottom:14px;">
        <div class="field span-4">
          <label>${escapeHtml(fmt('label.format'))}</label>
          <select name="box_format">
            <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
            <option value="BD" selected>${escapeHtml(fmt('format.bd_short'))}</option>
            <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
          </select>
        </div>
        <div class="field span-4">
          <label>${escapeHtml(fmt('label.box_barcode'))}</label>
          <input name="box_barcode" placeholder="${escapeHtml(fmt('ph.box_barcode'))}">
        </div>
        <div class="field span-4">
          <label>${escapeHtml(fmt('label.copy_count'))}</label>
          <input type="number" name="box_copy_count" value="1" min="1">
        </div>
      </div>

      <div class="sectionCard" style="margin-bottom:14px;">
        <div class="toolbar">
          <div>
            <h4>${escapeHtml(fmt('section.movies_in_box'))}</h4>
          </div>
          <div class="btnRow">
            <button class="btn btnPrimary btnTiny" type="button" data-action="add-movie">${escapeHtml(fmt('btn.add_movie'))}</button>
            <button class="btn btnTiny" type="button" data-action="paste-movies">${escapeHtml(fmt('btn.paste_list'))}</button>
          </div>
        </div>
        <div class="dataTableWrap">
          <table class="dataTable">
            <thead>
              <tr>
                <th>${escapeHtml(fmt('col.order'))}</th>
                <th>${escapeHtml(fmt('col.title'))}</th>
                <th>${escapeHtml(fmt('col.imdb'))}</th>
                <th>${escapeHtml(fmt('col.inner_ean'))}</th>
                <th>${escapeHtml(fmt('col.treat_as_single'))}</th>
                <th></th>
              </tr>
            </thead>
            <tbody data-role="movies"></tbody>
          </table>
        </div>
      </div>

      <div class="sectionCard">
        <h4>${escapeHtml(fmt('section.discs_in_box'))}</h4>
        <div class="formGrid" style="margin-bottom:14px;">
          <div class="field span-3">
            <label>${escapeHtml(fmt('label.disc_type'))}</label>
            <select name="disc_type">
              <option value="feature" selected>${escapeHtml(fmt('disc.feature'))}</option>
              <option value="bonus">${escapeHtml(fmt('disc.bonus'))}</option>
            </select>
          </div>
          <div class="field span-3">
            <label>${escapeHtml(fmt('label.disc_format'))}</label>
            <select name="disc_format">
              <option value="DVD">${escapeHtml(fmt('format.dvd_short'))}</option>
              <option value="BD" selected>${escapeHtml(fmt('format.bd_short'))}</option>
              <option value="UHD">${escapeHtml(fmt('format.uhd_short'))}</option>
            </select>
          </div>
          <div class="field span-3">
            <label>${escapeHtml(fmt('label.label'))}</label>
            <input name="disc_label" placeholder="${escapeHtml(fmt('ph.optional_label'))}">
          </div>
          <div class="field span-3">
            <label>${escapeHtml(fmt('label.storage_slot_no'))}</label>
            <input name="disc_storage_slot_no" type="number" min="1">
          </div>
          <div class="field span-6">
            <label>${escapeHtml(fmt('label.related_movie'))}</label>
            <select name="disc_related">
              <option value="">${escapeHtml(fmt('word.whole_box'))}</option>
            </select>
            <small>${escapeHtml(fmt('hint.related_movie'))}</small>
          </div>
          <div class="field span-3">
            <label>${escapeHtml(fmt('label.add_to_storage'))}</label>
            <label class="checkLine"><input type="checkbox" name="disc_add_to_storage"><span>${escapeHtml(fmt('word.yes'))}</span></label>
          </div>
          <div class="field span-3" style="align-self:end;">
            <button class="btn btnPrimary" type="button" data-action="add-disc">${escapeHtml(fmt('btn.add_disc'))}</button>
          </div>
        </div>
        <div class="dataTableWrap">
          <table class="dataTable">
            <thead>
              <tr>
                <th>${escapeHtml(fmt('col.order'))}</th>
                <th>${escapeHtml(fmt('col.type'))}</th>
                <th>${escapeHtml(fmt('col.format'))}</th>
                <th>${escapeHtml(fmt('col.label'))}</th>
                <th>${escapeHtml(fmt('col.storage_slot_no'))}</th>
                <th>${escapeHtml(fmt('col.add_to_storage'))}</th>
                <th>${escapeHtml(fmt('col.related_movie'))}</th>
                <th>${escapeHtml(fmt('col.bonus_items'))}</th>
                <th></th>
              </tr>
            </thead>
            <tbody data-role="discs"></tbody>
          </table>
        </div>
      </div>
    </div>
  `);

  const root = card;

  root.querySelector('[data-action="add-movie"]').addEventListener('click', () => addBoxTitleRow(root));
  root.querySelector('[data-action="paste-movies"]').addEventListener('click', () => {
    pasteTargetBoxSetId = boxSetId;
    openModal('pasteModal');
  });
  root.querySelector('[data-action="add-disc"]').addEventListener('click', () => {
    const typeDisc = root.querySelector('select[name="disc_type"]').value;
    const format = root.querySelector('select[name="disc_format"]').value;
    let label = root.querySelector('input[name="disc_label"]').value.trim();
    if (label === 'null' || label === 'NULL') label = '';
    const relatedIdx = root.querySelector('select[name="disc_related"]').value;
    const storageSlotRaw = root.querySelector('input[name="disc_storage_slot_no"]').value.trim();
    const storage_slot_no = storageSlotRaw === '' ? null : Number(storageSlotRaw);
    const add_to_storage = root.querySelector('input[name="disc_add_to_storage"]').checked;

    let relatedTitle = '';
    if (relatedIdx !== '') {
      const rows = root.querySelectorAll('tbody[data-role="movies"] tr');
      const tr = rows[Number(relatedIdx)];
      relatedTitle = tr ? tr.querySelector('input[name="title"]').value.trim() : '';
    }

    addBoxDiscRow(root, {
      typeDisc,
      format,
      label,
      relatedIndex: relatedIdx,
      relatedTitle,
      storage_slot_no,
      add_to_storage,
    });

    root.querySelector('input[name="disc_label"]').value = '';
    root.querySelector('input[name="disc_storage_slot_no"]').value = '';
    root.querySelector('input[name="disc_add_to_storage"]').checked = false;
    scheduleSave();
  });
  root.querySelector('[data-action="remove-boxset"]').addEventListener('click', () => {
    root.remove();
    scheduleSave();
  });
  root.querySelector('input[name="box_barcode"]').addEventListener('input', () => {
    updateBoxSetSummary(root);
    scheduleSave();
  });
  root.querySelector('select[name="box_format"]').addEventListener('change', scheduleSave);
  root.querySelector('input[name="box_copy_count"]').addEventListener('input', scheduleSave);
  root.querySelector('input[name="disc_label"]').addEventListener('input', scheduleSave);
  root.querySelector('input[name="disc_storage_slot_no"]').addEventListener('input', scheduleSave);
  root.querySelector('input[name="disc_add_to_storage"]').addEventListener('change', scheduleSave);

  addBoxTitleRow(root);
  refreshBoxRelatedDropdown(root);
  updateBoxSetSummary(root);

  return card;
}

function readAllBoxSets() {
  const cards = [...boxSetsContainer.querySelectorAll('[data-boxset-id]')];
  return cards.map((root, idx) => {
    const format = root.querySelector('select[name="box_format"]').value;
    const boxBarcode = root.querySelector('input[name="box_barcode"]').value.trim() || null;
    const copyCount = Number(root.querySelector('input[name="box_copy_count"]').value || 1);

    const movies = [...root.querySelectorAll('tbody[data-role="movies"] tr')].map((tr, i) => {
      const title = tr.querySelector('input[name="title"]').value.trim();
      const imdb = tr.querySelector('input[name="imdb"]').value.trim();
      const tmdb = tr.querySelector('input[name="tmdb_id"]').value.trim();
      const tvdb = tr.querySelector('input[name="tvdb_id"]').value.trim();
      const innerEan = tr.querySelector('input[name="inner_ean"]').value.trim();
      const treatAsSingle = tr.querySelector('input[name="treat_as_single"]').checked;
      return {
        order: i + 1,
        title,
        imdb_id: imdb || null,
        tmdb_id: tmdb || null,
        tvdb_id: tvdb || null,
        inner_case_ean: innerEan || null,
        treat_as_single: treatAsSingle,
      };
    });

    const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => {
      const type_disc = tr.querySelector('[data-role="disc-type"]')?.textContent?.trim() || null;
      const formatValue = tr.querySelector('[data-role="disc-format"]')?.textContent?.trim() || null;
      const labelRaw = tr.querySelector('[data-role="disc-label"]')?.textContent ?? '';
      const label = labelRaw.trim() || null;
      const related_title = tr.querySelector('[data-role="disc-related"]')?.textContent?.trim() || null;
      const relatedIndexRaw = tr.getAttribute('data-related-index');
      const related_index = relatedIndexRaw === '' || relatedIndexRaw == null ? null : Number(relatedIndexRaw);
      const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);
      const storageSlotRaw = tr.getAttribute('data-storage-slot-no');
      const storage_slot_no = storageSlotRaw === '' || storageSlotRaw == null ? null : Number(storageSlotRaw);
      const add_to_storage = tr.getAttribute('data-add-to-storage') === '1';

      return {
        order: Number(tr.children[0].textContent.trim()),
        type_disc,
        format: formatValue,
        label,
        storage_slot_no,
        add_to_storage,
        related_index,
        related_title: related_title || null,
        bonus_items,
      };
    });

    return {
      box_set_index: idx + 1,
      storage_id: getStorageId(),
      format,
      box_set_barcode: boxBarcode,
      copy_count: copyCount,
      movies,
      discs,
    };
  });
}

function serializeState() {
  const singles = {
    storage_id: getStorageId(),
    default_format: document.getElementById('singleFormat').value,
    default_copy_count: Number(document.getElementById('singleCopyCount').value || 1),
    quick_title: document.getElementById('singleQuickImport').value || '',
    rows: [...singleTbody.querySelectorAll('tr')].map(tr => ({
      title: tr.querySelector('input[name="title"]')?.value ?? '',
      format: tr.querySelector('select[name="single_row_format"]')?.value ?? 'BD',
      barcode: tr.querySelector('input[name="barcode"]')?.value ?? '',
      imdb: tr.querySelector('input[name="imdb"]')?.value ?? '',
      tmdb: tr.querySelector('input[name="tmdb_id"]')?.value ?? '',
      tvdb: tr.querySelector('input[name="tvdb_id"]')?.value ?? '',
      discs: getJsonAttr(tr, 'data-discs', []),
    })),
  };

  const boxSets = [...boxSetsContainer.querySelectorAll('[data-boxset-id]')].map(root => {
    const movies = [...root.querySelectorAll('tbody[data-role="movies"] tr')].map(tr => ({
      title: tr.querySelector('input[name="title"]')?.value ?? '',
      imdb: tr.querySelector('input[name="imdb"]')?.value ?? '',
      tmdb: tr.querySelector('input[name="tmdb_id"]')?.value ?? '',
      tvdb: tr.querySelector('input[name="tvdb_id"]')?.value ?? '',
      inner_ean: tr.querySelector('input[name="inner_ean"]')?.value ?? '',
      treat_as_single: !!tr.querySelector('input[name="treat_as_single"]')?.checked,
    }));

    const discs = [...root.querySelectorAll('tbody[data-role="discs"] tr')].map(tr => ({
      typeDisc: tr.querySelector('[data-role="disc-type"]')?.textContent?.trim() ?? 'feature',
      format: tr.querySelector('[data-role="disc-format"]')?.textContent?.trim() ?? 'BD',
      label: tr.querySelector('[data-role="disc-label"]')?.textContent?.trim() ?? '',
      relatedIndex: tr.getAttribute('data-related-index') ?? '',
      relatedTitle: tr.querySelector('[data-role="disc-related"]')?.textContent?.trim() ?? '',
      storage_slot_no: tr.getAttribute('data-storage-slot-no') ?? '',
      add_to_storage: tr.getAttribute('data-add-to-storage') === '1',
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

  return {
    storage_id: getStorageId(),
    singles,
    boxSets,
  };
}

function restoreState(state) {
  if (!state) return;

  const restoredStorageId = state.storage_id ?? state.singles?.storage_id ?? DEFAULT_STORAGE_ID;
  document.getElementById('storageId').value = String(restoredStorageId);

  if (state.singles?.default_format) document.getElementById('singleFormat').value = state.singles.default_format;
  if (state.singles?.default_copy_count) document.getElementById('singleCopyCount').value = String(state.singles.default_copy_count);
  if (typeof state.singles?.quick_title === 'string') document.getElementById('singleQuickImport').value = state.singles.quick_title;

  singleTbody.innerHTML = '';
  (state.singles?.rows || []).forEach(row => {
    addSingleRow({ title: row.title, format: row.format, barcode: row.barcode, imdb: row.imdb, tmdb: row.tmdb, tvdb: row.tvdb });
    const trEl = singleTbody.lastElementChild;
    if (trEl) {
      setJsonAttr(trEl, 'data-discs', Array.isArray(row.discs) ? row.discs : []);
      updateSingleDiscButtonLabel(trEl);
    }
  });
  if ((state.singles?.rows || []).length === 0) addSingleRow();

  boxSetsContainer.innerHTML = '';
  boxSetSeq = 0;
  (state.boxSets || []).forEach(bs => {
    const card = createBoxSetCard();
    boxSetsContainer.appendChild(card);
    const root = card;

    root.querySelector('select[name="box_format"]').value = bs.box_format || 'BD';
    root.querySelector('input[name="box_barcode"]').value = bs.box_barcode || '';
    root.querySelector('input[name="box_copy_count"]').value = String(bs.box_copy_count || 1);

    const moviesTbody = root.querySelector('tbody[data-role="movies"]');
    moviesTbody.innerHTML = '';
    (bs.movies || []).forEach(movie => addBoxTitleRow(root, movie));
    if ((bs.movies || []).length === 0) addBoxTitleRow(root);

    const discsTbody = root.querySelector('tbody[data-role="discs"]');
    discsTbody.innerHTML = '';
    (bs.discs || []).forEach(disc => addBoxDiscRow(root, {
      typeDisc: disc.typeDisc,
      format: disc.format,
      label: disc.label ?? '',
      relatedIndex: disc.relatedIndex ?? '',
      relatedTitle: disc.relatedTitle ?? '',
      storage_slot_no: disc.storage_slot_no ?? '',
      add_to_storage: !!disc.add_to_storage,
      bonus_items: disc.bonus_items ?? [],
    }));

    refreshBoxRelatedDropdown(root);
    updateBoxSetSummary(root);
  });

  if ((state.boxSets || []).length === 0) boxSetsContainer.appendChild(createBoxSetCard());
}

function activateTab(tabName) {
  document.querySelectorAll('.tabBtn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabName);
  });
  document.querySelectorAll('.tabPanel').forEach(panel => {
    panel.classList.toggle('active', panel.dataset.tabPanel === tabName);
  });
}

function searchMovies(query) {
  loadingSpinner.classList.remove('hidden');
  searchStatus.textContent = fmt('status.search_loading');
  dropdownResults.innerHTML = '';
  selectedItemContainer.innerHTML = '';

  fetch(`${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}`)
    .then(async response => {
      if (!response.ok) {
        const text = await response.text();
        let message = text;
        try {
          const data = JSON.parse(text);
          message = data.error || text;
        } catch {}
        throw new Error(message || fmt('status.error'));
      }
      return response.json();
    })
    .then(data => {
      loadingSpinner.classList.add('hidden');
      if (!data.results || data.results.length === 0) {
        searchStatus.textContent = fmt('status.search_no_results');
        return;
      }

      searchStatus.textContent = data.search_year
        ? fmt('status.search_results_year', { n: data.results.length, year: data.search_year })
        : fmt('status.search_results', { n: data.results.length });

      displayDropdown(data.results);
    })
    .catch(error => {
      loadingSpinner.classList.add('hidden');
      searchStatus.textContent = `${fmt('status.error')} ${error.message}`;
    });
}

function displayDropdown(results) {
  dropdownResults.innerHTML = '';
  results.forEach(item => dropdownResults.appendChild(createDropdownItem(item)));
}

function createDropdownItem(item) {
  const div = document.createElement('div');
  div.className = 'searchItem';

  const posterPath = item.poster_path ? `${IMAGE_BASE_URL}${item.poster_path}` : null;
  const title = item.media_type === 'tv' ? (item.name || item.title) : item.title;
  const releaseDate = item.media_type === 'tv' ? item.first_air_date : item.release_date;
  const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : '—';
  const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
  const mediaType = item.media_type === 'tv' ? fmt('text.search_tv') : fmt('text.search_movie');

  div.innerHTML = `
    ${posterPath
      ? `<img src="${escapeAttr(posterPath)}" class="searchPoster" alt="${escapeHtml(title)}">`
      : `<div class="searchNoPoster">${escapeHtml(fmt('text.search_no_poster'))}</div>`}
    <div>
      <div><strong>${escapeHtml(title || '')}</strong> <span class="tag">${escapeHtml(mediaType)}</span></div>
      <div class="searchMeta">📅 ${escapeHtml(releaseYear)} · ⭐ ${escapeHtml(rating)}</div>
    </div>
  `;

  div.addEventListener('click', () => showDetails(item));
  return div;
}

function searchTvdbMovies(query) {
  tvdbLoadingSpinner.classList.remove('hidden');
  tvdbSearchStatus.textContent = fmt('status.search_loading');
  tvdbDropdownResults.innerHTML = '';

  const tvdbType = document.querySelector('input[name="tvdbSearchType"]:checked')?.value || 'movie';

  fetch(`${API_ENDPOINT}?action=search_tvdb&query=${encodeURIComponent(query)}&type=${encodeURIComponent(tvdbType)}`)
    .then(async response => {
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error(text || fmt('status.error'));
      }
      if (!response.ok) {
        throw new Error(data.error || data.message || fmt('status.error'));
      }
      return data;
    })
    .then(data => {
      tvdbLoadingSpinner.classList.add('hidden');
      const results = data.data || [];
      if (results.length === 0) {
        tvdbSearchStatus.textContent = fmt('status.search_no_results');
        return;
      }
      tvdbSearchStatus.textContent = fmt('status.search_results', { n: results.length });
      tvdbDropdownResults.innerHTML = '';
      results.forEach(item => tvdbDropdownResults.appendChild(createTvdbDropdownItem(item)));
    })
    .catch(error => {
      tvdbLoadingSpinner.classList.add('hidden');
      tvdbSearchStatus.textContent = `${fmt('status.error')} ${error.message}`;
    });
}

function createTvdbDropdownItem(item) {
  const div = document.createElement('div');
  div.className = 'searchItem';

  const tvdbId = item.tvdb_id ?? item.id;
  const title = item.name || item.title || '';
  const posterUrl = item.image_url || item.poster || item.thumbnail || null;
  const year = item.year || '—';

  div.innerHTML = `
    ${posterUrl
      ? `<img src="${escapeAttr(posterUrl)}" class="searchPoster" alt="${escapeHtml(title)}">`
      : `<div class="searchNoPoster">${escapeHtml(fmt('text.search_no_poster'))}</div>`}
    <div>
      <div><strong>${escapeHtml(title)}</strong></div>
      <div class="searchMeta">📅 ${escapeHtml(year)} · TVDB ID: ${escapeHtml(String(tvdbId))}</div>
    </div>
  `;

  div.addEventListener('click', () => {
    if (window.__bulkActiveTvdbInput) {
      window.__bulkActiveTvdbInput.value = String(tvdbId);
      window.__bulkActiveTvdbInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    closeModal('tvdbSearchModal');
  });

  return div;
}

function showDetails(item) {
  searchStatus.textContent = fmt('status.search_details');

  fetch(`${API_ENDPOINT}?action=details&id=${encodeURIComponent(item.id)}&type=${encodeURIComponent(item.media_type)}`)
    .then(async response => {
      if (!response.ok) {
        const text = await response.text();
        let message = text;
        try {
          const data = JSON.parse(text);
          message = data.error || text;
        } catch {}
        throw new Error(message || fmt('status.error'));
      }
      return response.json();
    })
    .then(details => {
      searchStatus.textContent = '';
      displayInfoPage(details, item.media_type);
    })
    .catch(error => {
      searchStatus.textContent = `${fmt('status.error')} ${error.message}`;
    });
}

function displayInfoPage(details, type) {
  const posterPath = details.poster_path ? `${IMAGE_BASE_URL}${details.poster_path}` : null;
  const title = type === 'tv' ? (details.name || details.title) : details.title;
  const releaseDate = type === 'tv' ? details.first_air_date : details.release_date;
  const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : '—';
  const rating = details.vote_average ? details.vote_average.toFixed(1) : 'N/A';
  const imdbId = details.external_ids?.imdb_id || '';
  const tmdbId = details.id?.toString() || '';
  const mediaType = type === 'tv' ? fmt('text.search_tv') : fmt('text.search_movie');
  const overview = details.overview || fmt('text.search_overview_missing');

  selectedItemContainer.innerHTML = `
    <div class="selectedItem">
      <div class="selectedLayout">
        <div>
          ${posterPath
            ? `<img src="${escapeAttr(posterPath)}" alt="${escapeHtml(title || '')}">`
            : `<div class="emptyPoster">${escapeHtml(fmt('text.search_no_poster'))}</div>`}
        </div>
        <div class="stack">
          <div>
            <h3 style="margin:0 0 8px;">${escapeHtml(title || '')}</h3>
            <div class="btnRow">
              <span class="tag">${escapeHtml(mediaType)}</span>
              <span class="tag">⭐ ${escapeHtml(rating)}</span>
              <span class="tag">📅 ${escapeHtml(releaseYear)}</span>
            </div>
          </div>
          <div class="stack" style="gap:8px;">
            <div><strong>TMDB ID:</strong> <span class="mono">${escapeHtml(tmdbId)}</span></div>
            <div><strong>IMDb ID:</strong> ${imdbId
              ? `<a href="https://www.imdb.com/title/${encodeURIComponent(imdbId)}/" target="_blank" rel="noopener">${escapeHtml(imdbId)}</a>`
              : escapeHtml(fmt('text.search_imdb_missing'))}</div>
          </div>
          <div class="noteBox">${escapeHtml(overview)}</div>
          <div class="btnRow">
            <button class="btn btnSuccess" type="button" id="btnUseSearchIds">${escapeHtml(fmt('btn.use_ids'))}</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.getElementById('btnUseSearchIds').addEventListener('click', () => applyIdsToBulkRow(tmdbId, imdbId));
}

function applyIdsToBulkRow(tmdbId, imdbId) {
  const imdbTarget = window.__bulkActiveImdbInput;
  const tmdbTarget = window.__bulkActiveTmdbInput;

  if (tmdbTarget && tmdbId) {
    tmdbTarget.value = tmdbId;
    tmdbTarget.dispatchEvent(new Event('input', { bubbles: true }));
    tmdbTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  if (imdbTarget && imdbId) {
    imdbTarget.value = imdbId;
    imdbTarget.dispatchEvent(new Event('input', { bubbles: true }));
    imdbTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  searchStatus.textContent = fmt('status.ids_applied');
  selectedItemContainer.innerHTML = '';
  closeModal('searchModal');
  scheduleSave();
}

document.querySelectorAll('#mainTabs .tabBtn').forEach(btn => {
  btn.addEventListener('click', () => activateTab(btn.dataset.tab));
});

document.querySelectorAll('.modal').forEach(modal => {
  modal.addEventListener('click', event => {
    if (event.target !== modal) return;
    if (modal.id === 'discModal' || modal.id === 'bonusModal') return;
    closeModal(modal);
  });
  modal.querySelectorAll('[data-modal-close]').forEach(button => {
    button.addEventListener('click', () => closeModal(modal));
  });
});

document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  const activeModals = [...document.querySelectorAll('.modal.active')];
  const topModal = activeModals[activeModals.length - 1];
  if (!topModal) return;
  if (topModal.id === 'discModal' || topModal.id === 'bonusModal') return;
  closeModal(topModal);
});

document.getElementById('btnAddBonusItemRow').addEventListener('click', () => {
  const seqs = [...bonusItemsTbody.querySelectorAll('input[name="seq_no"]')]
    .map(input => Number(input.value))
    .filter(number => Number.isFinite(number) && number > 0);
  const next = seqs.length ? Math.max(...seqs) + 1 : 1;
  addBonusItemEditorRow({ seq_no: next });
  scheduleSave();
});

document.getElementById('btnSaveBonusItems').addEventListener('click', () => {
  if (!bonusTarget?.node) return;

  const items = [...bonusItemsTbody.querySelectorAll('tr')].map(tr => {
    const seq = Number(tr.querySelector('input[name="seq_no"]').value || 0);
    const title = tr.querySelector('input[name="title"]').value.trim();
    const item_type = tr.querySelector('select[name="item_type"]').value;
    const runtimeRaw = tr.querySelector('input[name="runtime_seconds"]').value;
    const runtime_seconds = runtimeRaw.trim() === '' ? null : Number(runtimeRaw);
    const notes = tr.querySelector('input[name="notes"]').value.trim();
    return { seq_no: seq || null, title, item_type, runtime_seconds, notes: notes || null };
  });

  const cleaned = items.filter(item => (item.title && item.title.trim()) || item.seq_no);
  cleaned.sort((a, b) => (a.seq_no ?? 0) - (b.seq_no ?? 0));
  setJsonAttr(bonusTarget.node, 'data-bonus-items', cleaned);

  const button = bonusTarget.node.querySelector('button[data-action="edit-bonus"]');
  if (button) button.textContent = cleaned.length ? fmt('btn.edit_count', { n: cleaned.length }) : fmt('btn.edit');

  bonusTarget = null;
  clearBonusTable();
  closeModal('bonusModal');
  scheduleSave();
});

document.getElementById('btnAddDiscEditorRow').addEventListener('click', () => {
  const rowFormat = discModalTargetSingleRow
    ? discModalTargetSingleRow.querySelector('select[name="single_row_format"]').value
    : document.getElementById('singleFormat').value;
  addDiscEditorRow({ type_disc: 'feature', format: rowFormat });
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

    const storageSlotRaw = tr.querySelector('input[name="storage_slot_no"]').value.trim();
    const storage_slot_no = storageSlotRaw === '' ? null : Number(storageSlotRaw);
    const add_to_storage = tr.querySelector('input[name="add_to_storage"]').checked;
    const bonus_items = getJsonAttr(tr, 'data-bonus-items', []);

    return { type_disc, format, label: label || null, bonus_items, storage_slot_no, add_to_storage };
  });

  setJsonAttr(discModalTargetSingleRow, 'data-discs', discs);
  updateSingleDiscButtonLabel(discModalTargetSingleRow);

  discModalTargetSingleRow = null;
  clearDiscEditorTable();
  closeModal('discModal');
  scheduleSave();
});

document.getElementById('btnSingleAddRow').addEventListener('click', () => addSingleRow());
document.getElementById('btnSingleAddFromText').addEventListener('click', () => {
  const value = document.getElementById('singleQuickImport').value.trim();
  if (value) addSingleRow({ title: value });
  document.getElementById('singleQuickImport').value = '';
  scheduleSave();
});
document.getElementById('singleQuickImport').addEventListener('keydown', event => {
  if (event.key !== 'Enter') return;
  event.preventDefault();
  document.getElementById('btnSingleAddFromText').click();
});
document.getElementById('btnSinglePreview').addEventListener('click', () => {
  if (!validateSinglesHaveFeatureDisc()) return;
  showPreview(readSinglesForm());
});
document.getElementById('btnSubmitSingles').addEventListener('click', () => {
  if (!validateSinglesHaveFeatureDisc()) return;
  submitPayload(readSinglesForm(), document.getElementById('btnSubmitSingles'));
});

document.getElementById('btnAddBoxSet').addEventListener('click', () => {
  boxSetsContainer.appendChild(createBoxSetCard());
  scheduleSave();
});
document.getElementById('btnPreviewAllBoxSets').addEventListener('click', () => {
  showPreview({
    kind: 'box_sets_bulk',
    storage_id: getStorageId(),
    box_sets: readAllBoxSets(),
  });
});
document.getElementById('btnSubmitBoxSets').addEventListener('click', () => {
  submitPayload({
    kind: 'box_sets_bulk',
    storage_id: getStorageId(),
    box_sets: readAllBoxSets(),
  }, document.getElementById('btnSubmitBoxSets'));
});
document.getElementById('btnBoxPasteApply').addEventListener('click', () => {
  const lines = document.getElementById('boxPasteArea').value
    .split('\n')
    .map(line => line.trim())
    .filter(Boolean);

  document.getElementById('boxPasteArea').value = '';

  if (!pasteTargetBoxSetId) return;
  const target = boxSetsContainer.querySelector(`[data-boxset-id="${pasteTargetBoxSetId}"]`);
  if (!target) return;

  lines.forEach(title => addBoxTitleRow(target, { title }));
  pasteTargetBoxSetId = null;
  closeModal('pasteModal');
  scheduleSave();
});

document.getElementById('btnResetAll').addEventListener('click', () => {
  if (!confirm(fmt('confirm.reset'))) return;
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch {}
  location.reload();
});

document.addEventListener('input', scheduleSave, true);
document.addEventListener('change', scheduleSave, true);
searchInput.addEventListener('input', event => {
  const query = event.target.value.trim();
  clearTimeout(searchTimeout);

  if (query.length === 0) {
    dropdownResults.innerHTML = '';
    selectedItemContainer.innerHTML = '';
    searchStatus.textContent = fmt('status.search_short');
    return;
  }

  if (query.length < 2) {
    dropdownResults.innerHTML = '';
    selectedItemContainer.innerHTML = '';
    searchStatus.textContent = fmt('status.search_short');
    return;
  }

  searchTimeout = setTimeout(() => searchMovies(query), 350);
});

tvdbSearchInput.addEventListener('input', event => {
  const query = event.target.value.trim();
  clearTimeout(tvdbSearchTimeout);

  if (query.length < 2) {
    tvdbDropdownResults.innerHTML = '';
    tvdbSearchStatus.textContent = fmt('status.search_short');
    return;
  }

  tvdbSearchTimeout = setTimeout(() => searchTvdbMovies(query), 350);
});

document.querySelectorAll('input[name="tvdbSearchType"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const query = tvdbSearchInput.value.trim();
    if (query.length >= 2) searchTvdbMovies(query);
  });
});

autoInit();

function autoInit() {
  addSingleRow();
  boxSetsContainer.appendChild(createBoxSetCard());
  setStatus('idle', fmt('status.idle'), {});

  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) restoreState(JSON.parse(raw));
  } catch {}
}

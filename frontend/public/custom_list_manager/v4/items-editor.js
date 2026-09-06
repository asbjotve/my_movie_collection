// items-editor.js
//
// Powers the "Rediger liste" / "Edit list" tab in custom_list_manager v4:
//   - loads and renders all items in the selected list as an inline-
//     editable table (title/original_title/year/imdb/tmdb/tvdb/season +
//     cover replace + delete, per row)
//   - handles the "+ Nytt element" panel at the bottom, which replaces
//     v3's separate "legg til element" tab/form.
//
// All calls go through this same page (index.php?ajax=...), which proxies
// to the backend using the server-side JWT/API key (see the AJAX gate in
// index.php) - the browser never talks to the backend directly.
//
// Text comes from window.CLM_EDIT_I18N (see index.php, clm.edit_tab.*).

(function () {
  const I18N = window.CLM_EDIT_I18N || {};

  function tr(key, ...args) {
    let value = I18N[key];
    if (typeof value !== 'string') return key;
    args.forEach((arg) => {
      value = value.replace(/%s|%d/, String(arg));
    });
    return value;
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  function ajaxUrl(action) {
    return `${window.location.pathname}?ajax=${encodeURIComponent(action)}`;
  }

  const listSelect = document.getElementById('editListSelect');
  const statusEl = document.getElementById('editItemsStatus');
  const tableContainer = document.getElementById('editItemsTable');
  const addStatusEl = document.getElementById('addItemStatus');
  const btnAddItem = document.getElementById('btnAddItem');

  let initialised = false;
  let currentItems = [];

  function setStatus(el, text, type) {
    if (!el) return;
    el.textContent = text || '';
    el.className = text ? `notice ${type || 'info'}` : '';
  }

  async function loadItems(listId) {
    if (!listId) {
      currentItems = [];
      tableContainer.innerHTML = `<div class="empty-hint">${escapeHtml(tr('select_list_hint'))}</div>`;
      return;
    }

    setStatus(statusEl, tr('loading'), 'info');
    tableContainer.innerHTML = '';

    try {
      const res = await fetch(`${ajaxUrl('list_items')}&list_id=${encodeURIComponent(listId)}`);
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error || `HTTP ${res.status}`);
      }

      currentItems = Array.isArray(data) ? data : [];
      setStatus(statusEl, '', 'info');
      renderTable(currentItems, listId);
    } catch (err) {
      setStatus(statusEl, tr('fetch_error_prefix', err.message || err), 'error');
    }
  }

  function renderTable(items, listId) {
    if (items.length === 0) {
      tableContainer.innerHTML = `<div class="empty-hint">${escapeHtml(tr('empty'))}</div>`;
      return;
    }

    const table = document.createElement('table');
    table.className = 'items-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>${escapeHtml(tr('col_cover'))}</th>
          <th>${escapeHtml(tr('col_title'))}</th>
          <th>${escapeHtml(tr('col_original_title'))}</th>
          <th>${escapeHtml(tr('col_year'))}</th>
          <th>${escapeHtml(tr('col_imdb'))}</th>
          <th>${escapeHtml(tr('col_tmdb'))}</th>
          <th>${escapeHtml(tr('col_tvdb'))}</th>
          <th>${escapeHtml(tr('col_season'))}</th>
          <th>${escapeHtml(tr('col_actions'))}</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');

    items.forEach((item) => {
      const tr_ = document.createElement('tr');
      tr_.dataset.itemId = item.list_item_id;
      tr_.innerHTML = `
        <td class="cell-cover">
          ${item.cover_image ? `<img src="${escapeHtml(item.cover_image)}" class="row-cover-preview" alt="">` : ''}
          <input type="file" class="row-cover-input" accept="image/*">
        </td>
        <td><input type="text" class="row-title" value="${escapeHtml(item.title)}" required></td>
        <td><input type="text" class="row-original-title" value="${escapeHtml(item.original_title)}"></td>
        <td><input type="number" class="row-year" inputmode="numeric" min="1888" max="2100" value="${escapeHtml(item.first_release_year)}"></td>
        <td><input type="text" class="row-imdb" value="${escapeHtml(item.imdb_id)}"></td>
        <td><input type="text" class="row-tmdb" value="${escapeHtml(item.tmdb_id)}"></td>
        <td><input type="text" class="row-tvdb" value="${escapeHtml(item.tvdb_id)}"></td>
        <td><input type="text" class="row-season" value="${escapeHtml(item.season)}"></td>
        <td class="cell-actions">
          <button type="button" class="btn-row-save">${escapeHtml(tr('btn_save'))}</button>
          <button type="button" class="btn-row-delete">${escapeHtml(tr('btn_delete'))}</button>
          <span class="row-status"></span>
        </td>
      `;

      tr_.querySelector('.btn-row-save').addEventListener('click', () => saveRow(tr_, listId));
      tr_.querySelector('.btn-row-delete').addEventListener('click', () => deleteRow(tr_, listId, item.title));

      tbody.appendChild(tr_);
    });

    tableContainer.innerHTML = '';
    tableContainer.appendChild(table);
  }

  async function saveRow(rowEl, listId) {
    const statusSpan = rowEl.querySelector('.row-status');
    const title = rowEl.querySelector('.row-title').value.trim();

    if (title === '') {
      statusSpan.textContent = tr('add_title_required');
      statusSpan.className = 'row-status error';
      return;
    }

    statusSpan.textContent = tr('saving');
    statusSpan.className = 'row-status info';

    const formData = new FormData();
    formData.append('list_item_id', rowEl.dataset.itemId);
    formData.append('title', title);
    formData.append('original_title', rowEl.querySelector('.row-original-title').value.trim());
    formData.append('first_release_year', rowEl.querySelector('.row-year').value.trim());
    formData.append('imdb_id', rowEl.querySelector('.row-imdb').value.trim());
    formData.append('tmdb_id', rowEl.querySelector('.row-tmdb').value.trim());
    formData.append('tvdb_id', rowEl.querySelector('.row-tvdb').value.trim());
    formData.append('season', rowEl.querySelector('.row-season').value.trim());

    const coverFile = rowEl.querySelector('.row-cover-input').files?.[0];
    if (coverFile) {
      formData.append('cover_image', coverFile);
    }

    try {
      const res = await fetch(ajaxUrl('update_item'), {
        method: 'POST',
        body: formData,
        keepalive: true,
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error || `HTTP ${res.status}`);
      }

      statusSpan.textContent = tr('saved');
      statusSpan.className = 'row-status success';

      if (data.cover_image) {
        const preview = rowEl.querySelector('.row-cover-preview');
        if (preview) {
          preview.src = data.cover_image;
        } else {
          const cell = rowEl.querySelector('.cell-cover');
          const img = document.createElement('img');
          img.className = 'row-cover-preview';
          img.src = data.cover_image;
          cell.prepend(img);
        }
      }
    } catch (err) {
      statusSpan.textContent = tr('save_error_prefix', err.message || err);
      statusSpan.className = 'row-status error';
    }
  }

  async function deleteRow(rowEl, listId, title) {
    if (!window.confirm(tr('confirm_delete', title))) {
      return;
    }

    const statusSpan = rowEl.querySelector('.row-status');
    statusSpan.textContent = tr('deleting');
    statusSpan.className = 'row-status info';

    try {
      const res = await fetch(ajaxUrl('delete_item'), {
        method: 'POST',
        body: new URLSearchParams({ list_id: listId, list_item_id: rowEl.dataset.itemId }),
        keepalive: true,
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error || `HTTP ${res.status}`);
      }

      rowEl.remove();
      currentItems = currentItems.filter((it) => it.list_item_id !== rowEl.dataset.itemId);
      if (currentItems.length === 0) {
        tableContainer.innerHTML = `<div class="empty-hint">${escapeHtml(tr('empty'))}</div>`;
      }
    } catch (err) {
      statusSpan.textContent = tr('delete_error_prefix', err.message || err);
      statusSpan.className = 'row-status error';
    }
  }

  async function addNewItem() {
    const listId = listSelect?.value;
    const titleInput = document.getElementById('title');
    const title = titleInput?.value.trim() || '';

    if (!listId) {
      setStatus(addStatusEl, tr('select_list_hint'), 'error');
      return;
    }
    if (title === '') {
      setStatus(addStatusEl, tr('add_title_required'), 'error');
      return;
    }

    setStatus(addStatusEl, tr('add_saving'), 'info');

    const formData = new FormData();
    formData.append('list_id', listId);
    formData.append('title', title);
    formData.append('original_title', document.getElementById('original_title')?.value.trim() || '');
    formData.append('first_release_year', document.getElementById('first_release_year')?.value.trim() || '');
    formData.append('imdb_id', document.getElementById('imdb_id')?.value.trim() || '');
    formData.append('tmdb_id', document.getElementById('tmdb_id')?.value.trim() || '');
    formData.append('tvdb_id', document.getElementById('tvdb_id')?.value.trim() || '');
    formData.append('season', document.getElementById('season')?.value.trim() || '');

    const coverFile = document.getElementById('cover_image')?.files?.[0];
    if (coverFile) {
      formData.append('cover_image', coverFile);
    }

    try {
      const res = await fetch(ajaxUrl('add_item'), { method: 'POST', body: formData });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error || `HTTP ${res.status}`);
      }

      setStatus(addStatusEl, '', 'info');

      // Clear the add-item fields and reload the table so the new item
      // shows up immediately.
      ['title', 'original_title', 'first_release_year', 'imdb_id', 'tmdb_id', 'tvdb_id', 'season'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
      });
      const coverInput = document.getElementById('cover_image');
      if (coverInput) coverInput.value = '';
      const preview = document.getElementById('preview');
      if (preview) {
        preview.style.display = 'none';
        preview.removeAttribute('src');
      }

      await loadItems(listId);
    } catch (err) {
      setStatus(addStatusEl, tr('add_error_prefix', err.message || err), 'error');
    }
  }

  listSelect?.addEventListener('change', () => loadItems(listSelect.value));
  btnAddItem?.addEventListener('click', addNewItem);

  // Called from index.php's tab-switch handler the first time the "edit"
  // tab is opened (lazy init - no point loading anything before the user
  // actually looks at this tab).
  window.clmInitEditTab = function () {
    if (initialised) return;
    initialised = true;
    loadItems(listSelect?.value || '');
  };
})();

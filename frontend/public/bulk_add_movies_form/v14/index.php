<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/lang.php';

$lang = bamf_current_lang();
$t = bamf_load_translations($lang);

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(tr($t, 'page.title')) ?></title>
  <style>
    :root{
      --bg:#0c1024;
      --panel:#141a33;
      --panel2:#0f1428;
      --line:#26305a;
      --text:#eef1ff;
      --muted:#98a2ce;
      --accent:#6f8dff;
      --accent2:#3ddc97;
      --danger:#ff8a8a;
      --warning:#ffd36a;
      --radius:16px;
      --shadow:0 18px 40px rgba(0,0,0,.25);
    }
    *{ box-sizing:border-box; }
    html, body{ margin:0; min-height:100%; }
    body{
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(111,141,255,.16), transparent 28%),
        radial-gradient(circle at top right, rgba(61,220,151,.10), transparent 24%),
        var(--bg);
      color:var(--text);
    }
    button, input, select, textarea{ font:inherit; }
    button{ cursor:pointer; }
    a{ color:var(--accent); }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .page{ max-width:1460px; margin:0 auto; padding:24px 18px 48px; }
    .hero{
      display:flex;
      justify-content:space-between;
      gap:18px;
      align-items:flex-start;
      margin-bottom:18px;
      padding:18px 20px;
      background:rgba(20,26,51,.92);
      border:1px solid var(--line);
      border-radius:20px;
      box-shadow:var(--shadow);
    }
    .hero h1{ margin:0 0 6px; font-size:28px; }
    .hero p{ margin:0; color:var(--muted); max-width:760px; }
    .heroActions{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-end; }
    .langForm{
      display:flex;
      align-items:center;
      gap:8px;
      padding:8px 10px;
      background:var(--panel2);
      border:1px solid var(--line);
      border-radius:12px;
    }
    .langForm label{ color:var(--muted); font-size:12px; }
    .layout{ display:grid; gap:18px; }
    .card{
      background:rgba(20,26,51,.95);
      border:1px solid var(--line);
      border-radius:18px;
      box-shadow:var(--shadow);
    }
    .cardHead{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      padding:16px 18px 0;
    }
    .cardHead h2,
    .cardHead h3,
    .cardHead h4{ margin:0; }
    .cardBody{ padding:18px; }
    .noteBox{
      background:rgba(111,141,255,.08);
      border:1px solid rgba(111,141,255,.35);
      border-radius:14px;
      padding:14px 16px;
      color:var(--muted);
      font-size:13px;
    }
    .noteBox ul{ margin:8px 0 0 18px; padding:0; }
    .noteBox li + li{ margin-top:6px; }
    .tag{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      border:1px solid rgba(111,141,255,.4);
      background:rgba(111,141,255,.14);
      color:var(--text);
      font-size:12px;
      white-space:nowrap;
    }
    .tag.good{
      background:rgba(61,220,151,.14);
      border-color:rgba(61,220,151,.5);
      color:#d9ffee;
    }
    .tag.bad{
      background:rgba(255,138,138,.14);
      border-color:rgba(255,138,138,.5);
      color:#ffd3d3;
    }
    .tag.warn{
      background:rgba(255,211,106,.14);
      border-color:rgba(255,211,106,.45);
      color:#ffe8a6;
    }
    .tabBar{
      display:flex;
      gap:6px;
      border-bottom:1px solid var(--line);
      margin-bottom:16px;
      padding-bottom:6px;
    }
    .tabBtn{
      appearance:none;
      border:1px solid transparent;
      background:transparent;
      color:var(--muted);
      font-size:14px;
      font-weight:600;
      padding:10px 14px;
      border-radius:12px;
    }
    .tabBtn:hover{ color:var(--text); background:rgba(111,141,255,.08); }
    .tabBtn.active{
      color:var(--text);
      background:rgba(111,141,255,.15);
      border-color:rgba(111,141,255,.45);
    }
    .tabPanel{ display:none; }
    .tabPanel.active{ display:block; }
    .formGrid{
      display:grid;
      grid-template-columns:repeat(12, minmax(0, 1fr));
      gap:14px;
    }
    .field{ display:grid; gap:6px; }
    .field label{ font-size:12px; color:var(--muted); }
    .span-3{ grid-column:span 3; }
    .span-4{ grid-column:span 4; }
    .span-6{ grid-column:span 6; }
    .span-8{ grid-column:span 8; }
    .span-12{ grid-column:span 12; }
    input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea{
      width:100%;
      background:var(--panel2);
      border:1px solid var(--line);
      border-radius:12px;
      color:var(--text);
      padding:11px 12px;
      outline:none;
    }
    textarea{ min-height:140px; resize:vertical; }
    input::placeholder, textarea::placeholder{ color:#7f88b0; }
    input:focus, select:focus, textarea:focus{
      border-color:rgba(111,141,255,.85);
      box-shadow:0 0 0 3px rgba(111,141,255,.18);
    }
    .field small{ color:var(--muted); font-size:12px; }
    .checkLine{ display:flex; align-items:center; gap:8px; color:var(--muted); font-size:13px; }
    .checkLine input{ accent-color:var(--accent2); }
    .btnRow{ display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .btn{
      appearance:none;
      border:1px solid var(--line);
      background:var(--panel2);
      color:var(--text);
      border-radius:12px;
      padding:10px 14px;
      font-weight:600;
    }
    .btn:hover{ border-color:rgba(111,141,255,.55); }
    .btnPrimary{ background:rgba(111,141,255,.16); border-color:rgba(111,141,255,.5); }
    .btnSuccess{ background:rgba(61,220,151,.16); border-color:rgba(61,220,151,.46); }
    .btnDanger{ background:rgba(255,138,138,.14); border-color:rgba(255,138,138,.42); }
    .btnGhost{ background:transparent; }
    .btnTiny{ padding:6px 10px; font-size:12px; border-radius:10px; }
    .btnIcon{ padding:10px 12px; min-width:44px; }
    .toolbar{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      margin-bottom:14px;
      flex-wrap:wrap;
    }
    .toolbar p{ margin:4px 0 0; color:var(--muted); font-size:13px; }
    .dataTableWrap{ overflow:auto; border:1px solid var(--line); border-radius:16px; }
    table.dataTable{
      width:100%;
      border-collapse:collapse;
      background:var(--panel);
    }
    table.dataTable th, table.dataTable td{
      padding:12px 14px;
      border-bottom:1px solid var(--line);
      text-align:left;
      vertical-align:middle;
      font-size:13px;
    }
    table.dataTable th{
      color:var(--muted);
      background:var(--panel2);
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.06em;
    }
    table.dataTable tbody tr:hover{ background:rgba(111,141,255,.06); }
    table.dataTable tbody tr:last-child td{ border-bottom:none; }
    .inlineInput{
      display:flex;
      gap:8px;
      align-items:stretch;
    }
    .inlineInput input{ flex:1; }
    input[name="tvdb_id"]{ margin-top:6px; }
    .stack{ display:grid; gap:14px; }
    .boxSetCard{ padding:18px; }
    .boxSetHeader{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      margin-bottom:14px;
      flex-wrap:wrap;
    }
    .boxSetHeader h3{ margin:0 0 4px; }
    .subtle{ color:var(--muted); font-size:12px; }
    .summaryLine{ display:flex; flex-wrap:wrap; gap:8px; }
    .sectionCard{
      background:rgba(15,20,40,.9);
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
    }
    .sectionCard h4{ margin:0 0 12px; font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
    .statusCard{ padding:16px 18px; display:grid; gap:12px; }
    .statusHeader{ display:flex; justify-content:space-between; gap:12px; align-items:center; }
    .statusBody{ color:var(--muted); font-size:13px; }
    pre.jsonBox{
      margin:0;
      padding:14px;
      background:#09101f;
      border:1px solid #1d274e;
      border-radius:14px;
      color:#dfe6ff;
      overflow:auto;
      max-height:360px;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .modal{
      position:fixed;
      inset:0;
      display:none;
      z-index:80;
      align-items:center;
      justify-content:center;
      padding:22px;
      background:rgba(5,8,18,.78);
      backdrop-filter:blur(4px);
    }
    .modal.active{ display:flex; }
    .modalDialog{
      width:min(1120px, 100%);
      max-height:calc(100vh - 44px);
      overflow:auto;
      background:linear-gradient(180deg, rgba(20,26,51,.98), rgba(15,20,40,.98));
      border:1px solid var(--line);
      border-radius:20px;
      box-shadow:var(--shadow);
    }
    .modalDialog.modalNarrow{ width:min(820px, 100%); }
    .modalHead, .modalFoot{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      padding:16px 18px;
      border-bottom:1px solid var(--line);
    }
    .modalFoot{ border-top:1px solid var(--line); border-bottom:none; }
    .modalHead h3{ margin:0; }
    .modalBody{ padding:18px; }
    .modalTitleBlock{ display:grid; gap:4px; }
    .modalTitleBlock p{ margin:0; color:var(--muted); font-size:13px; }
    .closeBtn{
      appearance:none;
      border:1px solid var(--line);
      background:transparent;
      color:var(--muted);
      border-radius:999px;
      width:38px;
      height:38px;
      font-size:20px;
      line-height:1;
    }
    .closeBtn:hover{ color:var(--text); border-color:rgba(111,141,255,.5); }
    .searchShell{ display:grid; gap:12px; }
    .searchBar{
      display:flex;
      gap:10px;
      align-items:center;
      padding:10px 12px;
      background:var(--panel2);
      border:1px solid var(--line);
      border-radius:14px;
    }
    .searchBar input{ border:none; background:transparent; padding:0; }
    .searchBar input:focus{ box-shadow:none; }
    .searchResults{
      display:grid;
      gap:8px;
      max-height:320px;
      overflow:auto;
      padding-right:4px;
    }
    .searchItem{
      display:grid;
      grid-template-columns:56px 1fr;
      gap:12px;
      padding:10px;
      background:rgba(15,20,40,.9);
      border:1px solid var(--line);
      border-radius:14px;
      cursor:pointer;
    }
    .searchItem:hover{ border-color:rgba(111,141,255,.55); }
    .searchPoster, .searchNoPoster{
      width:56px;
      height:84px;
      border-radius:10px;
      object-fit:cover;
      background:#0b1122;
      border:1px solid rgba(238,241,255,.08);
    }
    .searchNoPoster{
      display:grid;
      place-items:center;
      text-align:center;
      color:var(--muted);
      font-size:11px;
      padding:6px;
    }
    .searchMeta{ color:var(--muted); font-size:12px; margin-top:4px; }
    .selectedItem{
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
      background:rgba(15,20,40,.9);
    }
    .selectedLayout{ display:grid; grid-template-columns:180px 1fr; gap:16px; align-items:start; }
    .selectedLayout img{ width:100%; border-radius:14px; border:1px solid rgba(238,241,255,.08); }
    .emptyPoster{
      min-height:260px;
      border-radius:14px;
      display:grid;
      place-items:center;
      color:var(--muted);
      border:1px solid rgba(238,241,255,.08);
      background:#0b1122;
    }
    .muted{ color:var(--muted); }
    .hidden{ display:none !important; }
    @media (max-width: 980px){
      .span-3, .span-4, .span-6, .span-8{ grid-column:span 12; }
      .selectedLayout{ grid-template-columns:1fr; }
    }
    @media (max-width: 700px){
      .page{ padding:16px 12px 36px; }
      .hero{ padding:16px; }
      .heroActions{ justify-content:flex-start; }
      table.dataTable th, table.dataTable td{ padding:10px; }
      .modal{ padding:10px; }
      .btnRow{ width:100%; }
    }
  </style>
</head>
<body>
  <main class="page">
    <section class="hero">
      <div>
        <h1><?= h(tr($t, 'page.h1')) ?> <span class="tag">v14</span></h1>
        <p><?= h(tr($t, 'page.lead')) ?></p>
      </div>
      <div class="heroActions">
        <form method="get" class="langForm">
          <label for="langSelect"><?= h(tr($t, 'lang.label')) ?></label>
          <select id="langSelect" name="lang" onchange="this.form.submit()">
            <option value="nb" <?= $lang === 'nb' ? 'selected' : '' ?>><?= h(tr($t, 'lang.nb')) ?></option>
            <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>><?= h(tr($t, 'lang.en')) ?></option>
          </select>
          <noscript><button class="btn btnTiny" type="submit"><?= h(tr($t, 'btn.apply')) ?></button></noscript>
        </form>
        <button class="btn btnDanger" type="button" id="btnResetAll"><?= h(tr($t, 'btn.reset')) ?></button>
      </div>
    </section>

    <section class="layout">
      <article class="card">
        <div class="cardHead">
          <div>
            <h2><?= h(tr($t, 'section.setup')) ?></h2>
          </div>
          <span class="tag"><?= h(tr($t, 'hint.send_api')) ?></span>
        </div>
        <div class="cardBody stack">
          <div class="formGrid">
            <div class="field span-8">
              <label for="storageId"><?= h(tr($t, 'label.storage_id')) ?></label>
              <input type="text" id="storageId" class="mono" value="564a3999-5d00-11f1-9526-bab3c527eb51" />
              <small><?= h(tr($t, 'hint.storage_id')) ?></small>
            </div>
          </div>
          <details class="noteBox" open>
            <summary><?= h(tr($t, 'btn.show_help')) ?></summary>
            <ul>
              <li><?= tr($t, 'help.li1_html') ?></li>
              <li><?= tr($t, 'help.li2_html') ?></li>
              <li><?= tr($t, 'help.li3_html') ?></li>
              <li><?= tr($t, 'help.li4_html') ?></li>
            </ul>
          </details>
        </div>
      </article>

      <article class="card">
        <div class="cardBody">
          <div class="tabBar" id="mainTabs">
            <button class="tabBtn active" type="button" data-tab="singles"><?= h(tr($t, 'tab.singles')) ?></button>
            <button class="tabBtn" type="button" data-tab="boxsets"><?= h(tr($t, 'tab.boxsets')) ?></button>
          </div>

          <section class="tabPanel active" data-tab-panel="singles">
            <div class="toolbar">
              <div>
                <h2><?= h(tr($t, 'section.singles')) ?></h2>
                <p><?= h(tr($t, 'hint.discs_html')) ?></p>
              </div>
              <div class="btnRow">
                <button class="btn btnPrimary" type="button" id="btnSingleAddRow"><?= h(tr($t, 'btn.add_row')) ?></button>
                <button class="btn" type="button" id="btnSinglePreview"><?= h(tr($t, 'btn.preview_singles')) ?></button>
                <button class="btn btnSuccess" type="button" id="btnSubmitSingles"><?= h(tr($t, 'btn.submit_singles')) ?></button>
              </div>
            </div>

            <div class="formGrid" style="margin-bottom:16px;">
              <div class="field span-3">
                <label for="singleFormat"><?= h(tr($t, 'label.default_format')) ?></label>
                <select id="singleFormat">
                  <option value="DVD"><?= h(tr($t, 'format.dvd_short')) ?></option>
                  <option value="BD" selected><?= h(tr($t, 'format.bd_short')) ?></option>
                  <option value="UHD"><?= h(tr($t, 'format.uhd_short')) ?></option>
                </select>
                <small><?= h(tr($t, 'hint.default_format')) ?></small>
              </div>
              <div class="field span-3">
                <label for="singleCopyCount"><?= h(tr($t, 'label.default_copy_count')) ?></label>
                <input type="number" id="singleCopyCount" min="1" value="1" />
              </div>
              <div class="field span-6">
                <label for="singleQuickImport"><?= h(tr($t, 'label.quick_add_title')) ?></label>
                <div class="inlineInput">
                  <input id="singleQuickImport" placeholder="<?= h(tr($t, 'ph.quick_add_title')) ?>" />
                  <button class="btn btnPrimary" type="button" id="btnSingleAddFromText"><?= h(tr($t, 'btn.add')) ?></button>
                </div>
                <small><?= h(tr($t, 'hint.quick_add')) ?></small>
              </div>
            </div>

            <div class="dataTableWrap">
              <table class="dataTable" id="singleTable">
                <thead>
                  <tr>
                    <th><?= h(tr($t, 'col.title')) ?></th>
                    <th><?= h(tr($t, 'col.format')) ?></th>
                    <th><?= h(tr($t, 'col.barcode')) ?></th>
                    <th><?= h(tr($t, 'col.imdb')) ?></th>
                    <th><?= h(tr($t, 'col.discs')) ?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </section>

          <section class="tabPanel" data-tab-panel="boxsets">
            <div class="toolbar">
              <div>
                <h2><?= h(tr($t, 'section.boxsets')) ?></h2>
                <p><?= h(tr($t, 'hint.boxsets')) ?></p>
              </div>
              <div class="btnRow">
                <button class="btn btnPrimary" type="button" id="btnAddBoxSet"><?= h(tr($t, 'btn.add_box_set')) ?></button>
                <button class="btn" type="button" id="btnPreviewAllBoxSets"><?= h(tr($t, 'btn.preview_box_sets')) ?></button>
                <button class="btn btnSuccess" type="button" id="btnSubmitBoxSets"><?= h(tr($t, 'btn.submit_box_sets')) ?></button>
              </div>
            </div>
            <div class="stack" id="boxSetsContainer"></div>
          </section>
        </div>
      </article>

      <article class="card statusCard" id="apiStatusCard">
        <div class="statusHeader">
          <h3><?= h(tr($t, 'section.api_status')) ?></h3>
          <span class="tag" id="apiStatusTag">…</span>
        </div>
        <div class="statusBody" id="apiStatusText"><?= h(tr($t, 'status.idle')) ?></div>
        <pre class="jsonBox" id="apiStatusPre">{}</pre>
      </article>
    </section>
  </main>

  <div class="modal" id="pasteModal" aria-hidden="true">
    <div class="modalDialog modalNarrow">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.paste.title')) ?></h3>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody stack">
        <div class="field span-12">
          <label for="boxPasteArea"><?= h(tr($t, 'modal.paste.label')) ?></label>
          <textarea id="boxPasteArea" placeholder="<?= h(tr($t, 'modal.paste.placeholder')) ?>"></textarea>
        </div>
      </div>
      <div class="modalFoot">
        <button class="btn" type="button" data-modal-close><?= h(tr($t, 'btn.cancel')) ?></button>
        <button class="btn btnPrimary" type="button" id="btnBoxPasteApply"><?= h(tr($t, 'btn.add')) ?></button>
      </div>
    </div>
  </div>

  <div class="modal" id="discModal" aria-hidden="true">
    <div class="modalDialog">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.discs.title')) ?></h3>
          <p id="discModalSubtitle">—</p>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody stack">
        <div class="toolbar" style="margin-bottom:0;">
          <p><?= tr($t, 'modal.discs.hint_html') ?></p>
          <button class="btn btnPrimary" type="button" id="btnAddDiscEditorRow"><?= h(tr($t, 'btn.add_disc')) ?></button>
        </div>
        <div class="dataTableWrap">
          <table class="dataTable" id="discEditorTable">
            <thead>
              <tr>
                <th><?= h(tr($t, 'col.type')) ?></th>
                <th><?= h(tr($t, 'col.format')) ?></th>
                <th><?= h(tr($t, 'col.label')) ?></th>
                <th><?= h(tr($t, 'col.storage_slot_no')) ?></th>
                <th><?= h(tr($t, 'col.add_to_storage')) ?></th>
                <th><?= h(tr($t, 'col.bonus_items')) ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modalFoot">
        <button class="btn" type="button" data-modal-close><?= h(tr($t, 'btn.cancel')) ?></button>
        <button class="btn btnSuccess" type="button" id="btnSaveDiscsForSingle"><?= h(tr($t, 'btn.save_discs')) ?></button>
      </div>
    </div>
  </div>

  <div class="modal" id="bonusModal" aria-hidden="true">
    <div class="modalDialog">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.bonus.title')) ?></h3>
          <p id="bonusModalSubtitle">—</p>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody stack">
        <div class="noteBox hidden" id="bonusNotBonusAlert"><?= tr($t, 'modal.bonus.warn_not_bonus_html') ?></div>
        <div class="toolbar" style="margin-bottom:0;">
          <p><?= tr($t, 'modal.bonus.stored_as_html') ?></p>
          <button class="btn btnPrimary" type="button" id="btnAddBonusItemRow"><?= h(tr($t, 'btn.add_item')) ?></button>
        </div>
        <div class="dataTableWrap">
          <table class="dataTable" id="bonusItemsTable">
            <thead>
              <tr>
                <th><?= h(tr($t, 'col.seq')) ?></th>
                <th><?= h(tr($t, 'col.title')) ?></th>
                <th><?= h(tr($t, 'col.type')) ?></th>
                <th><?= h(tr($t, 'col.runtime')) ?></th>
                <th><?= h(tr($t, 'col.notes')) ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modalFoot">
        <button class="btn" type="button" data-modal-close><?= h(tr($t, 'btn.cancel')) ?></button>
        <button class="btn btnSuccess" type="button" id="btnSaveBonusItems"><?= h(tr($t, 'btn.save_items')) ?></button>
      </div>
    </div>
  </div>

  <div class="modal" id="previewModal" aria-hidden="true">
    <div class="modalDialog modalNarrow">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.preview.title')) ?></h3>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody">
        <pre class="jsonBox" id="previewPre"></pre>
      </div>
      <div class="modalFoot">
        <button class="btn" type="button" data-modal-close><?= h(tr($t, 'btn.close')) ?></button>
      </div>
    </div>
  </div>

  <div class="modal" id="searchModal" aria-hidden="true">
    <div class="modalDialog modalNarrow">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.search.title')) ?></h3>
          <p><?= h(tr($t, 'hint.search')) ?></p>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody searchShell">
        <div class="searchBar">
          <span>🔍</span>
          <input type="text" id="searchInput" placeholder="<?= h(tr($t, 'ph.search')) ?>" autocomplete="off" />
          <span id="loadingSpinner" class="muted hidden"><?= h(tr($t, 'status.search_loading')) ?></span>
        </div>
        <div class="muted" id="searchStatus"><?= h(tr($t, 'status.search_short')) ?></div>
        <div class="searchResults" id="dropdownResults"></div>
        <div id="selectedItem"></div>
      </div>
    </div>
  </div>

  <div class="modal" id="tvdbSearchModal" aria-hidden="true">
    <div class="modalDialog modalNarrow">
      <div class="modalHead">
        <div class="modalTitleBlock">
          <h3><?= h(tr($t, 'modal.search_tvdb.title')) ?></h3>
          <p><?= h(tr($t, 'hint.search_tvdb')) ?></p>
        </div>
        <button class="closeBtn" type="button" data-modal-close aria-label="<?= h(tr($t, 'aria.close_modal')) ?>">×</button>
      </div>
      <div class="modalBody searchShell">
        <div class="searchBar">
          <span>🔍</span>
          <input type="text" id="tvdbSearchInput" placeholder="<?= h(tr($t, 'ph.search_tvdb')) ?>" autocomplete="off" />
          <span id="tvdbLoadingSpinner" class="muted hidden"><?= h(tr($t, 'status.search_loading')) ?></span>
        </div>
        <div class="checkLine" style="gap:16px;">
          <label><input type="radio" name="tvdbSearchType" value="movie" checked> <?= h(tr($t, 'text.search_movie')) ?></label>
          <label><input type="radio" name="tvdbSearchType" value="series"> <?= h(tr($t, 'text.search_tv')) ?></label>
        </div>
        <div class="muted" id="tvdbSearchStatus"><?= h(tr($t, 'status.search_short')) ?></div>
        <div class="searchResults" id="tvdbDropdownResults"></div>
      </div>
    </div>
  </div>

  <script>
    window.BAMF_I18N = <?= json_encode(bamf_flatten_for_js($t), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.BAMF_DEFAULT_STORAGE_ID = '564a3999-5d00-11f1-9526-bab3c527eb51';
  </script>
  <script src="script.js"></script>
</body>
</html>

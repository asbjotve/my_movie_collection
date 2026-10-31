<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

/**
 * share.php – public, read-only view of a single custom list via its
 * unguessable share_token (website_template_example/v19).
 *
 * Deliberately standalone - includes _shared/auth.php only for its
 * helper functions (fetch_default_language_setting(), used by
 * lang.php's language resolution), but never calls is_logged_in()/
 * require_login_or_json_401() - anyone with the link can view this
 * page, the token itself is the access control, same model as e.g. a
 * Google Docs "anyone with the link" share. See:
 *   - set_list_share_token()/get_list_by_share_token() in
 *     custom_list_manager.py (backend)
 *   - GET /lists/share/{token} (backend route)
 *   - api.php?action=shared_list&token=... (proxy, also unguarded)
 *   - custom_list_manager/v4's "Del offentlig"-toggle (admin UI that
 *     turns the link on/off and shows the URL to copy)
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= htmlspecialchars(t('wte.share.meta_title')) ?></title>
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
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    main{ max-width:760px; margin:0 auto; padding:28px 20px 60px; }
    h1{ font-size:20px; margin:0 0 4px; }
    .shareHint{ color: var(--muted); font-size:12px; margin:0 0 24px; }
    h4{ margin:20px 0 6px; color: var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.06em; }
    .tag{
      font-size:11px; padding:3px 8px; border-radius:999px;
      background: rgba(111,141,255,.14);
      border:1px solid rgba(111,141,255,.4);
      color: var(--text);
    }
    .list{ display:grid; gap:10px; }
    .row{
      display:flex; justify-content:space-between; align-items:center; gap:12px;
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:12px 14px;
    }
    .row > *{ width:auto; max-width:none; flex-shrink:1; }
    .row strong{ font-size:14px; }
    .statusText{ color: var(--muted); font-size:13px; }
    .statusText.error{ color: var(--danger); }
    footer{ margin-top:32px; color: var(--muted); font-size:11px; }
  </style>
</head>
<body>
  <main>
    <h1 id="shareListTitle"><?= htmlspecialchars(t('wte.share.loading')) ?></h1>
    <p class="shareHint"><?= htmlspecialchars(t('wte.share.footer_note')) ?></p>
    <p class="statusText" id="shareStatus"><?= htmlspecialchars(t('wte.share.loading')) ?></p>
    <div class="list" id="shareItemsList"></div>
  </main>

  <script>
    const WTE_I18N = {
      share: <?= json_encode(wte_translations_branch('wte.share'), JSON_UNESCAPED_UNICODE) ?>,
      andre_lister: <?= json_encode(wte_translations_branch('wte.index.andre_lister'), JSON_UNESCAPED_UNICODE) ?>,
    };

    function escapeHtml(s){
      return String(s ?? "").replace(/[&<>"']/g, c => ({
        "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
      }[c]));
    }

    (async function(){
      const titleEl = document.getElementById("shareListTitle");
      const statusEl = document.getElementById("shareStatus");
      const listEl = document.getElementById("shareItemsList");
      const token = new URLSearchParams(window.location.search).get("token") || "";

      if (!token) {
        titleEl.textContent = WTE_I18N.share.not_found;
        statusEl.style.display = "none";
        return;
      }

      try {
        const res = await fetch("api.php?action=shared_list&token=" + encodeURIComponent(token));
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || res.statusText);

        titleEl.textContent = json.list_name || WTE_I18N.share.meta_title;
        document.title = (json.list_name ? json.list_name + " – " : "") + WTE_I18N.share.meta_title;

        const items = Array.isArray(json.items) ? json.items : [];
        if (!items.length) {
          statusEl.textContent = WTE_I18N.share.empty;
          return;
        }
        statusEl.style.display = "none";

        // Samme gruppe-oppdeling (group_label) som "Andre lister"-
        // detaljvisningen i index.php.
        const groups = [];
        const groupIndexByLabel = new Map();
        items.forEach(it => {
          const label = it.group_label || null;
          if (!groupIndexByLabel.has(label)) {
            groupIndexByLabel.set(label, groups.length);
            groups.push({ label, items: [] });
          }
          groups[groupIndexByLabel.get(label)].items.push(it);
        });

        let position = 0;
        listEl.innerHTML = groups.map(group => {
          const header = group.label ? `<h4>${escapeHtml(group.label)}</h4>` : "";
          const rows = group.items.map(it => {
            position += 1;
            const year = it.first_release_year ? ` (${it.first_release_year})` : "";
            const ownedBadge = it.owned_physical
              ? `<span class="tag" title="${escapeHtml(WTE_I18N.andre_lister.owned_badge)}">✔ ${escapeHtml(WTE_I18N.andre_lister.owned_badge)}</span>`
              : "";
            const plexBadge = it.in_plex
              ? `<span class="tag" title="${escapeHtml(WTE_I18N.andre_lister.in_plex_badge)}">📺 ${escapeHtml(WTE_I18N.andre_lister.in_plex_badge)}</span>`
              : "";
            return `
            <div class="row">
              <span style="display:flex;align-items:center;gap:10px;">
                <span class="tag">${position}</span>
                <strong>${escapeHtml(it.title)}${escapeHtml(year)}</strong>
                ${ownedBadge}
                ${plexBadge}
              </span>
            </div>`;
          }).join("");
          return header + `<div class="list">${rows}</div>`;
        }).join("");
      } catch (err) {
        titleEl.textContent = WTE_I18N.share.not_found;
        statusEl.textContent = "";
        statusEl.style.display = "none";
      }
    })();
  </script>
</body>
</html>

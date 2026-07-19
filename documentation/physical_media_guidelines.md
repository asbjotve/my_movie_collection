# Physical media catalog – modeling rules (barcode, box sets, discs)

These rules aim to:
- keep `content` clean (movies only),
- represent products/editions accurately (`physical_collection`),
- model bonus material without creating “noise” in `content`,
- make barcode lookup and disc listings consistent.

---

## 1) What goes into `content`?

**Rule 1.1 — `content` contains works you treat as titles (typically movies).**  
Do **not** create `content` rows for bonus material like “Appendices”, “Bonus Disc”, “Behind the scenes”.

**Rule 1.2 — One movie = one `content` row, regardless of edition.**  
DVD / Blu-ray / 4K / steelbook / box set all reference the same `content` movie rows.

---

## 2) What is a `physical_collection`?

**Rule 2.1 — `physical_collection` represents a purchasable product/edition.**  
Examples:
- single release (one movie sold alone)
- box set (multiple movies sold together)
- special editions (steelbook, collector’s edition, etc.)

**Rule 2.2 — Different edition/product = different `physical_collection`, even for same movie(s).**

---

## 3) Barcode fields: `barcode` vs `box_set_barcode`

Pick a single interpretation and stick to it. Recommended (since you already have both fields):

**Rule 3.1 — Single release barcode**  
If the product is a *single release* (one movie sold alone):
- set `physical_collection.barcode = EAN`
- set `physical_collection.box_set_barcode = NULL`

**Rule 3.2 — Box set barcode**  
If the product is a *box set* (multiple movies sold as a box):
- set `physical_collection.box_set_barcode = EAN`
- set `physical_collection.barcode = NULL`

**Rule 3.3 — When singles and a box both exist (e.g., Police Academy case)**  
Register:
- one `physical_collection` per single movie (each with `barcode`), **and**
- one `physical_collection` for the box set (with `box_set_barcode`)

These are different products.

**Rule 3.4 — Barcode lookup**  
When searching/scanning a barcode, check both fields:
- `barcode = ? OR box_set_barcode = ?`

---

## 4) Linking works to products: `content_in_physical_collection`

**Rule 4.1 — Every `physical_collection` must have at least one row in `content_in_physical_collection`.**
- single release: exactly 1 movie
- box set: multiple movies

**Rule 4.2 — `box_set_title_sort` is the title order within the box.**
- box set: 1..N
- single release: always set to `1`

---

## 5) Owned items: `physical_copy`

**Rule 5.1 — `physical_copy` represents your owned copy #X of a product.**
- If you own two identical box sets: `copy_id = 1` and `copy_id = 2`.

---

## 6) Discs: `disc` and `disc_in`

**Rule 6.1 — Every physical disc is modeled as a `disc`, placed via `disc_in`.**  
Do not model discs as `content` (neither movie discs nor bonus discs).

**Rule 6.2 — Disc role**
- `disc.role = 'movie'` for movie discs
- `disc.role = 'bonus'` for bonus/extra material discs
- (optional) `disc.role = 'other'` for things like soundtrack CDs, etc.

**Rule 6.3 — Disc labeling (`disc.label`)**
Use a human-friendly, stable, searchable label. Suggested patterns:
- single release: `"<Title> – Movie"`, `"<Title> – Bonus"`
- box set: `"<MovieTitle> – Movie Disc 1"`, `"<MovieTitle> – Bonus"`, `"Box-set – Bonus"`

**Rule 6.4 — Disc order within a product**
`disc_in.box_set_disc_order` is the physical order in the packaging, typically `1..N`,
and should be unique per `(collection_id, copy_id)`.

---

## 7) Mapping a disc to a movie (bonus-per-movie without bonus-as-content)

This assumes you have (or add) a nullable field:
- `disc_in.related_content_id` (FK to `content.content_id`)

**Rule 7.1 — Movie disc in a box set**
Set:
- `disc_in.related_content_id = that movie’s content_id`

**Rule 7.2 — Bonus disc that belongs to a specific movie**
Set:
- `disc_in.related_content_id = that movie’s content_id`
and:
- `disc.role = 'bonus'`

**Rule 7.3 — Bonus disc for the entire box set**
Set:
- `disc_in.related_content_id = NULL`
and:
- `disc.role = 'bonus'`
- `disc.label = "Box-set – Bonus"` (or similar)

**Rule 7.4 — Single release with a bonus disc**
Set:
- `disc_in.related_content_id = the single movie’s content_id`
This keeps “bonus per movie” queries consistent.

---

## 8) “Minimal noise” guidelines

**Rule 8.1 — Never create `content` rows for bonus features.**  
Model them only as discs (`disc.role='bonus'`, `disc.label`, and optionally `related_content_id`).

**Rule 8.2 — If you only need to know bonus exists, `role + label` is enough.**

**Rule 8.3 — If you need to know which movie the bonus relates to, use `related_content_id`.**

---

## Optional (recommended) data integrity constraints

If your DB supports it, consider enforcing the rules:

- Exactly one barcode field set per `physical_collection` (single vs box):
  - `CHECK ((barcode IS NULL) <> (box_set_barcode IS NULL))`
- Limit disc roles:
  - `CHECK (role IN ('movie','bonus','other'))`
- Ensure disc order is positive:
  - `CHECK (box_set_disc_order > 0)`
- Ensure disc order uniqueness per owned copy:
  - `UNIQUE (collection_id, copy_id, box_set_disc_order)`

# Physical collection import rules

This document describes how import payloads for physical media releases should be interpreted and mapped into the database.

---

## 1. Overall model

The system distinguishes between:

- **`content`** = the work/title itself, typically a movie
- **`physical_collection`** = a physical product or physical release unit
- **`physical_copy`** = one owned copy of a product
- **`disc`** = a physical disc
- **`disc_in`** = the link between a disc and a physical copy / collection

### Core rule
A movie should only exist once in `content`, but it may be linked to multiple different `physical_collection` rows.

Example:
- *The Lord of the Rings: The Fellowship of the Ring* exists as one `content`
- but it may be linked to:
  - a single Blu-ray release
  - a box set
  - an inner case inside a box set

---

## 2. `content`

### Rule 2.1
`content` contains only works/titles, typically movies.

### Rule 2.2
Bonus material must **not** be created as separate `content` rows.

### Rule 2.3
The same movie should be reused across editions/releases if it already exists.

Example:
- one `content` row for *Police Academy*
- the same `content` may be linked both to a single release and to a box set if both exist

---

## 3. `physical_collection`

`physical_collection` represents a physical product or physical unit in the collection.

### Rule 3.1 — Single release
If the product is a normal single release:
- `physical_collection.barcode = EAN`
- `physical_collection.box_set_barcode = NULL`

### Rule 3.2 — Box set
If the product is a box set:
- `physical_collection.barcode = NULL`
- `physical_collection.box_set_barcode = EAN`

### Rule 3.3 — Inner case as its own physical collection
If a movie inside a box set has `inner_case_ean`, that movie must also get its own row in `physical_collection`.

Then set:
- `physical_collection.barcode = inner_case_ean`
- `physical_collection.box_set_barcode = the box set EAN`

This means the inner case is treated as its own physical unit, even though it also belongs to a box set.

### Rule 3.4 — The same movie may exist in multiple `physical_collection` rows
If both a single release and a box set exist, they must be registered as separate products.

Example:
- one `physical_collection` for *Police Academy* single release
- one `physical_collection` for the *Police Academy* box set
- optionally one `physical_collection` for an inner case inside the box set if it has its own barcode

---

## 4. Linking `content` and `physical_collection`

The link is stored in:

- `content_in_physical_collection`

### Rule 4.1
All `physical_collection` rows must have at least one link to `content`.

### Rule 4.2 — Single release
A single release should normally have:
- exactly one `content` link
- `box_set_title_sort = 1`

### Rule 4.3 — Box set
A box set should have one link per movie in the box.

### Rule 4.4 — Box set title order
`box_set_title_sort` is used to store the ordering of the movies inside the box set.

---

## 5. `physical_copy`

`physical_copy` represents an actually owned copy of a `physical_collection`.

### Rule 5.1
If you own one copy of a product:
- create one `physical_copy` row with `copy_id = 1`

### Rule 5.2
If you own multiple identical copies:
- create multiple rows with the same `collection_id`
- use `copy_id = 1, 2, 3, ...`

---

## 6. `disc`

`disc` represents a physical disc.

### Rule 6.1
All discs must be stored in `disc`.

### Rule 6.2
Discs must **not** be stored as `content`.

### Rule 6.3
Fields used on `disc`:
- `type_disc`
- `format`
- `label`

### Rule 6.4 — Label
`label` is a human-friendly and searchable description.

Examples:
- `The Lord of the Rings: The Fellowship of the Ring – Movie`
- `The Lord of the Rings: The Two Towers – Movie`
- `Box-set – Bonus`

---

## 7. `disc_in`

`disc_in` describes where a disc is physically placed.

### Rule 7.1
A disc is linked to a physical copy via:
- `collection_id`
- `copy_id`
- `disc_id`

### Rule 7.2
`box_set_disc_order` stores the physical order of the disc within the product.

### Rule 7.3
`related_content_id` is used to indicate which movie the disc belongs to, when relevant.

---

## 8. Meaning of `related_index` in box set payloads

In `box_sets_bulk` payloads, `related_index` points to an index in the `movies` list.

### Example
If the payload has:

```json
"movies": [
  { "order": 1, "title": "The Lord of the Rings: The Fellowship of the Ring" },
  { "order": 2, "title": "The Lord of the Rings: The Two Towers" }
]
```

then:
- `related_index = 0` → first movie in the list
- `related_index = 1` → second movie in the list

### Rule 8.1
`related_index` is used to find the correct `content`.

### Rule 8.2
When `related_index` is present, `disc_in.related_content_id` must be set to that movie’s `content_id`.

---

## 9. Physical placement of discs in box set payloads

This is the most important rule for box set import.

### Rule 9.1 — `related_index != null`
If a disc has `related_index` set:
- the disc must be registered on the **inner-case `physical_collection`**
- the disc is related to the movie at that index
- `disc_in.related_content_id` must be set to that movie’s `content_id`

This requires that the referenced movie has `inner_case_ean`.

### Rule 9.2 — `related_index == null`
If a disc has `related_index = null`:
- the disc must be registered on the **box set `physical_collection`**
- `disc_in.related_content_id = NULL`

### Rule 9.3 — Validation
If a disc has `related_index != null`, but the referenced movie does not have `inner_case_ean`, the payload is invalid.

---

## 10. Meaning of `inner_case_ean`

### Rule 10.1
`inner_case_ean` means that the movie inside the box set must also be registered as its own `physical_collection`.

### Rule 10.2
This inner-case collection represents a physical sub-unit / inner case inside the box set.

### Rule 10.3
The same movie should still also be linked to the box set collection itself.

This means the same `content` will often be linked to:
- the outer box set
- the inner case
- optionally a separate single release

---

## 11. Meaning of `treat_as_single`

### Rule 11.1
`treat_as_single` indicates that the movie inside the box set should be treated as its own physical unit.

### Rule 11.2
In practice, this is used together with `inner_case_ean`.

### Rule 11.3
If `treat_as_single = true` but `inner_case_ean` is missing, the payload should be treated as invalid or incomplete.

---

## 12. Import behavior for `box_sets_bulk`

When `kind = "box_sets_bulk"`, the import must do the following:

### Step 1
Create one `physical_collection` for the box set itself:
- `barcode = NULL`
- `box_set_barcode = payload.box_set_barcode`

### Step 2
For each movie in `movies`:
- find or create `content`
- link that movie to the box set through `content_in_physical_collection`

### Step 3
If the movie has `inner_case_ean`:
- create a separate `physical_collection`
- link the same `content` to that collection as well

### Step 4
Create `physical_copy` rows for the box set and for any inner-case collections

### Step 5
For each disc in `discs`:
- if `related_index != null`:
  - place the disc on the referenced movie’s inner-case collection
- if `related_index == null`:
  - place the disc on the box set collection

### Step 6
Create any `disc_bonus_item` rows for each disc

---

## 13. Import behavior for singles

When the payload describes single releases:

### Step 1
Create one `physical_collection` per row:
- `barcode = row.barcode`
- `box_set_barcode = NULL`

### Step 2
Find or create the correct `content`

### Step 3
Link `content` to `physical_collection`

### Step 4
Create `physical_copy`

### Step 5
Create discs and link them through `disc_in`

---

## 14. Barcode lookup

When scanning or looking up a barcode, both fields must be checked:

- `physical_collection.barcode`
- `physical_collection.box_set_barcode`

### Rule 14.1
Barcode lookup should use:

```sql
WHERE barcode = ? OR box_set_barcode = ?
```

---

## 15. Important principles

### Principle 15.1
`content` must stay clean and represent only works/titles.

### Principle 15.2
`physical_collection` represents products or physical units.

### Principle 15.3
Bonus material should be modeled as discs and bonus items, not as `content`.

### Principle 15.4
The same movie may be linked to multiple different physical products.

### Principle 15.5
In box set payloads, `related_index` is used both to:
- find the correct movie
- determine that the disc belongs to an inner case

When `related_index = null`, the disc belongs to the box set itself.

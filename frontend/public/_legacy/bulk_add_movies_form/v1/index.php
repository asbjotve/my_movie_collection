<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Legg til filmer og box sets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h1>Legg til filmer og box sets</h1>
    <form id="bulkAddForm">
        <!-- Default Storage ID -->
        <div class="mb-4">
            <label for="defaultStorageId" class="form-label">Default Storage ID (valgfritt)</label>
            <input name="default_storage_id" id="defaultStorageId" type="text" class="form-control" placeholder="Standard Storage ID">
        </div>

        <!-- Enkeltfilmer -->
        <div id="singleFilms" class="mb-4">
            <h3>Enkeltfilmer</h3>
            <button type="button" class="btn btn-outline-primary mt-2" id="addSingleFilm">Legg til film</button>
        </div>

        <!-- Boxsets -->
        <div id="boxSets">
            <h3>Box Sets</h3>
            <button type="button" class="btn btn-outline-primary mt-2" id="addBoxSet">Legg til box set</button>
        </div>

        <button type="submit" class="btn btn-success mt-4">Send inn</button>
    </form>

    <div id="result" class="mt-3"></div>

    <script>
    /**
     * Disc form builder.
     * options:
     *  - showOrder: boolean (box_set_disc_order)
     *  - defaultFormat: string
     *  - defaultTypeDisc: string ("feature" / "bonus" / etc)
     *  - lockTypeDisc: boolean (true => readonly)
     */
    function addDiscForm(discsDiv, options = {}) {
        const {
            showOrder = true,
            defaultFormat = "Blu-ray",
            defaultTypeDisc = "feature",
            lockTypeDisc = false
        } = options;

        const discDiv = document.createElement('div');
        discDiv.className = "disc-row row g-2 mb-2 align-items-end";

        discDiv.innerHTML = `
            <div class="col-md">
                <label class="form-label mb-1">Type</label>
                <input name="type_disc" type="text" class="form-control" placeholder="feature/bonus/etc"
                       value="${defaultTypeDisc}" ${lockTypeDisc ? "readonly" : ""}>
            </div>

            <div class="col-md">
                <label class="form-label mb-1">Format</label>
                <input name="format" type="text" class="form-control" placeholder="Format" value="${defaultFormat}">
            </div>

            ${showOrder ? `
            <div class="col-md">
                <label class="form-label mb-1">Disc order</label>
                <input name="box_set_disc_order" type="number" class="form-control" placeholder="Disc order (i box set)">
            </div>` : ``}

            <div class="col-md">
                <label class="form-label mb-1">Storage ID</label>
                <input name="storage_id" type="text" class="form-control" placeholder="Storage ID">
            </div>

            <div class="col-md">
                <label class="form-label mb-1">Nr i storage</label>
                <input name="number_in_storage" type="number" class="form-control" placeholder="Nummer i storage">
            </div>

            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-danger remove-disc">Fjern</button>
            </div>
        `;
        discsDiv.appendChild(discDiv);
    }

    // Enkeltfilm
    function addSingleFilmForm() {
        const filmDiv = document.createElement('div');
        filmDiv.className = "single-film mb-3 border rounded p-3";
        filmDiv.innerHTML = `
            <div class="row g-2">
                <div class="col-md">
                    <input name="title" type="text" class="form-control" placeholder="Tittel" required>
                </div>
                <div class="col-md">
                    <input name="imdb_id" type="text" class="form-control" placeholder="IMDb ID">
                </div>
                <div class="col-md">
                    <input name="barcode" type="text" class="form-control" placeholder="Barcode">
                </div>
            </div>

            <div class="mt-2">
                <label class="form-label mb-1">Discer:</label>
                <div class="discs"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-singlefilm-disc">Legg til disc</button>
            </div>

            <div class="mt-3 d-flex flex-row gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-danger remove-film">Fjern enkeltfilm</button>
            </div>
        `;

        document.getElementById('singleFilms').insertBefore(filmDiv, document.getElementById('addSingleFilm'));
        wireRemoveFilmButtons(document.querySelectorAll('.single-film'), { label: "Fjern enkeltfilm" });
    }

    // Box set
    function addBoxSetForm() {
        const boxDiv = document.createElement('div');
        boxDiv.className = "box-set mb-3 border rounded p-3";

        boxDiv.innerHTML = `
            <div class="row g-2">
                <div class="col-md">
                    <input name="box_set_barcode" type="text" class="form-control" placeholder="Box Set Barcode">
                </div>
                <div class="col-md">
                    <input name="format" type="text" class="form-control" value="Blu-ray" required>
                </div>
            </div>

            <!-- Box set discs (no film-tilknytning) -->
            <div class="mt-3">
                <label class="form-label mb-1">Discer uten film-tilknytning (ofte bonus):</label>
                <div class="boxset-discs discs"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-boxset-disc">Legg til disc</button>
                <div class="form-text">
                    Disse discene har ingen film-tilknytning. Feature-discer kan fortsatt legges under filmer også.
                    Disc order kan stå tomt, da autogenereres det ved innsending uten å kollidere med de du har fylt inn manuelt.
                </div>
            </div>

            <hr class="my-3">

            <div class="films">
                <label class="form-label mb-1">Filmer:</label>
                <div class="boxset-films"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-boxset-film mt-1">Legg til film</button>
            </div>

            <div class="mt-3 d-flex flex-row gap-2 justify-content-end">
                <button type="button" class="btn btn-danger remove-boxset">Fjern box set</button>
            </div>
        `;

        document.getElementById('boxSets').insertBefore(boxDiv, document.getElementById('addBoxSet'));
        wireRemoveBoxSetButtons();
    }

    function addBoxSetFilmForm(boxsetFilmsDiv) {
        const filmDiv = document.createElement('div');
        filmDiv.className = "boxset-film mb-2 border rounded p-2";
        filmDiv.innerHTML = `
            <div class="row g-2">
                <div class="col-md">
                    <input name="title" type="text" class="form-control" placeholder="Tittel" required>
                </div>
                <div class="col-md">
                    <input name="imdb_id" type="text" class="form-control" placeholder="IMDb ID">
                </div>
                <div class="col-md">
                    <input name="barcode" type="text" class="form-control" placeholder="Barcode">
                </div>
                <div class="col-md">
                    <input name="box_set_title_sort" type="number" class="form-control" placeholder="Sortering (nummer)">
                </div>
            </div>

            <div class="mt-2">
                <label class="form-label mb-1">Discer (feature/bonus/etc) for denne filmen:</label>
                <div class="discs"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-boxsetfilm-disc">Legg til disc</button>
            </div>

            <div class="mt-3 d-flex flex-row gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-danger remove-film">Fjern film i box set</button>
            </div>
        `;

        boxsetFilmsDiv.appendChild(filmDiv);
        wireRemoveFilmButtons(boxsetFilmsDiv.querySelectorAll('.boxset-film'), { label: "Fjern film i box set" });
    }

    function wireRemoveFilmButtons(nodeList, { label }) {
        nodeList.forEach(div => {
            const removeBtn = div.querySelector('.remove-film');
            if (!removeBtn) return;
            removeBtn.innerText = label;
            removeBtn.onclick = () => div.remove();
        });
    }

    function wireRemoveBoxSetButtons() {
        document.querySelectorAll('.box-set').forEach(boxDiv => {
            const removeBtn = boxDiv.querySelector('.remove-boxset');
            if (!removeBtn) return;
            removeBtn.innerText = 'Fjern box set';
            removeBtn.onclick = () => boxDiv.remove();
        });
    }

    // Buttons
    document.getElementById('addSingleFilm').addEventListener('click', () => addSingleFilmForm());
    document.getElementById('addBoxSet').addEventListener('click', () => addBoxSetForm());

    // Delegert click-handling
    document.addEventListener('click', function(e) {
        // Single film discs
        if (e.target.classList.contains('add-singlefilm-disc')) {
            const host = e.target.closest('.single-film');
            if (!host) return;
            addDiscForm(host.querySelector('.discs'), {
                showOrder: true,
                defaultFormat: "Blu-ray",
                defaultTypeDisc: "feature",
                lockTypeDisc: false
            });
        }

        // Box set level discs (default bonus, but editable)
        if (e.target.classList.contains('add-boxset-disc')) {
            const boxDiv = e.target.closest('.box-set');
            if (!boxDiv) return;
            const boxFormat = boxDiv.querySelector('input[name="format"]')?.value || "Blu-ray";
            addDiscForm(boxDiv.querySelector('.boxset-discs'), {
                showOrder: true,
                defaultFormat: boxFormat,
                defaultTypeDisc: "bonus",
                lockTypeDisc: false
            });
        }

        // Add film in box set
        if (e.target.classList.contains('add-boxset-film')) {
            const boxDiv = e.target.closest('.box-set');
            if (!boxDiv) return;
            addBoxSetFilmForm(boxDiv.querySelector('.boxset-films'));
        }

        // Box set film discs (feature/bonus/etc)
        if (e.target.classList.contains('add-boxsetfilm-disc')) {
            const filmDiv = e.target.closest('.boxset-film');
            if (!filmDiv) return;
            addDiscForm(filmDiv.querySelector('.discs'), {
                showOrder: true,
                defaultFormat: "Blu-ray",
                defaultTypeDisc: "feature",
                lockTypeDisc: false
            });
        }

        // Remove disc row
        if (e.target.classList.contains('remove-disc')) {
            const discRow = e.target.closest('.disc-row');
            if (discRow) discRow.remove();
        }
    });

    // Serialization helpers
    function readDiscRow(discDiv) {
        const type_disc = discDiv.querySelector('input[name="type_disc"]')?.value ?? "";
        const format = discDiv.querySelector('input[name="format"]')?.value ?? "";
        const orderEl = discDiv.querySelector('input[name="box_set_disc_order"]');
        const storageIdEl = discDiv.querySelector('input[name="storage_id"]');
        const numberEl = discDiv.querySelector('input[name="number_in_storage"]');

        return {
            type_disc,
            format,
            box_set_disc_order: orderEl && orderEl.value ? parseInt(orderEl.value) : null,
            storage_id: storageIdEl && storageIdEl.value ? storageIdEl.value : null,
            number_in_storage: numberEl && numberEl.value ? parseInt(numberEl.value) : null
        };
    }

    function readDiscs(containerEl) {
        const discs = [];
        if (!containerEl) return discs;
        containerEl.querySelectorAll('.disc-row').forEach(discDiv => discs.push(readDiscRow(discDiv)));
        return discs;
    }

    /**
     * Autogenerate missing box_set_disc_order.
     * - Existing positive integers are reserved.
     * - Missing/invalid values get assigned lowest free positive integer.
     * - Never overwrites existing specified order values.
     * - Sort output: explicit first, then generated; both groups sorted by order.
     */
    function autogenerateBoxSetDiscOrder(discs) {
        const used = new Set();

        // reserve explicit orders
        discs.forEach(d => {
            if (Number.isInteger(d.box_set_disc_order) && d.box_set_disc_order > 0) {
                used.add(d.box_set_disc_order);
            }
        });

        let next = 1;
        function nextAvailable() {
            while (used.has(next)) next++;
            const v = next;
            used.add(v);
            next++;
            return v;
        }

        // assign missing
        discs.forEach(d => {
            const isValid = Number.isInteger(d.box_set_disc_order) && d.box_set_disc_order > 0;
            if (!isValid) {
                d.box_set_disc_order = nextAvailable();
                d._autogenerated_order = true;
            } else {
                d._autogenerated_order = false;
            }
        });

        // prioritize explicit first (your requirement #2)
        discs.sort((a, b) => {
            const aAuto = a._autogenerated_order ? 1 : 0;
            const bAuto = b._autogenerated_order ? 1 : 0;
            if (aAuto !== bAuto) return aAuto - bAuto;
            return (a.box_set_disc_order ?? 0) - (b.box_set_disc_order ?? 0);
        });

        discs.forEach(d => { delete d._autogenerated_order; });
        return discs;
    }

    function serializeForm() {
        const default_storage_id = document.getElementById('defaultStorageId').value || null;

        // Single films
        const singleFilms = [];
        document.querySelectorAll('.single-film').forEach(filmDiv => {
            const title = filmDiv.querySelector('input[name="title"]').value;
            if (!title) return;

            const imdb_id = filmDiv.querySelector('input[name="imdb_id"]').value;
            const barcode = filmDiv.querySelector('input[name="barcode"]').value;
            const discs = readDiscs(filmDiv.querySelector('.discs'));

            singleFilms.push({ title, imdb_id, barcode, discer: discs });
        });

        // Box sets
        const boxSets = [];
        document.querySelectorAll('.box-set').forEach(boxDiv => {
            const format = boxDiv.querySelector('input[name="format"]').value;
            if (!format) return;

            const box_set_barcode = boxDiv.querySelector('input[name="box_set_barcode"]').value;

            // Box set-level discs (no film-tilknytning)
            let box_set_discs = readDiscs(boxDiv.querySelector('.boxset-discs'));
            box_set_discs = autogenerateBoxSetDiscOrder(box_set_discs);

            // Films in box set
            const films = [];
            boxDiv.querySelectorAll('.boxset-film').forEach(filmDiv => {
                const title = filmDiv.querySelector('input[name="title"]').value;
                if (!title) return;

                const imdb_id = filmDiv.querySelector('input[name="imdb_id"]').value;
                const barcode = filmDiv.querySelector('input[name="barcode"]').value;
                const box_set_title_sort = filmDiv.querySelector('input[name="box_set_title_sort"]').value
                    ? parseInt(filmDiv.querySelector('input[name="box_set_title_sort"]').value)
                    : null;

                const discs = readDiscs(filmDiv.querySelector('.discs'));

                films.push({ title, imdb_id, barcode, box_set_title_sort, discer: discs });
            });

            boxSets.push({ box_set_barcode, format, box_set_discs, films });
        });

        return {
            single_films: singleFilms,
            box_sets: boxSets,
            default_storage_id
        };
    }

    // Submit (uendret)
    document.getElementById('bulkAddForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const payload = serializeForm();
        const token = localStorage.getItem('token');

        fetch('/proxy_filmdatabase.php?endpoint=api/films/bulk-add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.detail && data.detail === "Not authenticated") {
                document.getElementById('result').innerHTML =
                    `<div class="alert alert-danger">Du må logge inn før du kan sende inn skjemaet!</div>`;
            } else if (data.detail) {
                const msg = typeof data.detail === "object" ? JSON.stringify(data.detail, null, 2) : data.detail;
                document.getElementById('result').innerHTML =
                    `<div class="alert alert-danger">Feil: ${msg}</div>`;
            } else if (data.error) {
                const msg = typeof data.error === "object" ? JSON.stringify(data.error, null, 2) : data.error;
                document.getElementById('result').innerHTML =
                    `<div class="alert alert-danger">Feil: ${msg}</div>`;
            } else {
                document.getElementById('result').innerHTML =
                    `<div class="alert alert-success">Filmer og box sets lagt til!<br><pre>${JSON.stringify(data, null, 2)}</pre></div>`;
                e.target.reset();
                document.querySelectorAll('.single-film').forEach(el => el.remove());
                document.querySelectorAll('.box-set').forEach(el => el.remove());
            }
        })
        .catch(err => {
            document.getElementById('result').innerHTML =
                `<div class="alert alert-danger">Feil ved innsending: ${err}</div>`;
        });
    });
    </script>
</body>
</html>

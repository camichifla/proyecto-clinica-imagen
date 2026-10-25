(function () {
    let clinics = [];

    const DIAS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];

    // Escapa texto de la base antes de armarlo como HTML.
    function esc(texto) {
        const div = document.createElement("div");
        div.textContent = texto == null ? "" : String(texto);
        return div.innerHTML;
    }

    // [[dia, apertura, cierre], ...] -> "Lun a Vie 08:00-20:00 · Sáb 08:00-13:00"
    function horariosTexto(horarios) {
        const porDia = {};
        horarios.forEach(([dia, apertura, cierre]) => {
            porDia[dia] = (porDia[dia] ? porDia[dia] + " / " : "") + apertura + "-" + cierre;
        });
        const grupos = [];
        for (let dia = 1; dia <= 7; dia++) {
            if (!porDia[dia]) {
                continue;
            }
            const ultimo = grupos[grupos.length - 1];
            if (ultimo && ultimo.hasta === dia - 1 && ultimo.texto === porDia[dia]) {
                ultimo.hasta = dia;
            } else {
                grupos.push({ desde: dia, hasta: dia, texto: porDia[dia] });
            }
        }
        return grupos
            .map((g) => (g.desde === g.hasta ? DIAS[g.desde - 1] : DIAS[g.desde - 1] + " a " + DIAS[g.hasta - 1]) + " " + g.texto)
            .join(" · ");
    }

    const map = L.map("map", { zoomControl: false }).setView([-34.9011, -56.1645], 12);
    L.control.zoom({ position: "topright" }).addTo(map);
    // The map is hidden on phones (CSS); recompute its size when it becomes visible again.
    window.addEventListener("resize", () => map.invalidateSize());

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);

    const markerIcon = L.divIcon({
        className: "custom-marker",
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -30],
    });

    const markers = new Map();

    function addMarkers() {
        clinics.forEach((clinic) => {
            if (clinic.latitud === null || clinic.longitud === null) {
                return; // listed, not pinned
            }
            const marker = L.marker([clinic.latitud, clinic.longitud], { icon: markerIcon }).addTo(map);
            marker.bindPopup(popupTemplate(clinic));
            marker.on("click", () => setActiveClinic(clinic.id));
            markers.set(clinic.id, marker);
        });
    }

    // Equipamiento is free text, one item per line; esc() keeps it safe inside the template.
    function equipamientoLista(texto) {
        const items = texto.split("\n").map((l) => l.trim()).filter(Boolean);
        return `<ul class="equipamiento-lista">${items.map((i) => `<li>${esc(i)}</li>`).join("")}</ul>`;
    }

    function popupTemplate(clinic) {
        return `
            <div class="popup-clinic">
                <h4>${esc(clinic.nombre)}</h4>
                <p>${esc(clinic.direccion)}</p>
                <div class="popup-meta">
                    ${clinic.equipamiento ? equipamientoLista(clinic.equipamiento) : ""}
                    <span>${esc(horariosTexto(clinic.horarios))}</span>
                </div>
            </div>
        `;
    }

    function clinicCardTemplate(clinic) {
        return `
            <div class="clinic-card" data-id="${clinic.id}">
                <div class="clinic-card-header">
                    <span class="clinic-name">${esc(clinic.nombre)}</span>
                </div>
                <div class="clinic-address">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    ${esc(clinic.direccion)}
                </div>
                ${clinic.equipamiento ? equipamientoLista(clinic.equipamiento) : ""}
                <div class="clinic-footer">
                    <span class="clinic-hours">${esc(horariosTexto(clinic.horarios))}</span>
                </div>
            </div>
        `;
    }

    const emptyStateTemplate = `
        <div class="clinics-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
            </svg>
            <h4>No se encontraron sucursales</h4>
            <p>Probá con otro nombre, ciudad o dirección.</p>
        </div>
    `;

    const listEl = document.getElementById("clinicsList");
    const resultsCountEl = document.getElementById("resultsCount");
    const searchEl = document.getElementById("clinicSearch");

    function renderList(items) {
        listEl.innerHTML = items.length
            ? items.map(clinicCardTemplate).join("")
            : emptyStateTemplate;

        // Count only while a search filters the list.
        resultsCountEl.textContent = items.length === clinics.length
            ? ""
            : `${items.length} resultado${items.length === 1 ? "" : "s"}`;

        listEl.querySelectorAll(".clinic-card").forEach((card) => {
            card.addEventListener("click", () => {
                const id = Number(card.dataset.id);
                setActiveClinic(id);
                const marker = markers.get(id);
                if (marker) {
                    map.panTo(marker.getLatLng());
                    marker.openPopup();
                }
            });
        });
    }

    function setActiveClinic(id) {
        listEl.querySelectorAll(".clinic-card").forEach((card) => {
            card.classList.toggle("active", Number(card.dataset.id) === id);
        });
    }

    searchEl.addEventListener("input", () => {
        const query = searchEl.value.trim().toLowerCase();
        const filtered = clinics.filter((clinic) =>
            clinic.nombre.toLowerCase().includes(query) ||
            (clinic.direccion || "").toLowerCase().includes(query)
        );
        renderList(filtered);
    });

    fetch("api/sucursales-publicas.php")
        .then((respuesta) => respuesta.json())
        .then((data) => {
            clinics = data.sucursales;
            document.getElementById("mapCount").textContent = clinics.length;
            addMarkers();
            renderList(clinics);
        })
        .catch(() => {
            listEl.textContent = "No se pudieron cargar las sucursales.";
        });
})();

(function () {
    let clinics = [];

    const DIAS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];

    function el(tag, className, texto) {
        const nodo = document.createElement(tag);
        if (className) {
            nodo.className = className;
        }
        if (texto != null) {
            nodo.textContent = texto;
        }
        return nodo;
    }

    const SVG_NS = "http://www.w3.org/2000/svg";

    function icono(size, formas) {
        const svg = document.createElementNS(SVG_NS, "svg");
        const atributos = { width: size, height: size, viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", "stroke-width": 2, "stroke-linecap": "round", "stroke-linejoin": "round" };
        Object.entries(atributos).forEach(([k, v]) => svg.setAttribute(k, v));
        formas.forEach(([tag, attrs]) => {
            const forma = document.createElementNS(SVG_NS, tag);
            Object.entries(attrs).forEach(([k, v]) => forma.setAttribute(k, v));
            svg.append(forma);
        });
        return svg;
    }

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
                return;
            }
            const marker = L.marker([clinic.latitud, clinic.longitud], { icon: markerIcon }).addTo(map);
            marker.bindPopup(popupElement(clinic));
            marker.on("click", () => setActiveClinic(clinic.id));
            markers.set(clinic.id, marker);
        });
    }

    function equipamientoLista(texto) {
        const ul = el("ul", "equipamiento-lista");
        texto.split("\n").map((l) => l.trim()).filter(Boolean).forEach((item) => ul.append(el("li", "", item)));
        return ul;
    }

    function popupElement(clinic) {
        const meta = el("div", "popup-meta");
        if (clinic.equipamiento) {
            meta.append(equipamientoLista(clinic.equipamiento));
        }
        meta.append(el("span", "", horariosTexto(clinic.horarios)));
        const popup = el("div", "popup-clinic");
        popup.append(el("h4", "", clinic.nombre), el("p", "", clinic.direccion), meta);
        return popup;
    }

    function clinicCardElement(clinic) {
        const card = el("div", "tarjeta-sede");
        card.dataset.id = clinic.id;
        const header = el("div", "tarjeta-sede-header");
        header.append(el("span", "clinic-name", clinic.nombre));
        const address = el("div", "clinic-address");
        address.append(
            icono(14, [["path", { d: "M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" }], ["circle", { cx: 12, cy: 10, r: 3 }]]),
            " " + clinic.direccion
        );
        const footer = el("div", "clinic-footer");
        footer.append(el("span", "clinic-hours", horariosTexto(clinic.horarios)));
        card.append(header, address);
        if (clinic.equipamiento) {
            card.append(equipamientoLista(clinic.equipamiento));
        }
        card.append(footer);
        return card;
    }

    function emptyStateElement() {
        const vacio = el("div", "sedes-vacio");
        vacio.append(
            icono(32, [["circle", { cx: 11, cy: 11, r: 8 }], ["path", { d: "m21 21-4.3-4.3" }]]),
            el("h4", "", "No se encontraron sedes"),
            el("p", "", "Probá con otro nombre, ciudad o dirección.")
        );
        return vacio;
    }

    const listEl = document.getElementById("clinicsList");
    const resultsCountEl = document.getElementById("resultsCount");
    const searchEl = document.getElementById("clinicSearch");

    function renderList(items) {
        listEl.replaceChildren(...(items.length ? items.map(clinicCardElement) : [emptyStateElement()]));

        resultsCountEl.textContent = items.length === clinics.length
            ? ""
            : `${items.length} resultado${items.length === 1 ? "" : "s"}`;

        listEl.querySelectorAll(".tarjeta-sede").forEach((card) => {
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
        listEl.querySelectorAll(".tarjeta-sede").forEach((card) => {
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

    apiJson("api/sucursales-publicas.php", { publica: true }).then((data) => {
        if (!data || !data.sucursales) {
            listEl.textContent = ERROR_CONEXION;
            return;
        }
        clinics = data.sucursales;
        document.getElementById("mapCount").textContent = clinics.length;
        addMarkers();
        renderList(clinics);
    });
})();

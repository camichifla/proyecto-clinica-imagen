(function () {
    const clinics = [
        { id: 1, name: "Clinica Imagen - Centro", address: "Av. 18 de Julio 1234, Montevideo", phone: "+598 2900 1111", hours: "Lun a Vie 8:00 - 19:00", lat: -34.9058, lng: -56.1913 },
        { id: 2, name: "Clinica Imagen - Pocitos", address: "Av. Brasil 2456, Montevideo", phone: "+598 2900 2222", hours: "Lun a Sab 9:00 - 18:00", lat: -34.9137, lng: -56.1548 },
        { id: 3, name: "Clinica Imagen - Carrasco", address: "Av. Arocena 1890, Montevideo", phone: "+598 2900 3333", hours: "Lun a Vie 8:30 - 17:30", lat: -34.8896, lng: -56.0567 },
        { id: 4, name: "Clinica Imagen - Cordon", address: "Bvar. Artigas 987, Montevideo", phone: "+598 2900 4444", hours: "Lun a Vie 8:00 - 20:00", lat: -34.9016, lng: -56.1721 },
        { id: 5, name: "Clinica Imagen - Malvin", address: "Av. Rivera 5432, Montevideo", phone: "+598 2900 5555", hours: "Lun a Vie 9:00 - 18:00", lat: -34.9052, lng: -56.1247 },
        { id: 6, name: "Clinica Imagen - Union", address: "Av. 8 de Octubre 3200, Montevideo", phone: "+598 2900 6666", hours: "Lun a Vie 8:00 - 17:00", lat: -34.8756, lng: -56.1489 },
        { id: 7, name: "Clinica Imagen - Buceo", address: "Av. Luis A. de Herrera 1120, Montevideo", phone: "+598 2900 7777", hours: "Lun a Sab 9:00 - 19:00", lat: -34.9086, lng: -56.1329 },
        { id: 8, name: "Clinica Imagen - Prado", address: "Av. Agraciada 3456, Montevideo", phone: "+598 2900 8888", hours: "Lun a Vie 8:00 - 18:00", lat: -34.8776, lng: -56.1892 },
        { id: 9, name: "Clinica Imagen - Punta Carretas", address: "Ellauri 678, Montevideo", phone: "+598 2900 9999", hours: "Lun a Vie 9:00 - 18:30", lat: -34.9198, lng: -56.1592 },
        { id: 10, name: "Clinica Imagen - Aguada", address: "Av. Gral. San Martin 2100, Montevideo", phone: "+598 2900 1010", hours: "Lun a Vie 8:00 - 17:30", lat: -34.8977, lng: -56.1811 },
        { id: 11, name: "Clinica Imagen - La Blanqueada", address: "Av. 8 de Octubre 2456, Montevideo", phone: "+598 2900 1111", hours: "Lun a Vie 8:30 - 18:00", lat: -34.8867, lng: -56.1583 },
    ];

    const map = L.map("map", { zoomControl: false }).setView([-34.9011, -56.1645], 12);
    L.control.zoom({ position: "topright" }).addTo(map);

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

    clinics.forEach((clinic) => {
        const marker = L.marker([clinic.lat, clinic.lng], { icon: markerIcon }).addTo(map);
        marker.bindPopup(popupTemplate(clinic));
        marker.on("click", () => setActiveClinic(clinic.id));
        markers.set(clinic.id, marker);
    });

    function popupTemplate(clinic) {
        return `
            <div class="popup-clinic">
                <h4>${clinic.name}</h4>
                <p>${clinic.address}</p>
                <div class="popup-meta">
                    <span>${clinic.phone}</span>
                    <span>${clinic.hours}</span>
                </div>
            </div>
        `;
    }

    function clinicCardTemplate(clinic) {
        return `
            <div class="clinic-card" data-id="${clinic.id}">
                <div class="clinic-card-header">
                    <span class="clinic-name">${clinic.name}</span>
                </div>
                <div class="clinic-address">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    ${clinic.address}
                </div>
                <div class="clinic-footer">
                    <span class="clinic-phone">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.79.63 2.65a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.43-1.27a2 2 0 0 1 2.11-.45c.86.3 1.75.51 2.65.63A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        ${clinic.phone}
                    </span>
                    <span class="clinic-hours">${clinic.hours}</span>
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

        resultsCountEl.textContent = items.length === clinics.length
            ? "Mostrando todas"
            : `${items.length} resultado${items.length === 1 ? "" : "s"}`;

        listEl.querySelectorAll(".clinic-card").forEach((card) => {
            card.addEventListener("click", () => {
                const id = Number(card.dataset.id);
                setActiveClinic(id);
                const marker = markers.get(id);
                map.panTo(marker.getLatLng());
                marker.openPopup();
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
            clinic.name.toLowerCase().includes(query) ||
            clinic.address.toLowerCase().includes(query)
        );
        renderList(filtered);
    });

    renderList(clinics);
})();

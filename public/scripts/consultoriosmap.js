        const clinics = [
            {
                id: 1,
                name: "Sucursal Durazno",
                address: "Durazno",
                city: "Montevideo",
                phone: "092 745 398",
                hours: "Lun y mié 11:00-19:00hs. Mar, jue y vie 08:00-12:00 y 14:00-18:00hs. Sáb 09:00-13:00hs.",
                lat: -34.9059,
                lng: -56.1913,
                mapUrl: "https://maps.app.goo.gl/Hs8fENGdQhUNZLXh9"
            },
            {
                id: 2,
                name: "Sucursal Libertad",
                address: "Libertad",
                city: "Montevideo",
                phone: "092 390 847",
                hours: "Lun 14:00-18:30hs. Mar y vie 9:00-13:00hs. Mié 9:00-12:00 y 14:00-18:30hs. Jue 17:00-19:00hs",
                lat: -34.9011,
                lng: -56.1645,
                mapUrl: "https://maps.app.goo.gl/z5RTd5b2NiPYsKHbA"
            },
            {
                id: 3,
                name: "Sucursal Colonia",
                address: "Colonia del Sacramento",
                city: "Colonia",
                phone: "092 745 398",
                hours: "Lun a mié 08:00-20:00hs. Jue y vie 08:00-16:00hs. Sáb 09:00-13:00hs.",
                lat: -34.4714,
                lng: -57.8443,
                mapUrl: "https://maps.app.goo.gl/DGaumeRoRCeVHhRPA"
            },
            {
                id: 4,
                name: "Sucursal Las Piedras",
                address: "Las Piedras",
                city: "Canelones",
                phone: "098 786 833",
                hours: "Lun a vie 8:30-12:30hs y 15-19hs. Sáb 08-14hs.",
                lat: -34.7308,
                lng: -56.2167,
                mapUrl: "https://maps.app.goo.gl/pYzemSh7WzL3RRDm9"
            },
            {
                id: 5,
                name: "Sucursal Punta del Este",
                address: "Punta del Este",
                city: "Maldonado",
                phone: "092 745 398",
                hours: "Lun a vie 08:00-20:00hs. Sáb 09:00-13:00hs.",
                lat: -34.9369,
                lng: -54.9284,
                mapUrl: "https://maps.app.goo.gl/vqLdTqvB77YAw3aT7"
            },
            {
                id: 6,
                name: "Sucursal Caudillos",
                address: "Caudillos",
                city: "Montevideo",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun a vie 8-20hs. Sáb 9-14hs.",
                lat: -34.8790,
                lng: -56.1410,
                mapUrl: "https://maps.app.goo.gl/yiPLvrLg9kh8pGju8"
            },
            {
                id: 7,
                name: "Sucursal Nuevo Centro",
                address: "Nuevo Centro",
                city: "Montevideo",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun a vie 8-20hs. Sáb 9-14hs.",
                lat: -34.8935,
                lng: -56.1234,
                mapUrl: "https://maps.app.goo.gl/pTEMZnt3dpCCjphw5"
            },
            {
                id: 8,
                name: "Sucursal Montevideo Shopping",
                address: "Montevideo Shopping",
                city: "Montevideo",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun 8-14hs. Mar, jue y vie 8-16hs.",
                lat: -34.9035,
                lng: -56.1380,
                mapUrl: "https://maps.app.goo.gl/PHCvRUpuRBQCA74u5"
            },
            {
                id: 9,
                name: "Sucursal Barra de Carrasco",
                address: "Barra de Carrasco",
                city: "Canelones",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun 8-14hs. Mar, jue y vie 8-16hs.",
                lat: -34.8833,
                lng: -56.0584,
                mapUrl: "https://maps.app.goo.gl/LMcu1mo9W2grx6V28"
            },
            {
                id: 10,
                name: "Sucursal Atlantida",
                address: "Atlántida",
                city: "Canelones",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun a vie 8-20hs. Sáb 9-14hs.",
                lat: -34.7710,
                lng: -55.7600,
                mapUrl: "https://maps.app.goo.gl/VtvuWtE1hwzvojFC9"
            },
            {
                id: 11,
                name: "Sucursal Lagomar",
                address: "Lagomar",
                city: "Canelones",
                phone: "2602 6631 // 092 745 398",
                hours: "Lun a vie 8-20hs. Sáb 8-13hs.",
                lat: -34.7870,
                lng: -55.8200,
                mapUrl: "https://maps.app.goo.gl/mninTMBWsAgZ7R1g7"
            }
        ];

        // ═══════════════════════════════════════════════════════════════
        // STATE
        // ═══════════════════════════════════════════════════════════════
        let map;
        let markers = [];
        let activeFilter = 'all';
        let searchQuery = '';

        // ═══════════════════════════════════════════════════════════════
        // INIT
        // ═══════════════════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            renderClinics();
            setupEventListeners();
        });

        // ═══════════════════════════════════════════════════════════════
        // MAP
        // ═══════════════════════════════════════════════════════════════
        function initMap() {
            map = L.map('map').setView([-34.9011, -56.1645], 10);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 19
            }).addTo(map);

            renderMarkers();
        }

        function createCustomIcon() {
            return L.divIcon({
                className: 'custom-marker',
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
            });
        }

        function renderMarkers() {
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            const filtered = getFilteredClinics();

            filtered.forEach(clinic => {
                const marker = L.marker([clinic.lat, clinic.lng], {
                    icon: createCustomIcon()
                }).addTo(map);

                const popupContent = `
                    <div class="popup-clinic">
                        <h4>${clinic.name}</h4>
                        <p>${clinic.address}, ${clinic.city}</p>
                        <div class="popup-meta">
                            <span>📞 ${clinic.phone}</span>
                            <span>🕐 ${clinic.hours}</span>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupContent);
                marker.on('click', () => highlightClinic(clinic.id));
                markers.push(marker);
            });

            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.15));
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // LIST RENDERING
        // ═══════════════════════════════════════════════════════════════
        function getFilteredClinics() {
            return clinics.filter(clinic => {
                const matchesFilter = activeFilter === 'all' || clinic.category === activeFilter;
                const searchLower = searchQuery.toLowerCase();
                const matchesSearch = !searchQuery || 
                    clinic.name.toLowerCase().includes(searchLower) ||
                    clinic.address.toLowerCase().includes(searchLower) ||
                    clinic.city.toLowerCase().includes(searchLower) ||
                    clinic.phone.includes(searchQuery);
                return matchesFilter && matchesSearch;
            });
        }

        function renderClinics() {
            const listContainer = document.getElementById('clinicsList');
            const filtered = getFilteredClinics();

            document.getElementById('resultsCount').textContent = 
                `${filtered.length} resultado${filtered.length !== 1 ? 's' : ''}`;

            if (filtered.length === 0) {
                listContainer.innerHTML = `
                    <div class="clinics-empty">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        <h4>No se encontraron sucursales</h4>
                        <p>Probá con otra búsqueda o filtro</p>
                    </div>
                `;
                return;
            }

            listContainer.innerHTML = filtered.map(clinic => `
                <div class="clinic-card" data-id="${clinic.id}" onclick="focusClinic(${clinic.id})">
                    <div class="clinic-card-header">
                        <span class="clinic-name">${clinic.name}</span>
                    </div>
                    <div class="clinic-address">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        ${clinic.address}, ${clinic.city}
                    </div>
                    <div class="clinic-footer">
                        <span class="clinic-phone">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            ${clinic.phone}
                        </span>
                    </div>
                    <div class="clinic-hours" style="margin-top:4px;">${clinic.hours}</div>
                </div>
            `).join('');
        }

        // ═══════════════════════════════════════════════════════════════
        // INTERACTIONS
        // ═══════════════════════════════════════════════════════════════
        function focusClinic(id) {
            const clinic = clinics.find(c => c.id === id);
            if (!clinic) return;

            map.flyTo([clinic.lat, clinic.lng], 15, { duration: 1.2 });

            const marker = markers.find(m => {
                const latLng = m.getLatLng();
                return Math.abs(latLng.lat - clinic.lat) < 0.0001 && Math.abs(latLng.lng - clinic.lng) < 0.0001;
            });

            if (marker) {
                setTimeout(() => marker.openPopup(), 1300);
            }

            highlightClinic(id);
        }

        function highlightClinic(id) {
            document.querySelectorAll('.clinic-card').forEach(card => {
                card.classList.toggle('active', parseInt(card.dataset.id) === id);
            });

            const activeCard = document.querySelector(`.clinic-card[data-id="${id}"]`);
            if (activeCard) {
                activeCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function setupEventListeners() {
            const searchInput = document.getElementById('clinicSearch');
            let debounceTimer;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    searchQuery = e.target.value;
                    renderClinics();
                    renderMarkers();
                }, 300);
            });

            document.querySelectorAll('.filter-tag').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.filter-tag').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    activeFilter = btn.dataset.filter;
                    renderClinics();
                    renderMarkers();
                });
            });
        }
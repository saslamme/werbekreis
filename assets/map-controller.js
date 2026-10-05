/** Progressive enhancement: maps are opt-in, cards remain usable without tiles or JavaScript. */
export function initializeMaps(document, leaflet) {
    for (const container of document.querySelectorAll('[data-company-map]')) {
        const button = container.querySelector('[data-map-load]');
        if (!button) continue;
        button.addEventListener('click', () => {
            let map;
            try {
                const data = JSON.parse(container.dataset.map);
                const markers = data.markers.filter(marker =>
                    Number.isFinite(marker.latitude) && Math.abs(marker.latitude) <= 90 &&
                    Number.isFinite(marker.longitude) && Math.abs(marker.longitude) <= 180 &&
                    typeof marker.url === 'string' && marker.url.startsWith('/unternehmen/') && !marker.url.includes('\\'));
                if (!markers.length) throw new Error('No coordinates');
                const canvas = document.createElement('div');
                canvas.className = 'company-map-canvas';
                canvas.tabIndex = 0;
                canvas.setAttribute('aria-label', 'Unternehmensstandorte – Informationen auch in der Liste');
                container.append(canvas);
                map = leaflet.map(canvas, { scrollWheelZoom: false });
                const tiles = leaflet.tileLayer(data.tileUrl, {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                }).addTo(map);
                let tileFailed = false;
                tiles.on('tileerror', () => {
                    if (tileFailed) return;
                    tileFailed = true;
                    container.querySelector('[data-map-status]').textContent = 'Kartenkacheln konnten nicht geladen werden. Bitte verwenden Sie die Ergebnisliste.';
                    container.querySelector('[data-map-status]').classList.remove('visually-hidden');
                });
                const bounds = [];
                for (const company of markers) {
                    const popup = document.createElement('div');
                    const name = document.createElement('strong');
                    name.textContent = company.name;
                    const categories = document.createElement('p');
                    categories.textContent = (company.categories || []).join(', ');
                    const description = document.createElement('p');
                    description.textContent = company.description || '';
                    const link = document.createElement('a');
                    link.href = company.url;
                    link.textContent = 'Unternehmen ansehen';
                    popup.append(name, categories, description, link);
                    const marker = leaflet.marker([company.latitude, company.longitude], {
                        title: company.name, alt: company.name,
                        icon: leaflet.divIcon({ className: 'company-map-marker', html: '<span aria-hidden="true">●</span>', iconSize: [28, 28] }),
                    }).addTo(map).bindPopup(popup, { maxHeight: 160, maxWidth: 260 });
                    const card = document.getElementById(`company-${company.slug}`);
                    marker.on('popupopen', () => card?.classList.add('is-map-focused'));
                    marker.on('popupclose', () => card?.classList.remove('is-map-focused'));
                    if (card) {
                        const focus = document.createElement('button');
                        focus.type = 'button';
                        focus.className = 'btn btn-sm btn-outline-primary company-map-focus';
                        focus.textContent = 'Auf Karte zeigen';
                        focus.addEventListener('click', () => {
                            map.setView([company.latitude, company.longitude], 15);
                            marker.openPopup();
                            canvas.scrollIntoView({ block: 'nearest' });
                            canvas.focus();
                        });
                        card.append(focus);
                    }
                    bounds.push([company.latitude, company.longitude]);
                }
                map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
                button.remove();
                container.querySelector('[data-map-placeholder]')?.remove();
            } catch {
                map?.remove();
                button.disabled = true;
                const status = container.querySelector('[data-map-status]');
                if (status) {
                    status.textContent = 'Die Karte ist derzeit nicht verfügbar. Bitte verwenden Sie die Ergebnisliste.';
                    status.classList.remove('visually-hidden');
                }
            }
        }, { once: true });
    }
}

console.log("Map script loaded");

let dancefyMap = null;

function tryInitMap(retries = 20) {
    const mapContainer = document.getElementById("map");
    const dataElement = document.getElementById("map-data");

    if (!mapContainer || !dataElement) {
        if (retries > 0) {
            setTimeout(() => tryInitMap(retries - 1), 100);
        }
        return;
    }

    if (!dataElement.dataset.classes) {
        if (retries > 0) {
            setTimeout(() => tryInitMap(retries - 1), 100);
        }
        return;
    }

    loadMap();
}

function loadMap() {

    const dataElement = document.getElementById("map-data");
    let classes;

    try {
        classes = JSON.parse(dataElement.dataset.classes);
    } catch (e) {
        console.error("JSON parse error:", e);
        return;
    }

    if (!Array.isArray(classes)) return;

    initMap(classes);
}

function initMap(classes) {

    if (dancefyMap !== null) {
        dancefyMap.remove();
        dancefyMap = null;
    }

    dancefyMap = L.map('map', { zoomControl: false }).setView([50.0755, 14.4378], 12);

    L.control.zoom({ position: 'bottomright' }).addTo(dancefyMap);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap & Carto'
    }).addTo(dancefyMap);

    const markers = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 50,
        iconCreateFunction: function (cluster) {
            return L.divIcon({
                html: `<div class="cluster-dot">${cluster.getChildCount()}</div>`,
                className: '',
                iconSize: [34, 34]
            });
        }
    });

    const bounds = [];

    classes.forEach(cls => {

        const lat = parseFloat(cls.latitude);
        const lng = parseFloat(cls.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        bounds.push([lat, lng]);

        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: '',
                html: `<div class="marker-dot"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        });

        const popupHtml = `
            <a onclick="window.location.href='/openclass-listing.php?id=${cls.openclass_id}'" class="df-popup-link">
                <div class="df-popup">
                    <img class="df-cover" src="/uploads/openclasses/${cls.cover_image}" alt="">
                    <div class="df-content">
                        <div class="df-title">${cls.title}</div>
                        <div class="df-meta">${cls.formatted_date} • ${cls.start_time}</div>
                        <div class="df-address">${cls.address}</div>
                    </div>
                </div>
            </a>
        `;

        marker.bindPopup(popupHtml, {
            closeButton: false,
            offset: [0, -5]
        });

        markers.addLayer(marker);
    });

    dancefyMap.addLayer(markers);

    if (bounds.length > 0) {
        dancefyMap.fitBounds(bounds, { padding: [60, 60] });
    }

    setTimeout(() => {
        dancefyMap.invalidateSize();
    }, 200);
}

document.addEventListener("DOMContentLoaded", function () {
    tryInitMap();
});


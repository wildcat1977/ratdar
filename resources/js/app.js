import L from 'leaflet';
import 'leaflet.heat';
import 'leaflet.markercluster';
import { geocoder as createGeocoder, geocoders } from 'leaflet-control-geocoder';
import exifr from 'exifr';
import {
    Chart,
    ArcElement, Tooltip, Legend, PieController,
    CategoryScale, LinearScale, BarElement, BarController,
} from 'chart.js';

Chart.register(
    ArcElement, Tooltip, Legend, PieController,
    CategoryScale, LinearScale, BarElement, BarController,
);
window.Chart = Chart;

// 讓 Alpine x-init inline 可以直接用 window.L
window.L = L;

/**
 * 從選取的 File 物件解析 EXIF GPS 座標。
 * 回傳 { lat, lng } 或 null（無 GPS 資訊 / 解析失敗）。
 */
async function readExifLocation(file) {
    try {
        const gps = await exifr.gps(file);
        if (gps && gps.latitude != null && gps.longitude != null) {
            return { lat: gps.latitude, lng: gps.longitude };
        }
    } catch (_) { /* 靜默忽略解析錯誤 */ }
    return null;
}

/**
 * 用 canvas 將圖片縮小至長邊 ≤ 1024px，品質 0.72，回傳 File 物件。
 * 如果原圖已經夠小就直接回傳原 File。
 */
async function compressImage(file, maxEdge = 1024, quality = 0.72) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            const { naturalWidth: w, naturalHeight: h } = img;
            if (w <= maxEdge && h <= maxEdge) { resolve(file); return; }

            const scale  = maxEdge / Math.max(w, h);
            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(w * scale);
            canvas.height = Math.round(h * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
            }, 'image/jpeg', quality);
        };
        img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
    });
}

/**
 * 全域供 blade 的 @change handler 呼叫。
 * 1. canvas 壓縮（長邊 ≤ 1024px）
 * 2. 解析 EXIF GPS
 * 3. 把壓縮後的 File 回填到 input，Livewire 再讀取就拿到小檔
 */
window.onPhotoChange = async function (input, wire) {
    const file = input.files?.[0];
    if (!file) return;

    // 壓縮
    const compressed = await compressImage(file);

    // 把壓縮後的檔案回填到 input（觸發 Livewire wire:model 重新讀取）
    if (compressed !== file) {
        const dt = new DataTransfer();
        dt.items.add(compressed);
        input.files = dt.files;
        // 手動觸發 livewire 的 input event 讓 wire:model 更新
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // EXIF GPS（從原始檔讀，壓縮後 EXIF 已遺失）
    const loc = await readExifLocation(file);
    if (!loc) return;

    window.dispatchEvent(new CustomEvent('exif-location', { detail: loc }));
    wire.setLocationFromExif(loc.lat, loc.lng);
};

const TILE_URL_DARK  = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
const TILE_URL_LIGHT = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
const MAP_THEME_KEY  = 'mouseradar_map_theme';
const TILE_OPTIONS = {
    attribution: '&copy; OpenStreetMap &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 20,
};

// 熱區色階：暗底→亮色跳出；亮底→深色才跳出（反轉亮度進程）
const HEAT_GRADIENT_DARK  = { 0.15: '#7f1d1d', 0.45: '#ef4444', 0.70: '#f97316', 0.90: '#fbbf24' };
const HEAT_GRADIENT_LIGHT = { 0.20: '#fde68a', 0.45: '#fb923c', 0.70: '#dc2626', 0.90: '#7f1d1d' };

let radarMap = null;
let heatLayer = null;
let markersLayer = null;
let clusterLayer = null;
let poisonLayer = null;        // 毒餌獨立圖層（不分群、永遠顯示）
let ratLayerVisible = true;    // 「鼠蹤熱區」圖層控制開關（同步控制 clusterLayer）
let currentTileLayer = null;   // 底圖圖層（隨主題切換）
// 讀 localStorage，預設 light（亮色對大多數使用者更易閱讀）
let currentTheme = (() => { try { return localStorage.getItem(MAP_THEME_KEY) || 'light'; } catch (_) { return 'light'; } })();
let radarCircle = null;  // 1km 防禦圈

// ── 跑馬燈廣播（從 #radar-map data-ticker 讀取真實資料）───────
function initTicker() {
    const mapEl = document.getElementById('radar-map');
    const messages = mapEl ? JSON.parse(mapEl.dataset.ticker || '[]') : [];
    const tickerEl = document.getElementById('radar-ticker-text');
    if (!tickerEl || !messages.length) return;

    let idx = 0;
    tickerEl.textContent = messages[0];
    setInterval(() => {
        tickerEl.style.opacity = '0';
        setTimeout(() => {
            idx = (idx + 1) % messages.length;
            tickerEl.textContent = messages[idx];
            tickerEl.style.opacity = '1';
        }, 400);
    }, 7000);
}

const MARKER_ZOOM_THRESHOLD = 14;  // 14 級以上才顯示獨立圖層，以下用 cluster

const MARKER_STYLES = {
    approved: {
        radius: 9, fillColor: '#ef4444', color: '#ffffff', weight: 2, opacity: 1, fillOpacity: 0.9,
    },
    reported_1999: {
        radius: 9, fillColor: '#f59e0b', color: '#ffffff', weight: 2, opacity: 1, fillOpacity: 0.9,
    },
    resolved: {
        radius: 9, fillColor: '#22c55e', color: '#ffffff', weight: 2, opacity: 1, fillOpacity: 0.9,
    },
};

const POISON_MARKER_STYLE = {
    radius: 10, fillColor: '#a855f7', color: '#ffffff', weight: 2, opacity: 1, fillOpacity: 0.95,
};

const STATUS_LABEL = {
    approved:      window.i18n?.statusApproved ?? '🔴 已回報',
    reported_1999: window.i18n?.statusReported1999 ?? '🟡 已通報 1999',
    resolved:      window.i18n?.statusResolved ?? '🟢 已處理完畢',
};

function markerStyle(status) {
    return MARKER_STYLES[status] ?? MARKER_STYLES.approved;
}

function createPopupEl(m) {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'width:260px;font-size:13px;line-height:1.5';

    if (m.img) {
        const img = document.createElement('img');
        img.src = m.img;
        img.alt = window.i18n?.reportPhoto ?? '回報照片';
        img.style.cssText = 'width:100%;height:180px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block;cursor:pointer';
        img.addEventListener('click', () => window.open(m.img, '_blank'));
        wrap.appendChild(img);
    }

    const statusEl = document.createElement('div');
    statusEl.style.cssText = 'margin-bottom:4px;font-weight:600;font-size:12px';
    if (m.type === 'poison') {
        statusEl.textContent = window.i18n?.poisonReport ?? '☠️ 毒餌通報';
        statusEl.style.color = '#a855f7';
    } else {
        statusEl.textContent = STATUS_LABEL[m.status] ?? STATUS_LABEL.approved;
    }
    wrap.appendChild(statusEl);

    if (m.desc) {
        const p = document.createElement('p');
        p.style.cssText = 'margin:0 0 4px;color:#1e293b';
        p.textContent = m.desc;
        wrap.appendChild(p);
    }
    const small = document.createElement('small');
    small.style.color = '#64748b';
    small.textContent = m.at;
    wrap.appendChild(small);

    return wrap;
}

function buildMarkersLayer(markersData) {
    const layer = L.layerGroup();
    markersData.forEach((m) => {
        const circle = L.circleMarker([m.lat, m.lng], markerStyle(m.status));
        circle.bindPopup(createPopupEl(m), { maxWidth: 280 });
        layer.addLayer(circle);
    });
    return layer;
}

function buildClusterLayer(markersData) {
    const cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        iconCreateFunction(c) {
            const n = c.getChildCount();
            const size = n < 10 ? 32 : n < 100 ? 38 : 44;
            return L.divIcon({
                html: `<div style="width:${size}px;height:${size}px;background:#ef4444;border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;box-shadow:0 0 8px rgba(239,68,68,0.55)">${n}</div>`,
                className: '',
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
            });
        },
    });
    markersData.forEach((m) => {
        const circle = L.circleMarker([m.lat, m.lng], markerStyle(m.status));
        circle.bindPopup(createPopupEl(m), { maxWidth: 280 });
        cluster.addLayer(circle);
    });
    return cluster;
}

function buildPoisonLayer(poisonData) {
    const layer = L.layerGroup();
    poisonData.forEach((m) => {
        const icon = L.divIcon({
            html: `<div style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:18px;background:#a855f7;border:2px solid #fff;border-radius:50%;box-shadow:0 0 10px rgba(168,85,247,0.7);color:#fff">☠</div>`,
            className: 'poison-marker',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
        const marker = L.marker([m.lat, m.lng], { icon });
        marker.bindPopup(createPopupEl({ ...m, type: 'poison' }), { maxWidth: 280 });
        layer.addLayer(marker);
    });
    return layer;
}

function updateMarkersVisibility(zoom) {
    if (!radarMap) return;
    // 鼠蹤熱區已隱藏時，同時隱藏 cluster/數字層
    if (!ratLayerVisible) {
        if (clusterLayer && radarMap.hasLayer(clusterLayer)) radarMap.removeLayer(clusterLayer);
        if (markersLayer && radarMap.hasLayer(markersLayer)) radarMap.removeLayer(markersLayer);
        return;
    }
    if (zoom >= MARKER_ZOOM_THRESHOLD) {
        // 移除舊的獨立圖層，改用 cluster
        if (markersLayer && radarMap.hasLayer(markersLayer)) radarMap.removeLayer(markersLayer);
        if (clusterLayer && !radarMap.hasLayer(clusterLayer)) radarMap.addLayer(clusterLayer);
    } else {
        if (clusterLayer && radarMap.hasLayer(clusterLayer)) radarMap.removeLayer(clusterLayer);
    }
}

// ── 地圖主題切換 ──────────────────────────────────────────────
function setMapTheme(theme) {
    currentTheme = theme;
    try { localStorage.setItem(MAP_THEME_KEY, theme); } catch (_) {}
    const mapEl = document.getElementById('radar-map');
    if (mapEl) mapEl.classList.toggle('map-theme-dark', theme === 'dark');
    if (radarMap && currentTileLayer) {
        radarMap.removeLayer(currentTileLayer);
        currentTileLayer = L.tileLayer(
            theme === 'dark' ? TILE_URL_DARK : TILE_URL_LIGHT,
            TILE_OPTIONS
        ).addTo(radarMap);
    }
    // 同步切換熱區色階；leaflet.heat 需手動 redraw 才會套用新 gradient
    if (heatLayer) {
        heatLayer.setOptions({
            gradient: theme === 'dark' ? HEAT_GRADIENT_DARK : HEAT_GRADIENT_LIGHT,
        });
        if (typeof heatLayer.redraw === 'function') heatLayer.redraw();
    }
}

const ThemeToggleControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd() {
        const btn = L.DomUtil.create('button', 'leaflet-theme-toggle');
        btn.type = 'button';
        const refresh = () => {
            btn.textContent = currentTheme === 'dark' ? '☀️ 亮色模式' : '🌙 暗色模式';
            btn.title = currentTheme === 'dark' ? '切換至亮色地圖' : '切換至暗色地圖';
        };
        refresh();
        L.DomEvent.disableClickPropagation(btn);
        btn.addEventListener('click', () => {
            setMapTheme(currentTheme === 'dark' ? 'light' : 'dark');
            refresh();
        });
        return btn;
    },
});

// ── 主雷達地圖 ──────────────────────────────────────────────
function initRadar() {
    const el = document.getElementById('radar-map');
    if (!el || radarMap) return;

    const lat = parseFloat(el.dataset.defaultLat);
    const lng = parseFloat(el.dataset.defaultLng);
    const zoom = parseInt(el.dataset.defaultZoom, 10);
    const points = JSON.parse(el.dataset.heatPoints || '[]');
    const markersData = JSON.parse(el.dataset.markers || '[]');
    const poisonData = JSON.parse(el.dataset.poisonMarkers || '[]');

    el.classList.toggle('map-theme-dark', currentTheme === 'dark');
    radarMap = L.map(el, { zoomControl: false, attributionControl: false }).setView([lat, lng], zoom);

    currentTileLayer = L.tileLayer(
        currentTheme === 'dark' ? TILE_URL_DARK : TILE_URL_LIGHT,
        TILE_OPTIONS
    ).addTo(radarMap);
    L.control.zoom({ position: 'topright' }).addTo(radarMap);

    heatLayer = L.heatLayer(points, {
        radius: 30,
        blur: 18,
        maxZoom: 17,
        minOpacity: 0.35,
        gradient: currentTheme === 'dark' ? HEAT_GRADIENT_DARK : HEAT_GRADIENT_LIGHT,
    }).addTo(radarMap);

    markersLayer = buildMarkersLayer(markersData);
    clusterLayer = buildClusterLayer(markersData);
    poisonLayer  = buildPoisonLayer(poisonData);
    poisonLayer.addTo(radarMap);
    radarMap.on('zoomend', () => updateMarkersVisibility(radarMap.getZoom()));
    updateMarkersVisibility(zoom);

    // 圖層切換控制（右上）
    const overlayMaps = {
        [window.i18n?.ratLayerLabel ?? '🐀 鼠蹤熱區']:   heatLayer,
        [window.i18n?.poisonLayerLabel ?? '☠️ 毒餌分佈']: poisonLayer,
    };
    L.control.layers(null, overlayMaps, { position: 'topright', collapsed: false }).addTo(radarMap);

    // 取消勾選「鼠蹤熱區」時同步隱藏數字 cluster；重新勾選時恢復
    radarMap.on('overlayremove', (e) => {
        if (e.layer === heatLayer) {
            ratLayerVisible = false;
            updateMarkersVisibility(radarMap.getZoom());
        }
    });
    radarMap.on('overlayadd', (e) => {
        if (e.layer === heatLayer) {
            ratLayerVisible = true;
            updateMarkersVisibility(radarMap.getZoom());
        }
    });

    new ThemeToggleControl().addTo(radarMap);

    // 地標搜尋（訪客可用，右上縮合圖示，展開後搜尋）
    createGeocoder({
        defaultMarkGeocode: false,
        collapsed: true,
        placeholder: window.i18n?.searchPlaceholderRadar ?? '搜尋地標或路名…(尚不支援詳細地址)',
        position: 'topleft',
        geocoder: geocoders.nominatim({
            serviceUrl: 'https://nominatim.openstreetmap.org/',
            geocodingQueryParams: { countrycodes: 'tw', limit: 5 },
        }),
    })
    .on('markgeocode', (e) => {
        radarMap.setView(e.geocode.center, 15);
    })
    .addTo(radarMap);

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude: userLat, longitude: userLng } = pos.coords;
                radarMap.setView([userLat, userLng], 16);
                updateNearbyCount(points, userLat, userLng, parseFloat(el.dataset.nearbyRadius));
                // 畫 1km 防禦圈
                if (radarCircle) radarMap.removeLayer(radarCircle);
                radarCircle = L.circle([userLat, userLng], {
                    radius: 1000,
                    color: '#4ade80',
                    weight: 1.5,
                    opacity: 0.55,
                    fillColor: '#4ade80',
                    fillOpacity: 0.04,
                    className: 'radar-scan-circle',
                }).addTo(radarMap);
            },
            () => updateNearbyCount(points, lat, lng, parseFloat(el.dataset.nearbyRadius)),
            { enableHighAccuracy: true, timeout: 8000 }
        );
    } else {
        updateNearbyCount(points, lat, lng, parseFloat(el.dataset.nearbyRadius));
    }
    initTicker();
}

function haversineKm(a, b) {
    const R = 6371;
    const dLat = ((b[0] - a[0]) * Math.PI) / 180;
    const dLng = ((b[1] - a[1]) * Math.PI) / 180;
    const lat1 = (a[0] * Math.PI) / 180;
    const lat2 = (b[0] * Math.PI) / 180;
    const x = Math.sin(dLat / 2) ** 2 + Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
    return 2 * R * Math.asin(Math.sqrt(x));
}

function updateNearbyCount(points, lat, lng, radiusKm) {
    const counter = document.getElementById('nearby-count');
    if (!counter) return;
    counter.textContent = points.filter((p) => haversineKm([lat, lng], [p[0], p[1]]) <= radiusKm).length;
}

// ── 回報表單小地圖（由 Alpine x-init 呼叫）──────────────────
/**
 * @param {HTMLElement} el   - 地圖容器
 * @param {object}      wire - Alpine $wire proxy（對應 ReportForm Livewire 元件）
 */
window.initPinMap = function (el, wire) {
    const DEFAULT_LAT = 25.033;
    const DEFAULT_LNG = 121.5654;
    let skipNextMoveEnd = false;

    const map = L.map(el, { zoomControl: true, attributionControl: false })
        .setView([DEFAULT_LAT, DEFAULT_LNG], 17);

    L.tileLayer(TILE_URL_LIGHT, TILE_OPTIONS).addTo(map);

    // 地址搜尋（Nominatim / OSM，免費無 API Key）
    createGeocoder({
        defaultMarkGeocode: false,
        collapsed: true,
        placeholder: window.i18n?.searchPlaceholderPin ?? '搜尋地標或路名…(暫不支援門牌號碼)',
        geocoder: geocoders.nominatim({ serviceUrl: 'https://nominatim.openstreetmap.org/', geocodingQueryParams: { countrycodes: 'tw', limit: 5 } }),
    })
    .on('markgeocode', (e) => {
        const { lat, lng } = e.geocode.center;
        setPos(lat, lng);
    })
    .addTo(map);

    // 📍 取得目前位置按鈕
    const LocateControl = L.Control.extend({
        options: { position: 'bottomright' },
        onAdd() {
            const btn = L.DomUtil.create('button', '');
            btn.innerHTML = window.i18n?.myLocation ?? '📍 我的位置';
            btn.title = window.i18n?.getGPS ?? '取得目前 GPS 位置';
            btn.style.cssText = 'padding:5px 10px;font-size:12px;cursor:pointer;background:#fff;color:#1e293b;border:1px solid rgba(0,0,0,0.2);border-radius:6px;white-space:nowrap;box-shadow:0 1px 3px rgba(0,0,0,0.12);';
            L.DomEvent.on(btn, 'click', (ev) => {
                L.DomEvent.stopPropagation(ev);
                if (!navigator.geolocation) return;
                btn.textContent = window.i18n?.locating ?? '定位中…';
                btn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        setPos(pos.coords.latitude, pos.coords.longitude);
                        btn.innerHTML = window.i18n?.myLocation ?? '📍 我的位置';
                        btn.disabled = false;
                    },
                    () => {
                        btn.innerHTML = window.i18n?.myLocation ?? '📍 我的位置';
                        btn.disabled = false;
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            });
            return btn;
        },
    });
    new LocateControl().addTo(map);

    // 中心固定圖釘（取代可拖曳 marker）— 地圖中心即回報座標
    const pinEl = document.createElement('div');
    pinEl.style.cssText = 'position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);pointer-events:none;z-index:800;font-size:28px;line-height:1;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5));';
    pinEl.textContent = '📍';
    el.appendChild(pinEl);

    const reverseGeocode = (lat, lng) => {
        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=zh-TW`)
            .then(r => r.json())
            .then(data => {
                const a = data.address || {};
                const city     = a.city || a.county || a.state || '';
                const district = a.city_district || a.suburb || a.town || a.village || '';
                const parts    = [city, district].filter(Boolean);
                wire.setAddress(parts.join(' '));
            })
            .catch(() => {});
    };

    // 地圖移動結束 → 以中心點更新回報座標（拖曳地圖 = 調整位置）
    map.on('moveend', () => {
        if (skipNextMoveEnd) { skipNextMoveEnd = false; return; }
        const { lat, lng } = map.getCenter();
        wire.setLocation(lat, lng);
        reverseGeocode(lat, lng);
    });

    const setPos = (lat, lng) => {
        skipNextMoveEnd = true;
        map.setView([lat, lng], 18);
        wire.setLocation(lat, lng);
        reverseGeocode(lat, lng);
    };

    // 接收 EXIF 解析成功後的位置事件
    const onExifLocation = (e) => setPos(e.detail.lat, e.detail.lng);
    window.addEventListener('exif-location', onExifLocation);
    // 元件移除時清理事件
    el.__exifCleanup = () => window.removeEventListener('exif-location', onExifLocation);

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => setPos(pos.coords.latitude, pos.coords.longitude),
            () => setPos(DEFAULT_LAT, DEFAULT_LNG),
            { enableHighAccuracy: true, timeout: 8000 }
        );
    } else {
        setPos(DEFAULT_LAT, DEFAULT_LNG);
    }
};

// ── 事件綁定 ──────────────────────────────────────────────
/**
 * LIFF 自動登入：若在 LINE 內建瀏覽器，直接走 LIFF 授權，
 * 取得 access token 後轉到後端 LINE OAuth callback。
 * 若 LIFF SDK 未載入（外部瀏覽器）則略過，維持現有的 Google / LINE 網頁登入。
 */
async function initLiff() {
    const liffId = window.__LIFF_ID__;
    if (!liffId || typeof liff === 'undefined') return;

    // 已有 Laravel session → 不需要重新走 LIFF 登入流程
    if (window.__AUTHED__) return;

    try {
        await liff.init({ liffId });
    } catch (_) { return; }

    // 只在 LINE 內建瀏覽器中自動觸發登入
    if (!liff.isInClient()) {
        // 使用者在 LINE 內建瀏覽器直接輸入網址（非透過 liff.line.me）
        // → 重導至 LIFF URL，讓 LINE 完成 LIFF 初始化後才回來
        if (/Line\//i.test(navigator.userAgent)) {
            window.location.replace('https://liff.line.me/' + liffId);
        }
        return;
    }
    if (!liff.isLoggedIn()) {
        liff.login({ redirectUri: window.location.href });
        return;
    }

    // 已登入：把 LINE access token（及 id_token）帶到後端換 Laravel session
    const token   = liff.getAccessToken();
    const idToken = liff.getIDToken(); // 需要 openid scope 才有值，否則為 null
    const params  = new URLSearchParams(window.location.search);
    // 僅在尚未登入 Laravel 時才導向（避免無窮迴圈）
    if (!params.has('liff_authed')) {
        let callbackUrl = '/auth/line/liff-callback?access_token=' + encodeURIComponent(token);
        if (idToken) callbackUrl += '&id_token=' + encodeURIComponent(idToken);
        callbackUrl += '&redirect=' + encodeURIComponent(window.location.pathname);
        window.location.href = callbackUrl;
    }
}

document.addEventListener('livewire:initialized', () => {
    initLiff();
    initRadar();

    window.addEventListener('mouseradar:report-clicked', () => {
        window.Livewire.dispatch('open-report-form');
    });

    window.addEventListener('mouseradar:heat-updated', (e) => {
        if (heatLayer && Array.isArray(e.detail?.points)) {
            heatLayer.setLatLngs(e.detail.points);
        }
        if (radarMap && Array.isArray(e.detail?.markers)) {
            if (markersLayer) radarMap.removeLayer(markersLayer);
            if (clusterLayer) radarMap.removeLayer(clusterLayer);
            markersLayer = buildMarkersLayer(e.detail.markers);
            clusterLayer = buildClusterLayer(e.detail.markers);
            updateMarkersVisibility(radarMap.getZoom());
        }
        if (radarMap && Array.isArray(e.detail?.poisonMarkers)) {
            if (poisonLayer) radarMap.removeLayer(poisonLayer);
            poisonLayer = buildPoisonLayer(e.detail.poisonMarkers);
            poisonLayer.addTo(radarMap);
        }
    });
});


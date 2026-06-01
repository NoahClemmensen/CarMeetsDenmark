import { Controller } from '@hotwired/stimulus';
import { showToast } from '../utilities/toast.js';

/**
 * Drives the Hotspots page: a Leaflet map with a Leaflet.heat overlay plus the
 * "I'm here" ping button.
 *
 * Leaflet and Leaflet.heat are loaded as global scripts (window.L) from the
 * page template, so this controller does not import them.
 *
 * Heat tuning: each ping contributes a small intensity, so an isolated ping
 * reads green and only clusters of overlapping pings build up to red.
 */
const PING_INTENSITY = 0.5;
const HEAT_OPTIONS = {
    radius: 35,
    blur: 25,
    maxZoom: 12,
    max: 3.0,
    gradient: { 0.1: '#1bc298', 0.4: '#fdcc09', 0.8: '#be2e2b' },
};
const DENMARK_CENTER = [55.3, 10.3];
const DENMARK_ZOOM = 10;
const REFRESH_MS = 30000;

export default class extends Controller {
    static targets = ['map', 'button', 'ring', 'count'];
    static values = {
        pointsUrl: String,
        toggleUrl: String,
        csrf: String,
        active: Boolean,
    };

    connect() {
        if (typeof window.L === 'undefined') {
            console.error('[heatmap] Leaflet (window.L) is not loaded.');
            return;
        }

        this.map = window.L.map(this.mapTarget, { zoomControl: true }).setView(DENMARK_CENTER, DENMARK_ZOOM);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(this.map);

        this.heat = window.L.heatLayer([], HEAT_OPTIONS).addTo(this.map);

        // Leaflet mis-measures a container that was hidden/animating on first paint.
        setTimeout(() => this.map.invalidateSize(), 0);

        this.refresh();
        this.timer = window.setInterval(() => this.refresh(), REFRESH_MS);
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    activeValueChanged() {
        this.renderButton();
    }

    async refresh() {
        try {
            const response = await fetch(this.pointsUrlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            const points = (data.points || []).map(([lat, lng]) => [lat, lng, PING_INTENSITY]);
            if (this.heat) {
                this.heat.setLatLngs(points);
            }
            if (this.hasCountTarget) {
                this.countTarget.textContent = String(points.length);
            }
        } catch (e) {
            // Network hiccups are non-fatal — the next interval retries.
        }
    }

    async toggle() {
        if (this.activeValue) {
            await this.sendToggle();
            return;
        }

        this.setBusy(true);
        try {
            const position = await this.currentPosition();
            await this.sendToggle({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            });
            if (this.activeValue && this.map) {
                this.map.setView([position.coords.latitude, position.coords.longitude], 12);
            }
        } catch (e) {
            showToast('Giv adgang til din lokation for at lave en ping.', 'warning');
        } finally {
            this.setBusy(false);
        }
    }

    async sendToggle(coords = null) {
        this.setBusy(true);
        try {
            const body = new URLSearchParams();
            body.set('_token', this.csrfValue);
            if (coords) {
                body.set('lat', String(coords.lat));
                body.set('lng', String(coords.lng));
            }

            const response = await fetch(this.toggleUrlValue, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body,
            });

            if (response.status === 429) {
                showToast('Du har lavet for mange pings. Prøv igen senere.', 'warning');
                return;
            }
            if (!response.ok) {
                showToast('Noget gik galt. Prøv igen.', 'error');
                return;
            }

            const data = await response.json();
            this.activeValue = Boolean(data.active);
            this.refresh();
        } catch (e) {
            showToast('Noget gik galt. Prøv igen.', 'error');
        } finally {
            this.setBusy(false);
        }
    }

    currentPosition() {
        return new Promise((resolve, reject) => {
            if (!('geolocation' in navigator)) {
                reject(new Error('Geolocation unavailable'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
            });
        });
    }

    setBusy(busy) {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = busy;
        }
    }

    renderButton() {
        if (!this.hasButtonTarget) return;

        const base = 'relative inline-flex items-center gap-2 rounded-button px-5 py-3 font-semibold shadow-lg transition-colors disabled:opacity-60';

        if (this.activeValue) {
            this.buttonTarget.className = `${base} bg-gradient-to-r from-accent-d to-error text-white`;
            this.buttonTarget.innerHTML = `${this.dotIcon()}<span>Du er live</span><span class="opacity-80 font-normal">· Fjern</span>`;
            if (this.hasRingTarget) this.ringTarget.classList.remove('hidden');
        } else {
            this.buttonTarget.className = `${base} bg-primary-text text-white hover:bg-black`;
            this.buttonTarget.innerHTML = `${this.pinIcon()}<span>Jeg er her</span>`;
            if (this.hasRingTarget) this.ringTarget.classList.add('hidden');
        }
    }

    pinIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="4 3 12 14" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99875 4.55557C8.85482 4.55557 7.75775 5.00999 6.94887 5.81887C6.14 6.62775 5.68558 7.72482 5.68558 8.86874C5.68558 10.5359 6.77611 12.1847 8.0179 13.4955C8.6229 14.1341 9.23053 14.6576 9.68779 15.0217C9.80204 15.1127 9.90652 15.1934 9.99875 15.2632C10.091 15.1934 10.1954 15.1127 10.3097 15.0217C10.767 14.6576 11.3746 14.1341 11.9796 13.4955C13.2214 12.1847 14.3119 10.5359 14.3119 8.86874C14.3119 7.72482 13.8575 6.62775 13.0486 5.81887C12.2397 5.00999 11.1427 4.55557 9.99875 4.55557Z"/><path fill-rule="evenodd" clip-rule="evenodd" d="M9.9987 7.9495C9.49104 7.9495 9.0795 8.36104 9.0795 8.8687C9.0795 9.37636 9.49104 9.7879 9.9987 9.7879C10.5064 9.7879 10.9179 9.37636 10.9179 8.8687C10.9179 8.36104 10.5064 7.9495 9.9987 7.9495Z"/></svg>';
    }

    dotIcon() {
        return '<span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-white"></span></span>';
    }
}

import { Controller } from '@hotwired/stimulus';
import { showToast } from '../utilities/toast.js';

/**
 * Drives the Hotspots page: a Leaflet map with a Leaflet.heat overlay plus the
 * "Drop a pin" ping button.
 *
 * Leaflet and Leaflet.heat are loaded as global scripts (window.L) from the
 * page template, so this controller does not import them.
 *
 * Heat tuning: each ping contributes a small intensity, so an isolated ping
 * reads green and only clusters of overlapping pings build up to red.
 */
const PING_INTENSITY = 1;
const HEAT_OPTIONS = {
    radius: 35,
    blur: 30,
    maxZoom: 12,
    max: 3.0,
};
const DENMARK_CENTER = [55.3, 10.3];
const DENMARK_ZOOM = 10;
const REFRESH_MS = 30000;

export default class extends Controller {
    static targets = ['map', 'button', 'count'];
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

        // Start on the visitor's location (Denmark center stays as the fallback above).
        this.locateUser();

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

    // Recenter on the visitor's location once the browser resolves it. If they
    // deny the prompt or geolocation is unavailable, the Denmark default stands.
    async locateUser() {
        try {
            const position = await this.currentPosition();
            if (this.map) {
                this.map.setView([position.coords.latitude, position.coords.longitude], DENMARK_ZOOM);
            }
        } catch (e) {
            // Location denied/unavailable, keep the default Denmark view.
        }
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
            this.countTargets.forEach((el) => { el.textContent = String(points.length); });
        } catch (e) {
            // Network hiccups are non-fatal. The next interval retries.
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
            showToast('Allow location access to drop a pin.', 'warning');
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
                showToast('You\'ve dropped too many pins. Try again later.', 'warning');
                return;
            }
            if (!response.ok) {
                showToast('Something went wrong. Try again.', 'error');
                return;
            }

            const data = await response.json();
            this.activeValue = Boolean(data.active);
            this.refresh();
        } catch (e) {
            showToast('Something went wrong. Try again.', 'error');
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
        this.buttonTargets.forEach((btn) => { btn.disabled = busy; });
    }

    // Each button keeps its own layout/size classes from the template; this only
    // swaps the state colours and label, so the desktop FAB and the mobile bar
    // button can be sized independently.
    renderButton() {
        this.buttonTargets.forEach((btn) => {
            if (this.activeValue) {
                btn.classList.remove('bg-primary-text', 'hover:bg-black');
                btn.classList.add('bg-gradient-to-r', 'from-accent-d', 'to-error');
                btn.innerHTML = `${this.dotIcon()}<span>Pinged</span><span class="opacity-80 font-normal">· Remove</span>`;
            } else {
                btn.classList.remove('bg-gradient-to-r', 'from-accent-d', 'to-error');
                btn.classList.add('bg-primary-text', 'hover:bg-black');
                btn.innerHTML = `${this.pinIcon()}<span>Drop a pin</span>`;
            }
        });
    }

    pinIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="4 3 12 14" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99875 4.55557C8.85482 4.55557 7.75775 5.00999 6.94887 5.81887C6.14 6.62775 5.68558 7.72482 5.68558 8.86874C5.68558 10.5359 6.77611 12.1847 8.0179 13.4955C8.6229 14.1341 9.23053 14.6576 9.68779 15.0217C9.80204 15.1127 9.90652 15.1934 9.99875 15.2632C10.091 15.1934 10.1954 15.1127 10.3097 15.0217C10.767 14.6576 11.3746 14.1341 11.9796 13.4955C13.2214 12.1847 14.3119 10.5359 14.3119 8.86874C14.3119 7.72482 13.8575 6.62775 13.0486 5.81887C12.2397 5.00999 11.1427 4.55557 9.99875 4.55557Z"/><path fill-rule="evenodd" clip-rule="evenodd" d="M9.9987 7.9495C9.49104 7.9495 9.0795 8.36104 9.0795 8.8687C9.0795 9.37636 9.49104 9.7879 9.9987 9.7879C10.5064 9.7879 10.9179 9.37636 10.9179 8.8687C10.9179 8.36104 10.5064 7.9495 9.9987 7.9495Z"/></svg>';
    }

    dotIcon() {
        return '<span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-white"></span></span>';
    }
}

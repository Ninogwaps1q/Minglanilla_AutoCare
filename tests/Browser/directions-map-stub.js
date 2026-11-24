export async function installDirectionsMapStub(page, routeResult = 'success') {
    await page.addInitScript(result => {
        class Map {
            constructor(element, options) { this.element = element; this.element.dataset.testMapZoom = String(options.zoom); }
            addListener() {}
            fitBounds(bounds) { if (bounds.testArea) this.element.dataset.testMapArea = bounds.testArea; }
            getZoom() { return 15; }
            setZoom() {}
        }

        class Marker {
            constructor(options) {
                this.map = options.map;
                this.listeners = {};
                const image = document.createElement('img');
                image.className = 'test-google-marker-icon';
                image.src = typeof options.icon === 'string' ? options.icon : (options.icon?.url || 'https://maps.google.com/mapfiles/ms/icons/red-dot.png');
                image.title = options.title;
                image.addEventListener('click', () => this.listeners.click?.());
                this.map.element.append(image);
            }
            addListener(event, callback) { this.listeners[event] = callback; }
            setMap() {}
        }

        class InfoWindow {
            setContent(content) { this.content = content; }
            open({ map }) {
                const popup = document.createElement('div');
                popup.className = 'gm-style-iw';
                popup.append(this.content);
                map.element.append(popup);
            }
        }

        class LatLngBounds {
            extend() {}
        }

        class Route {
            static async computeRoutes() {
                if (result === 'error') throw new Error('Routes API unavailable');
                if (result === 'empty') return { routes: [] };

                return { routes: [{
                    distanceMeters: 2500,
                    durationMillis: 360000,
                    path: [{ lat: 6.7493, lng: 125.3552 }, { lat: 6.741, lng: 125.351 }],
                    legs: [{ steps: [{ instructions: 'Turn right onto Test Road', localizedValues: { distance: '500 m' } }] }],
                    createPolylines: () => [{ setMap() {} }],
                }] };
            }
        }

        class Geocoder {
            async geocode({ address }) {
                return { results: [{ geometry: { location: { lat: 6.7493, lng: 125.3552 }, viewport: { testArea: address } } }] };
            }
        }

        window.google = { maps: {
            Map,
            Marker,
            InfoWindow,
            LatLngBounds,
            event: { addListenerOnce() {}, trigger: (marker, event) => marker.listeners[event]?.() },
            importLibrary: async () => ({ Route, Geocoder }),
        } };
    }, routeResult);
}

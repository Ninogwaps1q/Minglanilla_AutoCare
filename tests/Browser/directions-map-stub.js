export async function installDirectionsMapStub(page, routeResult = 'success') {
    await page.addInitScript(result => {
        class Map {
            constructor(element) { this.element = element; }
            fitBounds() {}
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
                    path: [{ lat: 10.2443, lng: 123.7898 }, { lat: 10.241, lng: 123.781 }],
                    legs: [{ steps: [{ instructions: 'Turn right onto Test Road', localizedValues: { distance: '500 m' } }] }],
                    createPolylines: () => [{ setMap() {} }],
                }] };
            }
        }

        window.google = { maps: {
            Map,
            Marker,
            InfoWindow,
            LatLngBounds,
            event: { addListenerOnce() {}, trigger: (marker, event) => marker.listeners[event]?.() },
            importLibrary: async () => ({ Route }),
        } };
    }, routeResult);
}

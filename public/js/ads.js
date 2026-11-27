/**
 * Tracking de impresiones de publicidad (REQ-IMP-01 / REQ-IMP-07).
 *
 * Una impresion cuenta cuando el anuncio esta al menos 50% dentro del viewport
 * durante 1000 ms continuos. Se dispara una sola vez por creatividad y carga de
 * pagina. Sin IntersectionObserver (o sin JS) no se cuenta nada.
 *
 * El endpoint se toma del atributo data-endpoint del propio <script>; el uuid
 * viaja en el body, nunca se lee la cookie desde /api.
 */
(function () {
    'use strict';

    var THRESHOLD = 0.5;
    var DWELL_MS = 1000;
    var DEFAULT_ENDPOINT = '/api/ads/impression';

    function resolveEndpoint() {
        var script = document.currentScript;
        if (script && script.getAttribute('data-endpoint')) {
            return script.getAttribute('data-endpoint');
        }
        return DEFAULT_ENDPOINT;
    }

    function sendImpression(url, payload) {
        var body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) {
                    return;
                }
            } catch (e) {
                // Cae al fetch keepalive.
            }
        }

        if (window.fetch) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                keepalive: true,
                credentials: 'omit'
            }).catch(function () {});
        }
    }

    function init() {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        var endpoint = resolveEndpoint();
        var timers = {};
        var sent = {};

        function identityOf(element) {
            return element.getAttribute('data-campaign-key') + '|' +
                element.getAttribute('data-placement');
        }

        function clearTimer(identity) {
            if (timers[identity]) {
                window.clearTimeout(timers[identity]);
                timers[identity] = null;
            }
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var element = entry.target;
                var identity = identityOf(element);

                if (sent[identity]) {
                    observer.unobserve(element);
                    return;
                }

                if (entry.intersectionRatio >= THRESHOLD) {
                    if (!timers[identity]) {
                        timers[identity] = window.setTimeout(function () {
                            timers[identity] = null;

                            if (sent[identity]) {
                                return;
                            }
                            sent[identity] = true;

                            var payload = {
                                creative: element.getAttribute('data-campaign-key'),
                                placement: element.getAttribute('data-placement')
                            };
                            var uuid = element.getAttribute('data-uuid');

                            if (uuid) {
                                payload.uuid = uuid;
                            }

                            sendImpression(endpoint, payload);
                            observer.unobserve(element);
                        }, DWELL_MS);
                    }
                } else {
                    clearTimer(identity);
                }
            });
        }, { threshold: [THRESHOLD] });

        var elements = document.querySelectorAll(
            '[data-ad-slot] [data-campaign-key][data-placement]'
        );

        Array.prototype.forEach.call(elements, function (element) {
            observer.observe(element);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

{{--
    Scan box: handheld scanners type the tracking number and press Enter. The key makes a double
    submit count once. On a phone, "Camera" reads the waybill's barcode or QR code in the browser
    and submits it; the camera stays on across scans until closed. Needs HTTPS (or localhost).
--}}
@php($inputId = $id ?? 'tracking_no')
<form method="POST" action="{{ $action }}" class="pr-scan" autocomplete="off" data-scan-form>
    @csrf
    @isset($scanKey)<input type="hidden" name="scan_key" value="{{ $scanKey }}">@endisset
    <label for="{{ $inputId }}" class="pr-scan-label">{{ $label ?? 'Scan or type a tracking number' }}</label>
    <div class="pr-scan-row">
        <input id="{{ $inputId }}" name="tracking_no" class="pr-input pr-scan-input" placeholder="PR…" required autofocus inputmode="text" spellcheck="false">
        <button type="button" class="pr-btn pr-btn-outline" data-scan-camera-open aria-controls="{{ $inputId }}-camera-panel">📷 Camera</button>
        <button type="submit" class="pr-btn pr-btn-primary">{{ $button ?? 'Scan' }}</button>
    </div>
    <div class="pr-scan-camera" id="{{ $inputId }}-camera-panel" data-scan-camera-panel hidden>
        <div id="{{ $inputId }}-camera" class="pr-scan-video"></div>
        <div class="pr-scan-camera-bar">
            <span data-scan-camera-status>Starting camera…</span>
            <span style="display:flex;gap:.4rem">
                <button type="button" class="pr-btn pr-btn-outline pr-btn-sm" data-scan-torch hidden>Flash</button>
                <button type="button" class="pr-btn pr-btn-outline pr-btn-sm" data-scan-camera-close>Close camera</button>
            </span>
        </div>
    </div>
</form>

@once
<style>
    .pr-scan-camera { margin-top: .75rem; }
    .pr-scan-video { width: 100%; max-width: 480px; margin: 0 auto; border-radius: 10px; overflow: hidden; background: #111; }
    .pr-scan-video video { width: 100% !important; display: block; }
    .pr-scan-camera-bar { display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: .5rem; font-size: .9rem; }
    .pr-scan-camera-bar [data-scan-camera-status].is-ok { color: var(--pr-ok); font-weight: 700; }
    .pr-scan-camera-bar [data-scan-camera-status].is-bad { color: var(--pr-bad); font-weight: 700; }
    @media (max-width: 600px) {
        .pr-scan-row { flex-wrap: wrap; }
        .pr-scan-input { flex-basis: 100%; }
        .pr-scan-row .pr-btn { flex: 1; }
    }
</style>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const KEEP_ON = 'pickrush-scan-camera:' + location.pathname;   // reopen the camera after each scan reloads this page
    const LAST = 'pickrush-scan-last';         // the code just sent, ignored while it is still in view

    document.querySelectorAll('[data-scan-form]').forEach((form) => {
        const input = form.querySelector('input[name="tracking_no"]');
        const panel = form.querySelector('[data-scan-camera-panel]');
        const status = form.querySelector('[data-scan-camera-status]');
        const torch = form.querySelector('[data-scan-torch]');
        const videoId = form.querySelector('.pr-scan-video').id;
        let scanner = null;
        let torchOn = false;

        const say = (text, tone) => {
            status.textContent = text;
            status.className = tone ? 'is-' + tone : '';
        };

        const beep = () => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                osc.frequency.value = 1200;
                osc.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch (e) { /* no sound is fine */ }
            if (navigator.vibrate) navigator.vibrate(80);
        };

        const onCode = (raw) => {
            const code = raw.replace(/\s+/g, '').toUpperCase();
            if (!code) return;
            try {
                const last = JSON.parse(sessionStorage.getItem(LAST) || 'null');
                if (last && last.code === code && Date.now() - last.at < 6000) return;   // same waybill still in front of the camera
                sessionStorage.setItem(LAST, JSON.stringify({ code, at: Date.now() }));
            } catch (e) { /* storage blocked: submit anyway */ }
            beep();
            say('Got ' + code + ' — sending…', 'ok');
            input.value = code;
            scanner.pause(true);
            form.requestSubmit ? form.requestSubmit() : form.submit();
        };

        const stop = async () => {
            try { sessionStorage.removeItem(KEEP_ON); } catch (e) {}
            if (scanner) {
                try { await scanner.stop(); } catch (e) {}
                scanner.clear();
                scanner = null;
            }
            panel.hidden = true;
            torch.hidden = true;
            input.focus();
        };

        // Plain-language reason a camera could not open, and what to do about it.
        const explain = async (err) => {
            const name = err?.name || String(err);
            let state = null;
            try { state = (await navigator.permissions.query({ name: 'camera' })).state; } catch (e) {}
            const iPhone = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (/NotAllowed|PermissionDenied|Permission/i.test(name + String(err))) {
                if (state === 'denied') {
                    return 'Camera is blocked for this site. Tap the lock icon (left of the address) → Permissions → Camera → Allow, reload, then tap Camera.';
                }
                return iPhone
                    ? 'Your iPhone is blocking the camera for Safari. Open Settings → Safari → Camera → Allow, then tap Camera again.'
                    : 'Your phone is blocking the camera for the browser app. Open phone Settings → Apps → Chrome → Permissions → Camera → Allow, then tap Camera again.';
            }
            if (/NotReadable|TrackStart|AbortError/i.test(name)) {
                return 'The camera is busy in another app. Close other camera or video apps, then tap Camera again.';
            }
            if (/NotFound|Overconstrained|DevicesNotFound/i.test(name)) {
                return 'No usable camera was found on this device. Type the number instead.';
            }
            return 'Could not open the camera (' + name + '). Tap Camera to try again, or type the number.';
        };

        const config = { fps: 12, qrbox: (w, h) => ({ width: Math.floor(w * 0.85), height: Math.floor(Math.min(h * 0.6, w * 0.6)) }) };

        const start = async () => {
            panel.hidden = false;
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                return say('The camera only works over HTTPS. Open the portal with https://', 'bad');
            }
            if (typeof Html5Qrcode === 'undefined') {
                return say('Could not load the scanner. Check the connection, or type the number.', 'bad');
            }
            if (scanner) return;
            say('Allow the camera when your browser asks…');

            // 1. Ask for the camera on its own first, with a soft "back camera" hint, so the
            //    browser shows its prompt and every phone can say yes (no strict constraints).
            try {
                const probe = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                probe.getTracks().forEach((t) => t.stop());
            } catch (err) {
                try { sessionStorage.removeItem(KEEP_ON); } catch (e) {}
                return say(await explain(err), 'bad');
            }

            // 2. Start the scanner: back camera by hint, else the back camera picked by name.
            say('Starting camera…');
            const F = Html5QrcodeSupportedFormats;
            const fresh = () => new Html5Qrcode(videoId, {
                formatsToSupport: [F.QR_CODE, F.CODE_128, F.CODE_39],
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                verbose: false,
            });
            scanner = fresh();
            try {
                await scanner.start({ facingMode: 'environment' }, config, onCode, () => {});
            } catch (first) {
                try { scanner.clear(); } catch (e) {}
                scanner = fresh();
                try {
                    const cameras = await Html5Qrcode.getCameras();
                    if (!cameras.length) throw first;
                    const back = cameras.find((c) => /back|rear|environment|main/i.test(c.label)) || cameras[cameras.length - 1];
                    await scanner.start(back.id, config, onCode, () => {});
                } catch (err) {
                    try { scanner.clear(); } catch (e) {}
                    scanner = null;
                    try { sessionStorage.removeItem(KEEP_ON); } catch (e) {}
                    return say(await explain(err), 'bad');
                }
            }

            try { sessionStorage.setItem(KEEP_ON, '1'); } catch (e) {}
            say('Point the camera at the waybill barcode or QR code.');
            try {
                torch.hidden = !scanner.getRunningTrackCameraCapabilities().torchFeature().isSupported();
            } catch (e) { torch.hidden = true; }
        };

        form.querySelector('[data-scan-camera-open]').addEventListener('click', start);
        form.querySelector('[data-scan-camera-close]').addEventListener('click', stop);
        torch.addEventListener('click', async () => {
            try {
                torchOn = !torchOn;
                await scanner.getRunningTrackCameraCapabilities().torchFeature().apply(torchOn);
            } catch (e) { torch.hidden = true; }
        });

        let keepOn = false;
        try { keepOn = sessionStorage.getItem(KEEP_ON) === '1'; } catch (e) {}
        if (keepOn) {
            // The scan just sent was refused (unknown, wrong station…): buzz, like the rider app.
            if (document.querySelector('.pr-alert-bad') && navigator.vibrate) navigator.vibrate([180, 90, 260]);
            input.blur();   // camera mode: don't pop the phone keyboard after every scan
            start();
        }
    });
});
</script>
@endonce

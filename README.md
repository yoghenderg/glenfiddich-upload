# YG event media frontend prototype

Laravel 10, Blade, Vite, vanilla JavaScript and custom SCSS. The prototype includes tablet upload and gallery screens in both orientations, a gallery preview/download dialog, and a streamlined mobile page for QR guests. The supplied Aston Martin Formula One Team × Glenfiddich logo appears in the shared header. The primary page colour is `#033037`, and Montserrat is bundled locally.

## Run locally

Use PHP 8.1 and Node 21 for the handoff environment. The Composer lockfile resolves as PHP 8.1 and the production assets were built successfully with Node 21. The available local PHP runtime is 8.5; final PHP 8.1 runtime validation remains Carlo's step.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open `/upload`, `/gallery`, and `/media/moment-001`. Use the host machine's LAN IP rather than `localhost` when scanning a QR from a phone; both devices must reach the same server. Set `APP_URL` to that reachable origin in `.env` when deploying to the event network. Serve over HTTPS for native file sharing where required by the browser.

## GitHub handoff

Commit the Laravel project files at the repository root so Carlo sees `app`, `resources`, `routes`, `composer.json`, `package.json` and this README directly. Do not commit `.env`, `vendor`, `node_modules`, generated `public/build`, temporary storage files, the ZIP handoff archive or screenshot previews. The included `.gitignore` covers the usual local artifacts when using Git or GitHub Desktop. After cloning, run the setup commands above to install dependencies and build assets. A GitHub repository stores the code; it does not run the Laravel site by itself.

## What is interactive today

- Upload: drag/drop or file picker, image/video preview, type and 200 MB size checks, choose another file, 1 MiB sequential chunk coordination, progress, cancellation, retry and completion through a mock adapter. **It does not save or publish files.** This keeps the prototype on dummy data until the real upload endpoint is connected.
- Gallery: three static sample records, date labels in `dd-mm-yyyy` with smaller 12-hour times on the right, a None/Today/Yesterday date selector, image and playable sample video, detail viewer, usable QR to the guest route, download, native Share/AirDrop sheet on supporting devices and copy-link fallback. The page checks `/gallery/feed?sort=…` every 20 seconds, shows a reconnecting indicator if it fails, and reloads when item IDs change.
- Guest: shared partner logo, one image or video, and one download action. This is the destination encoded in each gallery QR.
- State previews: `/gallery?state=empty`, `loading`, `error`, or `reconnecting`.

The demo photographs are generated fictional samples; the short sample MP4 is a still-image clip. They are not real event uploads.

## Structure and Carlo's integration points

| Area | Current source | Replace with |
| --- | --- | --- |
| Records | `app/Support/DemoMedia.php` | Media model/repository queried for the active event; keep the Blade-facing keys `id`, `title`, `filename`, `type`, `src`, `poster`, `alt`, `date` (ISO day), `date_display` (`dd-mm-yyyy`), `captured_at` (ISO timestamp) and `time_display` (`hh:mm AM/PM`). The gallery's Today/Yesterday selection currently filters by `date`; `none` shows all records. Generate the date and time in the event's timezone. |
| Routes/controller | `routes/web.php`, `app/Http/Controllers/DemoMediaController.php` | Event-scoped upload, gallery/feed and signed/unguessable guest media routes. Add access policy, storage URLs and pagination as needed. |
| Upload | `resources/js/uploads/chunked-upload.js` and `transports.js` | Implement the same-origin local-disk chunk API in [the chunked upload handoff](docs/CHUNKED-UPLOAD.md), then set `MEDIA_UPLOAD_ENDPOINT`. The default mock adapter saves nothing. No AWS or upload package is required. |
| Real time | `DemoMediaController::feed()` and `initGallery()` | Broadcast media-created/media-updated events through Echo/WebSockets/SSE, or retain polling. Refresh the cards and status from the authoritative event feed. Current `LIVE` means the mock feed responds, not an event socket connection. |
| QR | `initGallery()` and `route('media.guest', ...)` | Point to the production guest route. The generated QR encodes the current route URL. Require a device-reachable HTTPS host. |
| Media actions | `resources/views/pages/guest.blade.php`, gallery viewer | Return downloadable media with a stable filename and correct content type. If files move to another origin, ensure CORS allows Web Share file fetching or let the URL fallback handle it. |

The SCSS system is under `resources/scss`: `abstracts` holds tokens/mixins, `base` sets typography, defaults and reduced-motion-aware effects, `helpers` holds small reusable utilities, `components` holds shared controls, and `pages` scopes the three experiences. `resources/views/layouts/app.blade.php` owns the shared shell; Blade components/partials handle icons, media cards and media display.

## Validation and runtime note

Run `php artisan test`, `node --test tests/JavaScript/*.test.js` and `npm run build` after changes. The Composer lockfile resolves as PHP 8.1, though final runtime validation on PHP 8.1 remains for Carlo. Laravel 10 is the requested framework version, but `composer audit` reports three advisories affecting Laravel 10.50.3, including one rated high. `composer.json` permits installing this prototype by setting `policy.advisories.block` to `false`; review those advisories and upgrade or patch before a public deployment. The current upload uses the mock chunk adapter, and the polling feed only reports seeded records. The real HTTP adapter is ready for Carlo's receiver; it is not enabled until `MEDIA_UPLOAD_ENDPOINT` is configured.

## Responsive and touch behavior

The upload card starts directly below navigation, aligned with the gallery content area. Tablet/desktop grids use four compact columns; the three seeded cards retain that size and are centered. The viewer groups and centers its QR and actions. Hover effects are limited to fine mouse pointers, controls activate on a tap, and motion respects reduced-motion preferences. The AirDrop glyph is drawn inside its viewBox. File preparation happens before the share tap; actual AirDrop delivery still requires device testing.

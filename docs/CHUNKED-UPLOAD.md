# Direct chunked upload handoff for Carlo

## Current mode

This project remains a **frontend prototype**. With `MEDIA_UPLOAD_ENDPOINT` unset, `demoTransport()` simulates acknowledgement of each actual `File.slice()` chunk. Nothing is transmitted, stored, or published. The UI says “Preview complete — demo only.” No Laravel upload receiver is included in this frontend handoff.

`resources/js/uploads/chunked-upload.js` is the shared upload coordinator. `transports.js` contains both the demo adapter and the real, same-origin HTTP adapter. Set `MEDIA_UPLOAD_ENDPOINT=/uploads` only after implementing the following routes. No AWS, cloud storage SDK, or third-party upload package is used.

## Transport behavior

- Default chunk: **1 MiB** (1,048,576 bytes); only one request in flight.
- Maximum media size: **200 MiB**. The UI rejects empty and unsupported files.
- Multipart requests carry one binary slice, never the entire original file.
- Each request times out after 45 seconds. Network/timeout, HTTP 408/429 and 5xx errors retry up to three times with 1/2/4-second backoff.
- Non-transient validation/auth errors stop immediately. A 419 prompts a refresh.
- Retrying in the same open page checks the server's `next_index`, then continues there. Reload/resume across browser restarts is not implemented: the user would need to reselect the file and a securely validated resume token.
- Progress reflects bytes sent, capped at 99% until final assembly succeeds. A retry may briefly reduce the displayed percentage because unacknowledged bytes are resent.
- Cancel aborts the active request and attempts `DELETE`. Expiry cleanup must also handle browser closure or failed cancellation.
- Chunking bounds request size and makes failures recoverable; it does not guarantee faster uploads or eliminate server load. Tune limits against the actual event network/server.

## Required Laravel API

All routes must use the session/CSRF middleware, JSON responses, an authenticated event operator policy, and rate/concurrency limits. Bind each opaque upload ID to the current operator and event. IDs and filenames must never become unchecked filesystem paths.

| Request | Body | Successful response |
| --- | --- | --- |
| `POST /uploads` | JSON: `filename`, `size`, `mime`, `chunk_size`, `idempotency_key` | `{ "id": "opaque-id", "chunk_size": 1048576 }` |
| `GET /uploads/{id}` | — | `{ "next_index": 0, "complete": false }` |
| `POST /uploads/{id}/chunks/{index}` | Multipart `chunk` (`chunk.bin`), zero-based index | `{ "next_index": 1 }` |
| `POST /uploads/{id}/complete` | JSON `{}` | `{ "complete": true, "media_id": "...", "guest_url": "..." }` |
| `DELETE /uploads/{id}` | — | `{}` or 204 |

For an already completed upload, status may return `{ "complete": true, "media_id": "..." }`. Errors return `{ "message": "Readable explanation" }` and an appropriate HTTP status.

### Receiver requirements

1. Validate total size, allowed extension/MIME, chunk size and expected count at session creation. Enforce per-user/event disk quota and upload-session limits. Return the same session for a retried `idempotency_key` belonging to that operator, rather than creating duplicates.
2. Store chunks outside `public`, using Laravel's built-in local filesystem or PHP streams. Use generated names. Accept only the expected next index or an identical already accepted chunk; validate byte length, file hash and total bytes. Lock writes per upload and use atomic replacement to prevent duplicate/concurrent requests corrupting a file.
3. Persist acknowledged position after each chunk. An interrupted request must never advance the position. Duplicate chunk requests must be idempotent; reject a duplicate with different bytes.
4. At completion, check every chunk exists and stream them into a temporary assembled file in order. Do not read a 200 MB file into PHP memory. Verify final length and actual MIME/content; derive a safe extension. Atomically publish only the validated result. Completion must be idempotent if the browser retries after losing the response.
5. Create the event media record, generate the public/signed guest URL and poster/thumbnail (including video poster), and emit the gallery event only after final validation. Replace `DemoMedia` with that event-scoped repository. The current gallery still contains only the 32 mock records reusing three sample assets.
6. Delete temporary chunks after completion/cancellation. Schedule cleanup of expired abandoned sessions, using the same lock so cleanup cannot delete an active request. Keep completed media according to the event retention policy.
7. Configure PHP `upload_max_filesize`, `post_max_size` and the web-server body limit above one chunk plus multipart overhead (for example 2 MiB / 3 MiB / 3 MiB for 1 MiB chunks). Final assembly still needs a bounded execution time and adequate disk space. Larger deployments may assemble in a queue and extend the completion contract with a processing state.

The frontend sends `X-CSRF-TOKEN` from the Blade layout and uses same-origin cookies. No authentication token is embedded in JavaScript. Do not expose an unauthenticated receiver on a public event deployment.

## Sharing / AirDrop

`resources/js/share.js` prepares a shareable file while the viewer opens, limited to 32 MiB. The tap calls `navigator.share()` immediately, without awaiting a network download, to preserve Safari user activation. If the file is not ready, too large, or unsupported, it shares the guest URL. Unsupported browsers use copy-link fallback. Dismissing the sheet is silent.

Use a device-reachable HTTPS origin at the event (localhost is only useful on the host itself). The OS chooses available share targets; a website cannot force AirDrop or confirm delivery to another Apple device. Test on the actual iPad/Safari and another Apple device with AirDrop enabled before the event. See [Web Share requirements](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/share).

## Checks

`node --test tests/JavaScript/*.test.js` verifies chunk boundaries, resume position, retry, cancellation, validation errors and immediate native-share invocation. These adapter tests do not certify a backend receiver. After connecting it, test interrupted chunks, duplicate requests, concurrent sessions, invalid files, quota limits, cancellation, expiry cleanup, maximum-size media, and a full gallery/QR download on the event network.

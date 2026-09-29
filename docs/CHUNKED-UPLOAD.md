# Chunked uploads

The upload page uses the Laravel `/uploads` API with staff authentication and CSRF protection. Files up to 200 MiB are sent sequentially in 1 MiB chunks. The client retries transient errors and resumes from the last accepted chunk while the page remains open. Cancel removes unfinished chunks. Completed files are stored on the private local disk, with metadata in `media` and a public UUID guest link.

Routes: POST `/uploads` creates an idempotent session; GET `/uploads/{id}` returns `next_index` and `complete`; POST `/uploads/{id}/chunks/{index}` accepts a multipart `chunk`; POST `/uploads/{id}/complete` validates and publishes the assembled file; DELETE `/uploads/{id}` cancels an unfinished upload. Completion returns `complete`, `next_index`, and the guest `url`.

Each session belongs to its staff uploader. Database row locks serialize writes. Duplicate chunks must match their stored bytes. Validation checks actual MIME, extension, image dimensions, and total length before publishing. Video codecs are not transcoded or fully decoded by the server; browser playback depends on codec support. Gallery polling sees new uploads every 20 seconds, including from an empty gallery.

Run migrations before use. Configure request size limits above one chunk plus multipart overhead: PHP `upload_max_filesize=2M`, `post_max_size=3M`, and web-server body limit at least 3 MiB. Schedule `php artisan schedule:run` each minute for hourly cleanup of abandoned sessions older than 24 hours. Completed media is retained. Use a phone-reachable HTTPS origin for guest QR links and native sharing.

Validation: `php artisan test`, `node --test tests/JavaScript/*.test.js`, and `npm run build`. Browser-restart resume and automatic video transcoding are not implemented.

# AgriSense

Smart Agriculture Monitoring & AI Assistance, built with Laravel 13, Blade, Tailwind, Alpine.js, Chart.js, Lucide, Supabase PostgreSQL/Storage/Realtime, pgvector, and Gemini. React source left from the previous prototype is not used by Vite or the application.

## Run locally

Requirements: PHP 8.3+, Composer, Node.js 22.12+ (this workspace uses PHP 8.5 and Node 24), and a Supabase project. Enable PHP extensions openssl, mbstring, fileinfo, curl, pdo_pgsql and zip. PHPUnit also needs pdo_sqlite for its isolated in-memory test database. Match these extensions in both CLI and Apache PHP.

From the project root:

```sh
composer install
npm install --ignore-scripts
```

Copy `.env.example` to `.env` only for a new installation; do not overwrite existing credentials. Set:

```dotenv
APP_NAME=AgriSense
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=your-project-database-host
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=your-supabase-database-user
DB_PASSWORD=
DB_SSLMODE=require
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
GEMINI_EMBEDDING_MODEL=gemini-embedding-001
SUPABASE_URL=
SUPABASE_SERVICE_ROLE_KEY=
SUPABASE_ANON_KEY=
SUPABASE_REALTIME_ENABLED=false
SUPABASE_JWT_SECRET=
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1300
SESSION_DRIVER=database
SESSION_ENCRYPT=true
```

Copy the actual host, port and username from Supabase's Connect dialog; a pooler username may include the project reference. Use the direct or session-pooler connection for migrations. Enter keys and passwords only in the private `.env`. Never prefix private keys with `VITE_`.

```sh
php artisan key:generate
php artisan config:clear
php artisan migrate
npm run build
php artisan serve
```

Generate APP_KEY only on first installation. Replacing an existing key invalidates encrypted data and cookies. In a separate terminal, run the document worker:

```sh
php artisan queue:work --timeout=1200 --tries=1
```

The database queue retry interval must exceed the worker timeout. Configure your process manager to restart the worker. A stopped worker leaves documents visibly queued.

**Current workspace:** application code and isolated tests are implemented. The existing private database configuration points to local PostgreSQL and its migrations are pending. Supabase credentials must be entered before cloud migration and external integration verification. No cloud deployment or real hardware verification has been performed.

### Windows PHP extension troubleshooting

If PHP is installed but its CLI extensions are disabled, enable them in the loaded php.ini shown by `php --ini`. Until then, process-specific flags work without changing system PHP:

```powershell
php -d extension=openssl -d extension=mbstring -d extension=fileinfo -d extension=curl artisan serve
php -d extension=openssl -d extension=mbstring -d extension=fileinfo -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
node node_modules/vite/bin/vite.js build
```

Prefer enabling extensions in php.ini for normal Artisan child processes and Apache. Set Apache DocumentRoot to this project's `public` directory and enable rewrite support. Do not serve the repository root or `.env`. Production requires HTTPS, `APP_DEBUG=false`, secure session cookies, a real mail transport, and a persistent queue worker. Laravel's default log mailer does not deliver password reset email.

## Supabase setup

1. Enable the vector extension in Supabase. The migration creates it if necessary and adds a 768-dimensional vector column with an HNSW cosine index. Both `public` and `extensions` are on the configured search path.
2. Run the Laravel migrations against the selected Supabase project.
3. Run [database/supabase-setup.sql](database/supabase-setup.sql) in that project's SQL editor. It creates four **private** storage buckets, restricts browser roles, and installs a private reading-invalidation broadcast trigger.
4. Keep the Laravel database connection and service-role key on the server. Browser requests go through Laravel sessions, validation and ownership policies. Laravel sessions are separate from Supabase Auth; do not assume `auth.uid()` is the Laravel numeric user ID.
5. For private Realtime, configure the project’s legacy HS256 JWT signing secret and public anon key, disable “Allow public access” in Realtime settings, and set `SUPABASE_REALTIME_ENABLED=true`. If your project does not support that signing secret, leave this option disabled; monitoring continues with authenticated polling every 30 seconds.
6. The backend issues short-lived Realtime tokens containing a signed Laravel user ID. RLS permits only that user’s `farm:{id}` topic. The broadcast contains only `changed: true`; private readings are reloaded through Laravel.
7. Do not add broad policies granting browser roles access to application tables or these storage buckets. The server connection owns/bypasses RLS and is protected by Laravel authorization. On an existing shared Supabase project, review other Realtime and Storage policies: permissive policies combine with OR.
8. Uploaded object keys are random, owner-prefixed paths. Authorized downloads use signed URLs valid for five minutes. Deleting records removes retrieval access; unreferenced storage objects should be cleaned up using your retention process.

The app creates no fictitious “verified” sources, default agronomic ranges, shared account passwords, or simulated live readings.

## First farm and administrator

Register a real account at `/register`. New accounts receive the farmer role and a profile. Grant the first administrator from your trusted terminal:

```sh
php artisan agrisense:admin your-registered-email@example.com
```

Administrators can manage account roles, crops, devices, alerts, source verification, document processing, conversations, audit records and the farm notice. Accounts cannot change their own role through the admin form.

Add a crop, then register a device and save the one-time token. Online status comes from actual authenticated device contact. Disabling/disconnecting revokes access while retaining historical readings.

## ESP32 API and firmware

`POST /api/device/readings`

Headers: `Content-Type: application/json`, `Accept: application/json`, and `Authorization: Bearer YOUR_DEVICE_TOKEN`.

```json
{
  "device_id": "DEVICE-001",
  "reading_id": "55c2bb2d-cf34-497c-8543-49aacce6536b",
  "reading_at": "2026-09-14T08:00:00Z",
  "temperature": 28.4,
  "humidity": 68.2,
  "soil_moisture": 42.5,
  "soil_ph": 6.4,
  "light_intensity": 720
}
```

Use the actual observation time (UTC) and a new UUID per observation. Retrying the same observation uses the same UUID. The endpoint returns 201 for new readings, 200 for identical retries, 401 for authentication failure, 409 for an inactive/unassigned/mismatched device or conflicting replay, 422 for validation errors, and 429 for throttling. Readings may be at most 30 days old and must not be in the future. At least one sensor value is required; omitted measurements remain null.

[database/esp32/AgriSense.ino](database/esp32/AgriSense.ino) is an ESP32 Arduino example with a real DHT22 temperature/humidity driver, TLS certificate verification, a durable LittleFS queue, UUIDs, UTC timestamps, retries, and rejected-record retention. Copy it into an Arduino sketch directory named AgriSense, alongside a private `config.h` copied from [config.h.example](database/esp32/config.h.example). Install the ESP32 board support, ArduinoJson 7, Adafruit DHT sensor library and Adafruit Unified Sensor. Initialize a LittleFS partition before first use; the firmware deliberately does not automatically format existing queued data.

Configure the Wi-Fi, endpoint, one-time token, DHT wiring and trusted root certificate. Other sensors require their actual calibrated hardware drivers; raw ADC voltage is not reported as a fabricated moisture percentage, pH or lux value. No valid clock means no observation timestamp is invented. The example supports a maximum 2,000 pending readings; full storage is reported on Serial. Hardware compilation, calibration and disconnect/reconnect behavior still require checking on your selected board.

## Sensor ranges and alerts

Crop ranges reference a verified active source and a supporting page/section. A range is scoped to a crop record, its variety and its growth stage. Missing limits remain unbounded. Changing stage requires a matching range. A range never transfers automatically from tomato to corn.

Fresh, latest readings trigger alerts; historical queued data does not generate current alerts. Repeated out-of-range readings update the existing active device/sensor alert. A subsequent in-range reading resolves it. Manual crop condition is labeled as the user's assessment, independently from measured sensor conditions.

## Evidence and AI workflow

Register an authoritative source, review the original URL and authority, verify/activate it, upload a searchable PDF or text file, inspect extracted text, and process embeddings. Documents are limited to 5 MB and 250,000 extracted characters; scanned PDFs need OCR outside the app. Embedding work runs on the database queue. Failures remain visible and can be retried.

Retrieval filters verified + active + unexpired sources, processed documents, matching crop/general references and the configured embedding model. PostgreSQL performs cosine similarity search over pgvector. An optional service-level topic filter is supported. Test-only SQLite computes similarity in memory and is never the production database.

The Gemini service receives the question, retrieved chunks, selected crop, sensor observation time/staleness and optional image. It requests structured sections, explicit uncertainty and a citation list. The server rejects missing or unknown chunk citations, invalid response structures, generated URLs and numeric chemical dosing patterns. Source URLs displayed to users come only from stored references. A source changed during generation is rechecked before persistence. Missing evidence produces a low-confidence insufficient-evidence answer without a generation call.

These checks constrain output; citation membership does not prove every generated claim is correct. Human source review and agronomic evaluation remain necessary before using advice for farm decisions. Model changes require reprocessing document embeddings; queries filter out chunks from a different configured embedding model.

## Offline/PWA

The manifest and service worker support installation over HTTPS or localhost. Static assets and an offline shell are cached; authenticated pages, private images and AI messages are not placed in the shared service-worker cache. Settings offers an explicit trusted-device opt-in for IndexedDB monitoring snapshots. Snapshots are tied to the current account and filters, labeled **NOT LIVE**, and cleared on sign-out or account change. Changes are not silently queued while offline.

## Files and development phases

All working code is in the repository; the following maps the brief's phases to exact entry points.

| Phase | Main files |
| --- | --- |
| 1 · Framework/configuration | `composer.json`, `package.json`, `.env.example`, `config/database.php`, `config/agrisense.php` |
| 2 · Schema/relationships | `database/migrations/*.php`, `app/Models/*.php`, `database/supabase-setup.sql` |
| 3–4 · Authentication/roles | `app/Http/Controllers/Auth/*.php`, `app/Http/Middleware/RequireAdmin.php`, `app/Policies/*.php`, `routes/web.php` |
| 5–6 · Layout/dashboard | `resources/views/layouts/*.blade.php`, `resources/views/components/*.blade.php`, `resources/views/dashboard/index.blade.php`, `app/Http/Controllers/DashboardController.php` |
| 7 · Crop CRUD | `app/Http/Controllers/CropController.php`, `app/Http/Requests/CropRequest.php`, `resources/views/crops/{form,index,show}.blade.php` |
| 8 · Devices | `app/Http/Controllers/DeviceController.php`, `app/Services/DeviceService.php`, `resources/views/devices/{form,index,show}.blade.php` |
| 9 · ESP32 API | `routes/api.php`, `app/Http/Controllers/SensorController.php`, `app/Http/Requests/ReadingRequest.php`, `app/Services/SensorService.php`, `database/esp32/AgriSense.ino` |
| 10 · Monitoring/history | `app/Http/Controllers/MonitoringController.php`, `app/Http/Controllers/HistoryController.php`, `resources/views/components/chart-card.blade.php` |
| 11–12 · Ranges/alerts | `app/Http/Controllers/ThresholdController.php`, `app/Services/AlertService.php`, `resources/views/alerts/index.blade.php` |
| 13 · Source registry | `app/Http/Controllers/Admin/SourceController.php`, `resources/views/admin/sources/index.blade.php` |
| 14–16 · Upload/extraction/embedding | `app/Services/SupabaseStorageService.php`, `app/Http/Controllers/Admin/DocumentController.php`, `app/Services/DocumentService.php`, `app/Jobs/ProcessDocument.php` |
| 17–19 · RAG/Gemini/citations | `app/Services/RagService.php`, `app/Services/GeminiService.php`, `app/Http/Controllers/AIController.php` |
| 20–21 · Chat/image analysis | `resources/views/ai/index.blade.php`, `app/Http/Requests/AskQuestionRequest.php`, `resources/js/app.js` |
| 22 · Realtime | `app/Services/RealtimeService.php`, `database/supabase-setup.sql`, `resources/js/app.js` |
| 23 · Offline/PWA | `public/sw.js`, `public/offline.html`, `public/manifest.webmanifest`, `resources/views/settings.blade.php` |
| 24 · Security checks | `tests/Feature/AgriSenseTest.php`, `app/Http/Middleware/PrivateResponse.php`, `app/Providers/AppServiceProvider.php` |
| 25 · Responsive design | `resources/css/app.css`, `resources/views/partials/sidebar.blade.php` |

The original numeric Laravel primary keys are retained to preserve the existing schema; replay identifiers and storage object names use UUIDs. Extra tables record crop events and system settings. Cloud table access is intentionally through Laravel, rather than equating Laravel accounts to Supabase Auth users.

## Verification

```sh
php artisan test
php artisan route:list --except-vendor
php artisan view:cache
npm run build
php vendor/bin/pint --format agent
```

The test suite uses `phpunit.xml` to select an isolated, in-memory SQLite database. It checks registration/roles, ownership boundaries, device tokens, input validation, replay behavior, timestamp preservation, alert deduplication/resolution, stale readings, unavailable evidence, excluded sources, embeddings, citation rejection, profile/crop updates, password resets, login throttling, storage requests, and farmer/admin page rendering. Provider requests are faked only in tests.

After configuring Supabase, separately verify migrations/pgvector and RLS on PostgreSQL, a private Storage upload/download, a real Gemini question with reviewed evidence, two-account Realtime isolation, password-reset email delivery, and ESP32 retransmission on physical hardware. Do not run `migrate:fresh` against a database containing real farm data.

## Provider references

- [Gemini embedding API](https://ai.google.dev/api/embeddings)
- [Gemini model catalog](https://ai.google.dev/gemini-api/docs/models)
- [Supabase private Storage access](https://supabase.com/docs/guides/storage/security/access-control)
- [Supabase Realtime authorization](https://supabase.com/docs/guides/realtime/authorization)

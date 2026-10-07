# **CONTAINER:**

## 1. Pendaftaran & Definisi Service (Registration)

- **Definisi eksplisit**: Mendaftarkan service dengan identifier unik (nama class/alias).
- **Definisi array / struktur data**: Gaya deklaratif ala PHP-DI untuk pasangan nama → definisi.
- **Closure / factory function**: Service dibuat lewat fungsi anonim yang mengembalikan instance.
- **Factory services & factory methods**: Objek dibangun oleh pabrikan eksternal, bukan constructor langsung.
- **Mapping interface → implementasi**: Mengikat abstraksi ke class konkret tertentu.
- **Alias**: Memberi nama lain untuk entri, demi kompatibilitas atau kenyamanan.
- **Nilai skalar / parameter global**: String, angka, boolean, array sebagai konfigurasi yang bisa di-inject.
- **Env variables / runtime values**: Parameter mengambil nilai dari environment saat runtime.
- **Parameter providers**: Nilai dihitung/di-resolve saat fase kompilasi.

---

## 2. Autowiring (Resolusi Otomatis)

- **Autowiring berbasis type hint**: Membaca refleksi constructor dan memetakan tipe ke service terdaftar.
- **Autowiring agresif vs terkontrol**: PHP-DI otomatis penuh; Symfony butuh alias untuk interface ambigu.
- **Named arguments / autowiring aliases**: Injeksi berdasarkan nama parameter ketika tipe saja tidak cukup.
- **Explicit wins over auto**: Definisi manual selalu mengalahkan tebakan otomatis.
- **Fallback ke definisi manual**: Jika autowiring gagal, container mencari definisi eksplisit.

---

## 3. Autoconfiguration (Konfigurasi Otomatis Metadata)

- **Tag otomatis via interface penanda**: Class yang mengimplementasi marker-interface diberi tag relevan.
- **Attribute-based tagging/autoconfig**: Metadata dinyatakan lewat PHP attributes di class.
- **Mengurangi konfigurasi berulang**: Tidak perlu menandai tiap service satu per satu.

---

## 4. Injector (Injeksi Properti & Metode)

- **Constructor injection**: Cara utama menyuntik dependensi.
- **Method call injection**: Metode dipanggil setelah instansiasi untuk injeksi tambahan/opsional.
- **Property/setter injection**: Nilai ditetapkan ke properti setelah objek dibuat.
- **Injectors sebagai ekstensi opsional**: Bisa diaktif/dinonaktifkan sesuai kebutuhan performa.

---

## 5. Scope & Lifecycle (Siklus Hidup)

- **Singleton / shared (default)**: Instance sama dibagikan setiap diminta.
- **Factory / prototype (non-shared)**: Instance baru tiap permintaan.
- **Per-request scope**: Instance dibagikan dalam satu siklus request (relevan untuk Swoole/RoadRunner/FrankenPHP).
- **Lazy services / lazy building**: Dibangun hanya saat pertama dipakai, bisa pakai proxy lazy.
- **Kontrol waktu pembuatan singleton**: Segera saat registrasi atau ditunda saat diminta.
- **Public vs private services**: Privat dioptimalkan & tak diakses langsung dari luar.
- **Synthetic services**: Nilai disuntikkan ke container dari luar saat runtime.
- **Deprecation metadata**: Definisi ditandai usang lengkap dengan pesan peringatan.

---

## 6. Tags & Pengelompokan Layanan

- **Grouping by tag**: Banyak service diberi label sama lalu dikumpulkan bersama.
- **Tag tanpa arti bawaan**: Maknanya ditentukan logika pemroses (compiler pass).
- **Pola umum berbasis tag**: Event subscriber, voter, console command, extension.

---

## 7. Decorator & Proxy

- **Decorated services**: Service membungkus service lain, menambah perilaku sebelum/sesudah delegasi.
- **Proxy lazy**: Membungkus service agar pembuatannya tertunda penuh.

---

## 8. Compiler Passes & Kompilasi (Optimasi Statis)

- **Multi-pass compilation**: Definisi diproses berurutan melalui beberapa tahap sebelum final.
- **Collecting & transforming tags**: Pass membaca semua tag dan membangun relasi antar-service.
- **Removing unused services**: Service privat tak terpakai dibuang demi performa.
- **Inlining & optimization**: Referensi disederhanakan agar akses secepat mungkin.
- **Container compiler / cache (ala PHP-DI)**: Definisi dikompilasi jadi kode siap pakai, menghindari refleksi berulang.
- **PHP-dumper (ala Symfony)**: Hasil akhir ditulis sebagai file PHP murni, zero reflection overhead saat runtime.
- **Cache invalidation otomatis**: Dikompilasi ulang bila ada perubahan konfigurasi/class.

---

## 9. Ekstensibilitas Arsitektural

- **Definition source (PHP-DI)**: Menambah sumber definisi custom (misal database/format baru).
- **Extension point injector**: Menulis logika injeksi sendiri.
- **DI Extension / Bundle (Symfony)**: Library menyumbang konfigurasi & definisi service.
- **Configuration merging**: Konfigurasi banyak sumber digabung & divalidasi terstruktur.
- **Semantic configuration**: Konfigurasi tervalidasi dengan skema jelas.
- **Custom compiler pass**: Library mendaftarkan pass untuk memperluas perilaku container.

---

## 10. Standarisasi & Integrasi

- **Patuh PSR-11**: Memenuhi container interface standar PHP.
- **Service locator**: Sub-container ringan berisi subset service untuk konsumsi on-demand.
- **Framework-agnostic (PHP-DI)** vs **inti ekosistem Symfony**: Keduanya bisa menjembatani framework lain.
- **Analisis statis ramah (PHPStan/Psalm)**: Tipe hasil container dikenali alat analisis.

---

## 11. Performa

- **Reflection caching**: Hasil refleksi disimpan agar introspeksi tidak mahal.
- **Mode development vs production**: Cache & pelaporan error disesuaikan lingkungan.
- **Zero-overhead compiled container**: Untuk produksi berperforma tinggi.

---

## 12. Debugging & Developer Experience

- **Pesan error deskriptif**: Menjelaskan rantai dependensi yang gagal.
- **Deteksi circular dependency/reference**: Melaporkan referensi melingkar antar-service.
- **Introspeksi container**: Mengecek ketersediaan entri sebelum meminta.
- **Debug mode kaya informasi**: Menyimpan jejak bagaimana tiap service dibangun.
- **Perintah CLI analisis**: Melihat daftar service, dependensi, dan status tag.

---

---

# **Router (Enterprise-Grade Radix Tree)**

## 🎯 Visi

Membangun router PHP tercepat dan paling terstruktur yang menggabungkan **kecepatan algoritma Radix Tree** dengan **kelengkapan fitur enterprise** (Multi-tenancy, Observability, Security, dan Developer Experience), tanpa bergantung pada library pihak ketiga yang berat.

---

## 🟢 1: Core Engine & Foundation (The "Fast" Layer)

_Fokus: Membangun inti algoritma Radix Tree yang absolut cepat, stabil, dan patuh standar dasar PHP._

### 1.1 Struktur Data Radix Tree

- **Radix Trie Murni**: Implementasi node tree yang mengompresi prefix bersama untuk efisiensi memori maksimal.
- **Zero-Allocation Matching**: Algoritma pencarian URL tidak menghasilkan objek baru (garbage-free) selama proses _matching_.
- **Immutable Tree Structure**: Setelah router di-_freeze_, struktur pohon tidak dapat diubah, menjamin thread-safety dan performa konsulen.
- **Namespace Container**: Pemisahan logika penyimpanan rute (Domain) dari logika HTTP (Adapter), mendukung prinsip Hexagonal Architecture.

### 1.2 Pencocokan Rute (Matching Logic)

- **Prefix Matching**: Pencocokan rute berbasis prefix terpanjang (_longest prefix match_) untuk memastikan spesifisitas.
- **HTTP Method Mapping**: Pembedaan rute berdasarkan metode HTTP (GET, POST, PUT, DELETE, PATCH) di level node.
- **Host-Aware Routing**: Dukungan native untuk host berbeda (misal: `api.zef.io` vs `admin.zef.io`) tanpa konflik rute.
- **Content Negotiation (Basic)**: Respon `406 Not Acceptable` jika `Accept` header tidak cocok dengan MIME type yang didukung rute.

### 1.3 Manajemen Rute (Registration)

- **Route Grouping**: Dukungan nested group untuk prefix dan middleware yang tersarang.
- **Named Routes**: Penamaan rute untuk referensi balik (_reverse routing_) yang unik.
- **Constraint Validation**: Validasi parameter dinamis (regex constraint) saat registrasi, bukan saat runtime.
- **Duplicate Detection**: Deteksi dini konflik rute (method + path + host sama) saat registrasi.

### 1.4 Standar & Kualitas Kode

- **PSR-7 Compliance**: Operasi input/output berbasis `ServerRequestInterface`.
- **PSR-15 Compliance**: Antarmuka middleware standar (`RequestHandlerInterface`).
- **Strict Typing**: PHP 8.4+ dengan `declare(strict_types=1)`, readonly properties, dan enum.
- **Static Analysis**: Lolos PHPStan Level Max + Strict Rules.

---

## 🟡 2: Advanced Routing & Flexibility (The "Smart" Layer)

_Fokus: Menambahkan fitur logika bisnis yang kompleks yang biasa dibutuhkan aplikasi skala besar._

### 2.1 Multi-Tenancy & Subdomain

- **Subdomain Wildcards**: Dukungan pola `{tenant}.example.com` untuk aplikasi SaaS.
- **Tenant Context Injection**: Otomatis mengekstrak data tenant dari URL dan memasukkannya ke konteks request.
- **Isolated Route Tables**: Kemampuan router untuk memuat tabel rute berbeda per tenant (opsional).

### 2.2 Localization (i18n)

- **Locale Prefix Routing**: Middleware otomatis untuk mendeteksi locale dari URL (`/en/users`) atau header.
- **Locale Negotiator**: Logika fallback locale (URL -> Header -> Default) yang terintegrasi dengan router.
- **Localized URL Generation**: Generator URL yang otomatis menyisipkan prefix locale yang benar.

### 2.3 API Versioning

- **Version Header Routing**: Mendefinisikan rute berdasarkan header `Accept-Version` atau `X-API-Version`.
- **Path Versioning**: Dukungan standar `/v1/resource`, `/v2/resource` dengan manajemen namespace yang rapi.
- **Deprecation Middleware**: Menandai rute versi lama sebagai "deprecated" dengan header respons standar.

### 2.4 Middleware Pipeline Lanjutan

- **Per-Route Middleware**: Middleware spesifik yang hanya berjalan untuk rute tertentu (di dalam pipeline global).
- **Fail-Closed Execution**: Middleware tidak dijalankan jika service-nya tidak terdaftar atau invalid (security by default).
- **Middleware Ordering**: Kontrol penuh atas urutan eksekusi middleware per-rute.

---

## 🟠 3: Performance & Production (The "Scale" Layer)

_Fokus: Optimasi untuk lingkungan produksi dengan traffic tinggi dan constraint resource ketat._

### 3.1 Kompilasi & Caching

- **Revision-Aware Compile**: Pohon hanya di-rebuild jika ada perubahan rute baru (bukan setiap request).
- **Frozen Tree Optimization**: Setelah _freeze_, pohon dioptimalkan untuk kecepatan baca maksimal (menghapus overhead validasi).
- **Compiled Cache File**: Ekspor struktur pohon sebagai file PHP statis untuk booting instan di produksi.
- **Route Cache Storage**: Dukungan cache driver (File, Redis, APCu) untuk menyimpan hasil kompilasi.

### 3.2 Observability & Monitoring

- **Metrics Integration**: Pengumpulan metrik dasar (waktu pencarian rute, jumlah rute dicocokkan) untuk Prometheus.
- **Tracing Support**: Propagasi Trace ID ke dalam span router untuk Distributed Tracing (OpenTelemetry).
- **Debug Mode**: Mode development yang menampilkan alasan kegagalan pencocokan rute secara detail.

### 3.3 HTTP Optimizations

- **ETag / 304 Support**: Generasi ETag otomatis untuk rute statis atau hasil yang dapat di-cache.
- **Conditional GET**: Penanganan header `If-Match`, `If-None-Match`, `If-Modified-Since` di level router.
- **Head Request Handling**: Otomatis mengubah permintaan HEAD menjadi GET untuk mengeksekusi logika tapi membuang body.

---

## 🔵 4: Developer Experience & Tooling (The "UX" Layer)

_Fokus: Memudahkan developer bekerja, debugging, dan dokumentasi._

### 4.1 CLI Inspector

- **Route List Command**: Perintah `bin/zef route:list` menampilkan semua rute, method, host, middleware, dan nama.
- **JSON Output**: Output CLI dalam format JSON untuk integrasi dengan tools lain.
- **Route Test Command**: Simulasi pencocokan URL dari CLI untuk validasi cepat.
- **Visual Tree Dump**: (Opsional) Output struktur pohon sebagai teks/dot graph untuk debugging kompleks.

### 4.2 URL Generator (Reverse Routing)

- **Type-Safe Generation**: Generator URL yang divalidasi terhadap constraint rute (invariant check).
- **Self-Validation**: Memastikan URL yang dihasilkan _pasti_ cocok dengan rute aslinya sebelum dikembalikan.
- **Parameter Encoding**: Penanganan otomatis `rawurlencode` untuk parameter yang mengandung karakter spesial.

### 4.3 Documentation Auto-Generation

- **OpenAPI 3.1 Auto-Gen**: Ekspor otomatis spesifikasi OpenAPI dari tabel rute + PHP Attributes.
- **Swagger UI Integration**: Endpoint dev-only untuk visualisasi API docs.
- **Postman Export**: Ekspor kumpulan rute ke format Postman v2.1.

### 4.4 Security & Standards

- **RFC 9457 Problem Details**: Format error standar (JSON) untuk 404, 405, 406.
- **CSRF Integration**: Hook point untuk integrasi middleware CSRF di level router.
- **Rate Limiting Hooks**: Integrasi dengan rate limiter berbasis rute (misal: `/login` lebih ketat).

---

## 🟣 5: Ecosystem & Integration (The "Bridge" Layer)

_Fokus: Integrasi dengan ekosistem PHP modern dan runtime canggih._

### 5.1 Runtime Compatibility

- **RoadRunner Worker**: Optimasi untuk long-running process dengan state persisten.
- **Async/Fiber Support**: Dukungan untuk async routing context (untuk framework yang menggunakan Fiber).
- **Swoole/Hyperf Compatible**: Antarmuka yang kompatibel dengan runtime PHP berperforma tinggi lainnya.

### 5.2 Container Integration

- **PSR-11 Container**: Integrasi dengan container DI (PHP-DI, Laminas, dll) untuk injeksi dependency ke handler.
- **Route Model Binding**: Otomatis resolve parameter ID menjadi objek Model dari database (seperti Laravel).
- **Contextual Binding**: Mendefinisikan handler berbeda berdasarkan konteks request (misal: User vs Admin).

### 5.3 Event System

- **Route Event Dispatcher**: Emit event saat rute dicocokkan, sebelum/sesudah middleware, dan saat error.
- **Listener Support**: Developer dapat mendengarkan event router untuk logging, audit, atau logika kustom.

---

---

# **Configuration System + DSL + Radix Tree (The "Triad")**

Ini adalah arsitektur dengan menggabungkan **Configuration** (Sumber Kebenaran), **DSL** (Cara Mendeklarasikan), dan **Radix Tree** (Mesin Eksekusi) menciptakan sistem yang _declarative_, _type-safe_, dan _blazing fast_.

---

## 🏗️ Arsitektur Konsep: The Triad

1.  **Configuration Layer (Source of Truth)**
    - Menyimpan definisi rute sebagai data murni (Array/PHP Config).
    - Mendukung hierarki (Default < App < Env).
    - Tervalidasi ketat via Schema.
2.  **DSL Layer (The Compiler/Input)**
    - Menerjemahkan kode PHP deklaratif menjadi struktur data konfigurasi tersebut.
    - Memberikan IDE autocomplete & Type Safety saat developer menulis rute.
3.  **Radix Tree Layer (The Engine)**
    - Membaca struktur data konfigurasi.
    - Mengompilasi (_compile_) menjadi node-node radix tree yang terkompresi.
    - Menjalankan pencocokan URL dengan kecepatan O(k).

---

## 1. Configuration Layer: Struktur Data Rute

Di level terendah, router tidak peduli apakah Kita menggunakan `Route::get()` atau file YAML. Ia hanya butuh **Struktur Data Standar**.

### Skema Konfigurasi Rute (Config Schema)

Setiap rute didefinisikan sebagai array asosiatif dengan kunci-kunci standar berikut di dalam `config/routes.php`:

- **`path`**: String pola URL (misal: `/users/{id}`).
- **`method`**: Array metode HTTP (`['GET', 'POST']`).
- **`handler`**: Referensi ke Controller/Action (String Class::Method atau Closure ID).
- **`name`**: Nama unik untuk reverse routing.
- **`middleware`**: Daftar middleware yang melekat pada rute ini.
- **`constraints`**: Aturan validasi parameter (regex/tipe).
- **`meta`**: Metadata tambahan (versi API, tenant, locale).

### Contoh Isi File Konfigurasi (Raw Data)

```php
// config/routes.php
return [
    // Rute Sederhana
    [
        'path' => '/health',
        'method' => ['GET'],
        'handler' => HealthController::class . '@check',
        'name' => 'health.check',
    ],

    // Rute dengan Parameter & Constraint
    [
        'path' => '/users/{id}',
        'method' => ['GET'],
        'handler' => UserController::class . '@show',
        'constraints' => ['id' => '[0-9]+'],
        'middleware' => ['auth'],
    ],
];
```

> **Poin Penting:** Ini adalah "bahasa ibu (induk)" dari router Zef. Apapun yang Kita tulis di DSL nanti, akan berubah menjadi format ini sebelum masuk ke Radix Tree.

---

## 2. DSL Layer: Jembatan Developer Experience

Developer jarang menulis array mentah di atas karena rawan typo dan tidak ada autocompletion. Di sinilah **DSL (Domain Specific Language)** berperan.

DSL bertindak sebagai **"Compiler"** yang mengubah sintaks elegan menjadi struktur data konfigurasi di atas.

### Fitur DSL Router Zef

#### A. Fluent Interface (Chaining)

Metode DSL mengembalikan objek `$this` sehingga bisa dirangkai. Setiap pemanggilan metode hanya mengisi array konfigurasi sementara, belum mendaftarkan ke tree.

#### B. Grouping & Prefix Inheritance

DSL menangani logika kompleks seperti inheritance prefix secara otomatis. Saat Kita menutup group, DSL menggabungkan prefix parent ke semua anak-anaknya di level konfigurasi.

#### C. Middleware Stacking

DSL memungkinkan penumpukan middleware dari luar ke dalam. Middleware global -> Group Middleware -> Route Middleware digabung menjadi satu list di konfigurasi akhir.

#### D. Resource Macro

Satu perintah DSL dapat menghasilkan puluhan entri konfigurasi (untuk CRUD lengkap).

### Contoh Penggunaan DSL

```php
use Zef\Framework\Router\DSL\Route;

$router->define(function (Route $r) {
    // 1. Global Middleware
    $r->middleware(LoggingMiddleware::class);

    // 2. Nested Groups (Prefix & Middleware Inheritance)
    $r->group('/api/v1', function (Route $r) {

        // Public Routes
        $r->get('/login', AuthController::class)->name('auth.login');

        // Protected Group
        $r->middleware(AuthMiddleware::class)->group('/user', function (Route $r) {

            // Resource Macro (Generate 7 routes in config)
            $r->resource('/profile', ProfileController::class);

            // Custom Route with Constraints
            $r->get('/history/{year}', HistoryController::class)
              ->where(['year' => '\d{4}'])
              ->name('user.history');
        });
    });
});
```

### Proses Translasi DSL → Config

Ketika fungsi di atas dieksekusi, DSL membangun array konfigurasi final yang siap dibaca oleh engine.

---

## 3. Radix Tree Layer: Kompilasi & Eksekusi

Inilah bagian unik Zef Framework. Radix Tree tidak dibangun langsung dari kode PHP, melainkan dari **Output Konfigurasi**.

### Fase 1: Loading (Boot Time)

1.  **Load Config**: Sistem membaca `config/routes.php`. Jika ada cache, baca dari cache.
2.  **Validate Schema**: Pastikan setiap entri memiliki `path`, `method`, dan `handler` yang valid sesuai skema konfigurasi enterprise.
3.  **Flatten**: Jika menggunakan DSL, jalankan closure DSL untuk mendapatkan array datar.

### Fase 2: Compilation (Tree Building)

Algoritma Radix Tree mengambil daftar rute dari konfigurasi dan menyusunnya menjadi pohon:

1.  **Normalize Path**: `/api/v1/users/{id}` pecah menjadi segmen `[api, v1, users, {id}]`.
2.  **Insert into Trie**:
    - Buat root node.
    - Telusuri karakter demi karakter.
    - **Kompresi**: Jika node `/api` hanya punya satu anak `/v1`, gabungkan menjadi node `/api/v1`.
3.  **Attach Handler**: Simpan referensi handler di node daun (leaf).
4.  **Optimize**: Urutkan prioritas (statis > dinamis > wildcard).

### Fase 3: Runtime (Matching)

Saat request masuk:

1.  Ambil path URL.
2.  Telusuri Radix Tree (sudah dikompilasi dari Config).
3.  Temukan Node Daun.
4.  Ambil metadata (handler, middleware) dari node tersebut.

---

## 🔗 Integrasi Penuh: Alur Data Lengkap

Berikut adalah alur bagaimana ketiganya bekerja sama dalam satu siklus hidup aplikasi Zef:

| Tahap                | Komponen          | Aksi                                                 | Output                         |
| :------------------- | :---------------- | :--------------------------------------------------- | :----------------------------- |
| **1. Development**   | **DSL**           | Developer menulis `$r->get(...)`                     | Kode PHP Elegan                |
| **2. Build/Cache**   | **Config System** | DSL dijalankan, hasil disimpan ke `routes.cache.php` | Array Konfigurasi Terkompilasi |
| **3. Validation**    | **Config System** | Validasi tipe data & constraint                      | Konfigurasi Valid & Aman       |
| **4. Bootstrapping** | **Radix Tree**    | Baca Config Array, bangun struktur memori            | Objek `RadixNode` Tree         |
| **5. Runtime**       | **Radix Tree**    | Match URL vs Tree                                    | Handler & Parameters           |

---

## 💡 Keunggulan Pola Ini untuk Enterprise

### 1. Decoupling (Terpisah Rapat)

- Kita bisa mengganti **DSL** tanpa menyentuh **Radix Tree**. Misalnya, besok Kita ingin mendukung definisi rute via **YAML**, cukup buat parser YAML yang outputnya sama-sama array konfigurasi standar. Engine tetap jalan.
- Kita bisa mengganti algoritma **Radix Tree** dengan **Trie biasa** jika perlu, tanpa mengubah cara developer menulis rute.

### 2. Testability (Mudah Diuji)

- **Unit Test Config**: Kita bisa menguji apakah file konfigurasi valid tanpa menjalankan server.
- **Unit Test DSL**: Kita bisa menguji apakah DSL menghasilkan array yang benar.
- **Integration Test Tree**: Kita bisa menguji apakah Tree mencocokkan URL dengan benar berdasarkan array input.

### 3. Performance Optimization

Karena DSL dieksekusi sekali (saat build/cache), biaya parsing PHP attributes/closure hilang saat runtime. Radix Tree menerima input "makanan siap saji" (array bersih) sehingga proses pembangunannya sangat cepat.

### 4. Hot-Reload Friendly

Karena berbasis konfigurasi, perubahan rute di development mode cukup memicu ulang proses "Compile Config to Tree". Tidak perlu re-deploy.

---

## 🚀 Roadmap Implementasi Gabungan

saran urutannya:

1.  **Definisikan "Standard Route Schema"**: Tentukan format array baku (key apa saja yang wajib ada).
2.  **Buat "Config Loader"**: Sederhana dulu, baca file PHP return array.
3.  **Bangun "Radix Tree Engine"**: Buat class yang menerima array schema tadi dan membangun tree. Uji dengan hardcode array.
4.  **Buat "DSL Builder"**: Buat class yang membantu membuat array schema tersebut dengan cara enak dipakai.
5.  **Integrasikan**: Hubungkan Loader -> Engine -> Dispatcher.

---

---

# **Middleware Pipeline (Enterprise-Grade)**

## 1. Arsitektur Inti & Immutability (Core Architecture)

Fondasi pipeline Zef dirancang untuk keamanan memori dan prediksi state di lingkungan _long-running process_ (RoadRunner/Swoole).

- **Immutable Pipeline Design**: Class `MiddlewarePipeline` ditandai sebagai `final readonly`. Ini menjamin bahwa setelah pipeline dibangun, tidak ada properti internal (stack, terminal handler, index) yang bisa dimutasi secara diam-diam https://github.com/mbetixz/zef-framework/pull/72.
- **Safe State Advancement**: Kemajuan indeks eksekusi middleware dilakukan melalui pola `new self(...)` (clone-on-write), bukan mutasi variabel `$index++`. Ini mencegah race condition atau bug state saat debugging https://github.com/mbetixz/zef-framework/pull/72.
- **Final Class Enforcement**: Seluruh komponen Kernel (`Dispatcher`, `MiddlewarePipeline`, `ModuleBootstrapper`, `PipelineFactory`) dikunci sebagai `final` untuk mencegah inheritance yang tidak terkendali dan menjaga integritas kontrak pipeline https://github.com/mbetixz/zef-framework/pull/72.
- **Constructor Promotion**: Dependensi disuntikkan langsung via constructor promotion properties, memastikan semua service tersedia sebelum pipeline pertama kali dieksekusi https://github.com/mbetixz/zef-framework/pull/72.

---

## 2. Eksekusi & Orkestrasi (Execution Engine)

Bagaimana middleware benar-benar dijalankan saat request masuk.

- **PSR-15 Native Compliance**: Seluruh pipeline mematuhi standar `MiddlewareInterface` dan `RequestHandlerInterface` FIG, menjamin interoperabilitas dengan library pihak ketiga https://github.com/Zeflous/zef-framework https://github.com/Zeflous/zef-framework/pull/267.
- **Nested Inner Pipeline**: Dispatcher mendukung _inner pipeline_ untuk middleware per-rute. Middleware rute dibungkus di dalam stack global, menciptakan hierarki eksekusi yang presisi (Global → Group → Route → Handler) https://github.com/Zeflous/zef-framework/pull/354.
- **Terminal Handler Resolution**: Pipeline selalu berakhir pada satu `terminal handler` tunggal yang merepresentasikan Controller/Action akhir, menjamin respons HTTP selalu dihasilkan meskipun terjadi error di tengah chain https://github.com/mbetixz/zef-framework/pull/72.
- **Lazy Body Processing**: Pada middleware khusus seperti OpenAPI Gate, parsing body dilakukan secara _lazy_. Stream request tidak disentuh kecuali operasi mendeklarasikan `requestBody`, menghemat I/O untuk endpoint GET/HEAD https://github.com/Zeflous/zef-framework/pull/267.
- **Stream-Safe Rewind**: Penanganan body stream memperhatikan seekability. Jika stream bisa rewind, ia diputar ulang; jika tidak, di-buffer dan diganti dengan salinan agar middleware lain tetap bisa membaca body https://github.com/Zeflous/zef-framework/pull/267.

---

## 3. Keamanan & Fail-Closed Behavior (Security Hardening)

Ini adalah pembeda utama pipeline Zef dari framework biasa. Pipeline **tidak pernah** gagal secara diam-diam.

- **Fail-Closed Service Resolution**: Jika middleware dideklarasikan pada rute tetapi service-nya tidak terdaftar di Container atau bukan implementasi `MiddlewareInterface` yang valid, Dispatcher **menolak eksekusi** alih-alih melewati middleware tersebut https://github.com/Zeflous/zef-framework/pull/354.
- **P0 Security Patch History**: Audit v2.36.0 secara eksplisit memperbaiki celah di mana metadata middleware per-rute tersimpan dan tampil di CLI, tetapi _tidak pernah dieksekusi_ oleh Dispatcher. Perbaikan ini membuktikan komitmen terhadap eksekusi middleware yang diverifikasi ketat https://github.com/Zeflous/zef-framework/pull/354.
- **Deterministic Status Precedence**: Saat terjadi konflik penolakan (misal OpenAPI Gate), pipeline menerapkan urutan status deterministik: `405 > 401/403 > 415 > 400`. Ini mencegah kebocoran informasi atau respons ambigu https://github.com/Zeflous/zef-framework/pull/267.
- **RFC 9457 Problem Details Integration**: Setiap penolakan middleware menghasilkan respons `application/problem+json` standar, termasuk header `Allow` yang tepat untuk kasus 405 Method Not Allowed https://github.com/Zeflous/zef-framework/pull/267.
- **Contract-First Runtime Gate**: Middleware dapat menegakkan kontrak API (OpenAPI 3.1) pada setiap request masuk menggunakan dokumen spesifikasi yang sama dengan yang di-serve ke klien, menutup celah antara dokumentasi dan implementasi https://github.com/Zeflous/zef-framework/pull/267.

---

## 4. Integrasi Container & Dependency Injection

Pipeline bukan entitas terisolasi; ia terhubung langsung dengan DI Container Zef.

- **Container-Aware Middleware Resolution**: Middleware di-resolve dari PSR-11 Container saat pipeline dibangun, memungkinkan injeksi dependensi kompleks (DB connection, Logger, Cache) ke dalam middleware tanpa global state https://github.com/mbetixz/zef-framework/pull/72.
- **PipelineFactory Pattern**: Komponen terpisah bertanggung jawab membangun pipeline dari konfigurasi + container. Ini memisahkan logika _construction_ dari logika _execution_, memudahkan unit testing https://github.com/mbetixz/zef-framework/pull/72.
- **Service Validation at Build Time**: Factory memverifikasi bahwa setiap entry dalam stack middleware adalah instance `MiddlewareInterface` yang valid sebelum pipeline dianggap siap. Kegagalan terdeteksi saat boot, bukan saat runtime https://github.com/Zeflous/zef-framework/pull/354.

---

## 5. Developer Experience & Observability

Fitur yang membuat pengembangan dan debugging pipeline menjadi mudah.

- **CLI Route Inspector with Middleware Column**: Perintah `bin/zef route:list` menampilkan kolom **MIDDLEWARE** dan **HOST** untuk setiap rute, memberikan visibilitas instan atas middleware apa yang melekat pada endpoint tertentu https://github.com/Zeflous/zef-framework/pull/354.
- **JSON Output for Automation**: Dukungan flag `--json` pada route inspector memungkinkan tooling eksternal (CI/CD, security scanner) memverifikasi konfigurasi middleware secara otomatis https://github.com/Zeflous/zef-framework/pull/354.
- **Request Attribute Propagation**: Middleware dapat menyisipkan atribut terstruktur ke request object (misal: `zef.openapi.gate`) yang kemudian dapat dibaca oleh handler atau middleware downstream, menciptakan channel komunikasi type-safe antar lapisan https://github.com/Zeflous/zef-framework/pull/267.
- **Mutation Testing Coverage**: Pipeline memiliki cakupan mutation testing yang tinggi (frozen baseline 43→39 entries after readonly refactor), membuktikan bahwa logika branching dan edge case pipeline teruji hingga level statement coverage https://github.com/mbetixz/zef-framework/pull/72.

---

## 6. Performa & Produksi (Production Readiness)

Optimasi spesifik untuk environment high-throughput.

- **Zero-Rebuild Pipeline Construction**: Berkat integrasi dengan revision-aware router compilation, pipeline tidak perlu dibangun ulang untuk setiap request jika tidak ada perubahan rute/middleware. Ini krusial untuk RoadRunner persistent worker https://github.com/Zeflous/zef-framework/pull/354.
- **Readonly Memory Footprint**: Properti readonly mengurangi overhead PHP internal (tanpa write-protection checks tambahan) dan membantu OPcache melakukan optimasi lebih agresif pada hot path pipeline https://github.com/mbetixz/zef-framework/pull/72.
- **Long-Running Process Safety**: Desain immutable dan final class menjamin tidak ada memory leak atau state corruption selama ribuan siklus request dalam satu worker process https://github.com/mbetixz/zef-framework/pull/72.

---

---

# **HTTP Layer (Enterprise-Grade)**

Ini adalah draf **Roadmap HTTP Layer** yang disusun dari nol, mengikuti format persis seperti Router sebelumnya. Tujuannya: menjadikan lapisan HTTP Zef sebagai "Kulit & Sistem Saraf" yang aman, efisien, dan siap untuk _long-running processes_ (RoadRunner/Swoole).

---

## 🎯 Visi

Membangun HTTP Layer yang **Protocol-Agnostic**, **Security-First**, dan **Zero-Allocation Friendly**. Lapisan ini harus mampu menerjemahkan input mentah dari web server apa pun menjadi objek `Request` yang kaya fitur, serta memformat keluaran menjadi `Response` yang aman dan optimal, tanpa membebani Middleware Pipeline dengan detail teknis HTTP.

---

## 🟢 Fase 1: Core Message Implementation (The Foundation)

_Fokus: Implementasi PSR-7/17 yang benar-benar robust, bukan sekadar wrapper library pihak ketiga._

### 1.1 Custom Stream Engine

- **ZefStream Implementation**: Class stream custom yang mengimplementasikan `StreamInterface`. Dirancang khusus agar bisa di-_reset_ atau di-_truncate_ secara efisien antar-request dalam worker persistent.
- **Memory-Safe Buffering**: Mekanisme buffer otomatis yang pindah dari memori ke disk (`php://temp`) hanya jika ukuran payload melebihi threshold konfigurasi (misal: 2MB), mencegah OOM crash.
- **Immutable Stream Factory**: Implementasi `StreamFactoryInterface` (PSR-17) yang menghasilkan stream baru setiap kali, menjamin isolasi state antar-request.

### 1.2 Request Object Enrichment

- **Lazy Parsing Architecture**: Properti request (query params, body, cookies) tidak diparse saat object dibuat, tapi saat pertama kali diakses. Menghemat CPU untuk endpoint yang tidak membutuhkan data tersebut.
- **Normalized Server Params**: Normalisasi variabel `$_SERVER` yang konsisten antara PHP-FPM, CLI, dan Swoole/RoadRunner, menyembunyikan perbedaan environment dari developer.
- **Attribute Bag Integration**: Slot penyimpanan internal untuk data turunan (seperti hasil parsing JSON atau tenant ID) yang bisa diisi oleh middleware tanpa mutasi properti inti.

### 1.3 Response Builder

- **Fluent Response API**: Helper chainable untuk membangun response (status code, headers, body) tanpa boilerplate.
- **JSON Response Shortcut**: Method cepat `json($data, $code)` yang otomatis mengatur header `Content-Type`, encoding UTF-8, dan charset.
- **Empty Response Handling**: Penanganan khusus untuk status 204/304 yang memastikan body stream benar-benar kosong sesuai spesifikasi HTTP.

---

## 🟡 Fase 2: Security & Hardening (The Shield)

_Fokus: Keamanan bawaan (Secure by Default) tanpa perlu konfigurasi manual oleh user._

### 2.1 Input Validation Gate

- **Max Body Size Enforcement**: Checksum ukuran payload di level adapter sebelum parsing. Return `413 Payload Too Large` instan jika melebihi limit konfigurasi.
- **Content-Type Whitelist**: Menolak request dengan `Content-Type` yang tidak dikenal/didukung kecuali secara eksplisit diizinkan.
- **Header Injection Prevention**: Sanitasi ketat pada nilai header yang berasal dari input user untuk mencegah CRLF injection.

### 2.2 Security Headers Manager

- **Default Secure Headers**: Middleware bawaan yang menyuntikkan header keamanan standar ke _setiap_ response:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Cache-Control: no-store` (untuk endpoint sensitif)
- **CSP Policy Builder**: Helper deklaratif untuk membuat Content Security Policy string yang valid dan aman.
- **HSTS Configuration**: Dukungan native untuk Strict-Transport-Security dengan konfigurasi max-age dan includeSubDomains.

### 2.3 CORS Orchestrator

- **Pre-flight Handler**: Penanganan otomatis request `OPTIONS` berdasarkan konfigurasi rute/group, tanpa masuk ke controller.
- **Dynamic Origin Matching**: Logika matching origin yang mendukung wildcard, regex, atau list statis dengan prioritas tinggi.
- **Credential Support Toggle**: Konfigurasi granular untuk mengizinkan/tidak mengizinkan cookie credentials dalam request cross-origin.

---

## 🟠 Fase 3: Advanced Parsing & Formatting (The Translator)

_Fokus: Kemampuan menerjemahkan berbagai format data dengan efisien._

### 3.1 Multi-Format Body Parser

- **Strategy Pattern Parser**: Interface `BodyParserStrategy` dengan implementasi terpisah untuk JSON, Form-Data, XML, dan Text.
- **Auto-Negotiation**: Deteksi otomatis parser mana yang digunakan berdasarkan header `Content-Type`.
- **Multipart DoS Protection**: Batasan jumlah part dan ukuran per-part dalam parsing multipart/form-data untuk mencegah serangan resource exhaustion.

### 3.2 Output Compression Engine

- **Accept-Encoding Negotiator**: Pengecekan header klien untuk memilih algoritma kompresi terbaik (Brotli > Gzip > Deflate).
- **Streaming Compression**: Kompresi dilakukan secara streaming (chunked) alih-alih buffering penuh, menjaga memory footprint rendah.
- **Compression Threshold**: Hanya mengkompresi response yang melebihi ukuran minimum tertentu (misal: 1KB) untuk menghindari overhead CPU yang tidak perlu.

### 3.3 Session & Cookie Abstraction

- **Stateless Cookie Helper**: Utility untuk membaca/menulis cookie dengan flag keamanan default (`HttpOnly`, `Secure`, `SameSite=Lax`).
- **Session Adapter Interface**: Port hexagonal untuk session storage, memungkinkan swap driver (Redis/File/DB) tanpa mengubah kode aplikasi.

---

## 🔵 Fase 4: Observability & DX (The Eyes)

_Fokus: Memudahkan debugging dan monitoring traffic HTTP._

### 4.1 Request Lifecycle Tracing

- **Unique Request ID**: Generasi UUIDv7 unik di entry point, disisipkan ke attribute bag request, dan dikirim ke log/tracing.
- **Timing Metrics**: Pengukuran waktu eksekusi spesifik lapisan HTTP (parsing vs business logic vs serialization).
- **Slow Request Detection**: Logging otomatis jika total durasi request melebihi ambang batas konfigurasi.

### 4.2 Debugging Utilities

- **Raw Dump Inspector**: Command CLI untuk mensimulasikan request HTTP terhadap aplikasi lokal dan menampilkan raw request/response.
- **Header Validator**: Tool untuk mengecek apakah header yang dihasilkan sudah memenuhi standar keamanan modern (scan via securityheaders.com ruleset).

---

## 📊 Matriks Kesiapan (Current vs Target)

| Fitur                   | Status Saat Ini   | Target Roadmap                   | Prioritas  |
| ----------------------- | ----------------- | -------------------------------- | ---------- |
| **PSR-7/17 Compliance** | ✅ Ada (Library?) | ✅ Native Zef Implementation     | **High**   |
| **Stream Safety (RR)**  | ⚠️ Unknown        | ✅ Resettable Memory/Disk Stream | **High**   |
| **Body Size Limit**     | ❌ Belum          | ✅ Early Stage Enforcement       | **High**   |
| **Security Headers**    | ❌ Belum          | ✅ Built-in Default Secure       | **Medium** |
| **CORS Management**     | ❌ Belum          | ✅ Configurable Orchestrator     | **Medium** |
| **Compression**         | ❌ Belum          | ✅ Streaming Negotiation         | **Low**    |
| **Request ID**          | ❌ Belum          | ✅ UUIDv7 Propagation            | **Medium** |

---

## 💡 Saran Eksekusi Awal

catatn:

1.  **Jangan pakai library PSR-7 eksternal dulu.** Buat class `ZefStream` dan `ZefRequest` sederhana sendiri. Ini akan mengajarkan Kita seluk-beluk stream handling yang krusial untuk RoadRunner.
2.  **Fokus ke Fase 1 & 2 terlebih dahulu.** Performa (Phase 3) tidak berguna jika belum aman (Phase 2).
3.  **Tulis Test untuk Edge Case.** Contoh: Apa yang terjadi jika body request rusak? Apa yang terjadi jika Content-Length tidak cocok dengan actual body? Pastikan HTTP Layer Kita tahan banting.

---

---

# **Zef HTTP Layer: The "Zero-Library Shim" Architecture**

Ini adalah filosofi yang **brilian dan sangat matang**. Menggunakan _shim_ (polyfill ringan) tanpa library pihak ketiga untuk PSR-7/15 bukan hanya soal "tanpa dependensi", tapi tentang **kontrol penuh atas memori, performa, dan keamanan**.

Berikut adalah revisi Roadmap HTTP Layer khusus untuk implementasi **Shim-Native** di Zef Framework. Fokusnya bergeser dari "membuat class PSR-7" menjadi **"membangun shim engine yang efisien"**.

---

## 🎯 Visi Shim Engine

Membangun lapisan HTTP murni PHP yang bertindak sebagai _adapter layer_ antara standar FIG (PSR-7/15/17) dan runtime Zef. Shim ini harus:

1.  **Lightweight**: Tidak ada overhead refleksi atau magic method yang tidak perlu.
2.  **Runtime-Agnostic**: Satu kode yang sama berjalan sempurna di PHP-FPM, CLI, dan RoadRunner/Swoole.
3.  **Self-Contained**: Tidak bergantung pada `nyholm/psr7` atau `laminas/diactoros`, sehingga bebas CVE eksternal.

---

## 🟢 Fase 1: Core Shim Infrastructure (The Polyfill Engine)

_Fokus: Implementasi interface PSR secara manual namun elegan._

### 1.1 Interface Isolation Strategy

- **Interface Shimming**: Jika user belum menginstal `psr/http-message`, Zef mendeteksi dan memuat definisi interface internal (namespace berbeda) atau menggunakan `class_alias` jika sudah tersedia. Ini menjamin framework tetap jalan meski vendor folder kosong.
- **Type Hint Safety**: Semua class internal (`ZefRequest`, `ZefResponse`) mengimplementasikan interface PSR asli jika tersedia, atau shim internal jika tidak. Transisi mulus saat user memutuskan menambahkan composer package nanti.

### 1.2 Native Stream Implementation (`ZefStream`)

- **Resource Wrapper**: Class pembungkus `resource` PHP murni (`fopen`, `fwrite`, `fseek`). Hindari `php://memory` berlebihan; gunakan `php://temp` dengan threshold konfigurasi bawaan Zef.
- **Immutable Clone-on-Write**: Saat `withBody()` dipanggil, stream lama tidak dimutasi. Buat wrapper baru yang menunjuk ke resource yang sama (jika read-only) atau copy-on-write (jika writable).
- **RoadRunner Safe Reset**: Method internal `__resetForNextRequest()` yang membersihkan buffer stream tanpa menghancurkan resource object, krusial untuk persistent worker agar tidak terjadi memory leak antar-request.

### 1.3 Message Factory Shim (`ZefMessageFactory`)

- **Static Factory Pattern**: Alih-alih instantiate factory class berat, gunakan static methods internal yang langsung mengembalikan instance `ZefRequest/ZefResponse`.
- **Header Case Insensitivity**: Implementasi array map internal untuk lookup header case-insensitive yang cepat (O(1)) tanpa `array_change_key_case` berulang kali.

---

## 🟡 Fase 2: Runtime Adapter & Extraction (The Bridge)

_Fokus: Menerjemahkan input mentah server menjadi objek Shim._

### 2.1 Global Input Extractor (PHP-FPM Mode)

- **Superglobal Sanitizer**: Pembacaan `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` yang aman. Jangan pernah trust data mentah; selalu cast tipe dan filter di tahap ekstraksi ini.
- **Raw Body Reader**: Helper khusus untuk membaca `php://input` sekali saja dan menyimpannya di cache internal request object. Penting karena stream `php://input` tidak bisa dibaca dua kali di beberapa SAPI.
- **Uploaded File Mapper**: Konversi `$_FILES` array menjadi objek `ZefUploadedFile` yang patuh PSR-7, termasuk penanganan error code upload PHP secara native.

### 2.2 Worker Context Adapter (RoadRunner/Swoole Mode)

- **Payload-to-Shim Converter**: Fungsi spesifik yang mengambil payload binary/string dari RoadRunner Request dan membangun `ZefStream` dari string tersebut (bukan file temp), mengurangi I/O disk.
- **Connection Info Injector**: Mengambil info IP/Port dari context worker (bukan `$_SERVER`) dan menyuntikkannya ke property request secara presisi.

### 2.3 Response Emitter Shim

- **SAPI-Aware Header Sending**: Logic yang mengecek `headers_sent()`. Jika di CLI/Worker, simpan headers ke buffer internal. Jika di FPM, kirim via `header()`.
- **Chunked Output Handler**: Dukungan streaming response output. Kirim body per-chunk kecil agar klien mendapat TTFB (Time To First Byte) lebih cepat, sangat berguna untuk SSE atau download besar.

---

## 🟠 Fase 3: Security & Validation Hardening (The Shield)

_Fokus: Keamanan level rendah yang biasanya ditangani library eksternal._

### 3.1 Internal Input Validator

- **Method Spoofing Check**: Validasi ketat apakah method HTTP valid RFC 7230 sebelum diproses lebih lanjut.
- **Host Header Validation**: Whitelist validasi `Host` header terhadap konfigurasi domain aplikasi. Mencegah Cache Poisoning dan Password Reset Poisoning attacks.
- **URI Path Normalization**: Membersihkan path dari karakter berbahaya (`../`, null bytes, encoded slashes) sebelum masuk ke Radix Tree Router.

### 3.2 Cookie & Session Shim

- **Secure Cookie Builder**: Helper internal untuk generate string cookie dengan flag `HttpOnly`, `Secure`, `SameSite` yang benar secara default.
- **Session ID Generator**: Pengganti `session_id()` yang menghasilkan token cryptographically secure (menggunakan `random_bytes`) tanpa memulai session PHP native jika tidak diperlukan.

### 3.3 Error Mapping Shim

- **HTTP Exception Translator**: Peta konversi dari exception internal Zef (misal: `RouteNotFoundException`) menjadi objek `ZefResponse` dengan status code dan body JSON yang tepat, tanpa perlu try-catch di setiap controller.

---

## 🔵 Fase 4: Performance Tuning for Shim (The Optimizer)

_Fokus: Memastikan shim lebih cepat dari library populer._

### 4.1 Property Access Optimization

- **Direct Property vs Getter**: Untuk properti yang jarang berubah (seperti URI, Method), gunakan public readonly properties (PHP 8.4+) alih-alih getter method untuk mengurangi overhead function call dalam hot-path.
- **Lazy Loading Arrays**: Parse query string dan form data hanya saat `getQueryParams()` atau `getParsedBody()` pertama kali dipanggil, simpan hasilnya di private property.

### 4.2 Memory Management

- **Unset on Finish**: Di akhir lifecycle request (dalam Kernel Dispatcher), pastikan semua referensi stream dan body di-unset secara eksplisit untuk membantu Garbage Collector di environment long-running process.
- **String Interning Awareness**: Hindari pembuatan string duplikat untuk header name/status text. Gunakan constant pool internal.

---

## 📊 Perbandingan: Library vs Zef Shim

| Aspek                | Nyholm/Laminas PSR-7          | Zef Native Shim                     |
| -------------------- | ----------------------------- | ----------------------------------- |
| **Dependency**       | Berat (Vendor Folder)         | **Nol (Self-contained)**            |
| **Memory Leak Risk** | Tinggi (Jika salah cleanup)   | **Terkontrol (Custom reset logic)** |
| **Performance**      | Good (Generic implementation) | **Excellent (Optimized for Zef)**   |
| **Security Patch**   | Tunggu maintainer upstream    | **Instant Fix (Own codebase)**      |
| **Debugging**        | Stack trace panjang           | **Stack trace pendek & jelas**      |
| **Compatibility**    | Universal                     | **Universal + Custom Extensions**   |

---

## 💡 Saran Eksekusi Kode Pertama

Mulailah dengan membuat direktori `src/Shims/Psr/Http/`.

1.  **Langkah 1**: Copy definisi interface `Psr\Http\Message\...` ke dalam namespace internal Zef (atau deteksi keberadaan global).
2.  **Langkah 2**: Buat `class ZefStream implements StreamInterface`. Mulai dari constructor yang menerima `resource|string|null`.
3.  **Langkah 3**: Tulis test unit sederhana:
    - Baca string -> Write ke stream -> Read kembali -> Pastikan sama.
    - Cek `getSize()`, `isSeekable()`, `rewind()`.
4.  **Langkah 4**: Integrasi minimal ke `ZefRequest` constructor.

---

---

# **Event Source System (The Nervous System)**

--

## 1. Fitur Inti Event Source

Zef Framework tidak hanya punya "Event Dispatcher" sederhana, tapi juga memiliki fondasi untuk **Event Sourcing** dan **CQRS**.

### A. Core Event Dispatcher

- **Listener Prioritization**: Mendukung prioritas eksekusi listener (semakin tinggi prioritas, semakin dulu dijalankan). Ini krusial untuk logika seperti "Logging" yang harus berjalan sebelum "Error Handling".
- **Sync vs Async Execution**: Mendukung eksekusi listener secara sinkron (langsung) atau asinkron (di-antri), memungkinkan decoupling untuk tugas berat seperti pengiriman email.
- **Payload Validation**: Mendukung validasi tipe data payload event untuk mencegah bug runtime akibat struktur data yang salah.

### B. Event Sourcing Integration (Advanced)

- **Event Store Port**: Arsitektur modular (`EventStore` interface) yang memisahkan logika penyimpanan event dari logika bisnis.
- **InMemory & PDO Adapters**: Sudah tersedia adapter untuk pengembangan cepat (InMemory) dan produksi (PDO).
- **AggregateRoot Pattern**: Dukungan native untuk pola Aggregate Root dalam Domain-Driven Design (DDD), di mana state objek dibangun ulang dari sequence of events.
- **Snapshotting**: Mekanisme untuk mengambil snapshot state aggregate secara berkala, mempercepat proses rebuild state tanpa harus replay semua event sejak awal.

### C. CQRS (Command Query Responsibility Segregation)

- **Command Bus & Query Bus**: Komponen terpisah untuk menangani Command (perubahan state) dan Query (pembacaan data). Ini memastikan data tidak diubah secara tidak sengaja oleh query handler.
- **Handler Resolution**: Otomatis menyelesaikan handler command/query dari Container berdasarkan tipe command/query.

### D. Observability & Tracing

- **OpenTelemetry Integration**: Setiap event yang dipancarkan dapat dilacak sebagai span dalam distributed tracing system.
- **Event Logging**: Pencatatan event yang dipancarkan untuk debugging dan audit trail.

---

## 2. Fitur Enterprise yang Wajib Ada

Untuk menjadi sistem Event yang setara dengan Symfony EventDispatcher atau Laravel Event System, Zef perlu menambahkan fitur-fitur berikut:

### 🚨 fase 1: Advanced Listener Management (Kritis)

**Masalah:** Sistem event saat ini masih dasar dalam hal manajemen listener.
**Fitur yang Hilang:**

- **Wildcard Listener Support**: Kemampuan mendaftarkan listener untuk _group_ event (misal: `User.*` atau `Order.Created.*`). Ini mengurangi boilerplate kode saat satu handler perlu merespons banyak event dengan pola serupa.
- **Listener Removal/Runtime Modification**: Kemampuan untuk menghapus atau menambah listener secara dinamis saat runtime (berguna untuk plugin system atau modular architecture).
- **Listener Priority Grouping**: Mengelompokkan prioritas (misal: semua listener di grup "Logging" harus dijalankan sebelum grup "Processing").

### 🚨 fase 2: Dead Letter Queue & Retry Mechanism (Penting)

**Masalah:** Jika listener gagal (throw exception), apa yang terjadi? Saat ini belum ada mekanisme penanganan error yang robust untuk async events.
**Fitur yang Hilang:**

- **Automatic Retry with Exponential Backoff**: Jika listener gagal, event di-retry beberapa kali dengan jeda waktu yang meningkat (1s, 2s, 4s, dst).
- **Dead Letter Queue (DLQ)**: Jika retry maksimal tercapai, event dipindahkan ke "Dead Letter Queue" (tabel DB atau antrian terpisah) untuk dianalisis manual nanti, bukan hilang begitu saja.
- **Poison Pill Detection**: Mendeteksi event yang terus-menerus gagal dan secara otomatis menghentikan retry untuk mencegah loop tak terbatas.

### 🚨 fase 3: Event Serialization & Cross-Process Communication (Opsional tapi Penting)

**Masalah:** Event saat ini mungkin hanya bekerja dalam satu proses PHP. Bagaimana jika Kita menggunakan multiple workers?
**Fitur yang Hilang:**

- **Event Serialization**: Kemampuan mengubah event object menjadi JSON/String agar bisa dikirim antar-worker atau disimpan di Redis.
- **Cross-Process Event Bus**: Integrasi dengan Redis/RabbitMQ/Kafka untuk mengirim event antar-instan aplikasi yang berbeda.

### 🚨 fase 4: Transactional Event Handling (Penting)

**Masalah:** Jika event menyebabkan perubahan database (misal: listener mengirim email dan update log), dan salah satu gagal, bagaimana konsistensinya?
**Fitur yang Hilang:**

- **Transactional Event Dispatch**: Event hanya dipancarkan _setelah_ transaksi database berhasil commit. Jika transaksi rollback, event tidak pernah dipancarkan.
- **Outbox Pattern**: Simpan event di tabel "Outbox" dalam transaksi yang sama dengan perubahan data, lalu ada worker terpisah yang membaca dan mengirim event dari Outbox.

### 🚨 fase 5: Developer Experience & Debugging (DX)

**Masalah:** Debugging event yang kompleks sulit.
**Fitur yang Hilang:**

- **Event Traceability**: Melihat histori lengkap event yang dipancarkan, listener mana yang mengeksekusi, dan berapa lama waktunya.
- **Mock Listener for Testing**: Helper untuk mendaftarkan "Mock Listener" dalam test yang hanya mencatat event yang diterima tanpa eksekusi bisnis, memudahkan pengujian controller tanpa dependencies eksternal.

---

### Fase 6: Hardening Core Dispatcher (Minggu 1-2)

1.  **Wildcard Listener Support**: Tambahkan regex matcher untuk nama event.
2.  **Retry Mechanism**: Implementasi retry dengan exponential backoff untuk async events.
3.  **Dead Letter Queue**: Simpan event gagal ke tabel/queue khusus.

### Fase 7: Transactional & Serialization (Minggu 3-4)

1.  **Transactional Dispatch**: Integrasi dengan Database TransactionManager.
2.  **Serialization Engine**: Konversi event ke JSON dan sebaliknya.
3.  **Cross-Process Support**: Integrasi dengan Redis sebagai transport layer.

### Fase 8: Advanced DX & Testing (Minggu 5+)

1.  **Event Traceability**: Logging detail eksekusi listener.
2.  **Mock Listener**: Helper testing.
3.  **Event Profiling**: Metrics untuk melihat listener mana yang paling lambat.

---

## 4. Contoh Struktur Class Event Source Baru

```php
// src/Domain/Events/
├── Event.php                // Base interface/class
├── DomainEvent.php          // Event spesifik domain
└── SystemEvent.php          // Event internal framework

// src/Infrastructure/Event/
├── Dispatcher.php           // Core dispatcher logic
├── ListenerRegistry.php     // Manajemen listener & prioritas
├── RetryHandler.php         // Logika retry & DLQ
├── Serializer.php           // JSON/Serialization
└── TransactionalBus.php     // Integrasi transaksi DB

// src/Adapters/Event/
├── RedisEventTransport.php  // Transport untuk cross-process
└── FileEventStore.php       // Adapter untuk Event Sourcing
```

---

## Caratan

> **Perketat Penanganan Error (Retry/DLQ)**, **Flexibility Listener (Wildcard)**, dan **Cross-Process Communication**.

Dengan menambahkan fitur-fitur di atas, Zef Framework akan memiliki Event System yang tidak hanya cocok untuk arsitektur DDDD, tapi juga untuk microservices dan distributed systems modern.

---

---

# **Error Handling System (Enterprise-Grade)**

## 🎯 Visi

Membangun sistem Error Handling yang **Consistent** (format output sama di semua kondisi), **Context-Rich** (memberikan info cukup untuk debug tanpa bocor data sensitif), dan **Observability-Ready** (terintegrasi penuh dengan logging dan tracing).

---

## 🟢 Fase 1: Standardization & Core Structure (The Foundation)

_Fokus: Menyatukan semua jenis error ke dalam satu struktur data yang konsisten._

### 1.1 Unified Error Object (`ZefException`)

- **Base Exception Class**: Class `ZefException` sebagai induk semua exception framework.
- **Structured Payload**: Setiap exception harus menyimpan data standar:
  - `code`: Kode error unik (misal: `USER_NOT_FOUND`, `INVALID_TOKEN`).
  - `message`: Pesan ramah user (bukan stack trace).
  - `details`: Data teknis tambahan (opsional, untuk developer).
  - `trace_id`: ID request yang sedang berjalan (untuk tracing).
- **HTTP Status Mapping**: Otomatis memetakan tipe exception ke status code HTTP (misal: `ZefNotFoundException` -> 404, `ZefUnauthorizedException` -> 401).

### 1.2 RFC 9457 Compliance (Problem Details)

- **JSON Problem Response**: Hampir semua error API harus mengembalikan format JSON standar RFC 9457:
  ```json
  {
    "type": "https://zef.io/errors/not-found",
    "title": "Resource Not Found",
    "status": 404,
    "detail": "User with ID 123 not found.",
    "instance": "/api/v1/users/123",
    "trace_id": "abc-123"
  }
  ```
- **HTML Fallback**: Jika request meminta HTML (bukan JSON), tampilkan halaman error yang bersih dan branded (bukan stack trace PHP).

### 1.3 Exception Hierarchy

Buat hierarki exception yang logis, bukan satu class besar:

- `ZefException` (Induk)
  - `ZefValidationException` (Input salah) -> 400
  - `ZefAuthenticationException` (Akses ditolak) -> 401
  - `ZefAuthorizationException` (Hak akses kurang) -> 403
  - `ZefNotFoundException` (Resource tidak ada) -> 404
  - `ZefMethodNotAllowedException` -> 405
  - `ZefConflictException` -> 409
  - `ZefRateLimitException` -> 429
  - `ZefInternalException` (Bug framework/app) -> 500

---

## 🟡 Fase 2: The Handler Chain (The Logic)

_Fokus: Bagaimana error ditangkap dan diproses._

### 2.1 Global Error Handler Middleware

- **Single Entry Point**: Satu middleware yang membungkus seluruh pipeline. Jika exception muncul di mana saja, ditangkap di sini.
- **Context Preservation**: Middleware ini harus memiliki akses ke `Request ID` dan `Logger` untuk melacak error.
- **Graceful Degradation**: Jika error terjadi saat generating error response (rare case), fallback ke plain text error sederhana.

### 2.2 Custom Exception Mapping

- **Map Third-Party Exceptions**: Konversi exception dari library pihak ketiga (misal: `PDOException`, `GuzzleException`) menjadi `ZefException` standar.
  - Contoh: `PDOException` -> `ZefInternalException` (jika SQL error umum) atau `ZefDatabaseException` (jika bisa ditangani spesifik).
- **HttpKernel Exception Listener**: Pola Symfony di mana exception tertentu memicu handler spesifik (misal: `MethodNotAllowedException` memicu handler khusus yang mengisi header `Allow`).

### 2.3 Debug Mode vs Production Mode

- **Debug Mode (Dev)**:
  - Tampilkan Stack Trace lengkap.
  - Tampilkan variabel lokal (jika aman).
  - Tampilkan Timeline eksekusi request.
  - Format: HTML dengan toolbar debug (seperti Symfony Profiler).
- **Production Mode**:
  - Tampilkan hanya RFC 9457 JSON.
  - **Jangan** pernah bocorkan stack trace ke client.
  - Log detail ke server-side logger.

---

## 🟠 Fase 3: Observability & Logging (The Eyes)

_Fokus: Memastikan setiap error tercatat dan bisa ditracing._

### 3.1 Structured Logging

- **JSON Logs**: Log error dalam format JSON agar mudah diparse oleh ELK Stack/Datadog.
- **Context Enrichment**: Setiap log error harus menyertakan:
  - `request_id`
  - `user_id` (jika ada)
  - `ip_address`
  - `user_agent`
  - `exception_class`
  - `exception_code`
- **Log Levels**:
  - 4xx Client Errors -> `WARNING`
  - 5xx Server Errors -> `ERROR`
  - Fatal/Uncaught -> `CRITICAL`

### 3.2 Distributed Tracing Integration

- **Span Creation**: Saat exception terjadi, tutup span OpenTelemetry dengan status `Error`.
- **Error Attribute**: Simpan `exception.message` dan `exception.type` sebagai attribute pada span.
- **Trace Propagation**: Pastikan `trace_id` dari error response sama dengan trace ID di log server.

### 3.3 Error Analytics Dashboard (Opsional)

- **Error Rate Metrics**: Kirim jumlah error per tipe ke Prometheus.
  - `zef_exceptions_total{type="NotFound", route="/api/users"}`
- **Top Errors**: Dashboard untuk melihat error apa yang paling sering terjadi.

---

## 🔵 Fase 4: Developer Experience & Tooling (The UX)

_Fokus: Memudahkan developer saat debugging._

### 4.1 Debug Toolbar (Zef Profiler)

- **In-Page Toolbar**: Di mode debug, tampilkan toolbar kecil di bawah halaman yang menampilkan:
  - Waktu eksekusi request.
  - Jumlah query DB.
  - Jumlah exception yang terjadi.
  - Link ke log error terkait.
- **Click-to-View**: Klik pada exception di toolbar untuk melihat detail lengkap di panel sisi kanan.

### 4.2 CLI Error Inspector

- **Command `bin/zef errors:report`**: Menampilkan ringkasan error terbaru dari log.
- **Command `bin/zef errors:clear`**: Membersihkan queue error yang tertunda (jika ada).

### 4.3 Local Error Replay

- **Save/Replay Feature**: Developer bisa "save" request yang error (termasuk headers dan body) dan "replay" untuk debugging tanpa perlu reproduksi manual.

---

## 📊 Matriks Kesiapan: Error Handling System

| Fitur                   | Status Saat Ini | Target Roadmap                     | Prioritas  |
| ----------------------- | --------------- | ---------------------------------- | ---------- |
| **RFC 9457 Format**     | ✅ Ada (Basic)  | ✅ Complete (JSON + HTML Fallback) | **High**   |
| **Exception Hierarchy** | ❌ Belum        | ✅ ZefException & Subclasses       | **High**   |
| **Debug/Prod Mode**     | ❌ Belum        | ✅ Distinct Behaviors              | **High**   |
| **Structured Logging**  | ⚠️ Partial      | ✅ JSON + Context Enrichment       | **High**   |
| **Tracing Integration** | ⚠️ Partial      | ✅ Span Closing on Error           | **Medium** |
| **Debug Toolbar**       | ❌ Belum        | ✅ Zef Profiler                    | **Medium** |
| **Third-Party Mapping** | ❌ Belum        | ✅ PDO/Guzzle -> Zef               | **Medium** |
| **Error Analytics**     | ❌ Belum        | ✅ Prometheus Metrics              | **Low**    |

---

## 💡 Saran Eksekusi Awal

1.  **Buat `ZefException.php`**: Class dasar dengan property `code`, `message`, `details`.
2.  **Buat `ErrorHandlerMiddleware.php`**: Middleware yang menangkap `Throwable`, cek mode debug, dan return response sesuai RFC 9457.
3.  **Buat Exception Map**: Array mapping dari class exception ke status code.
4.  **Test dengan Postman**: Coba request ke endpoint yang error, pastikan response JSON-nya rapi dan konsisten.

---

---

# **Module Integration System (Hexagonal Plug-n-Play)**

Di Zef Framework, kita tidak butuh HMVC. Kita butuh **Module Contract** yang patuh pada prinsip Hexagonal Architecture: **Domain tidak boleh tahu tentang Module, tapi Module boleh mendaftarkan diri ke Domain/Infrastructure.**

Berikut adalah desain lengkap sistem modul "Plug-n-Play" untuk Zef.

---

## 🎯 Konsep Inti: "Self-Registering Modules"

Lupakan `register()` manual atau service provider tradisional. Di Zef, sebuah modul adalah **kotak hitam** yang hanya perlu memenuhi satu kontrak:

> _"Saya tahu siapa saya, apa yang saya butuhkan, dan bagaimana cara menyuntikkan diri ke dalam container/router/event bus."_

### Perbedaan HMVC vs Zef Module System

| Aspek             | HMVC Tradisional                                        | Zef Hexagonal Module                                    |
| ----------------- | ------------------------------------------------------- | ------------------------------------------------------- |
| **Struktur**      | Folder `/modules/payment/` berisi MVC lengkap           | Modul = Package Composer / Folder dengan `module.php`   |
| **Isolasi**       | Modul bisa panggil modul lain langsung (tight coupling) | Modul hanya bicara via Interface/Event (loose coupling) |
| **Bootstrapping** | Router HMVC mendeteksi folder                           | Kernel memuat daftar modul dari config                  |
| **Dependency**    | Sering global state                                     | Selalu via DI Container & Ports                         |
| **Portability**   | Sulit dipindah antar project                            | 100% portable sebagai composer package                  |

---

## 🏗️ Arsitektur Desain

### 1. The Module Contract (`Zef\Contracts\ModuleInterface`)

Ini adalah SATU-SATUNYA hal yang harus diimplementasikan oleh developer modul. Tidak ada inheritance berat, hanya interface murni.

```php
interface ModuleInterface {
    // Metadata: nama, versi, dependencies modul lain
    public function metadata(): ModuleMetadata;

    // Lifecycle: Dipanggil saat kernel boot
    public function register(ContainerInterface $container): void;

    // Lifecycle: Dipanggil setelah SEMUA modul terdaftar
    // Gunakan ini untuk definisi route, event listener, config schema
    public function boot(Router $router, EventBus $events, ConfigLoader $config): void;
}
```

### 2. The Module Registry (Kernel Component)

Komponen baru di `src/Adapters/Kernel/ModuleRegistry.php`. Tugasnya:

- Membaca daftar modul dari konfigurasi
- Memvalidasi dependency antar modul (topological sort)
- Memanggil `register()` semua modul → lalu `boot()` semua modul
- Menjamin urutan: Core → Infrastructure → Feature Modules

### 3. The Module Manifest (`module.php`)

Setiap modul menyediakan file manifest deklaratif. Ini memungkinkan **discovery tanpa refleksi** dan mendukung AOT compilation:

```php
// modules/payment/module.php
return [
    'name' => 'zef/payment',
    'version' => '^1.0',
    'requires' => ['zef/database', 'zef/events'],  // Dependency check
    'config_schema' => './config/schema.php',       // Validasi config
    'routes' => './config/routes.php',              // Auto-register routes
    'events' => [                                   // Auto-register listeners
        OrderPaid::class => SendReceiptListener::class,
    ],
];
```

---

## 🟢 Fase 1: Core Module Engine (The Foundation)

### 1.1 Module Discovery & Loading

- **Config-Driven Registration**: Daftar modul didefinisikan di `config/modules.php`, bukan hasil scan folder otomatis. Ini penting untuk production performance dan security (whitelist).
- **Composer Package Support**: Modul bisa berupa folder lokal ATAU composer package. Registry mendeteksi keduanya via class name.
- **Topological Sort Dependencies**: Jika modul A require B, pastikan B di-boot duluan. Deteksi circular dependency antar modul saat boot dengan error jelas.

### 1.2 Two-Phase Bootstrapping

- **Phase 1 - Register**: Semua modul mendaftarkan service ke Container. Belum ada service yang di-resolve. Ini menjamin binding apa pun bisa di-override sebelum instance dibuat.
- **Phase 2 - Boot**: Setelah semua modul register, barulah router/listener/config dijalankan. Ini mencegah race condition di mana modul A butuh route dari modul B yang belum terdaftar.

### 1.3 Configuration Namespacing

- **Auto-Namespace Config**: Config modul `payment` otomatis berada di bawah key `payment.*`. Tidak ada tabrakan nama dengan core atau modul lain.
- **Schema Validation per Module**: Setiap modul wajib provide schema. Jika user salah isi config modul, error muncul spesifik menyebut modul mana yang bermasalah.

---

## 🟡 Fase 2: Extension Points (The Plug-in Surface)

Modul tidak boleh menyentuh internals framework. Mereka hanya boleh menggunakan **Extension Points** resmi:

### 2.1 Route Extension Point

- Modul mengembalikan array rute dari manifest → Kernel menggabungkannya ke Radix Tree saat compile.
- Mendukung prefix otomatis per modul (misal: semua rute payment otomatis di bawah `/payment`).

### 2.2 Event Listener Extension Point

- Deklaratif di manifest: `'OrderPaid::class => Listener::class'`.
- Kernel mendaftarkan ke EventBus secara massal, lebih cepat daripada registrasi runtime satu-per-satu.

### 2.3 Service Decoration Extension Point

- Modul bisa mendekorasi service milik modul lain atau core.
- Contoh: Modul audit mendekorasi `LoggerInterface` untuk menambahkan tenant ID ke semua log.

### 2.4 CLI Command Extension Point

- Modul mendaftarkan command ke `bin/zef` registry.
- Perintah `bin/zef list` otomatis menampilkan command dari semua modul aktif.

### 2.5 Migration Extension Point

- Modul menyimpan migration di folder sendiri.
- Migrator agregat migration dari semua modul + core, dengan tracking namespace per modul.

---

## 🟠 Fase 3: Isolation & Governance (Enterprise Control)

### 3.1 Module Sandboxing

- **No Global State Access**: Modul dilarang akses superglobal. Hanya boleh terima data via constructor injection.
- **Readonly Module Context**: Modul menerima objek `ModuleContext` readonly berisi path, config, dan logger miliknya sendiri.

### 3.2 Health Check Aggregation

- Modul implement `HealthCheckableInterface` → health endpoint `/healthz` otomatis agregat status semua modul.
- Jika modul payment down, response tetap 200 tapi detail payment = degraded.

### 3.3 Feature Flag per Module

- Modul bisa di-enable/disable via env var tanpa hapus kode.
- Disabled module = tidak di-register, tidak ada overhead memori sama sekali.

### 3.4 Module Audit Trail

- Log setiap aktivitas bootstrapping modul: waktu load, jumlah service didaftarkan, error encountered.
- Berguna untuk debugging startup time di production.

---

## 🔵 Fase 4: Developer Experience (DX)

### 4.1 Module Generator

- `bin/zef make:module payment` → scaffold struktur folder lengkap dengan `module.php`, schema, routes, tests.

### 4.2 Module Inspector CLI

- `bin/zef module:list --json` → tampilkan semua modul aktif, versi, dependency graph.
- `bin/zef module:doctor` → cek konflik dependency, config missing, version mismatch.

### 4.3 Hot-Reload in Development

- File watcher mendeteksi perubahan `module.php` → re-boot modul terkait saja (tanpa restart full kernel), memanfaatkan RoadRunner persistent worker.

---

## 📊 Matriks Kesiapan: Module System

| Fitur                     | Status Saat Ini                     | Target Roadmap                   | Prioritas  |
| ------------------------- | ----------------------------------- | -------------------------------- | ---------- |
| **ModuleInterface**       | ❌ Belum                            | ✅ Kontrak Minimal               | **High**   |
| **Two-Phase Boot**        | ⚠️ Partial (ModuleBootstrapper ada) | ✅ Full Register/Boot Split      | **High**   |
| **Manifest Declaration**  | ❌ Belum                            | ✅ module.php deklaratif         | **High**   |
| **Dependency Resolution** | ❌ Belum                            | ✅ Topological Sort              | **Medium** |
| **Config Namespacing**    | ❌ Belum                            | ✅ Auto-prefix per modul         | **Medium** |
| **Extension Points**      | ⚠️ Manual wiring                    | ✅ Routes/Events/CLI declarative | **Medium** |
| **Module Doctor CLI**     | ❌ Belum                            | ✅ Conflict detection            | **Low**    |
| **Hot Reload Dev**        | ❌ Belum                            | ✅ RR-aware partial reload       | **Low**    |

---

## 💡 info

> **"Plug-n-play biasanya HMVC"** — Itu karena HMVC mudah dibuat tapi sulit dibersihkan.

Solusi Zef: **Pakai HMVC sebagai BATAS FISIK modul (folder terpisah), tapi pakai Hexagonal sebagai BATAS LOGIS (interface-only communication).**

Praktisnya:

1.  Modul punya folder sendiri → itu "HMVC-like" untuk organisasi kode.
2.  Modul TIDAK PERNAH import class dari modul lain secara langsung → itu hexagonal purity.
3.  Komunikasi antar modul HANYA via Events, Interfaces di shared contract layer, atau Container bindings.

Dengan begini, modul payment bisa dihapus total tanpa mengubah satu baris pun di modul user. Itulah plug-n-play sejati.

---

---

# **Adapters Layer (The Bridge to Ecosystem)**

Dalam arsitektur **Hexagonal**, bagian **Adapters** adalah "pelabuhan" tempat dunia luar (library pihak ketiga) bertemu dengan dunia dalam (Core Zef).

Dengan membuat Adapters terpisah, Kita mendapatkan dua keuntungan besar:

1.  **Core Tetap Bersih**: Kelas-kelas inti (`Domain`, `Application`) tidak pernah diimpor `use Carbon\Carbon` atau `use Illuminate\Database`.
2.  **Swapability**: Besok Kita bosan dengan View Engine A, Kita tinggal ganti Adapter View Engine A dengan B tanpa mengubah satu baris kode di Controller atau Router.

Berikut adalah desain lengkap untuk **View Engine** dan **Database Layer** Adapters.

---

## 🏗️ Prinsip Dasar Adapters Zef

### 1. Port & Adapter Pattern (DDD)

- **Port (Interface)**: Didefinisikan di `src/Domain/Ports/` atau `src/Application/Ports/`. Ini adalah kontrak yang **tidak boleh berubah**.
- **Adapter (Class)**: Didefinisikan di `src/Infrastructure/View/` atau `src/Infrastructure/Database/`. Ini adalah implementasi konkret.

### 2. Dependency Injection

- Core framework TIDAK tahu adapter mana yang dipakai.
- Container Zef melakukan binding: `Container::bind(ViewEngineInterface::class, TwigAdapter::class)`.

### 3. Zero-Copy Data Transfer

- Data dari Domain (Entity/Value Object) harus bisa dikonversi ke format yang dimengerti Adapter (Array/JSON) dengan efisien.

---

## 1. View Engine Adapter (The Presentation Layer)

### A. Port Interface (`ViewEngineInterface`)

Jangan terikat pada cara kerja framework lain. Buat interface yang sederhana dan universal.

```php
namespace Zef\Framework\Domain\Ports;

interface ViewEngineInterface {
    /**
     * Render template dengan data kontekstual.
     */
    public function render(string $template, array $data): string;

    /**
     * Register global variables/helpers untuk semua template.
     */
    public function share(string $key, mixed $value): void;

    /**
     * Register custom function/filter dari library pihak ketiga.
     */
    public function registerFunction(string $name, callable $callback): void;
}
```

### B. Fitur yang Harus Didukung

#### 1. Template Discovery

- **Namespace-based Path**: Mendukung prefix untuk menghindari konflik nama antar modul.
  - Contoh: `payment::invoice.html` (dari modul payment), `core::layout.html` (dari core).
- **Fallback Search**: Jika template tidak ditemukan di namespace modul, cari di global view directory.

#### 2. Data Binding & Security

- **Auto-Escaping**: Otomatis melindungi dari XSS. (Bisa dikontrol: `{{ $user->name }}` vs `{{ $user->name|raw }}`).
- **Lazy Loading Variables**: Variabel hanya di-render jika dipanggil dalam template (untuk performa).

#### 3. Layout & Composition

- **Block/Slot System**: Mendukung struktur layout (header, footer, content) agar modular.
- **Template Inheritance**: Child template bisa extend parent template.

#### 4. Performance

- **Compiled Templates**: Jika library pihak ketiga tidak punya cache, Zef Adapter bisa mengkompilasi template ke PHP opcode native (mirip Blade).

### C. Contoh Implementasi (Adapter Structure)

```php
// src/Infrastructure/View/TwigAdapter.php
class TwigAdapter implements ViewEngineInterface {
    public function __construct(private \Twig\Environment $twig) {}

    public function render(string $template, array $data): string {
        return $this->twig->render($template, $data);
    }
    // ... implementasi lainnya
}

// src/Infrastructure/View/BladeAdapter.php
class BladeAdapter implements ViewEngineInterface {
    public function __construct(private \Illuminate\View\Factory $factory) {}
    // ... implementasi lainnya
}
```

---

## 2. Database Layer Adapter (The Persistence Layer)

Ini adalah bagian paling kritis. Database di Zef harus bersifat **Domain-Driven**, bukan sekadar CRUD.

### A. Port Interface (`RepositoryInterface` & `EntityManagerInterface`)

Jangan gunakan interface `PDO` atau `mysqli` langsung di Domain. Abstraksi harus berbasis **Entity**.

#### 1. Generic Repository Pattern (Opsional tapi Direkomendasikan)

```php
namespace Zef\Framework\Domain\Ports;

interface RepositoryInterface {
    public function find(mixed $id): ?object;
    public function findAll(array $criteria = [], array $orderBy = []): array;
    public function save(object $entity): object;
    public function delete(mixed $id): bool;
}
```

#### 2. Query Builder Interface (Untuk Query Kompleks)

```php
interface QueryBuilderInterface {
    public function select(string ...$columns): self;
    public function from(string $table): self;
    public function where(string $column, mixed $value): self;
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function get(): array;
    public function first(): ?array;
}
```

### B. Fitur yang Harus Didukung

#### 1. Connection Pooling & Management

- **Persistent Connection**: Untuk RoadRunner/Swoole, koneksi harus reuse, bukan close-open setiap request.
- **Auto-Reconnect**: Jika koneksi mati, adapter harus mencoba reconnect sebelum throw error fatal.

#### 2. Transaction Management

- **Ambient Transaction**: Transaksi yang otomatis terbawa oleh context request.
- **Nested Savepoints**: Mendukung transaksi bersarang (misal: Controller start trans, Service dalam transaksi itu juga start trans = savepoint).

#### 3. Schema & Migration Integration

- **Migration Runner**: Adapter harus punya method untuk menjalankan migration file.
- **Schema Reflection**: Kemampuan membaca struktur tabel existing untuk validasi atau auto-generate entity.

#### 4. Data Type Mapping

- **Native Type Conversion**: Konversi otomatis tipe data PHP (DateTime, Bool, Int) ke tipe DB (Timestamp, TINYINT, INT) dan sebaliknya.
- **JSON Support**: Simpan/ambil data JSON dengan aman.

#### 5. Observability

- **Query Logging**: Log setiap query SQL (dengan placeholder) untuk debugging.
- **Slow Query Detection**: Log query yang melebihi waktu tertentu.

### C. Contoh Implementasi (Adapter Structure)

```php
// src/Infrastructure/Database/PdoAdapter.php
class PdoAdapter implements EntityManagerInterface {
    private PDO $connection;

    public function __construct(private array $config) {
        // Setup PDO dengan konfigurasi aman
    }

    public function transactional(callable $callback): mixed {
        return $this->connection->transaction($callback);
    }

    public function query(string $sql, array $params = []): QueryBuilderInterface {
        return new PdoQueryBuilder($this->connection, $sql, $params);
    }
}
```

---

## 🟢 Roadmap Implementasi Adapters

### Fase 1: Definisi Port (Minggu 1)

1.  Buat `ViewEngineInterface` di `src/Domain/Ports/`.
2.  Buat `EntityManagerInterface` dan `RepositoryInterface` di `src/Domain/Ports/`.
3.  Pastikan interface ini **stabil** dan **tidak berubah** setelah ini.

### Fase 2: Implementasi Adapter Default (Minggu 2-3)

1.  **View**: Pilih satu engine populer (misal: **Twig** atau **Blade**). Buat `TwigAdapter`.
    - Pastikan bisa render template sederhana dengan layout.
2.  **Database**: Gunakan **PDO Native** (bukan ORM berat dulu). Buat `PdoAdapter` yang mendukung:
    - Select/Insert/Update/Delete dasar.
    - Transaction.
    - Query Builder sederhana.

### Fase 3: Integrasi Container (Minggu 4)

1.  Di `Container.php`, tambahkan binding:

    ```php
    $container->bind(ViewEngineInterface::class, function($c) {
        return new TwigAdapter($c->get('twig.config'));
    });

    $container->bind(EntityManagerInterface::class, function($c) {
        return new PdoAdapter($c->get('database.config'));
    });
    ```

2.  Tes apakah Controller bisa mengakses view dan database tanpa tahu adapter mana yang dipakai.

### Fase 4: Testing & Swapability (Minggu 5)

1.  Buat Mock Adapter untuk Testing.
2.  Coba ganti `TwigAdapter` dengan `BladeAdapter` hanya dengan mengubah konfigurasi di file config, tanpa ubah kode lain.

---

## 💡 Saran Teknis "Pro"

1.  **Jangan Pakai ORM Berat Awal**: Untuk Zef, saya sarankan mulai dengan **Query Builder sederhana** + **Active Record Pattern** ringan. ORM seperti Eloquent atau Doctrine terlalu berat untuk framework yang ingin cepat dan ringan. Kita bisa buat ORM layer di atas Query Builder nanti jika perlu.
2.  **View Engine: Twig vs Blade**:
    - **Twig**: Lebih stabil, tipe aman, performa tinggi. Cocok untuk enterprise.
    - **Blade**: Lebih mirip PHP, mudah bagi developer PHP, tapi kadang "kurang aman" jika tidak hati-hati.
    - _Rekomendasi_: **Twig** untuk stabilitas, atau buat **Custom Minimalist Engine** (seperti Zef sendiri) jika ingin zero-dependency.
3.  **Database: PDO vs Library**:
    - Gunakan **PDO Native** dengan wrapper rapi. Hindari `Doctrine DBAL` atau `Laravel Query Builder` sebagai dependensi inti. Biarkan Zef punya Query Builder sendiri yang ringan.
    - Inspirasi database library `Pixie Query Builder`

---

## Kesimpulan

> **Adapters adalah "Wajah Zef di Depan Umum".**

Dengan desain ini, Zef Framework menjadi **Fleksibel**. Kita bisa menjual Zef sebagai "Framework Ringan", tapi karena punya Adapters, user bisa pakai library seberat apa pun di dalamnya tanpa merusak inti framework.

---
---
# **ATURAN VERSI**

- Vx.x.x {Release.Featured.FixedIssue}

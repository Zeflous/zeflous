# CI Notes — Integration (The Triad, langkah 5)

Decision record untuk lane Integrasi Loader → Engine → Dispatcher di bawah
**Configuration System** (`src/Zef/Framework/Config/Routing/`). Dokumen ini melengkapi
`docs/ci-notes/config-system.md` (KDD-1..7), `docs/ci-notes/radix-engine.md` (KDD-R1..R9)
dan `docs/ci-notes/dsl-builder.md` (KDD-D1..D8).

## KDD-I1..I6

- **I1 (addressing)**: `RoutingRuntime::fromConfig()` mengikuti semantik
  `RouteSchema::assertList()` — list ATAU map named-entries sama-sama valid ("the schema
  governs the entries, not the addressing"); map dinormalkan ke list via `array_values`
  dan KUNCINYA DIBUANG, karena nama rute adalah field `name` entri (satu sumber kebenaran:
  name index compile-time milik engine — preseden keputusan D4 lane DSL yang tidak
  mengadopsi nameIndex sendiri). Key `routes` absen = tabel nol-rute valid (konsisten
  `ConfigBootstrapper`); non-array eksplisit (scalar/null) = defect konfigurasi →
  `ConfigException` fail-closed dengan pesan milik lapisan schema. Entri TIDAK
  divalidasi ulang oleh runtime: schema validation milik bootstrapper di pipeline normal,
  dan `CompiledRoute::fromArray()` tetap jaring pengaman fail-closed atas repositori yang
  melewatkannya — satu titik kebijakan per lapisan.
- **I2 (compile-once)**: tree dikompilasi **paling banyak sekali per instance** (lazy,
  memoized — `tree()`/`match()`/`route()`/`url()` menjawab dari satu tree immutable).
  Hot-reload = instance baru (`fromConfig` atas repositori yang dibangun ulang); instance
  hidup tidak pernah di-read/di-compile ulang. Kelas sengaja tidak `final readonly`:
  tree memoized adalah state mutable-by-design (preseden `RouteDslScope`). Generator URL
  adalah VO stateless atas tree — `url()` membangun satu per panggilan sehingga permukaan
  mutable runtime tinggal kompilasi tunggal itu. `RoutingRuntimeBench` mencatat ekonominya
  secara jujur: match via memoisasi flat (4.1μs @15 rute, 5.8μs @300) vs rebuild per
  request (185μs / 4.070μs) — harga kompilasi terlihat, bukan disembunyikan.
- **I3 (handler resolution container-mediated)**: handler string diselesaikan LEWAT
  container, dua bentuk: (a) **service ID callable** — `has`+`get`, hasil wajib callable;
  (b) **`Class@method`** — instance diresolve dari container dengan ID = nama kelas
  (bentuk yang dipancarkan resource macro dan contoh ROADMAP: `PhotosController@index`).
  TIDAK ada instansiasi reflection bebas — lahan autowiring Container (ROADMAP area 2)
  akan melebarkan seam yang sama nanti; kelahiran kelas via reflection tanpa DI bukan
  kontrak framework dan tidak fail-closed. Precedence: service ID didahulukan —
  `Users@show` yang terdaftar sebagai ID dipakai sebagai service; yang tidak terdaftar
  jatuh ke bentuk pasangan. Semua kegagalan fail-closed dengan pesan eksak menyebut
  handler (pola legacy `Dispatcher`: resolve via container, tolak keras kalau bukan
  kontrak handler).
- **I4 (kontrak handler)**: handler menerima **satu argumen** `array<string,string>`
  (segmen tertangkap keyed nama) dan mengembalikan `mixed` — kontrak lapisan routing
  murni. Kontrak HTTP (Request→Response handler, eksekusi middleware) datang bersama
  lane HTTP Layer / Middleware Pipeline. Middleware rute TIDAK dieksekusi lane ini
  (milik lane Middleware Pipeline) tapi TIDAK di-silent-kan: rute termacth terbawa di
  outcome executed sehingga `middleware()`/`meta()` tetap terbaca lapisan berikutnya —
  pembelajaran P0-1 legacy (metadata yang tampil tapi tak pernah dieksekusi = celah).
- **I5 (outcome eksplisit)**: `dispatch()` mengembalikan `DispatchOutcome` —
  `executed(route, parameters, result)` / `notFound` / `methodNotAllowed(allowedMethods)`;
  tanpa sentinel null/bool; aksesor spesifik-state menolak data yang tidak dipegangnya
  dengan `InvariantViolationException` (pola `MatchResult` persis).
- **I6 (normalisasi resolver = VO)**: resolver mengembalikan `RouteHandler` — VO
  `@internal` dengan `__invoke(array): mixed` — bukan `Closure`/`callable` polos:
  phpstan max menuntut signature callable pada return (`missingType.callable`) sementara
  psalm menolak coercion `Closure(shape)` vs infer `Closure` generik dari
  `Closure::fromCallable` (terverifikasi empiris: @return shape → psalm
  MixedReturnTypeCoercion; @psalm-return override → phpstan ikut membacanya). Jalan
  keluar struktural: closure literal `static fn (array $p): mixed => $callable($p)`
  membungkus callable resolved — signature Closure eksplisit dari definisi fungsi,
  diterima kedua analyzer TANPA suppress/tag-trick (repo: nol `@psalm-suppress`).
  Preseden tipe: `Closure(Container): mixed` hanya pernah jadi property/param ber-shape
  (`ServiceStore::set`), tidak pernah return.

## Pola teknis yang terbukti (dibawa ke lane berikutnya)

- **Bentuk closure killer untuk mutan `explode` limit**: mutan `IncrementInteger`
  (limit 2→3) hanya terbedakan oleh handler BER-DUA separator yang kelasnya terdaftar —
  limit 2 menjaga pasangan (class, `method@x` → "does not resolve to a callable"), limit 3
  menghasilkan count 3 (→ "not a registered service"). Dua pesan berbeda = kill eksak.
- **Fixture di luar scope coverage**: `tests/Support/*` tidak masuk `<source>` phpunit —
  `#[CoversClass]` atas kelas fixture = warning "not a valid target for code coverage"
  → `failOnWarning=true` memfail coverage gate. Fixture CUKUP dieksekusi, tidak
  dideklarasikan.
- **Import wajib untuk `::class` di attribute**: `CoversClass(RouteHandler::class)` tanpa
  `use` men-resolve FQN namespace test → warning invalid target yang sama.
- **phpstan↔psalm callable**: lihat I6 — VO ber-`__invoke` adalah pemutus konflik
  signature-vs-coercion yang paling bersih; jangan mulai `@psalm-suppress` (repo nol
  suppress, jaga tetap nol).
- **Baseline shrink sebagai efek samping sehat**: memakai `Container::has()` menghidupkan
  method interface `has` → entry `PossiblyUnusedMethod` psalm-baseline usang → error
  `UnusedBaselineEntry` → hapus entry-nya (baseline mengecil, bukan membesar).
- **Deptrac perluasan satu arah**: `Config += Container` untuk seam runtime (resolver
  handler) — cermin `Kernel += Config`; container sendiri tidak pernah bergantung
  configurasi. Komentar ruleset menunjuk keputusan ini.

## Atribusi

Pola desain (bukan kode — doktrin zero-dependency tetap: `require` hanya `php: ^8.4`)
diinspirasi implementasi terbukti pada repo legacy `Zeflous/zef-framework`:
`Adapters/Kernel/Dispatcher` (handler = service ID di-resolve via container, gagal
keras kalau bukan kontrak handler — InvalidConfigurationException; terminal handler
resolution; per-route middleware sebagai inner pipeline — bagian pipeline TIDAK diadopsi
lane ini, menunggu lane Middleware Pipeline-nya sendiri). `ROADMAP.md` §"Standard Route
Schema" (handler = "String Class@Method atau Closure ID") dan §"Integrasi Penuh" (tabel
5 tahap: Development → Build/Cache → Validation → Bootstrapping → Runtime) adalah
spesifikasi yang ditutup lane ini dari sisi Configuration System.

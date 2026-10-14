# CI Notes — Middleware Pipeline & Config-Driven Container (PR-O)

Decision record untuk dua lane yang berdampingan sesuai arah pengguna
("keduanya berdampingan, juga buat agar config driven pada keduanya"):
**Middleware Pipeline** (`src/Zef/Framework/Middleware/`) dan
**Container definisi deklaratif** (`src/Zef/Framework/Container/Definition*` +
`src/Zef/Framework/ContainerBootstrapper`). Dokumen ini melengkapi
`docs/ci-notes/http-layer.md` (KDD-H1..H18) — pipeline adalah lane eksekusi
di atas PSR-15 shim yang sudah tuntas.

## KDD-M1..M9

- **M1 (config-driven keduanya, satu repositori)**: kedua lane membaca
  repositori konfigurasi beku yang sama, kunci berbeda: `services`
  (definisi deklaratif container) dan `middleware.stack` (urutan pipeline).
  Glue komposisinya = `Kernel` layer, BUKAN method container:
  `ContainerBootstrapper::bootstrap(Config, Container)` menginstal definisi
  melalui permukaan publik `set()` yang sudah ada. Container sendiri
  TIDAK bergantung Config (deptrac: `Container: [Contracts, Exception]`
  tetap) — grammar definisi adalah data murni; keputusan build-order
  CONTRIBUTING.md aturan 2 dipertahankan. `PipelineFactory` (layer
  Middleware baru) memang menerima `Config` langsung — konstruksi terpisah
  dari eksekusi (pola `PipelineFactory` legacy), dan layer Middleware
  sengaja berdiri DI ATAS Config/Container/Http (`Middleware:
  [Exception, Container, Config, Http]`): ia konsumen, bukan fondasi.
- **M2 (grammar definisi = empat shape, tepat satu)**: `['class' => FQCN,
  'arguments' => [...]]` / `['factory' => 'Target::method', 'arguments' =>
  [...]]` / `['alias' => id]` / `['value' => mixed]`. Kunci shape GANDA
  ditolak (ambiguitas, bukan komposisi), kunci TAK DIKENAL ditolak dengan
  daftar kunci yang dikenal (gaya schema layer), `class` wajib
  `class_exists` SAAT PARSE (definisi yang lolos boot hanya bisa gagal
  lewat referensi — yang sudah dibuktikan graph), `factory` wajib tepat
  satu `::`. Kunci `group`/`tags` legacy SENGAJA tidak diport: di legacy
  keduanya parsed-validated-reserved tanpa perilaku (issue #174) —
  membawa grammar mati ke depan adalah spekulatif.
- **M3 (referensi = satu bentuk, satu semantik)**: argumen `['service' =>
  id]` adalah SATU-satunya bentuk referensi; map berisi kunci `service`
  plus kunci lain DITOLAK (bentuk referensi memiliki seluruh map —
  ambiguitas diangkat ke permukaan, tidak ditebak). Semua referensi
  diselesaikan SAAT INSTANSIASI (bukan parse), jadi definisi dapat
  dideklarasikan dalam urutan apa pun — topologi divalidasi di muka,
  pembuatan tetap lazy sesuai semantik memoisasi container.
- **M4 (graph boot-time, dua kegagalan)**: `ServiceDefinitionGraph` buktikan
  (a) setiap referensi resolvable (definisi lain, ATAU service closure
  terdaftar — leaf host-app; leaf tidak bisa divalidasi lebih jauh dan
  memang bukan tanggung jawab grammar), (b) **tanpa siklus** — rantai
  ditolak dengan jalur yang DIJALANKAN (`a -> b -> c -> a`). Validasi
  berjalan SEBELUM satu pun definisi diinstal (transaksi-or-nothing:
  sibling yang valid tidak bocor ke container saat set-nya ditolak —
  diuji). Diamond (shared dependency) jelas bukan siklus. Hanya edge
  definisi-ke-definisi yang bisa bersiklus; edge ke service closure
  tidak pernah masuk siklus-check.
- **M5 (factory precedence: service mengalahkan class)**: target factory
  yang TERDAFTAR di container diresolve dan dipanggil sebagai method
  instance; target berupa nama kelas dipanggil statis. `DefinitionReferences`
  (untuk graph) dan `DefinitionCompiler` (untuk eksekusi) memakai
  presedensi yang sama — nama kelas yang juga terdaftar sebagai service
  adalah SERVICE, dan fallback statis tetap ada untuk kelas murni.
  Konsekuensi graph: target berupa kelas BUKAN edge container (tidak
  butuh registrasi, tidak bisa bersiklus) — diuji dua arah. Method
  hilang gagal tertutup dengan pesan menyebut target+method, dua cabang
  (service dan kelas) masing-masing diuji.
- **M6 (pipeline = port perilaku verbatim, narrowing kontrak)**:
  `MiddlewarePipeline` final-readonly, advance indeks LEWAT `new self(...)`
  (clone-on-write, bukan `$index++`), terminal tunggal, stack-habis-tanpa-
  terminal menjawab 500 plain-text `'Pipeline terminal missing.'` — semua
  verbatim legacy. Tiga penyempitana disengaja: (a) `=== null` menggantikan
  `instanceof RequestHandlerInterface` pada cek terminal (properti sudah
  bertipe `?RequestHandlerInterface` — cek ganda adalah dead branch);
  (b) 500 mengalir BALIK melalui onion seperti jawaban terminal biasa
  (middleware delegating tetap `exit` — pin test eksplisit, dan ini yang
  benar: response adalah response); (c) `withMiddleware` tetap dipertahankan
  (permukaan publik incremental) dengan imutabilitas diuji — original
  tidak berubah, derived menjalankan append.
- **M7 (prioritas = Schwartzian transform stabil)**: `priority` TERTINGGI
  DULU (500 = terluar), tie = urutan deklarasi lewat index tiebreak
  eksplisit — stabilitas sort engine TIDAK pernah diandalkan (komentar
  legacy Regresi I-6 / issue #174 dijaga). Entri string = shorthand
  priority-0. Priority menerima int/float/string-numerik (dicast ke int —
  TypeError properti bertipe int adalah kill channel CastInt); string
  NON-numerik ('highest') DITOLAK oleh `is_numeric` (gap hardening:
  gerbang tipe lama menerima string apa pun lalu cast diam-diam ke 0 —
  kontradiksi dengan docblock sendiri; bool/array/null non-eksplisit
  juga ditolak), `null` eksplisit koalesen ke default 0 (pin positif).
  `id` diterima sebagai alias ejaan `service` (legacy menerima keduanya).
- **M8 (fail-closed P0 ported)**: middleware yang TIDAK terdaftar →
  penolakan build dengan nama service; service yang bukan
  `MiddlewareInterface` → penolakan build. Pipeline tidak pernah
  menghapus/di-reorder middleware yang dideklarasikan secara diam-diam.
  Stack non-list (scalar/map-addressed) ditolak; key absen/null/empty-list
  = terminal saja (pipeline kosong valid). Semua cabang diuji.
- **M9 (ekonomi hot-path jujur)**: `PipelineBench` memisahkan eksekusi
  dari konstruksi: handle 5-deep murni ~3.4μs (≈0.7μs per stage — harga
  clone-on-write per middleware, linear terhadap kedalaman), build
  (parse+sort+resolve) ~14.9μs sekali per worker, early-return 1-deep
  ~3.6μs; rstdev ≤1.1%. Bukan klaim kemenangan — instrumen regresi:
  tiap metode menjaga invariannya sendiri (lane gagal jika hot-path
  rusak, tidak hanya melaporkan angka).

## KDD-M10: klasifikasi mutant "X=Syntax Error" — bedah dan pembersihan

Pengguna melihat `X` (= Syntax Error, lihat legenda `E: fatal error,
X: syntax error, T: timed out`) di log lane mutation CI pada beberapa PR.
Bedah lengkap (infection 0.31, PHPUnit adapter):

- **Mekanisme klasifikasi**: `PhpUnitAdapter::isSyntaxError()` = regex
  sederhana `/ParseError: syntax error/i` atas OUTPUT test process.
  `ConsoleDotLogger` lalu menampilkan mutant dengan status itu sebagai
  `X`. MSI = (killed + errored + syntax-error + timeout) / total, jadi
  X **tidak pernah menggagalkan gate** (dihitung sebagai terdeteksi) —
  murni noise tampilan.
- **Sumber X di repo ini**: 3 mutant `Concat`/`ConcatOperandRemoval` di
  SATU baris — `ConfigCache::render()` template `"\n\n"` — membuat file
  cache ter-generate jadi PHP invalid. Mutant itu JAHAT untuk kill
  channel biasa: `load()` fail-closed kita membungkus `ParseError`
  artifact sebagai `previous` cause `ConfigException`, dan PHPUnit
  mencetak rantai itu (`Caused by\nParseError: syntax error...`) saat
  exception tidak tertangkap — regex infeksi cocok pada baris "Caused by"
  itu → mutant diberi label SYNTAX_ERROR padahal sebenarnya sudah
  terdeteksi test.
- **Fix (tes, bukan sumber)**: 4 test round-trip `ConfigCacheTest`
  sekarang memuat lewat helper `loadDumpedCache()` yang menangkap
  `ConfigException` dan mengubahnya menjadi `self::fail()` bersih —
  round-trip TETAP assertion utama, kontrak fail-closed korupsi TETAP
  dipin test terpisah (`testLoadThrowsWhenTheCacheIsNotValidPhp` dll,
  yang memang meng-ASSERT exception + `getPrevious()`). Hasil: 27/27
  mutant zona ConfigCache KILLED murni (0 X).
- **Sumber E (= fatal error) baru di kode PR-O**: `DecrementInteger` di
  `MiddlewarePipeline::handle()` (`$this->index + 1` → `+ 0`) membuat
  pipeline men-dispatch stage yang sama selamanya — proses phpunit mati
  di tengah dots, output tanpa summary → ERROR. Fix: `RecordingMiddleware`
  double sekarang punya reentry guard (`$processing` + `LogicException`
  "was re-entered while still processing") — pelanggaran invarian onion
  berubah dari crash-tak-berdiagnosis menjadi exception yang
  dilaporkan test. 76/76 mutant zona Middleware KILLED murni.
- **Toleransi yang dipertahankan**: (a) 18 mutant `SharedPrefixLength`
  E-family pre-existing (mutasi termination literal rekursi → segfault
  engine, output terpotong) — bukan zona PR ini, pola yang sudah
  ditoleransi di main; (b) `Throw_` di `ServiceDefinitionGraph::visit`
  TIMED OUT (throw cycle-exception dihapus → rekursi tanpa batas pada
  graf siklik → 10s timeout) — sama seperti `Throw_ @ DotWriter`
  pre-existing; timeout dihitung terdeteksi oleh MSI. `ReturnRemoval
  @ ServiceDefinitionGraph::visit` diabaikan di `infection.json5`
  (memo settled = performance guard, value-equivalent — diverifikasi
  live: tanpa entry itu muncul 1 escape; dengan entry itu suppressed).

## Catatan lane & infrastruktur

- **Deptrac**: layer baru `Middleware`; collector `Kernel` melebar ke
  `(Kernel|Version|ContainerBootstrapper)`. `Kernel += Middleware`
  (ruleset) untuk komposisi masa depan; `Middleware` berdiri di atas
  `[Exception, Container, Config, Http]`.
- **Reuse**: doubles `FixedResponseHandler`/`RecordingHandler` dipakai
  ulang dari lane HTTP; doubles baru `MiddlewareTrace` /
  `RecordingMiddleware` / `EarlyReturnMiddleware` (onion-order evidence)
  + fixtures `Greeter`/`Announcer`/`ExternalFactory` (container).
- **Benchmark**: `benchmarks/PipelineBench.php` masuk lane bench CI.
- **Sisa lane Container** (sesuai roadmap, urutan bebas):
  autowiring (area 2 — seam `RouteDispatcher` sudah menunggu), scope &
  lifecycle eksplisit (factory/prototype), tags, decorator/proxy,
  compiler passes. **Sisa lane Middleware**: integrasi dispatcher
  (inner pipeline per-rute — `DispatchOutcome` sudah membawa
  `middleware()`), per-route fail-closed resolution (pola legacy #354).

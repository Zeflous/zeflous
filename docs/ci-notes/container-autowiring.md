# CI Notes — Container: The Autowiring Family (C1)

Decision record untuk lane pertama kampanye Container (`src/Zef/Framework/Container/Autowiring/`
+ grammar `class`-shape yang tumbuh). Dokumen ini melengkapi
`docs/ci-notes/middleware-pipeline.md` (KDD-M1..M10, keluarga definisi
deklaratif #328 yang menjadi fondasi lane ini) — autowiring adalah
"CONTAINER" area 2 di `ROADMAP.md`.

Model yang diport: **github v2.9 `AutowireCompilerPass`** (referensi
`reference/zef-framework-src`, atribusi di bawah), diadaptasi ke disiplin
zeflous (fail-closed, kolaborator ukuran-archeology, instalasi lewat
permukaan publik `set()`, Container layer bebas Config).

## KDD-A1..A14

- **A1 (compile-pass, bukan runtime reflection)**: refleksi hidup HANYA
  di boot, di dalam `ReflectionMetadataExtractor` + `AutowireSpecFactory`
  (+`AutowireParameterTypes`/`AutowireAttributes`). Hasil pass = set
  `InstanceDefinition` positional biasa — bentuk yang SAMA yang graph
  validasi dan compiler instal, jadi jalur ini menambah NOL mesin runtime
  baru. Runtime resolusi tetap `['service' => id]` → `newInstanceArgs`
  (seam area 8 yang akan menggantinya `new` literal).
- **A2 (formulir grammar = keputusan planning)**: `arguments` positional
  = rencana tangan (verbatim, `PositionalFit` membaca arity sekali untuk
  kejujuran boot); `arguments` name-keyed = hibrida (named menang atas
  auto); `autowire: true` = plan seluruh konstruktor. Kunci `autowire`
  di samping `arguments`, bernilai bukan-true, atau di shape lain =
  refusal. Opt-in autowiring hidup DI GRAMMAR, bukan tebakan.
- **A3 (explicit wins over auto, berjenjang)**: named argument →
  `#[Inject]` → `#[Target]` → tipe ter-deklarasi/alias/container →
  default konstruktor (baked verbatim) → refusal jujur. NULL TIDAK
  PERNAH ditebak ke slot: nullable-required tanpa binding = refusal yang
  meminta eksplisit (divergensi disengaja dari github yang membake null —
  disiplin F1-F3: permukaan kegagalan harus jujur).
- **A4 (TERKONTROL, bukan agresif)**: container tidak pernah melahirkan
  apa pun saat runtime. Kelahiran (birth) hanya terjadi di boot karena
  parameter TER-DECLARASI menamai kelas instantiable tak terdaftar —
  jadi setiap birth adalah definisi ter-deklarasi yang menunggangi graph
  yang sama dan tercatat di laporan (`AutowireResult::generated`).
- **A5 (disiplin siklus dua-waktu utuh)**: edge dari plan mengalir ke
  `ServiceDefinitionGraph` seperti referensi tulisan tangan (siklus
  ter-deklarasi = refusal graph boot); rantai birth yang menutup diri
  sendiri ditolak `AutowireBirths::guardChain` dengan rantai yang
  dijalankan — bentuk pesan yang sama dengan `CycleGuard` runtime (#380).
  `ServiceStore`/`Container`/`CycleGuard`: NOL perubahan.
- **A6 (resolusi tipe = nama tipe literal)**: `Greeter $g` menuntut id
  bernama `Greeter::class` ter-deklarasi/ter-alias/ter-registrasi;
  id host sembarang (`app.greeter`) TIDAK dipetakan otomatis — mapping
  eksplisit lewat shape `alias` (jalan keluarnya ada di pesan refusal).
  Tidak ada scan-tebakan ala Symfony `byId` heuristik.
- **A7 (spek = data murni = cache refleksi)**: `AutowireParameterSpec`
  snapshot tanpa objek `Reflection*` — "reflection caching" roadmap
  dimenangi lewat konstruksi. Properti yang tak dikonsumsi lane ini
  (position/allowsNull) DIBUANG — disiplin knob-harus-live
  (`FrameworkDefaults` mengajarkan hal yang sama).
- **A8 (AutowiringException extends DefinitionException)**: kegagalan
  plan = kecacatan deklarasi → keluarga catch yang sama dengan graph
  refusal. `DefinitionException` jadi root keluarga yang terbuka
  (un-final; phpcs exclude-pattern beralasan, preseden `Request.php`).
- **A9 (UnplannedInstanceDefinition sebagai marker)**: bentuk permintaan
  vs bentuk rencana terpisah kelas; graph DAN compiler menolak unplanned
  ("jalankan pass dulu") — penyalahgunaan seam gagal tertutup di dua
  titik konsumsi.
- **A10 (variadic = koleksi, ditunda ke C3)**: variadic tak ter-plan
  di-omit (koleksi kosong valid); named-variadic = daftar untuk spread,
  tiap entri grammar referensi yang sama. `#[Inject]/#[Target]` di
  variadic = refusal (koleksi bukan satu referensi).
- **A11 (scalar tanpa #[Value])**: `#[Value]` github TIDAK diport di
  C1 — butuh seam Config (deptrac: Container bebas Config); lane-nya
  keluarga C5 (registration completion). Union/intersection wajib =
  refusal dengan alasan; ber-default = default ter-bake.
- **A12 (laporan = observabilitas birth)**: `AutowireResult` menjawab
  definitions + generated (birth) + reused — tidak ada yang lahir di
  luar catatan (psalm baseline: properti generated/reused =
  API-surface-menunggu-konsumen, preseden `HealthExposition::render`).
- **A13 (assert() narrowing idiom)**: `assert($x !== null, 'the planner
  only routes ...')` — penyempitan runtime untuk phpstan di lengan
  resolver; assertion terkompilasi di jalur coverage (preseden #319).
- **A14 (bench: sekali per boot)**: `AutowirePassBench` — pass compile
  ~34.8µs/boot untuk 4 definisi + 1 birth + alias; resolusi memoised
  ~0.28μs — flat, identik dengan definisi tulisan tangan. Refleksi
  dibayar sekali, pernah.

## Lesson engineering (saga archeology D<20 + mutasi)

- Halstead difficulty = (n1/2)×(N2/n2): **operator UNIK (n1) tuas
  terbesar** — satu `resolve()` dispatch mengalahkan tiga lengan publik;
  guard bentuk positif (`if (class_exists || interface_exists) return`)
  menghapus `!`+`&&`; pesan refusal dipindah ke kelas kata-kata
  (`*Refusals`) sehingga lengan tinggal logika.
- **InstanceDefinition instanceof tanpa import = false SENYAP** — kelas
  tak ter-resolve di `instanceof` tidak fatal; cabang berjalan salah
  tanpa suara (bug halus yang lolos 40 test).
- **CoversClass = filter pelaporan coverage**: kolaborator tanpa
  CoversClass TIDAK muncul di laporan walau pcov melacaknya; attribute
  tanpa import resolve ke namespace TEST ("not a valid target") —
  pelajaran import-covers.
- **Continue_ mutator (continue→break)**: permukaan `continue` di loop
  verifikasi dihapus dengan helper per-item (`assertDeclared`/
  `assertNamed`) — phpcs early-exit dan mutasi sama-sama senang.
- `array_pop` penutup frame birth = nyaris value-equivalent dalam satu
  run; pembunuhnya = pesan siklus EXACT setelah birth yang selesai
  (residu rantai akan muncul di teks pesan).
- File sampah `/tmp/zef-upload-*.zef-tmp-*` dari proses terbunuh
  meracuni assertion glob UploadedFileTest → bersihkan sebelum
  infection/coverage run.

## Lesson engineering ronde 2 (gate post-push: archeology + SonarCloud)

- **REPETISI operand (N2/n2) membunuh kelas logic-kecil**: `ConstructorFit`
  D=23.4 dengan n2=9 — empat metode yang men-thread identitas variabel
  sama (definition/spec/known) menggandakan N2 tanpa menambah n2. Kata
  kunci saga "operator unik" hanya separuh cerita: **diversitas variabel
  separuh lainnya**. Split dua ranah verdict → `PositionalFit` (17.6) +
  `NamedFit` (13.5) + kelas kata-katan `ConstructorFitRefusals` (7.9)
  — semua lolos dengan margin.
- **Helper baru yang men-thread parameter yang sama = perangkap**:
  ekstraksi `declaredStretch()` ke planner untuk membayar Sonar S1142
  (maks 3 return) Menaikkan D 19→27 (tiga signature × operand sama).
  Solusi: pindahkan routing tier-1 ke kolaborator yang memang memilikinya
  (`AutowireDeclaredStretch::of()` — dispatch murni, D≈7) dan
  `AutowireDeclaredArguments` kembali ke tugas tunggalnya (grammar entri
  yang di-eja, D≈15). Planner turun ke 14.4 dengan tepat 3 return.
- **SonarPHP S1144 tidak melihat first-class callable sebagai usage**
  (`self::specOf(...)` di `array_map` → "unused private method" padahal
  itu satu-satunya call site; kasus `OtlpJsonPayloadEncoder::anyValue`
  tidak ter-flag — resolusinya tidak deterministik yang bisa diandalkan).
  Bentuk closure eksplisit
  (`static fn (X $x): Y => self::specOf($x)`) adalah bentuk
  yang terbaca semua analyser; rector
  `ArrowFunctionDelegatingCallToFirstClassCallableRector` menarik ke
  belakang — skip ber-dokumen di `rector.php` (preseden
  `EmittedHeaders.php`, "the analysers win").
- **Tiga gate, satu bentuk**: S1142 (≤3 return/ metode), archeology
  D<20, dan mutasi MSI-100 kadang menuntut bentuk yang berbeda —
  jawabannya bukan mengorbankan salah satu tapi memecah kolaborator
  sampai tiap kelas cukup kecil untuk memuaskan ketiganya sekaligus.
  Loop pengukuran `composer archeology:gate` setelah tiap reshape
  adalah satu-satunya jalan (tebakan mental Halstead meleset ±30%).

## Atribusi

- `AutowireCompilerPass`/tier-planning/`#[Inject]`/`#[Target]`/
  `AutowireResult`-shape: port-adaptasi dari github v2.9
  (`reference/zef-framework-src/src/{Application,Domain}/Container/Autowiring/`
  — `AutowireCompilerPass`, `AutowireArgumentResolver`,
  `ReflectionMetadataExtractor`, `AutowireParameterSpec`, `Inject`,
  `Target`), dimodifikasi: tanpa AOT codegen (area 8), tanpa `#[Value]`
  (C5), tanpa koleksi implementasi variadic (C3), null-tak-ditebak,
  output = grammar zeflous bukan generated-code.
- Guard rantai + disiplin fail-closed bergaya harden (gitlab v2.5-beta:
  `FailFastInitializationGuard` warisan disiplinnya), bentuk pesan
  rantai = `CycleGuard` #380.
- Grammar 4-shape + graph dua-waktu + instalasi `set()`: keluarga #328
  (KDD-M2..M4) yang tidak disentuh — lane ini tumbuh DI ATASNYA.

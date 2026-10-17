# CI Notes — Container: The Scope & Lifecycle Family (C2)

Decision record untuk lane kedua kampanye Container (`ServiceLifetime`
enum + grammar `lifetime` + `RequestScope`/`RequestInstances`/
`ActiveScope` + `CycleGuard` depth budget + `LifetimeCaptures` +
store-family reshape). Dokumen ini melengkapi
`docs/ci-notes/container-autowiring.md` (KDD-A1..A14, lane compile-pass
yang grammar-nya tumbuh di sini) — scope & lifecycle adalah "CONTAINER"
area 5 di `ROADMAP.md`.

Model yang diport: **gitlab v2.5-beta** sebagai tulang punggung harden
(`ServiceLifetime` taxonomy, `RequestScope` closed-flag + scope-instance
store, depth-limit 256) + **github v2.9** (taxonomy yang sama,
`ServiceDefinition.lifetime`), diadaptasi ke disiplin zeflous
(fail-closed, knob-must-be-live, instalasi lewat permukaan publik
`set()`, kolaborator ukuran-archeology).

## KDD-B1..B13

- **B1 (lifetime = enum = kebijakan sharing, TANPA knob `shared`)**:
  lineage referensi memakai `lifetime` + flag `shared` dengan invariant
  shared-implies-singleton — tapi `transient` ≡ `singleton`-unshared
  secara semantik persis (resolver gitlab memperlakukan keduanya sama),
  dan flag itu tak pernah bisa legal dikombinasikan dengan ejaan lain.
  Knob yang tak pernah mengubah apa pun = knob mati (pelajaran
  `FrameworkDefaults`) → vocabulary zeflous: `'singleton' |
  'request' | 'transient'`, enum backed string (preseden `AuthVerdict`),
  default `singleton` = perilaku semua deklarasi lama, nol perubahan.
- **B2 (grammar = tiga keputusan, diparse di tiap shape sendiri)**:
  kunci `lifetime` diterima DI shape class dan factory; alias =
  refusal "menunggangi lifetime target-nya" (transparansi), value =
  refusal "konstanta lahir sekali"; kata tak dikenal / non-string =
  refusal dengan vocabulary (parse `ServiceLifetime::fromKey` di enum,
  pesan ber-id). Simetri dengan refusal arguments/autowire yang sudah
  tinggal di masing-masing shape parser — bukan satu parser terpusat.
- **B3 (dispatch = satu `match`, preseden DefinitionShapes)**:
  `ServiceResolutions::resolve` — memo-hit menjawab langsung (jalur
  cepat TANPA alokasi closure dan TANPA membaca lifetime: hanya
  singleton pernah menulis memo, jadi cek memo tak perlu tahu
  lifetime), lalu `match` atas lifetime: REQUEST → mesin scope,
  TRANSIENT → guarded call tanpa memo, default → memo answer-or-build.
  Satu metode, dua return + match — S1142 dan archeology sama-sama
  senang; `match` menghapus `if`/`===`/`return` berulang (n1 turun).
- **B4 (scope = state resolusi, bukan signature baru)**: factory
  menerima `Container` (root) — scope tidak bisa dilewatkan lewat
  signature tanpa membekukan semua closure yang ada. Jawabannya pola
  CycleGuard: `ActiveScope` menyimpan scope-terbuka try/finally di
  sekeliling resolusi ter-scope (nested: save/restore) → dependency
  request-scoped transitive yang di-resolve factory DI TENGAH rantai
  mendarat di scope yang sama via root `get()`. Perilaku yang gitlab
  capai lewat `ResolutionContext` (ctx membawa scope ke factory),
  tanpa mengubah satu signature pun.
- **B5 (scope memilikikan instance-nya SENDIRI — WeakMap dibuang)**:
  desain awal menaruh `WeakMap<RequestScope,array>` di store ala
  gitlab; desain final: `RequestScope::$instances` privat + seam
  `answer()` — GC identik (instance mati bersama scope), tapi tanpa
  mesin map sampingan, dan seam answer-or-build-nya SPELI sama dengan
  `SingletonMemo::answer` (dua lane, satu idiom). Divergensi dari
  gitlab diatribusi di bawah.
- **B6 (root get request-id = refusal jujur dengan jalan keluar)**:
  pesan "open one with the container and resolve through it" —
  permukaan kegagalan jujur, gaya F1–F3. Bukan menebak (membangun
  forever = singleton yang ia tolak jadi; membuang memo = menolak
  semantik deklarasinya).
- **B7 (captive dependency = verdict graph ketiga)**: edge adalah
  capture — singleton→request = bohong yang graph LIHAT sebelum satu
  service resolve: janji "instance hidup tak lebih lama dari satu
  scope" vs singleton yang menggenggamnya selamanya. Refusal boot
  dengan dua jalan keluar di pesan (`LifetimeCaptures`). Yang lebih
  sempit tetap legal dan jujur didokumentasikan: singleton→transient
  = dibangun sekali di konstruksi captor (fakta konstruksi),
  request→request = hidup seumur keduanya. Invariant lineage
  (shared-implies-singleton) diport sebagai disiplin capture, bukan
  sebagai flag.
- **B8 (depth budget = saudara tiri cycle)**: cycle ditolak dengan
  rantai yang berjalan (disiplin dua-waktu #380); chain-dalam-tanpa-
  cycle adalah near-cousin patologisnya — `CycleGuard` menolak di
  `count(resolving) >= depthLimit` (default 256, paritas gitlab)
  SEBELUM call stack engine sendiri menyerah (fatal yang tak bisa
  di-catch). Batas konstruksi `< 1` = refusal (gitlab clamp senyap
  `max(1,..)` — TIDAK diport; fail-closed menang). Perhatikan
  off-by-one: frame DAUN ikut dihitung — 256 frame = 255 factory
  berantai + 1 daun.
- **B9 (scope ditutup mid-resolusi = refusal di DUA titik)**: factory
  yang memanggil `close()` milik scope resolusinya sendiri adalah
  sekuens API publik yang legal, dan keduanya gagal jujur: (a) resolusi
  request-scoped BERIKUTNYA setelah close ditolak EARLY — sebelum
  factorynya berjalan (dibunuh mutan penghapus guard lewat counter
  efek-samping `leafBuilds`); (b) completion yang selesai SETELAH
  close ditolak oleh `RequestScope::record` sendiri — instance yatim
  tak punya scope untuk dimiliki (dibunuh lewat test close-and-refuse).
  Kedua refusal satu pesan: "closed mid-resolution".
- **B10 (lifetime menunggangi definisi, bukan closure)**: compiler
  menghasilkan closure yang lifetime-agnostic (nol perubahan lane C1);
  lifetime naik dari `ServiceDefinition` → `Container::set($id,
  $factory, $lifetime)` (parameter defaulted — semua call-site lama
  kompatibel) → `FactoryRegistrations`. Install tetap 100% lewat
  permukaan publik `set()`; Container layer tidak pernah melihat
  Config (seam tetap di bootstrapper).
- **B11 (birth = singleton)**: kelas yang lahir dari parameter
  ter-deklarasi (lane C1) memakai default singleton — kelahiran adalah
  definisi ter-deklarasi di bawah FQCN-nya; request/transient untuk
  kelas sama tinggal dideklarasi eksplisit host.
- **B12 (store-family reshape = tujuh kolaborator)**: `ServiceStore`
  berhenti jadi dispatcher-tabung: `FactoryRegistrations` (map factory
  + lifetime, ditulis bersama), `SingletonMemo` (answer-or-build +
  forget), `ServiceResolutions` (match dispatch + guarded call),
  `RequestInstances` + `ActiveScope` (mesin scope), `RequestScope`
  (facade + answer seam), `RequestScopeRefusals` (kata-kata). Container
  sendiri memegang `RequestInstances` (facade membuka scope-nya) dan
  MENGKONSTRUKSI `RequestScope` langsung — `open()` di mesin scope
  dihapus. Setiap kolaborator D<20 terukur.
- **B13 (bench: harga jujur tiap lane)**: `ScopeResolutionBench` —
  memo-hit singleton ~0.52µs (pre-lane ~0.29µs: pembacaan lifetime +
  hop kolaborator adalah harga kebijakan sharing; jalur cepat TIDAK
  mengalokasikan closure — itulah yang beda 0.74µs vs 0.52µs),
  memo-hit dalam scope ~1.26µs (facade + ActiveScope + answer seam),
  transient ~1.0µs (full factory call — harga "fresh tiap kali"),
  scope open+resolve+close ~2.1µs per request.

## Lesson engineering ronde 3+4 (saga D berlanjut)

- **STALE-COPY write-back = bug halus kelas transitive-scope**: memo
  scope ditulis lewat salinan lokal yang dibaca SEBELUM factory call
  → tulisan nested (dependency sesama scope di tengah rantai) tertimpa
  salinan basi. Test transitive adalah satu-satunya penjaga; tulis
  baca-segar (`$map[$k1][$k2] = $x` membaca offset segar di statement
  itu) atau seam yang menulis hidup (`answer`/`record`) adalah bentuk
  yang benar.
- **Method count = D driver yang tak terduga**: signature mengulang
  nama variabel (param + body) — RequestScope 8 metode D=27.75, enam
  metode D=18. `open()` satu-baris yang bisa diganti konstruksi
  langsung = −3 D. Ekstraksi kolaborator yang MENYALIN threading tanpa
  memecah ranah = D berpindah tempat, tidak turun (RequestInstances
  28→35 saat helper salah bentuk; turun ke 19.33 saat ranah benar).
- **`match` = tuas n1 terkuat untuk dispatch**: mengganti if/===/return
  beruntun dengan satu match arm-set menurunkan ServiceResolutions ke
  D=9.5 dengan fast-path sekalipun. Guard bentuk positif (buang `!`+
  `&&`) menurunkan RequestScope 23.1→18.0 — dua operator unik.
- **Exemplar kalibrasi**: AutowireReferences D=19.5 = 3 metode, N2=39,
  n2=9 — rasio N2/n2 ~4.3 adalah bentuk sehat; rasio >7 (ServiceStore
  awal 61/8) tidak bisa ditebus.rename variabel — hanya pemecahan
  ranah atau pengurangan metode yang jujur.
- **Closure per-call = harga hot-path**: `fn () => ...` yang dibuat
  di dalam resolve() dialokasikan SEBELIK memo-hit dicek → +0.2µs tiap
  get. Fast-path sebelum pembuatan closure adalah disiplin hot-path;
  jalur miss (yang membayar factory call) boleh membayar closure.
- **Rector menarik-narik nama**: rename param/property otomatis
  (`$lifetime`→`$serviceLifetime`, `$registrations`→
  `$factoryRegistrations`) menaikkan D-efek samping (nama panjang =
  baris panjang, 12 pelanggaran 120-char) DAN mengubah metrik —
  jalankan archeology SETELAH rector, bukan sebelum. Konversi
  `\assert(instanceof)` → `Assert::assertInstanceOf` oleh set phpunit
  rector = perubahan SEMANTIK yang menyenangkan (fail-closed tanpa
  zend.assertions) tapi harus diverifikasi test.
- **enum case = UP CASE** (`ServiceLifetime::SINGLETON`, bukan
  `::Singleton` gaya const-class) — enum PHP di-resolve lewat NAMA
  case; konstanta tak terdefinisi = Error runtime yang senyap di titik
  default parameter (166 test merah sebelum ketahuan — jejak kegagalan
  yang baik, tapi tetap satu putaran hilang).
- **Factory tak bisa menerima scope lewat signature** (`Closure
  (Container)` sudah beku) — state-resolusi ala CycleGuard (`ActiveScope`)
  adalah jalan yang tidak merusak satu closure pun; gitlab menyeberang
  lewat `ResolutionContext` karena factory-nya memang menerima ctx.

## Atribusi

- `ServiceLifetime` taxonomy singleton/request/transient + refusal
  vocabulary: port-adaptasi gitlab v2.5-beta
  (`reference/gitlab-dl/extracted/zef-main/src/Framework/Container/ServiceLifetime.php`)
  dan github v2.9 (`reference/zef-framework-src/src/Domain/Container/
  ServiceLifetime.php` — identik, lineage yang sama), dimodifikasi:
  tanpa flag `shared` (B1), enum bukan const-class, parse fromKey di
  enum dengan pesan ber-id.
- `RequestScope` closed-flag + get/has/close + scope-instance store:
  port-adaptasi gitlab `RequestScope.php` + `ContainerResolver.php`
  (createRequestScope/releaseScope/scope-instances), dimodifikasi:
  instance map milik scope-nya sendiri (gitlab: WeakMap di resolver —
  B5), tanpa trio deprecated hasInstance/getInstance/setInstance,
  tanpa `hasInContext` ke resolver — facade tipis di atas `answer`
  seam.
- Transitive scope-via-state (B4): alternatif zeflous untuk
  `ResolutionContext` gitlab — masalah sama (factory menerima root),
  jawaban yang tidak membekukan signature.
- Depth-limit 256 di guard resolusi: port-adaptasi gitlab
  `ResolutionContext::push` (limit + count), dimodifikasi: menempel di
  `CycleGuard` (pemilik stack resolusi), refusal eksplisit (bukan
  clamp senyap), exception bernama sendiri.
- Captive dependency (singleton→request = boot refusal): desain baru
  zeflous atas invariant shared-implies-singleton lineage (B7) —
  adaptasi disiplin, bukan port baris.
- Store-family reshape (B12) dan idiom answer-or-build: desain baru
  zeflous (dipaksa disiplin archeology D<20 + pelajaran saga —
  lihat ronde 3+4), tanpa padanan di referensi.

## Lesson engineering ronde 5: ronde gate CI (PR #396, Infection merah)

CI Infection menolak PR dengan **11 mutant lolos** (MSI 99.84%) padahal
baterai lokal dicatat hijau. Otopsi menemukan TIGA kelas cacat, ketiganya
bukan nondeterminisme — reproduksi lokal dengan `--filter` atas tujuh
file pemilik mutant menghasilkan daftar lolos yang identik:

1. **Celah fail-open report basi (akar klaim lokal yang salah)**:
   `composer ci:infection` = `composer mutation` (full, tulis report)
   LALU `composer mutation:zones` (gate baca report). Jalankan
   `mutation:zones` SENDIRIAN di lokal = membaca report run TERAKHIR —
   yang bisa berasal dari state kode sebelum reshape. Klaim "MSI 100%"
   di worklog berasal dari report basi. Disiplin: gerbang mutation
   lokal hanya sah setelah `composer mutation` penuh pada state final;
   di CI urutan skrip sudah menjamin kesegaran (mutation harus hijau
   sebelum zones berjalan).
2. **Covers-restriction PHPUnit membuat test KUAT tak terlihat oleh
   Infection**: `requireCoverageMetadata="true"` + `#[CoversClass]`
   membatasi coverage per-test ke target yang didaftarkan. Test grammar
   lifetime ditulis di `ServiceLifetimeTest` (covers hanya
   `ServiceLifetime`) — test-nya benar dan kuat, tapi baris yang
   dimutasi hidup di `ArgumentsForm`/`AutowireKey`/`FactoryShape`
   (di-cover `ServiceDefinitionTest`), sehingga test itu TIDAK masuk
   covering-set mutant. Sama untuk jalur scope: `RequestScopeTest`
   menembus `ServiceResolutions` penuh tanpa mencantumkannya di covers.
   C1 lolos karena kebetulan test-nya berada di file yang covers-nya
   mencakup kelas yang dimutasi. Perbaikan: test dipindah ke rumah yang
   mencover kolaboratornya (grammar → `ServiceDefinitionTest`), dan
   `#[CoversClass(ServiceResolutions::class)]` ditambahkan ke
   `RequestScopeTest`. Pelajaran umum: **letakkan test pada file yang
   covers-nya memuat pemilik cabang yang diuji** — test yang tak
   terlihat oleh mutasi melindungi apa pun yang bisa dilihat mutasi.
3. **Slack batas pada test budget kedalaman**: test "257-frame" lama
   ternyata 258 frame (loop `0..256` + leaf) — tetap menolak di bawah
   budget yang dilonggarkan (256→257). Boundary pin sejati: rantai
   **tepat 257 frame** dengan `assertSame` pesan ber-budget-256 —
   hanya frame count itu yang membedakan 256 vs 257. Test 258-frame
   tetap dipertahankan sebagai sisi dalam.

Empat mutant keluarga Coalesce (`$lifetime ?? SINGLETON` dibalik):
tanpa test non-default yang menembus TIAP bentuk grammar (bare-class /
positional / named-map / autowire / factory), pembalikan operand tak
teramati — test per bentuk kini ada di `ServiceDefinitionTest`.
UnwrapFinally `ActiveScope`: tak ada test yang mengamati pemulihan
scope pasca-resolusi — dua test baru (root menolak SETELAH resolusi
scope selesai; scope dalam memulihkan scope luar untuk dependency
berikutnya). Continue_ `LifetimeCaptures`: tak ada test dengan
dependent non-singleton DI DEPAN singleton yang bersalah —
`testTheVerdictWalksPastANonSingletonDependent` (transient legal dulu,
refusal singleton kemudian; `break` menghentikan walk sebelum sampai).

**Satu ignore baru terdokumentasi (ReturnRemoval fast-path memo)**:
`ServiceResolutions::resolve()` baris `return
$this->singletonMemo->instance($id);` — fall-through mendarat di seam
answer-or-build milik `SingletonMemo::answer()` yang mengecek ulang
memo, sehingga mutant menjawab instance identik lewat jalan lebih
panjang: ekuivalen secara konstruksi (diverifikasi: suite penuh 3070
test hijau di bawah mutasi yang diterapkan manual). Baris itu sendiri
ada demi hot-path terukur (B13: 0.52µs vs 0.74µs). Ignore di-scope
`ignoreSourceCodeByRegex` ke SATU baris sumber — `return match`
tetangganya tetap hidup dan terbunuh. Mengikuti preseden gaya
`ignoreSourceCodeByRegex` yang sudah ada di `infection.json5`.

## Ronda 5 lanjutan: kunci duplikat JSON5 melucuti tiga ignore terdokumentasi

Ronde kedua CI pada commit perbaikan menolak lagi — tapi kali ini
tiga mutant lolos yang BUKAN dari keluarga scope:
`DefinitionCycles::visit:48`, `PrometheusNumbers::diverged:58`,
`TelemetrySanitizer::scrubbed:248` — tiga method yang docblock-nya
sendiri sudah mendokumentasikan zona ekuivalen masing-masing, dengan
entri ignore method-scoped `ReturnRemoval` yang sudah ada di
`infection.json5` sejak lama.

Akar masalahnya kesalahan perbaikan itu sendiri: entri
`"ReturnRemoval"` baru (regex fast-path memo) ditambahkan di ujung
file **tanpa memeriksa bahwa kunci itu sudah dipakai** di tengah
file. Parser konfigurasi berlaku last-wins — entri baru menimpa
entri lama, tiga ignore terdokumentasi dilucuti diam-diam, dan
ketiga mutant ekuivalen itu "lahir kembali" sebagai lolos. Angka
generated yang bertambah 6707→6710 adalah sidik jarinya: ignore
method-scoped MENGECUALIKAN mutant saat generasi (tidak dihitung
sebagai ignored), regex-ignore menandainya setelah dibuat —
generated naik tepat +3 = tiga method yang tak lagi dikecualikan.

Perbaikan: regex digabung ke entri lama (satu kunci, `ignore` +
`ignoreSourceCodeByRegex` berdampingan — preseden `CastFloat`),
komentar lama dibiarkan utuh, komentar baru menambah zona keempat
plus peringatan NB: entri ini adalah SATU-SATUNYA kunci
`ReturnRemoval` di file ini; kunci duplikat melucuti semua ignore
yang terdaftar di dalamnya tanpa suara. Pelajaran ronde ini
melengkapi ronde sebelumnya: **sebelum menambah entri mutator di
infection.json5, grep dulu kunci yang sudah ada** — file ini bukan
daftar yang boleh ditumpuki; dan ketika mutant "baru" muncul di
file yang tak tersentuh, curigai konfigurasi sebelum menyalahkan
test.

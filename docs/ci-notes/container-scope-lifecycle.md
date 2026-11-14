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

## KDD-B14..B19: alias menunggangi lifetime target-nya (#769)

- **B14 (refusal grammar akhirnya jujur — alias transparency jadi kode)**:
  `AliasShape` menolak kunci `lifetime` dengan klaim "an alias is
  transparent: it rides the lifetime of its target" — tapi kompiler
  mendaftarkan closure alias di bawah lane singleton default, sehingga
  memo alias men-PIN jawaban pertama target transient selamanya:
  `get(alias) === get(alias)` true sementara `get(target)` fresh tiap
  kali. Pesan refusal dan perilaku adalah kebalikan satu sama lain
  (temuan audit A-C1). Fix = opsi #1 issue: stage planning baru
  `AliasLifetimes::align` meng-rewrite tiap alias biasa ke kebijakan
  sharing target-nya SEBELUM validasi graph dan sebelum apply dekorator
  — pesan itu kini menyatakan kebenaran yang boot instal.
- **B15 (posisi stage: contextual→alignment→decorators→graph)**:
  urutan bukan arbitrasi. Sebelum graph karena `LifetimeCaptures` kini
  membaca lifetime yang sudah selaras: alias→target request jadi edge
  request→request yang legal (dulu ditolak boot sebagai "singleton
  mekanis menangkap request"), dan singleton yang bergantung pada
  alias→request ditolak dengan nama alias — capture senyap lintas edge
  alias (singleton pin instance scope lewat memo alias) jadi verdict
  jujur di boot. Sebelum decorators karena wrapper dan base re-homed
  dibangun dari lifetime deklarasi — alias yang selaras lebih dulu
  membuat seluruh rantai dekorasi konsisten membawa kebijakan target.
- **B16 (`@contextual:` skip = kontrak lain, bukan escape)**:
  alias sintetis lane contextual SENGAJA membawa lifetime konsumen
  (`ContextualSet` — rantai rewrite harus berperilaku persis seperti
  referensi langsung konsumen→target). Itu kontrak yang berbeda dari
  transparansi; alignment melewati prefix `@contextual:` yang lane itu
  miliki. Alias biasa yang menargetkan sintetis contextual menunggangi
  lifetime deklarasinya langsung (tanpa re-align — walk menghormati
  target yang ditemukannya).
- **B17 (probe lifetime = totalitas has(), precedence store>fallback)**:
  leaf target (tidak ada di set) butuh baca kebijakan: `Container::
  lifetime(?ServiceLifetime)` — pelebaran kelima fasad (probe read-only
  di balik `ContainerAnswers` → `ServiceStore::lifetime` → registrasi;
  `??` → `NamespaceFallbacks::lifetime` = kebijakan prefix covering).
  Invariant yang di-pin test: precedence probe = precedence `answer()`
  (registrasi menang atas fallback yang juga men-cover — mutant
  Coalesce-swap dibunuh test overlap), dan paritas `has ⟺ lifetime !==
  null` atas lane-lane campuran. Memo-only menjawab singleton
  (konstruksi: memo hanya terisi dari lane singleton).
- **B18 (walk: transitive, order-bebas, toleran siklus)**:
  rantai `a→b→c` semua menunggangi kebijakan `c` apa pun urutan
  deklarasi (walk atas set utuh + memo, bukan fold urutan). Target
  tak-terdeclare tapi ter-carried container → kebijakan registrasinya
  sendiri. Target tak-dikenal DAN siklus dibiarkan untuk verdict graph
  satu stage kemudian (alignment menilai lifetime saja, tidak pernah
  validitas set) — arm siklus `isset($visited)` menjamin terminasi.
  Mutant kelas isset-only bookkeeping (`TrueValue` pada mark) dan
  memo-early-return (`ReturnRemoval`) = ekuivalen terdokumentasi di
  `infection.json5` mengikuti preseden `DefinitionCycles::visit` dan
  `$seenParameters` (komentar baris demi baris di entri masing-masing).
- **B19 (AOT generation 3 bump = staleness semantics, bukan shape)**:
  entri `closure` artifact membake `lifetime` per definisi — dump baru
  otomatis membake lifetime selaras, tapi artifact LAMA membake pin
  singleton untuk alias target-transient: artifact yang secara diam
  melayani semantik lama. `AotDigest::ARTIFACT_GENERATION` 2→3 (digest
  melipat generation → setiap artifact lama proven-stale → boot
  recompile + re-dump, jalur graceful yang sudah ada). Hex pin
  `testTheFoldSpellsItsCanonicalFormStably` ikut bergerak — perubahan
  LOUD yang persis test itu jaga agar tak bergerak diam-diam. Round-trip
  test baru: boot kedua dari artifact menjawab transient-ride fresh
  (`get(alias) !== get(alias)` di lane zero-planning).


## Ronda gate: pelajaran attribution ke-lima (#769, Sonar 92.9%)

Ronde pertama PR #829 memerah di SonarCloud: new-code coverage 92.9%
(required 95%), padahal `composer coverage:gate` lokal hijau 98.57%.
Akar masalahnya bukan kode yang tak ter-cover — runtime probe membuktikan
`NamespaceFallbacks::lifetime()` dieksekusi dan menjawab TRANSIENT —
melainkan **`requireCoverageMetadata="true"` di phpunit.xml.dist**: hit
dari sebuah test hanya ter-atribusi ke kelas-kelas di daftar
`#[CoversClass]`-nya. Tiga file test baru slice ini hanya men-list
subjek utamanya; kolaborator rantai probe (ServiceStore →
FactoryRegistrations/SingletonMemo → NamespaceFallbacks →
NamespaceCoverings) terbaca NOL di clover meski dieksekusi — persis
pelajaran #439 (covers-filter) dan #487 (boot lanes missing) yang
lupakan dirinya sendiri kembali. Fix: daftar covers ketiga baterai
diperluas ke setiap kolaborator yang diamatiri asersinya (probe battery
+7 kelas, alignment battery +10, boot battery +12 — mekanik request
scope dan graph verdict yang asersi boot-level bacanya langsung).
Clover setelahnya: `NamespaceFallbacks::lifetime` count 5, baris tak
ter-cover 0, gate naik 98.57% → 98.58%. Efek samping yang sehat:
infection scoped membaca attribution yang sama — 89 → 93 mutant
(rewrite if/else menambah mutant cabang) semuanya tetap MSI 100/100
0 escape. Pelajaran ke-lima untuk kanon attribution battery:
**test baru yang menyentuh rantai kolaborator baru wajib men-list
rantainya sejak lahir** — hijau lokal gate lama tidak menutup
celah Sonar yang membaca clover yang sama.

## Ronda gate kedua: difficulty ceiling dan siklus dependensi (#769, archeology 6 temuan)

Ronde kedua PR #829 memerah di PhpCodeArcheology: enam temuan
error-level — file/kelas `AliasLifetimes` (42.50), `ServiceStore`
(28.88), dan `NamespaceFallbacks` (21.09) melewati difficulty
ceiling 20 (`php-codearch-config.yaml`, difficulty = Halstead:
n1/2 × N2/n2 — volume operator/operand, BUKAN cabang siklikomatis).
Diagnosis lokal awalnya terbaca "0 temuan" karena
`composer archeology:gate` membaca report basi direktori build —
pelajaran #445/#647 replayed: analisis fresh sebelum gate. Tiga
perbaikan, masing-masing question-honest:

- **Split walk jadi empat unit pertanyaan** (preseden #647 "seams
  split the QUESTIONS"): `AliasLifetimes` fold stage (12.57),
  `AliasChains` satu-alias ride (17.14), `AliasTargets` kebijakan
  satu target (16.00), `ContextualSynthetics` vocabulary guard
  `@contextual:` (1.25 — preseden `DecoratorLayers::occupies`).
- **Probe store-half pindah ke inti registrasi**: `ContainerAnswers::
  lifetime` membaca `ServiceStore::factoryRegistrations()` (accessor
  yang sudah ada) → `FactoryRegistrations::sharingPolicy` (null saat
  tak terdaftar; arm memo di-drop karena unreachable — `set()` menulis
  pasangan bersama dan melupakan memo, lane singleton hanya mengisi
  yang registrasinya ada) — `ServiceStore` kembali ke baseline 16.39
  tanpa menyentuh jalurnya.
- **Fallback-half dipindah ke unit walk coverage**: `NamespaceCoverings`
  bertambah `entry()` + `lifetime()` (15.75/17.00), `FallbackSingletons`
  diekstrak dari arm singleton `resolve()` (5.25) — registry turun ke
  18.28/19.20.

Ronde ketiga kecil tapi tajam: kelas `AliasChains ↔ AliasTargets`
saling rekursif = temuan **circular dependency** error-level baru.
Perbaikan: arah rekursi dipecah satu-arah — `AliasTargets::ridden`
menjawab kebijakan leaf ATAU menyerahkan definisi alias-kelanjutan
(union `ServiceLifetime|AliasDefinition`), `AliasChains` yang memegang
rekursi; memo walk di-drop (guard perf murni; rantai boot-time
pendek-bound) sehingga entri ignore `ReturnRemoval` yang tadinya
dibutuhkan ikut HILUS dari infection.json5 — hanya ignore TrueValue
isset-blind yang tersisa. Pelajaran sampingan: mutant
`PublicVisibility` `longestPrefix` lolos karena pemanggil eksternalnya
hilang bersama refactor — visibility dititikan ulang jadi private
(jujur terhadap call graph baru). Dan pelajaran formulanya: D = n1/2 ×
N2/n2 bergerak aneh — membuang operand yang terakhir kalinya mengecilkan
n2 bisa MEMPERBURUK rasio; yang menurunkan D adalah menghilangkan
operator-UNIK terakhir pemakaiannya (kasus `??` satu-satunya di
NamespaceFallbacks) atau memindahkan volume ke unit lain.

## Ronda gate keempat: baris ignore tertelan (PR #829, CI Infection)

Ronde keempat: CI Infection memerah dengan TIGA escape TrueValue —
satu milik slice ini (regex ignore mengeja `\$alias->id` sementara
rector menamai ulang variabel jadi `\$aliasDefinition` di ronde
penyelesaian format — regex tak lagi cocok), dan DUA milik keluarga
telemetry (`\$emittedTypes[...]`) yang ignore-nya terdokumentasi.
Diagnosis: edit python slice ini pada entri TrueValue TERTELAN baris
regex `emittedTypes` yang lama (old_str mengandung dua baris,
new_str hanya menulis ulang satu + tambahan baru) — ignore
terdokumentasi hilang tanpa suara, mutant "lahir kembali" sebagai
escape. Preseden #824 lesson diperluas: bukan hanya kunci duplikat
yang melucuti ignore — edit TUPLE baris pada file ini harus
diaudit dengan diff penuh terhadap main sebelum commit
(`git diff origin/main -- infection.json5` kini langkah wajib slice).
Perbaikan: baris dipulihkan + regex slice mengeja nama variabel
pasca-rector; diff penuh terhadap main dijamin = satu tambahan
saja.

## KDD-B20..B24: memo alias ikut dilupakan saat target di-re-register (#770)

- **B20 (kontrak memo dilanggar di lane alias — temuan A-C2)**:
  `ServiceStore::set()` hanya `forget($id)` milik id yang di-daftar
  ulang — padahal post-#769 alias yang selaras singleton menyimpan
  jawabannya di slot memo MILIK ALIAS (`SingletonMemo` per-id), sehingga
  setelah `set('t', factoryBaru)`, `get('a')` (a→t) masih melayani
  instance LAMA sementara `get('t')` menjawab yang baru: setengah
  container melayani objek basi, docblock memo sendiri ("The previous
  instance must never answer again") dilanggar oleh lane alias. Fix =
  opsi #1 issue: reverse-index ride — registri `AliasRides` (alias→
  target) yang `set()` walk untuk melupakan slot SEMUA alias yang
  menunggangi id yang di-rebind, transitif (rider's rider sama basi
  dengan rider-nya: a→b→c, rebind c melupakan b dan a).
- **B21 (identitas, bukan dependensi — batas honest scope)**: invalidasi
  menjangkau LANE ALIAS saja, bukan dependen lain: singleton yang
  constructor-inject 't' (holder) tetap membawa instance lama pasca
  rebind — itu semantik rebind klasik DI (dependen = identitas lain,
  rebuild dependen bukan klaim issue ini; lihat doctrine rebuild
  namespace tersendiri kalau kelak dibutuhkan). Alias adalah identitas
  target dengan nama lain ("an alias is transparent") — di situlah
  bedanya: memo alias melanggar kontrak "previous instance must never
  answer again" MILIK target. Wrapper dekorasi pun identitas dependen
  (decoration = definition surgery, wrapper = service baru) — hanya
  slot `@decor:` yang re-homed tetap alias transparan yang ikut
  dicatat ride-nya. Scope request: slot scope milik target sendiri
  punya staleness pre-existing yang sama untuk keduanya (konsisten,
  alias TIDAK LEBIH basi dari target) — scope baru menjawab registrasi
  baru; test mem-pin scope-baru saja.
- **B22 (rekaman dari plan TERPASANG, bukan deklarasi parse)**: ride
  dicatat `ContainerInstalls` untuk SETIAP `AliasDefinition` di plan
  terpasang — apa pun id yang dihasilkan surgery pipeline (biasa,
  `@decor:` re-homed, `@contextual:` sintetis yang menunjuk
  implementasinya): graph yang di-walk invalidasi = graph yang boot
  instal. Predikat kosong (tanpa filter sintetis) itu sengaja:
  `@contextual:` sintetik singleton yang memo-nya men-PIN jawaban
  implementasi = bug kelas yang sama, invalidasinya benar juga.
  Re-register id alias itu sendiri = drop record (id kini jawab
  factory-nya sendiri, rebind target lama tak boleh menembusnya)
  sementara rider yang menunjuk id itu TETAP terjangkau (jawaban
  delegasi mereka berubah transitif). Fasilitas: `Container::
  markAliasRide()` (postur markLazy) + `ServiceStore::aliasRides()`.
- **B23 (walk BFS defensif, order tak diamati)**: `forgottenFor` =
  BFS reverse-atas-edge dengan visited-set — melanjutkan MELALUI alias
  yang terjangkau (rider's rider) karena jawaban delegasi mereka
  berubah transitif; arm siklus `isset($seen)` defensif (graph
  validator boot sudah menolak siklus — registry tidak mempercayai
  inputnya, preseden cycle-arm AliasChains). Urutan jawaban
  breadth-first TIDAK pernah diamati pemanggil (forget per-slot),
  dipin hanya demi determinisme baterai.
- **B24 (AOT generation 4 bump: member baru aliasRides)**: artifact
  membake index ride (`AotAliasRides::all` dari plan — alasan bake
  sama dengan lazy map: re-homing dekorasi tak selamat dari
  re-derivation parse; load lane `AotBootLane` men-mark dari peta
  baked, postur yang sama dengan lazy marks). Envelope enam kunci
  (digest/unreferenced/tags/lazy/entries/aliasRides), validator
  fail-closed per-member (`AotArtifactRides`: map, identifier
  non-kosong dua sisi — artifact robek tak bisa setengah-boot dengan
  ride menunjuk slot yang tak pernah dipasang). GENERATION 3→4:
  artifact generasi 3 tak membake index → re-registration invalidasi
  tidak menjangkau lane alias pada boot zero-planning — proven-stale,
  recompile + re-dump. Order kunci 'aliasRides' DITARUH SETELAH
  'entries' agar dua test missing-key lama (entries/lazy) tetap
  melaporkan kunci yang sama.

## Ronda gate: panggilan statik menambah operator unik (#770, archeology)

Ronde pertama slice #770 memerah archeology TIGA temuan: ServiceStore
file+class (22.33/22.50) dan AotArtifact class (20.17) di atas ceiling
20 — padahal main masing-masing 16.39 dan ~19.x. Diagnosis token:
Halstead D = (n1/2) × (N2/n2) — n1 file main = 5 operator unik,
tambahan panggilan STATIK `RebindInvalidations::forget(...)` di
ServiceStore::set() menaikkan n1 → 6 (operator baru), N2 59→61, n2
tetap 9 → D melompat 16.39→20.33 padahal volume tambahan kecil.
PANGGILAN INSTANCI `->` memakai operator yang sudah ada — mengganti
fold statik jadi KOLABORATOR INSTANCE (properti + konstruksi di ctor,
`$this->rebindInvalidations->forget($id)` di set()) menurunkan file
ke 17.50 (biaya slice +1.11 di atas main) TANPA mengubah semantik.
Pelajaran umum: difficulty Halstead sensitif terhadap operator UNIK
baru, bukan volume — dalam file yang n2-nya kecil (ServiceStore = 9
operand unik), satu operator baru = +20% D. Prefiks diagnosa:
bandingkan metrik file laporan ("Unique operators/operands") terhadap
main sebelum merancang refactor. AotArtifact 20.17 → 7.17 lewat
ekstraksi validatedTags ke unit keluarga AotArtifactTags (preseden
split per-member validator), juga mengikuti pola ronde archeology
#829 ("the walk splits into question units under the difficulty
ceiling").

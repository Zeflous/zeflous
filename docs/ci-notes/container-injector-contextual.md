# CI Notes — Container: Injector & Contextual Binding (C4)

Decision record untuk kampanye Container keluarga keempat
(`ROADMAP.md`, "CONTAINER" area 4 — Injector). C4 mengirim dua
sub-keluarga dalam satu PR: **method-call injection** (kunci `calls`
pada shape class/factory — desain baru, referensi tidak memilikinya)
dan **contextual binding** (knob `contextual` — port model
synthetic-alias-rewrite dari **github v2.9**
`ContextualBindingStore`/`ContextualBindingBuilder`, diadaptasi ke
grammar deklaratif zeflous).

## KDD-C4..C4.16

- **C4.1 (injection pasca-konstruksi adalah kunci grammar, bukan
  method container)**: referensi github imperatif
  (`$c->when()->needs()->give()`) tidak dipindah mentah — zeflous
  memakai grammar deklaratif `services.php`, jadi kedua fitur lahir
  sebagai permukaan deklaratif: kunci `calls` per-deklarasi (opt-in
  per definisi = "ekstensi opsional" roadmap terpenuhi tanpa knob
  global — absen = nol mesin) dan knob top-level `contextual`
  (mirror knob `tags`: map consumer → map dep → target).
- **C4.2 (synthetic-alias rewrite — jaminan graph tetap utuh)**:
  inti port github: dependensi konsumen ditulis ulang mengalir lewat
  id sintetik `@contextual:<consumer>|<dep>` yang merupakan
  `AliasDefinition` ke target. Graph boot lalu melihat edge nyata:
  target hilang → refusal unknown-service; siklus lewat binding →
  ditangkap DisciplineCycles; captive lifetime → ditangkap
  LifetimeCaptures. Nol jaminan baru yang harus dibuktikan — semua
  jaminan lama berlaku atas set yang ditulis ulang (test mem-pin
  ketiganya).
- **C4.3 (alias sintetik membawa LIFETIME KONSUMEN, bukan default
  singleton)**: temuan desain kritis — `LifetimeCaptures` menolak
  edge singleton→request; alias sintetik ber-default singleton akan
  memicu false refusal pada chain request-consumer→via→request-target
  (dan sebaliknya: memoisasi via singleton akan menangkap instance
  request secara diam-diam). Solusi: `AliasDefinition` melebar satu
  parameter opsional `ServiceLifetime` (default tidak berubah,
  grammar host tetap menolak kunci `lifetime` pada alias) — via
  ber-lifetime konsumen membuat analisis graph DAN resolusi runtime
  berperilaku setara referensi langsung consumer→target (test
  request→request legal; singleton→request tetap captive).
- **C4.4 (rewrite menargetkan deklarasi EFEKTIF — kemunculan
  terakhir)**: merge `framework.services` + `services` membiarkan id
  ganda (rebind: host menang). `ContextualConsumer::effective` berjalan
  terbalik sehingga kecocokan pertama = kemunculan terakhir —
  konsisten dengan last-wins `DefinitionEdges` dan loop install.
- **C4.5 (deklarasi dependensi = referensi di arguments ATAU calls)**:
  `ContextualDeclarations::declares` memeriksa kedua ranah — binding
  bisa meng-override referensi yang dibawa method-call injection,
  bukan hanya argumen konstruktor. Refusal jujur saat konsumen tak
  mendeklarasikan dep (alias/value konsumen kena ini secara alami).
- **C4.6 (guard runtime pakai kontrak callable, bukan
  method_exists)**: `MethodCallInvocations` memakai idiom
  `[$instance, $method]` + `is_callable` (preseden
  `FactoryInvocation`) — narrowing stan/psalm dan kontrak jujur untuk
  produk factory yang bertipe tak dikenal di boot. Pesan "is not
  callable on" mencakup missing DAN non-public. Bentuk kelas TETAP
  dibuktikan method_exists di parse-time (disiplin boot: tak pernah
  crash method-missing di first resolution untuk shape class).
- **C4.7 (jawaban method DIBUANG — injection, bukan builder)**: nilai
  balik method yang dipanggil `calls` diabaikan; instance termutasi
  adalah jawaban service. Dipin di test fixture fluent
  (`addTag(): self`).
- **C4.8 (produk factory non-object + calls = refusal jujur)**:
  factory boleh menjawab nilai apa pun; hanya object bisa menerima
  injeksi method. `MethodCallInvocations::onFactoryAnswer` menolak
  dengan nama factory + get_debug_type (test: `made:widget` string).
- **C4.9 (referensi calls = edge graph)**: `DefinitionReferences`
  mengumpulkan referensi argumen calls sebagai edge — resolvability,
  siklus, dan captive lifetime lewat setter terbukti di boot sama
  seperti lewat konstruktor ("singleton yang menangkap request-scoped
  lewat setter call = defect captive yang sama" — test mem-pin).
- **C4.10 (threading calls via WITH-METHOD, bukan parameter
  konstruktor)**: pelajaran archeology ronde-3 — menambah parameter
  `methodCalls` ke `ArgumentsForm`/`AutowireKey` menaikkan mass
  operand 6-param × repeat (D ArgumentsForm melonjak 20→38).
  Penggantinya: `ServiceDefinition::withMethodCalls()` — base menolak
  (guard misuse hand-built, preseden unreachable-guard), tiga VO
  implementasi override (`InstanceDefinition`,
  `UnplannedInstanceDefinition`, `FactoryDefinition`); satu seam di
  `InstanceArguments`/`FactoryShape`. `withAddedTags` adalah
  presedennya. Lesson: **ekstraksi helper ber-parameter-banyak
  MENGGENGAM operand mass; with-method pada VO TIDAK**.
- **C4.11 (kosakata penolakan ke kelas kata-kata sendiri)**:
  `MethodCallRefusals` + `ContextualRefusals` (preseden
  `AutoconfigRefusals`) — pesan refusal = operand mass terbesar di
  kelas grammar; memindahnya ke kelas kata-kata menurunkan D
  `MethodCall` 32→10 tanpa menyentuh logika.
- **C4.12 (pengenalan referensi dipisah dari kelas grammar)**:
  `ArgumentNames::identifier()` — pengenalan bentuk `['service'=>id]`
  yang dipakai jalur graph (`DefinitionReferences`) di kelas kecil
  sendiri; `ArgumentReference` tetap murni grammar validation
  (D-nya 14 dengan headroom tipis). `ContextualReference::names`
  tetap varian bool milik jalur contextual — dua ranah, dua bentuk,
  kontrak sama persis dengan `ArgumentResolution`.
- **C4.13 (spelling grammar factory dipecah)**: `FactorySpellings`
  membawa grammar `'Target::method'` (idiom double-explode tetap
  utuh — zona mutasi immortal terdokumentasi); `FactoryShape`
  turun ke dispatch + konstruksi.
- **C4.14 (binding duplikat struktural mustahil dari parse)**: key
  PHP unik per level — guard duplikat apply adalah unreachable-defense
  terdokumentasi (preseden `InstanceShape`); guard collision id
  sintetik MENJANGKAU misuse nyata: apply ganda / host mendeklarasikan
  literal `@contextual:...` (test mem-pin keduanya).
- **C4.15 (property injection ditolak secara desain)**: roadmap
  area 4 menyebut "Property/setter injection" — setter injection
  ADALAH `calls` pada method setX (bentuk terberkati dari "nilai
  ditetapkan ke properti setelah objek dibuat"); property injection
  langsung (tulis properti publik) DITOLAK karena melanggar disiplin
  readonly Enterprise Standard. Dicatat jujur di docs roadmap saat
  pin.
- **C4.16 (locus state)**: `ContextualSet::withBinding` melakukan
  operasi set (rebuild in-place + append alias + scan collision dalam
  satu loop); `ContextualBinding` murni guards; `ContextualBindings`
  murni loop knob; `ContextualConsumer`/`ContextualOverride`/
  `MethodCallShape`/`MethodCallNames`/`ArgumentNames` unit-unit
  fokus tunggal. Struktur akhir: 17 file src baru untuk C4 —
  semuanya unit archeology-sized (D < 20 semua, gate PASSED).

## Baterai & gerbang

- Test: +67 (3266→3333; +108 assertion) — grammar refusals
  ber-pesan penuh, apply/rewrite semantics, e2e bootstrapper
  (classic: `billing` autowire `PaymentGateway` → stripe sementara
  alias default tetap menjawab mock), graph/capture/cycle lewat
  binding + lewat calls, lifetime-carry, rebind last-occurrence.
- Saga archeology 6 ronde (15→13→11→7→2→0 error-level): strategi yang
  menang = (a) pesan ke kelas kata-kata, (b) with-method VO
  alih-alih param-threading, (c) unit fokus tunggal per concern,
  (d) early-return guard positif, (e) first-class callable
  `NamaKelas::metode(...)` menggantikan closure recognition.
- stan max ✓ ratchet ✓ (nol baseline baru) · psalm L1 ✓ (nol entri
  baseline baru) · fixer ✓ phpcs ✓ rector ✓ deptrac ✓ ·
  coverage 96.76% · archeology 0 error-level · bench & infection:
  jalur CI (lane masing-masing).

Atribusi referensi: github v2.9 (`reference/zef-framework-src/src/
Application/Container/ContextualBindingStore.php` +
`ContextualBindingBuilder.php`) — model synthetic-alias rewrite +
guard consumer/dep/duplikat diport dengan atribusi byte-level pada
format id `@contextual:<consumer>|<dep>`. Method-call injection:
desain baru zeflous (referensi tidak mengimplementasikannya —
konfirmasi klaim user "mengejar fungsi cepat" pada PORT-MANIFEST).

# CI Notes — Container: The Tags & Autoconfiguration Family (C3)

Decision record untuk lane ketiga kampanye Container (`ServiceTags` +
grammar `tags` + `ServiceTagIndex`/`TaggedServices` + koleksi variadic
`AutowireImplementations` + autoconfiguration `AutoconfigMap`/
`Autoconfiguration`/`#[Tag]`). Dokumen ini melengkapi
`docs/ci-notes/container-autowiring.md` (KDD-A1..A14) dan
`docs/ci-notes/container-scope-lifecycle.md` (KDD-B1..B13) — tags &
grouping adalah "CONTAINER" area 6, autoconfiguration area 3 di
`ROADMAP.md`.

Model yang diport: **github v2.9** untuk sisi tulis `tags` (validasi
konstruktor non-empty strings) dan sisi baca `TaggedServiceLocator`
(hasTag/idsFor/resolveAll) serta `collectImplementations` (koleksi
implementasi via `is_a` + inferensi return-type factory).
Autoconfiguration marker-interface/attribute **tidak ada di referensi**
(roadmap-only) = desain baru zeflous, diatribusikan jujur.

## KDD-C1..C14

- **C1 (grammar tag = `[A-Za-z0-9._-]{1,128}` TANPA trim)**: lineage
  referensi men-trim lalu memvalidasi — repair senyap yang menulis
  ulang deklarasi (tag `' app.x'` dibaca `'app.x'`). Zeflous menolak
  ejaan mentah di luar grammar dengan pesan ber-tag dan ber-grammar:
  kasus tertelan diam-diam adalah defect, bukan penyederhanaan
  (CONTRIBUTING rule 4). Duplikat tag dalam satu deklarasi = refusal
  menyebut tag yang berulang — referensi mendedup diam-diam
  (`in_array` guard) saat membangun indeks; deklarasi yang selamat
  boot adalah deklarasi yang host tulis.
- **C2 (`ServiceTags` = value object ber-otoritas grammar)**: satu
  tempat memegang grammar (`ServiceTags::assert`) yang dipanggil parser
  deklarasi (ctor), parser kunci (`fromKey`), attribute `#[Tag]`, DAN
  sisi baca (lookup) — lookup tak mungkin menerima ejaan yang
  deklarasi tolak, atau sebaliknya. Properti internal private +
  aksesor `all()`/`carries()` — permukaan bertipe penuh, konsumen tak
  menyentuh array mentah.
- **C3 (shape coverage simetris `lifetime`)**: kunci `tags` diterima
  shape class (ketiga bentuk argumen) dan factory; alias = refusal
  "transparan: tag milik definisi yang ditunjuk"; value = refusal
  "konstanta bukan layanan berkelompok". Threading menyusuri
  InstanceShape → InstanceArguments → ArgumentsForm/AutowireKey →
  InstanceDefinition & UnplannedInstanceDefinition, dan
  `AutowireRequestPlanning` **membawa tags unplanned → planned** —
  kehilangan tags saat planning adalah kasus tertelan yang
  sengaja dicegah (tag bertahan melewati compile-pass).
- **C4 (indeks = dua pass, semantik rebind `set()`)**:
  `ServiceTagIndex` dibangun SEKALI di boot dari set ter-plan:
  pass-1 `tagsById[id] = tags` (PHP: kunci eksisting menahan posisi
  pertama, nilai menang terakhir — rebind host menimpa tag framework,
  id tetap di posisi deklarasi pertama), pass-2 invert ke
  `tag → list<id>` urutan deklarasi. Tanpa dedupe eksplisit: kunci map
  unik + tag per-deklarasi unik (C1) — jawaban stabil untuk set
  deklarasi yang stabil.
- **C5 (read side = layanan reserved `framework.tags`)**: locator
  `TaggedServices` diregistrasi ContainerBootstrapper SEBELUM loop
  install — preseden `framework.config`: rebind host eksplisit atas
  nama reserved MENANG (mesin framework tidak pernah menimpa pilihan
  host; exposure ini disengaja dan didokumentasikan). Lazy: closure
  `set()` membangun indeks saat `get('framework.tags')` pertama —
  boot tanpa lookup tag tak membayar apa pun. Resolusi via
  `Container::get()` apa adanya — lifetime/guard/depth tak tersentuh,
  kegagalan resolusi propagasi (fail-fast referensi).
- **C6 (permukaan locator = empat jawaban, `one()` fail-closed dua
  arah)**: referensi menjawab "tak dipakai" dengan null
  (`resolveOne`) dan "beberapa" lewat exception tipe kedua
  (`requireOne` + `ServiceNotFoundException`) — dua ejaan lembut satu
  kekhawatiran. Zeflous: `has()`/`ids()`/`services()`/`one()` —
  absent = refusal ber-way-out, multiple = refusal menyebut semua id.
  Lookup tag di luar grammar = refusal ber-grammar (C2).
- **C7 (koleksi variadic = SET TERDEKLARASI saja, births dikecualikan)**:
  port `collectImplementations` github, tapi sumbernya snapshot set
  ter-deklarasi saat `open()`. Kelahiran (birth) adalah insiden
  planning sesi ini, bukan deklarasi ekosistem tipe — memasukkannya
  membuat jawaban bergantung urutan parameter kebetulan di-plan
  (order-dependence = tebakan). Test simetris dua urutan mem-pin.
- **C8 (factory ikut koleksi hanya dalam bentuk statis murni)**:
  return-type `Target::method` diinferensi bila `class_exists(target)`
  DAN target tak tertutup id ter-deklarasi/container (preseden
  preseden edge `DefinitionReferences`: id terdaftar menang atas nama
  kelas → instance-method yang produknya tak terbukti dari ejaan) DAN
  method static dengan return type non-builtin tunggal. Over-inclusion
  (anggota palsu = TypeError runtime) lebih buruk dari exclusion
  terdokumentasi + way-out (deklarasikan lewat class shape) — jalan
  konservatif sesuai disiplin jalur ini (null tak pernah ditebak).
- **C9 (amendmen C1: union/intersection variadic = refusal, bukan
  [])**: C1 menjawab [] untuk variadic unspelled apa pun (deferral
  area 3). Dengan koleksi hidup, [] senyap untuk tipe ambigu adalah
  inkonsistensi yang di-flag: variadic ter-classname → kumpulkan;
  union/intersection → refusal jujur ("koleksi adalah implementasi
  ter-deklarasi SATU tipe"); scalar/untyped → [] tetap jujur (tak ada
  ekosistem). Spelled stretch tetap menang atas koleksi (explicit wins
  over auto).
- **C10 (autoconfig = desain baru; knob `tags.interfaces`)**: roadmap
  area 3 tak punya kode referensi (manifest grup C: "autoconfig
  marker-interface/attribute: TIDAK ada di referensi = desain baru").
  Bentuk: map `tags.interfaces` (interface FQCN → tag) diparse
  `AutoconfigMap::parse` murni-di-Container-ns (preseden
  `DefinitionMap`: array polos masuk, bukan kelas Config — deptrac
  Container-bebas-Config utuh). Unknown-key ditolak ber-daftar-known;
  interface tak-ada = refusal ber-nama (aturan yang tak pernah match
  = typo berbaju aturan); tag di luar grammar = refusal
  ber-interface. Default FrameworkDefaults: `['interfaces' => []]` —
  framework tak mengirim aturan marker-nya sendiri.
- **C11 (#[Tag] = attribute kelas, IS_REPEATABLE)**: `#[Tag('name')]`
  di kelas, dibaca boot-only per class shape ter-deklarasi (satu
  `ReflectionClass` per deklarasi — refleksi tetap hanya di boot).
  IS_REPEATABLE: kelas ber-berapa tag berbeda legal (tiap attribute
  satu tag); tag sama dua kali = collision refusal. Kelas yang tak
  pernah dinamai set ter-deklarasi membawa attribute-nya TIDAK
  terdengar — jalur ini tidak men-scan apa pun (discovery bukan
  bisnis lane ini; deklarasi adalah sumber apa yang boot).
- **C12 (satu tag satu sumber — collision = refusal menyebut semua
  sumber)**: eksplisit ∩ aturan interface ∩ attribute, dua aturan,
  attribute ganda — semuanya refusal dengan tiap sumber dinamai
  (tags key / aturan interface / attribute). Alternatif dedupe-senyap
  ditolak: upgrade framework yang membuat aturan baru menutup kelas
  yang sudah di-tag eksplisit harus jadi HALT yang terlihat dan
  pembersihan yang bisa ditindak, bukan rewrite senyap atas apa yang
  host deklarasikan. Pesan menyertakan way-out: "declare the tag
  once, from one source."
- **C13 (autoconfig hanya menjangkau class shape)**: hanya class shape
  menamai kelasnya → hanya ia bisa mendapat tag otomatis. Factory
  shape: tipe produk tak dieja deklarasi (C8 jalan keluarnya); alias
  dan value tak menamai kelas. Apply berjalan SETELAH compile-pass
  (semua class shape sudah InstanceDefinition planned), SEBELUM graph
  dan indeks — tag hasil earn dan eksplisit tak terbedakan di
  hilir: satu daftar tag, bagaimanapun ia diperoleh.
- **C14 (dokumen + knob hidup + bench)**: docblock `services.php`
  mengeja grammar tags + dua ejaan otomatis + way-out collision;
  knob `tags` FrameworkDefaults kini hidup (rule "every knob must be a
  live key"); `TaggedServicesBench` mem-pin ekonomi sisi baca (lookup
  ids = konstanta map; services = biaya resolusi per anggota).

## Pelajaran proses (untuk baterai gate)

- Test grammar tags tinggal di `ServiceDefinitionTest` (covers shape
  parsers + ServiceTags) — pelajaran covers-restriction ronde-1 #396
  diterapkan sejak baris pertama: test harus terlihat oleh infection
  melalui covers pemilik baris yang dimutasi.
- IS_REPEATABLE ditemukan lewat error PHP asli saat test collision
  attribute ganda: PHP menolak attribute non-repeatable yang
  berulang di `getAttributes()` — kegagalan keras itu jujur tapi di
  luar family exception; desain diputar ke IS_REPEATABLE + deteksi
  collision milik lane (pesan ber-sumber).

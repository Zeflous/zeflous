# CI Notes — HTTP Layer (Zero-Library Shim, Fase 1)

Decision record untuk lane 1 arah user **"HTTP Layer -> Container -> Middleware"** di bawah
area ROADMAP **HTTP Layer** + **HTTP Layer — Zero-Library Shim**. Dokumen ini menutup Fase 1
(inti PSR-7/17: contracts, stream, Uri, message/response, request objects, uploaded file,
factory), Fase 2 (runtime adapter: `fromGlobals` + emitter, KDD-H10..H12) dan potongan
security Fase 3 pertama (input validation gate, KDD-H13).
Dokumen ini melengkapi `docs/ci-notes/config-system.md` (KDD-1..7), `radix-engine.md` (KDD-R1..R9),
`dsl-builder.md` (KDD-D1..D8) dan `integration.md` (KDD-I1..I6) — pola yang sama, lane yang lain.

## KDD-H1..H9

- **H1 (namespace & boundary)**: implementasi hidup di `Zef\Framework\Http\` (cermin `Config\`);
  kontrak vendored di `src/Contracts/Psr/Http/{Message,Server}/` (kelanjutan preseden PSR-11 shim:
  phpcs/php-cs-fixer/rector exclude `Contracts/Psr`, analyzer puas lewat docblock, deptrac exclude
  — boundary artifact, bukan layer ZEF). Autoload composer sudah terpasang sejak scaffold. Enam
  slice Fase 1 mendarat di urutan: stream engine (#256), Uri (#259), message+response (#262),
  request objects (#265), uploaded file (#271), PSR-17 factory (PR lane ini).
- **H2 (satu akar exception fail-closed)**: `final class HttpException extends ZefException` —
  seluruh sinyal legacy (`StreamException`, `InvalidArgumentException`,
  `UploadedFileException`) dilipat ke satu akar, SEMUA pesan kegagalan byte-identik legacy
  (`Stream detached.`, `Unable to write uploaded file.`, `Uploaded file is not available
  (error N).`, ...). Catchability struktural tidak hilang: akar framework extends RuntimeException
  persis seperti kegagalan runtime legacy. Deviasi terdokumentasi: (a) `Psr17Factory` tidak lagi
  membungkus fopen dengan pesan `Unable to open '{file}'...` milik legacy — pemanggilan routed
  lewat seam `StreamOpener` (H3) sehingga pesannya format seam; (b) garbage `$uri` pada
  `createRequest`/`createServerRequest` yang legacy koersikan diam-diam lewat `(string)` kini
  ditolak keras — koersi array-ke-`'Array'` adalah silent fallback, melanggar prinsip layer.
- **H3 (seam operasi engine)**: dua seam dengan disiplin berbeda — `StreamOpener::open()`
  melempar DARI DALAM handler (format pesan milik seam), `ScopedDiagnostics::fopen()/rename()`
  menangkap warning sebagai NILAI (pesan legacy meng-append diagnostik engine, jadi warning
  harus kembali sebagai data — kembar capture-style dari StreamOpener). Alasan sama dengan lane
  Config: `php://temp` tidak pernah gagal, file nyata bisa — cabang error I/O wajib ter-cover
  jalur nyata (pembelajaran Sonar new_coverage per-baris).
- **H4 (kontrak vendored bertipe)**: interface shim bertipe + docblock lengkap, tanpa guard
  `interface_exists` (preseden PSR-11). Parameter untyped di upstream (PSR-7 `$value` header,
  PSR-17 `$uri`, `$resource`) diimplementasikan `mixed` — penyempitan dilarang (kontravariansi
  PHP) — dan runtime-check menjadi enforcement kontrak: `resolveUri()` menolak non-string
  non-Uri, `createStreamFromResource()` menolak non-resource dan resource tak-baca. Docblock
  `@param` implementasi WAJIB membawa deskripsi — bare type dihapus fixer sebagai superfluous,
  dan tanpa docblock local, analyzer mewarisi klaim `non-empty-string`/`resource` milik interface
  sehingga runtime-check dikira dead-code.
- **H5 (baseline psalm sebagai kendaraan)**: 15 interface turun sekali di PR-A; entry UnusedClass
  dipangkas SETIAP konsumen mendarat: −5 StreamInterface (moveTo #271), −6 antarmuka factory
  (factory ini), entri PossiblyUnusedMethod baru menunggu konsumen Fase 2 (runtime adapter akan
  memanggil createServerRequest/createStreamFromFile/...). Net-zero di akhir lane tetap tujuan;
  `MiddlewareInterface` menyusul di lane 3.
- **H6 (immutable via clone / one-shot move)**: with* = `clone $this` + mutasi private (semantik
  PSR-7; zero-trait: RequestTrait legacy → hirarki kelas `Request` non-final → `ServerRequest`).
  `UploadedFile` satu-satunya objek bermutasi by-design: flag `moved` + stream ditutup setelah
  move sukses — move adalah operasi sekali-jalan, akses berikutnya gagal tertutup (kontrak PSR-7
  "moved to a new location").
- **H7 (grammar RFC di kelas statis)**: pola `RoutePathGrammar` diikuti — `UriGrammar` +
  `UriHostGrammar` + `UriEncoder` (RFC 3986 byte-wise), `HeaderGrammar` (RFC 9110 token/field-value),
  `HttpStatusLine` (status 100..599 + reason-phrase byte grammar), `HttpMethod` (token),
  `StreamMode` (grammar mode fopen legacy: dua pola anchored untuk dua urutan flag). Fixer
  `mb_str_functions` dihindari lewat iterasi byte `isset($value[$i])` (UriEncoder) atau bentuk
  eksplisit `mb_strlen($s, '8bit')`/`mb_substr(..., '8bit')` — byte-exact untuk payload biner
  upload, bentuk yang fixer-stable.
- **H8 (departurisasi fitur security lintas-lapis)**: `trustedHosts`, `LimitedInputStream`,
  `RequestBodyPolicy`, CORS, EAG tetap TIDAK ikut — milik Fase 3 security di PR masing-masing.
  Fase 1 murni grammar + kontrak PSR-7/17.
- **H9 (docs & bench jujur)**: dokumen ini adalah deliverable closeout; atribusi legacy
  (`Zeflous/zef-framework` `src/Compat/Psr/Http/*` shim bertipe + `src/Adapters/Http/*`
  implementasi — `Stream/Uri/UriGrammar/Message/MessageBase/RequestTrait/Request/ServerRequest/
  Response/UploadedFile/Psr17Factory`) tercatat di docblock tiap kelas. TIDAK ada bench baru di
  Fase 1: `new Stream`/`new Uri` bukan hot-path yang layak diukur — bench menunggu emitter dan
  factory hot-path nyata di Fase 2 (preseden RoutingRuntimeBench: ekonomi diukur saat ada
  pertanyaan, bukan sekadar bisa).

## Hasil experiment sandbox (menopang H3/H7 — tersimpan `scripts/php-stream-experiment.php` + `scripts/uploaded-file-experiment.php`)

1. `fwrite`/`fread` return `false` BISA dipicu stream-wrapper userland (fixture test) — cabang
   defensif `=== false` ter-cover ASLI, bukan mutant-inversion saja.
2. `ftell(php://stdout)` menjawab `false` beneran (test stdout-shape-sensitif: jalankan via pipe,
   bukan redirect file).
3. Wrapper tanpa `stream_tell` tidak error — ftell menjawab posisi default; wrapper seekable-meta
   vs fseek-gagal = bentuk ketidaksesuaian engine yang wajib gagal tertutup (`NoSeekStreamWrapper`).
4. `fread` pada wrapper yang eof-nya berbohong (selalu false) dan read-nya habis setelah payload:
   fread menjawab payload lalu `''` TANPA engine-loop — break defensif loop copy hidup sebagai
   cabang nyata (`LyingEofStreamWrapper`).
5. `fopen` pada wrapper yang `stream_open`-nya menjawab false: warning engine ter-capture handler
   scoped + kembalian false; `url_stat` mode direktori membuat `is_dir(dirname)` lolos sementara
   nama tmp tidak dianggap file eksisting (`UnopenableStreamWrapper`).
6. `rename(file, direktori-eksisting)` gagal nyata dengan warning `Is a directory` — jalur
   finalize gagal tanpa wrapper (`E3`), file sumber tetap ada lalu dibersihkan cleanup.
7. `fwrite` pada wrapper yang `stream_write`-nya menjawab false: fwrite menjawab false —
   kegagalan copy payload di uji lewat resource tujuan asli (`UnwritableStreamWrapper`).

## Pola battle lane ini (untuk PR HTTP berikutnya)

- **Split difficulty**: bentuk single-class legacy melampaui budget (UploadedFile D 51.11/CC 24)
  → split kolaborator ala stream engine: UploadedMove/UploadedTarget/UploadedTransfer/
  UploadedPosition/ScopedDiagnostics (semua ≤ D 25→masing-masing ≤ 6.7... gate PASSED).
  Senjata pertama selalu split, bukan Suppress.
- **Mutan guard-nullable + catch(Throwable)**: `ReturnRemoval` pada early-return nullable di
  dalam scope yang menelan Throwable TIDAK terbunuh (TypeError tertelan, observabel nol) —
  null-check pindah ke PEMANGGIL dengan tipe param non-nullable; mutan flip terbunuh TypeError.
- **Timeout = kill sah**: mutan yang melahirkan loop tak-hingga (break removal, `+=`→`-=`)
  terbunuh via timeout infection — by design, bukan escape.
- **ksort DirectoryLoader**: satu-satunya escape lokal yang terdokumentasi (urutan filesystem
  lokal sudah sorted) — CI membunuh; jangan "perbaiki" yang bukan defect.
- **Sonar S3415 vs PHPUnit**: `assertStringEqualsFile($file, literal)` kena heuristik
  literal-first — ganti ke `assertSame(literal, file_get_contents($file))`, tanpa NOSONAR.
- **Gate roadmap #266**: Commit cell HARSHA SHA merge asli — row HISTORY menumpang PIN PR
  pasca-merge (preseden #256/#250/#253; PR-E: #272), bukan placeholder PENDING di branch feature.
- **Lingkungan lokal**: coverage/infection WAJIB via php84apt + PHPRC pcov.ini (php84 portabel
  TANPA pcov — clover basi = positif palsu); `/tmp` dibersihkan dari debris `.zef-tmp-*`
  (mutan hapus-unlink membocorkan file saat terbunuh) sebelum suite yang meng-assert glob kosong.

## Closeout Fase 1

Enam slice merged (#256, #259, #262, #265, #271, + PR lane ini): kontrak PSR-7/15/17 vendored,
stream engine, Uri VO, message+response, request objects, uploaded file, PSR-17 factory —
single fail-closed `HttpException`, pesan byte-identik legacy, MSI 100% (CI), coverage ~99.2%,
archeology gate PASSED, baseline psalm dipangkas seiring konsumen mendarat. Yang TIDAK ikut
(milik fase/lane lain): runtime adapter superglobal→ServerRequest + emitter (Fase 2),
`MiddlewareInterface`/`RequestHandlerInterface` konsumsi pipeline (lane 3), security
(trustedHosts, CORS, ETag, request-body policy — Fase 3), bench hot-path (menunggu emitter).

## Fase 2 — KDD-H10/H11 (superglobal request factory)

**H10 — klaim key-folding PSR bag.** PHP melipat numeric-string array key ke bentuk integer
pada setiap insert, sehingga kunci integer sebuah bag SAPI ADALAH bentuk kanonik engine dari
numeric-string key-nya — dua key `0` dan `'0'` tidak dapat dibedakan lewat observasi PHP
mana pun. Kontrak PSR vendored mendeklarasikan bag-bag itu `array<string, mixed>`; parameter
factory tetap jujur array-keyed, dan empat seam `@var` (satu di `fromGlobals` untuk `$_SERVER`,
tiga di `fromServer` untuk cookie/query/uploads) menyatakan klaim kontrak pada titik temu —
pola standar ekosistem PSR, tanpa kode runtime, sehingga bebas mutant (CastString pada
re-keying runtime akan menjadi mutant identitas yang abadi).

**H11 — grammar-collaborator melawan mutant MBString posisi.** Rector mengonversi
`strpos/substr/strlen/strtolower` ke `mb_*`; mutator MBString (aktif dalam `@default` lewat
`@extensions`) mencongkelnya kembali ke versi byte. Untuk semantik Unicode (case fold, trim)
mutan itu terbunuh lewat boundary Unicode (Kelvin sign U+212A, long-s U+017F, NBSP). Untuk
POSISI pada konteks yang input multibyte-nya pasti invalid (host/label), mutan versi byte
bersifat identik-observabel (abadi) — maka kolaborator grammar ditulis sebagai satu pattern
anchored (`BracketedAuthority`, `DnsHost`) atau explode+count (`BareAuthority`), mengikuti
gaya rumah UriParser/HeaderGrammar (parse_url + preg_match, bukan fungsi posisi). Fungsi
`mb_trim`/`mb_rtrim` TIDAK termasuk daftar konversi mutator — bebas mutan secara gratis.

Pola battle tambahan lane ini:

- **Mutasi tertutupi normalisasi Uri**: port default (`:80`/`:443`) dinormalkan oleh `Uri`
  sendiri (PSR-7 getPort null untuk port standar) — mutan `isDefaultSchemePort` tertutupi
  pada observasi uri-string; dibunuh lewat unit test langsung `ServerPort::resolve` (matriks
  11 kasus), bukan lewat fromServer.
- **Mut early-return ekuivalen-jatuh**: `return false`/`return ''` yang jalur jatuhnya
  (fall-through) menghasilkan jawaban sama bersifat abadi — direstrukturisasi (flatten
  ekspresi, gabung guard) atau dibunuh lewat kasus yang mengekspos determinasi berlebih
  (mis. `SCRIPT_NAME='/'` → `basename('/')=''` membuka flip `(A||B)&&C`).
- **Mutan Coalesce port vs authority**: `$authorityPort ?? resolve(...)` dibalik mutan —
  dibunuh lewat kasus authority-port-vs-SERVER_PORT (8443 menang atas 8080).
- **Ghost-downgrade menutupi koersi error**: error `'4'` (NO_FILE) menjadikan mutan koersi
  tak-terbedakan — matriks koersi memakai `'7'` (CANT_WRITE) + payload nyata.
- **Continue_→break pada passthrough**: dibunuh lewat leaf pra-ternormalisasi (objek
  UploadedFile langsung di `$_FILES`) SEBELUM field spec — break menghentikan walk field
  berikutnya.
- **Karakter kontrol authority**: pre-check `[\x00-\x20\x7f@\/?#]` legacy terbukti redundan
  secara observabel (semua byte itu juga gagal grammar IP/DNS) — dilipat ke delegasi
  validitas, pesan exception identik.

## Fase 2 — KDD-H12 (SAPI response emitter)

**H12 — transmisi SAPI sebagai seam ber-sink.** Eksperimen engine (tersimpan
`/home/z/my-project/scripts/sapi-emitter-experiment.php` di sisi riset): CLI SAPI
**membuang `header()` secara diam-diam** — `headers_list()` selalu kosong bahkan saat
`headers_sent()` menjawab false — sehingga cabang real `header()` bisa DIEKSEKUSI di
phpunit (PHPUnit menjaga satu output buffer terbuka, `headers_sent()` tetap false) tetapi
tidak pernah terobservasi. Sebaliknya `http_response_code()` TERobservasi di CLI
(set/get round-trip). Konsekuensi desain: `SapiTransmitter` adalah seam yang menerima
tiga sink closure opsional di konstruktor (field/status/sent); versi default memanggil
SAPI asli, versi rekaman mengubah void-write menjadi nilai sehingga call-site emission
tetap jujur-mutan (flag replace, ejaan baris field, dan status yang dikirim semuanya
menjadi assertable) TANPA menumbuhkan API test-only di layer (psalm findUnusedCode hanya
menganalisis src). Dua pengecualian yang diambil dengan sengaja dan terdokumentasi:

- `header($line, $replace)` di cabang real = satu-satunya panggilan fungsi yang
  penghapusannya tak terobservasi oleh proses test mana pun — `FunctionCallRemoval`
  di-skip SATU method itu di `infection.json5` (preseden config-artefak: skip-rule
  `rector.php`, `single_line=false` fixer, exclusion phpcs). Tetangganya
  `http_response_code()` tetap di bawah mutator dan penghapusannya dibunuh test SAPI-real.
- Keluarga mutan `UnwrapArray*` (array_values/array_filter/array_map pada normalizer
  murni) bersifat abadi — foreach tidak melihat kunci. Emission loop direstrukturisasi
  menjadi `array_walk` bersarang dengan closure ber-parameter `mixed` (pola
  `UploadedTree::assert`): tanpa normalizer, tanpa cast, tanpa `@var` seam, dan
  penghapusan `array_walk` itu sendiri terobservasi (jurnal kosong).

Atribusi legacy (`Adapters/Kernel/ResponseEmitter.php`, move-verbatim): urutan
rewind-before-headers (middleware inspektor body seperti ETag meninggalkan kursor di EOF),
guard `headers_sent()`, revalidasi status 100..599 fail-closed untuk implementasi asing,
rekonsiliasi Content-Length terhadap sisa stream (aritmetika `max(0, size - tell)` +
clamp nol + stream tak terukur kalah header — anti CL.CL smuggling RFC 9110 §8.6),
skip-on-invalid per field (catch-Throwable legacy dilipat ke gerbang boolean
`HeaderGrammar::acceptable()` yang tak-bisa-lempar), body eligibility 1xx/204/205/304,
chunk 8192 dengan ''-break defensif, dan `closeBody` di `finally`. Deviasi terdokumentasi
vs legacy: (a) anggota field non-string DI-DROP, bukan dikoersi string — aturan no-coercion
toko header (PR-C) dan resolusi uri (PR-F) diperpanjang ke jalur emission; (b) aliran
return `emitHeaders()` legacy dilipat ke `void` — strip CL hanya berdampak pada emission
field, tak ada observabel jalur body yang berubah.

### Hasil experiment sandbox (SAPI, menopang H12)

- `headers_sent()` di CLI pristine = **false**; byte output PERTAMA (bahkan `var_dump`)
  langsung membaliknya ke true; selama test PHPUnit = **false** (buffer output phpunit).
- `header()` di CLI: tidak pernah warning selama `headers_sent()` false, tetapi
  `headers_list()` **selalu kosong** — tidak ada channel observasi.
- `http_response_code(201)` di CLI/phpunit: set sukses, get round-trip ✓ — satu-satunya
  state SAPI yang terobservasi.
- `php://temp` kosong menjawab `eof() = false` SEBELUM read pertama — ''-break emitter
  adalah jalur WAJIB untuk body kosong (bukan sekadar defensif), terverifikasi lewat
  `RecordingStream` ([8192] pada stream kosong).
- `fread($h, 8192)` meminta PANJANG penuh walau bytes tersisa kurang — matriks ukuran
  chunk adalah [8192, 8192, 8192] untuk payload 20000 (panjang permintaan, bukan
  panjang balasan).

### Battle tambahan emitter

- **Mutant abadi → restrukturisasi, bukan ignore** (kecuali `header()` di atas): CastString
  pada `(string) $name` dibunuh lewat kunci integer engine — `withHeader('123', 'v')` pada
  `Response` sendiri melipat kunci numeric-string ke int saat insert (KDD-H10) dan
  `getHeaders()` menjawab `[123 => ['v']]`; mutan menghapus cast → `acceptable(int)` →
  TypeError. Jalur ini MENEMUKAN bug riil PR-C: `HeaderIndex::storedName` strcasecmp
  pada kunci terlipat → TypeError pada semua lookup — diperbaiki dengan cast + klaim
  param dilebarkan jujur (`array<array-key, list<string>>`), regresi dipinkan
  `ResponseTest::testResolvesNumericHeaderNamesThroughTheEngineFoldedKey`.
- **Mutan ReturnRemoval pada early-return ekuivalen-jatuh** (guard CL kosong): jatuhannya
  menghitung ulang dan menjawab respons ekuivalen — dibunuh lewat SPY, bukan nilai:
  `CursoredStream::$tellCalls` di-assert 0 (jalur tanpa-CL tak boleh konsultasi kursor).
- **Mutan `&&`→`||` pada sisa ternary CL**: satu operand null + sisi lain terkoersi nol
  secara diam (PHP arithmetic null→0) — dibunuh lewat deklarasi '0' + size null (real:
  drop; mutan: max(0, null-0)=0 → keep).
- **Mutan `||`→`&&` precedence pada guard CL**: dibunuh lewat 'abc' + sisa 0 ((int)'abc'
  = 0 = sisa → C false, A true, B false).
- **Mutan Continue_→break pada gate acceptable**: break menghentikan sisa nilai SATU
  baris — dibunuh lewat baris multi-nilai dengan anggota pertama invalid
  (`["first\nbad", 'second']` → hanya 'second' yang boleh lewat).
- **FalseValue pada default flag emit()**: dibunuh lewat pemanggilan BARE
  (`emit($response)`) dengan assert body terkirim + stream tetap terbuka.
- **Mutant UploadedMove ×6** (nama sibling tmp): lolos LOKAL pada main pristine yang sama
  (diverifikasi worktree main 0d7c091 — set identik) — kelas flake lokal yang dibunuh CI
  (PHP 8.4.26, runner segar), keluarga yang sama dengan flake ksort DirectoryLoader yang
  terdokumentasi; bukan regresi lane ini.

## Fase 3 — KDD-H13 (request body policy: input validation gate)

**H13 — dua paruh satu limit di ingress.** Roadmap "Security & Hardening" 2.1 menuntut
max body size enforcement "di level adapter sebelum parsing". Kebijakan satu objek
(`RequestBodyPolicy`, default 2 MiB, fail-closed pada maxBytes < 1) dijalankan dua
paruh yang MENJAWAB LIMIT YANG SAMA:

- **Paruh eager (klaim)**: `RequestFactory::fromServer/fromGlobals` menerima `?RequestBodyPolicy`
  (null = postur default, TIDAK pernah tak-terbatas) dan menilai header `Content-Length`
  SEBELUM `php://input` dibuka — claim ter-OWS di-trim dulu (Audit #329 legacy: `' 123'`
  pernah lolos ctype_digit), hanya digit-string well-formed yang dibandingkan; claim
  malformed BUKAN sinyal ukuran (dibiarkan ke paruh lazy — engine juga tidak percaya header).
- **Paruh lazy (byte nyata)**: body disajikan sebagai `LimitedInputStream` (decorator read-only
  atas stream inner) dengan dua kolaborator depth-split ala stream engine (gate archeology
  D 32.06 → 20.17/11.64/18.00 — senjata pertama selalu split, bukan Suppress): `BodyBudget`
  memegang counter + aritmetika policy — probe minta maksimal SATU byte melewati sisa budget
  (`min(length, max(1, remaining+1))`) — byte ekstra itulah yang membedakan "tepat di limit"
  (short read compliant) dari "melewati limit" tanpa pernah menampung lebih dari budget;
  `refuses()` yang melintas meracuni counter ke `maxBytes + REFUSED_OVERSHOOT` sehingga read
  berikutnya ikut gagal (sticky — konstanta bernama, bukan literal inline: infection TIDAK
  memutasi deklarasi konstanta kelas, dan kedalaman racun memang tak-terobservabel); 
  seek/rewind re-base counter (bug fix #1 legacy: read+rewind+read tidak double-charge, clamp
  `max(0, tell())` menolak kursor negatif); `effectiveSize()` menjawab ukuran efektif
  `min(inner, maxBytes)` (N-8 legacy: PSR consumer menalar "berapa byte yang BISA diantar").
  `BodyTransfer` memegang transfer terjaga (mirror policy-aware dari `StreamTransfer`):
  guard panjang + probe + refuse, dan drain 8192-chunk dengan break defensif `''`.
  Write ditolak langsung di wrapper (`'Limited input stream is read-only.'`).

Sebuah header bohong/absen/hitam hanya menentukan PARUH MANA yang melempar — keduanya
melempar `HttpException` `'Request body exceeds configured size limit.'` (413 semantics
milik error-mapping shim fase berikutnya). Deviasi terdokumentasi vs legacy: tiga sinyal
legacy (`PayloadTooLargeException`/`StreamNotWritableException`/`InvalidArgumentException`)
dilipat ke satu akar layer (H2), pesan byte-identik; `validateIngress()` legacy (read-ahead
maxBytes+1 untuk request yang SUDAH dibangun — jalur worker persisten) ditunda ke PR
host-validation (butuh trustedHosts yang bukan bagian slice ini).

Atribusi legacy: `Adapters/Http/RequestBodyPolicy.php` + `Adapters/Http/LimitedInputStream.php`
+ wiring `RequestFactory` (move-verbatim behaviour).

### Pola battle tambahan slice ini

- **CastInt mutan abadi pada `(int) $digits > $int`**: string-digit numerik vs int dibandingkan
  numerik oleh PHP — hasil identik dengan cast untuk SEMUA input digit, jadi penghapusan cast
  tidak terobservasi. Solusi idiom rumah: cast hidup DI BELAKANG return type `int` helper
  sendiri (`claimedBytes(): int { return (int) $digits; }`) — CastInt men-skip node cast
  yang removal-nya ber-TypedError di scope strict_types (param Arg / Return bertipe). Bentuk
  inline dalam comparison TIDAK dilindungi; ServerPort/UploadCoercion selamat karena cast-nya
  mengalir ke return bertipe, bukan karena kebetulan.
- **Probe = senjata mutant ganda**: journal `RecordingStream` pada inner membuat ukuran probe
  dan chunk 8192 ter-assert ([8192, 8192, 3617] untuk drain tepat-di-limit) — membunuh
  IncrementInteger (8192→8193), Plus→Minus pada `remaining+1` (probe 3615), dan menuangkan
  probe-shrink ke data. Tanpa journal, mutant ukuran-chunk identik-observabel selamanya.
- **Break defensif `''` di drain loop**: untuk stream jujur, loop keluar lewat kondisi eof
  dulu — break terlihat mati dan mutant break-removal identik. Double `NeverEndingEofStream`
  (eof() selalu false, payload sekali lalu '') membuat break SATU-SATUNYA jalan keluar —
  tanpanya infinite loop → timeout-kill by design.
- **Boundary compliance = kill GreaterThanOrEqual**: read tepat ke limit ('abcde', policy 5,
  read(5)) HARUS sukses — mutant `>`→`>=` melempar pada kasus tepat-itupun; drain
  tepat-di-limit (policy 20000, body 20000) memastikan jalur compliant juga hidup di getContents.
- **Sticky assertion ganda**: setelah overflow, read KEDUA tetap melempar walaupun inner sudah
  EOF — membunuh Plus→Minus pada racun `maxBytes + REFUSED_OVERSHOOT` (racun `-1` akan
  menyisakan budget 1 byte). Literal racun sendiri HIDUP DI KONSTANTA kelas: Decrement/
  IncrementInteger pada deklarasi const TIDAK digenerate infection (diverifikasi empiris —
  `COPY_CHUNK_SIZE = 8192` UploadedTransfer tak pernah termutasi), dan kedalaman racun
  memang tak-terobservabel — konstanta menghapus kedua mutant sekaligus tanpa ignore.
- **Mutan Throw_ pada guard duplikat**: guard panjang-negatif di wrapper dan di `Stream` inner
  melempar PESAN IDENTIK — penghapusan throw wrapper jatuh ke guard inner yang menjawab sama.
  Kill: inner TANPA self-guard (`NeverEndingEofStream`) + journal — mutant menjalani probe
  min(-1, ...)=-1 → journal terisi, tak ada exception → assertThrows gagal.
- **Mutan MBString `mb_strlen(x, '8bit')` → `strlen(x)` = ABADI MATEMATIS**: keduanya menghitung
  byte untuk SEMUA input ('8bit' memang semantik byte) — suite penuh lolos pada mutasi manual
  (diverifikasi). Ignore ter-scoped per-method di infection.json5 berpreseden H12
  (`SapiTransmitter::field`), berkomentar dengan bukti; tetangga `mb_substr` TETAP di bawah
  mutator (encoding-arg yang hilang mengubah semantik karakter pada payload biner — test
  membunuhnya).
- **Journal + clamp `max(0, tell())`**: DecrementInteger (0→-1) pada clamp hanya terbedakan
  lewat journal probe (compose `RecordingStream(CursoredStream(null, -5))` — tell negatif,
  read berjournal); IncrementInteger (0→1) dibunuh lewat boundary seek(0) + read(100) yang
  HARUS sukses tepat-di-limit (mutant melempar).
- **`?? ''` pada header lookup**: mutant penghapus coalesce memicu undefined-key warning pada
  kasus TANPA content-length → failOnWarning phpunit membunuhnya gratis.
- **max/min tidak termutasi infection** (RoundingFamily hanya floor/ceil/round) — `max(0, tell())`
  defensif dan clamp `min(size, maxBytes)` bebas mutant struktural; kejujuran mereka dijamin
  test nilai, bukan test mutan.

## Fase 3 — KDD-H14 (trusted host ingress policy)

**H14 — dua paruh satu gerbang di ingress yang SUDAH dibangun.** Roadmap "Host Header Validation"
(whitelist Host terhadap domain aplikasi — pertahanan cache-poisoning/password-reset-poisoning)
dieksekusi sebagai `RequestFactory::validateIngress(request, trustedHosts, trustedProxies,
bodyPolicy)` — jalur worker persisten yang request-nya TIDAK melewati `fromServer()`
(wiring legacy `Adapters/Http/RequestFactory` move-verbatim):

- **Paruh host** (`TrustedRequestHost::assert()`): URI host dinilai DULU (hostless = `Missing
  request host.`, bukan host terpercaya), lalu otoritas efektif: `X-Forwarded-Host` HANYA ketika
  pengirim adalah proxy terpercaya (`TrustedProxyMatcher::matches(REMOTE_ADDR, ...)` — entri exact
  atau CIDR; `REMOTE_ADDR` korup = absensi, kebijakan `ServerValues`) DAN headernya hadir; selain
  itu Host header request; selain itu URI host (jalur HTTP/1.0 — hanya terjangkau lewat double
  asing `ForeignServerRequest`, karena request milik sendiri selalu bootstrap Host dari URI).
  Nilai pertama list forwarded adalah satu-satunya yang mengklaim kepercayaan (`firstForwardedValue`);
  otoritas terpilih di-parse oleh grammar `HostAuthority` milik lapisan lalu dinilai allow-list yang
  sama — URI host dan Host header dinilai BERDIRI SENDIRI (bentuk cache-poisoning: URI trusted +
  Host evil melempar di paruh kedua, sebaliknya di paruh pertama; XFH spoof dari peer tak-percaya
  TIDAK pernah sampai ke perbandingan — `&&`→`||` dibunuh test spoofing itu).
- **Paruh body** (`IngressBody::guard()` + `IngressProbe::verify()`): klaim Content-Length
  (`claimExceeds`), ukuran yang dinyatakan (`getSize()`), lalu probe read-ahead — rewind + drain
  di bawah `LimitedInputStream` + restore posisi di `finally`; body non-seekable tidak bisa
  di-restore jadi isinya menjadi body pengganti (`Stream::fromString`, seekable). `LyingSizeStream`
  (size bohong, kursor nyata) membunuh: probe membaca DARI AWAL (tanpa rewind, sisa 12 byte lolos
  padahal total 14 melebihi), dan restore-kursor setelah penolakan. Journal `RecordingStream`
  membedakan lemparan eager (journal KOSONG — stream tak pernah disentuh) dari lemparan probe
  (journal terisi). Non-seekable: `NonSeekableBody` (payload sekali, size null) — re-wrap
  `assertNotSame` + konten, dan penolakan lewat probe.
- **`TrustedHosts`** (legacy `TrustedHostValidator`): allow-list + `assertTrusted()` — list kosong
  = pinning tak dikonfigurasi (default terbuka; `failClosedOnEmptyList` opt-in untuk komposisi
  yang mewajibkan), host kosong diterima (ingress menolaknya sendiri), bentuk perbandingan =
  trim + fold Unicode penuh (Kelvin sign — cermin Uri) + strip bracket IPv6 via satu pola anchored
  dotall; entri di-narrow ke `list<string>` di batas ctor (`get_debug_type` fail-closed, legacy
  issues #278/#281). FQDN root-dot TIDAK difold di sini (berbeda dari `HostComparison` — comparison
  allow-list membandingkan ejaan apa adanya, move-verbatim).
- **`CidrAddress` + `MaskedPrefix`**: keluarga dibaca dari ejaan alamat sendiri (filter IPv4) —
  bukan panjang-byte `inet_pton` (idiom `isset($bin[4])` memiliki literal index yang tak-terbedakan
  pada {4,16}-byte). Perbandingan byte-prefix = `substr_compare` (byte-semantics; `strncmp`
  dilarang fixer mb_str; `mb_substr` KARAKTER-semantics pada biner — bytes `C2 80`/`C2 A8`
  substitusi mbstring menyamakan 192.169.0.0 dengan 194.128.0.0!). Mask sisanya = tabel konstanta
  `[0x80..0xFE]` — mask hasil left-shift TIDAK PERNAH membawa bit-0 (mutan `& 0xFF`→`& 0xFE`
  abadi matematis), jadi tujuh jawaban dinyatakan sebagai tabel (idiom konstanta H13); sisa yang
  melewati tabel akan indeks-out-of-range → warning engine → ter-observasi.
- **deptrac/layer**: semua kelas di `Zef\Framework\Http` (Http += Exception sudah ada), tanpa
  dependensi baru; psalm-baseline SHRINK −4 (`MessageInterface::hasHeader/withBody`,
  `RequestInterface::getUri`, `ServerRequestInterface::getServerParams` — konsumen produksi baru,
  H5 shrink-as-you-land).

Atribusi legacy: `Domain/Validation/TrustedHostValidator.php` + `Domain/Http/TrustedProxyMatcher.php`
+ wiring `Adapters/Http/RequestFactory::validateIngress/assertTrustedRequestHost/firstForwardedValue`
(move-verbatim behaviour, dilipat ke satu akar `HttpException` dengan pesan byte-identik — termasuk
em-dash pada pesan allow-list-kosong).

### Pola battle tambahan slice ini

- **PCOV + PHPUnit 13: atribusi per-test coverage HANYA mengikuti `#[CoversClass]`**: test yang
  mengeksekusi kelas tapi TIDAK mendeklarasikannya di CoversClass tidak mengatribusi barisnya ke
  kelas itu → infection tidak menjalankan test itu untuk mutan kelas tersebut → mutan "escape"
  padahal test pembunuhnya ada (ditemukan via `--coverage-xml` per-line `<covered by>`). Mutan
  MaskedPrefix "escape" sampai `#[CoversClass(MaskedPrefix::class)]` ditambahkan ke kelas test
  yang battery-nya membunuhnya. Pelajaran: SETIAP kelas baru yang dieksekusi sebuah kelas test
  WAJIB masuk daftar CoversClass kelas test itu — bukan hanya kelas test yang "memilikinya".
- **Battery non-provider untuk aritmetika**: kasus pembunuh yang dinyatakan sebagai DataProvider
  TIDAK diatribusi per-data-set oleh PCOV (nama atribusi = level method) — kasus pembunuh kritis
  (boundary /15-/17, bit-6, bit-0, /32-/128, high-bit base) dinyatakan sebagai SATU method test
  non-provider berisi matriks assert (`testTheContainmentArithmeticBoundaries`) agar atribusi
  (dan kill) dijamin.
- **Byte-prefix compare: `substr_compare`, bukan `strncmp`/`mb_substr`**: fixer mb_str melarang
  `substr`; `mb_substr` salah-semantik pada biner (substitusi sekuens invalid); `substr_compare`
  bebas fixer + mutator MBString + menerima length int apa pun (psalm tidak menuntut
  non-negatif — beda dengan `strncmp` yang minta `int<0,max>` dan memaksa clamp bermutan-abadi
  `max(0, ...)`).
- **Literal index `isset($bin[N])` = zona abadi untuk N∈[4..15]**: panjang packed selalu {4,16} —
  isset[4]≡isset[5]…isset[15]; keluarga harus dibaca dari channel lain (grammar filter) agar
  semua literal mutannya ter-observasi.
- **Dua lemparan pesan identik = saling-menutupi**: `Missing request host.` muncul di dua titik
  (URI hostless; otoritas efektif kosong) — mutan Throw_ pada yang pertama BERTAHAN karena lemparan
  kedua menyelamatkan assertThrows (pesan sama). Kill: test hostless sengaja MEMBAWA Host header
  terpercaya sehingga hanya lemparan pertama yang bisa menolak. Pola yang sama pada
  `Untrusted host:` — pesan tetap identik (H2), pembeda adalah BENTUK request, bukan teks.
- **`\assert($x instanceof T, 'message')` untuk narrowing**: sniff `RequireExplicitAssertion`
  menuntut assert (bukan @var docblock — fixer phpdoc_to_comment meluruhkannya, phpstan berhenti
  membaca); assert TANPA message dihapus Rector (`RemoveDeadInstanceOfAssertRector`) — assert
  DENGAN message bertahan, men-narrow phpstan+psalm, nol biaya di produksi, dan gagal-keras di
  development melawan implementasi asing yang melanggar kontrak `withBody()`-preserves-type.
- **Mutasi regex PregMatch**: `PregMatchRemoveCaret` dibunuh dengan host yang bracket-NYA di
  tengah mengandung nama terpercaya (`evil.com[trusted.name]` — tanpa ^, mutant meng-strip ke
  nama terpercaya dan lolos; asli melempar); `PregMatchRemoveFlags` (dotall) dibunuh literal
  bracket yang memuat newline.
- **`inet_pton()` tidak pernah warning pada input invalid (diverifikasi engine PHP 8.4)** — guard
  `filter_var` di depannya redundan perilaku; guard dihapus, `$binary === false` tetap menjadi
  satu-satunya kanal soft-false (mutan penghapus return-nya dibunuh lewat TypeError strict-types
  ketika family-width detour lolos: ip IPv6 valid vs network garbage → width 128=128 →
  `MaskedPrefix::matches(valid, false)` → TypeError).

## Fase 3 — KDD-H15 (ETag conditional GET: PSR-15 middleware pertama)

**H15 — middleware pertama mengonsumsi kontrak PSR-15 yang telah lamavendored.** Roadmap "ETag / 304
Support + Conditional GET" (RFC 9110 §13) dieksekusi sebagai `ETagMiddleware implements
MiddlewareInterface` — konsumen PERTAMA `src/Contracts/Psr/Http/Server/*` — dengan grammar evaluasi
dipecah ke kolaborator (wajib archeology ≤ 20: orchestrator 23.56 → dua ronde split →
`RequestPreconditions` (precedence INM-shadows-IMS P-13), `StrongEtag` (digest), `RepresentationBody`
(read + restore-cursor audit #303), `NotModifiedResponse` (304 tanpa Content-Length), plus
`IfNoneMatch`/`IfModifiedSince`/`HttpDate`):

- **`in_array($m, ['GET','HEAD'], true)` = zona abadi flag-ketiga**: `true→false` (loose vs strict)
  identik untuk needle/array semua-string — literal flag TIDAK punya kanal observasi. Idiom rumah:
  perbandingan eksplisit `$m !== 'GET' && $m !== 'HEAD'` — setiap flip punya pembunuh (GET-test,
  HEAD-test, POST-test, 201-test).
- **Guard redundan = mutan abadi**: (a) early-return legacy "body kosong + tanpa Last-Modified"
  jatuh-ekuivalen dengan fall-through (tidak ada validator untuk dihitung, tidak ada precondition
  bisa cocok, tidak ada yang di-stamp) — guard dilipat; (b) dua guard `!== ''` pada lengan IMS
  duplikat parse-null `HttpDate` (string kosong gagal grammar) — dihapus. Pelajaran umum: sebelum
  menulis guard, tanyakan "apakah downstream SUDAH menolak nilai itu secara kanonik?" — jika ya,
  guard hanya menambah permukaan mutan tanpa menambah semantik.
- **Normalisasi nilai-0 ke jalur yang BISA dibandingkan**: `$errors === false || $errors['warning_count'] === 0`
  — domain terjangkau array selalu w≥1 (clean→false), sehingga `=== 0` ↔ `=== -1` ekuivalen
  (DecrementInteger lolos). Kill: ternary normalisasi `$count = $errors === false ? 0 : $errors['warning_count']`
  — kini nilai 0 BENAR-BENAR hadir pada parse bersih, mutan literal pada ternary maupun pembanding
  menjadi terobservasi (parse bersih ditolak → semua test IMS gagal). Ini generalisasi idiom
  CastInt-di-balik-helper (H13): arahkan literal melewati jalur nilai bertipe.
- **mb_strtoupper pada token method = abadi matematis**: tidak ada karakter Unicode yang
  upper-case KE ASCII G/E/T/H/A/D (exception ſ→S dan ligatur tidak pernah mengeja GET/HEAD);
  token yang berbeda antara dua fold pasti gagal gate dua-duanya → ignore ter-scoped
  `ETagMiddleware::applyConditionalGet` di infection.json5 dengan bukti (preseden
  BodyBudget::refuses — ignore KETIGA berkategori ini).
- **`mb_substr` pasca-guard-ASCII ≡ `substr` KECUALI substitusi invalid-UTF-8**: prefix 'W/'
  (2 byte ASCII) membuat offset-byte-2 ≡ offset-char-2 untuk SEMUA input yang lolos guard —
  KECUALI remainder invalid-UTF-8 yang DISUBSTITUSI mbstring ('?' = 0x3F — diverifikasi sandbox:
  `mb_substr("W/\xA9tag",2)` = `3f746167` vs `substr` = `a9746167`). Kill mutan MBString +
  IncrementInteger(2→3) sekaligus: `assertTrue(matches('W/abc','abc'))` (strip TEPAT 2 char,
  char ke-3 non-quote) + `assertFalse(matches("W/\xA9tag","\xA9tag"))` (obs-text invalid-UTF-8
  tidak bisa match bentuk weak-nya — semantik fold karakter, terdokumentasi di kelas).
- **`'D'` (day abbreviation) MENERIMA nama hari penuh pada engine ini** ('Monday' parse tanpa
  warning melalui `D, d M Y H:i:s T` — diverifikasi) → format `l, ...` legacy = grammar mati
  redundan (mutan ArrayItemRemoval-nya abadi) — DIBUANG, deviasi terdokumentasi di docblock
  HttpDate; bentuk RFC 850 dashed ('Monday, 01-Jan-24') memang TIDAK pernah parse di legacy
  (diverifikasi) — tidak ada yang hilang. `setTimezone(GMT)` pasca-createFromFormat juga dibuang:
  timestamp frame-independent, mutan penghapusnya ekuivalen.
- **`getLastErrors()` PHP 8.4 = `false` saat bersih, `array{w≥1}` saat parse-dengan-warning,
  `date === false` saat error** — tiga-keadaan ini yang membuat normalisasi ternary (poin di atas)
  menjadi satu-satunya struktur bebas-mutan-abadi.
- **Fixer mb_str meluruhkan `trim`→`mb_trim` dan `strtoupper`→`mb_strtoupper` di src** —
  mb_trim TIDAK termutasi MBString (tidak ada di tabel converter) = selamat otomatis;
  mb_strtoupper membawa satu ignore ter-scoped (poin sebelumnya). phpcs↔fixer PING-PONG pada
  `new readonly class(...)` anon (SpaceAfterKeyword vs pembuangan spasi) — selesaikan dengan
  double BERNAMA di tests/Support (idiom rumah: ForeignResponse dkk), bukan anon.
- **Baseline psalm menyusut −5 sekaligus** (implementasi MiddlewareInterface + pemanggilan
  `handle()` + `withHeader`/`withStatus`/`getMethod`) dan BERTAMBAH +2 (`MiddlewareInterface::process`
  — belum ada pemanggil internal; `ETagMiddleware` UnusedClass — pola entry-point opt-in yang
  sama dengan RequestFactory): baseline = pembukuan konsumsi kontrak, bukan angka yang harus
  monoton turun.
- **CoversClass harus memuat SEMUA kelas baru yang dieksekusi battery** (H14 berlaku penuh):
  ETagMiddlewareTest mendeklarasikan 10 kelas (middleware + 7 kolaborator + Response + Stream);
  battery pembunuh aritmetika/boundary tetap non-provider.

## Fase 3 — KDD-H16 (CORS: middleware PSR-15 kedua, nol ignore baru)

**H16 — slice CORS dieksekusi dengan kanal-kill dirancang SEBELUM kodenya ditulis, dan
infection.json5 TIDAK berubah sama sekali** (ignores tetap 3: SapiTransmitter::field,
BodyBudget::refuses, ETagMiddleware::applyConditionalGet). Roadmap "CORS Middleware" dieksekusi
sebagai `CorsMiddleware implements MiddlewareInterface` — middleware kedua lane ini — dengan
dua belas kolaborator (wajib archeology ≤ 20: CorsPolicy 40.80 → tiga ronde split →
`AllowedOrigins` 12.50 + `AllowListEntries` 17.33 + CorsPolicy 18.67; CorsMiddleware 21.20 →
`PreflightResponse::answer()` menyerap keputusan short-circuit → 12.54; CorsOrigin 20.67 →
`OriginPort` diekstrak → 13.75):

- **mb_strtoupper pada gate 'OPTIONS' TIDAK abadi — berbeda dari GET/HEAD (H15)**: huruf S
  TERJANGKAU lewat peta case Unicode (ſ U+017F upper-case → S), sehingga token `optionſ` pada
  request asing melipat menjadi `OPTIONS` di bawah mb_strtoupper tetapi TIDAK di bawah
  strtoupper (diverifikasi sandbox: `4f5054494f4e53` vs `4f5054494f4ec5bf`) → mutan MBString
  DIBUNUH test `testALongSTokenFoldsIntoOptions`, bukan di-ignore. GET/HEAD (H15) memang tidak
  punya huruf yang terjangkau (ſ→S dan ligatur tidak mengeja GET/HEAD) — ignore itu tetap sah,
  tapi setiap gate method baru harus diaudit ulang alfabet-reachability-nya, tidak diwarisi.
  Double `ForeignMethodServerRequest` (request asing dengan token method arbitrer) adalah bentuk
  yang membuat fold terobservasi — `HttpMethod::assert` grammar tchar ASCII menolak token
  demikian di request rumah sendiri.
- **Dedup case-insensitive BUTUH kill dua arah**: fold kandidat (call-site) dan fold
  anggota-terkumpul (helper) adalah dua call-site mb_strtolower = dua mutan terpisah. Kill
  Kelvin U+212A→`k` arah pertama (token ASCII dulu, Kelvin kedua → tereliminasi) HANYA
  membunuh mutan call-site; mutan helper lolos sampai test ARAH KEDUA ditulis (token
  Kelvin terkumpul dulu → fold helper-lah yang harus mengenalinya). Dua test, dua arah,
  nol escape.
- **Flag `s` pada pola bracket-strip = grammar MATI (poin H15 'l' diulang)**: satu-satunya
  karakter yang dibedakan `.` dengan/sans `s` — CR/LF di dalam bracket — sudah ditolak guard
  smuggling Origin di UPSTREAM, jadi PregMatchRemoveFlags abadi secara struktural → flag
  DIBUANG (bukan di-ignore), deviasi terdokumentasi di docblock unbracketed(). ANCHOR `^`
  sebaliknya TERKILL: `parse_url('http://a[b]')` menjawab host `a[b]` — tanpa anchor pola
  menangkap `b` dan meloloskan grammar → test `testAnUnanchoredBracketPairIsNotAnIpv6Literal`
  membunuh PregMatchRemoveCaret.
- **`array_values(array_unique(...))` legacy pada allow-list = normalizer murni tak-terobservasi
  (H12)**: judgment iterasi list dan menjawab pada kesetaraan pertama — duplikat
  tak-terbedakan dari penghapusannya LALU SELURUH PIPELINE dedup dibuang (dokumentasi
  deviasi di AllowedOrigins). Placeholder `['*']` legacy sama: flag allowAll mendominasi
  setiap pembacaan list — state mati, dibuang. Sebagai gantinya wildcard dideteksi scan
  `array_any` penuh TERPISAH dari normalisasi, sehingga "entri pasca-wildcard tidak
  divalidasi" (perilaku legacy, return-awal yang tak-terbedakan) menjadi cabang yang
  TERobservasi lewat jalur lemparan.
- **Setiap lengan `match` butuh test perilakunya sendiri**: MatchArmRemoval lengan
  `$allowOrigin === null => []` pada union-ctor lolos sampai test ctor-`null` eksplisit
  ditulis (null → disabled, bukan `[null]` → lemparan). Sama untuk ArrayOneItem pada
  list ternormalisasi: list DUA-entri dengan entri KEDUA yang diminta adalah satu-satunya
  pembunuh — test satu-entri tidak pernah menyentuh indeks 1.
- **`continue`→`break` pada skip-dedup hanya terkill jika token berbeda menyusul duplikat**:
  duplikat sebagai token TERAKHIR jatuh-ekuivalen (loop berakhir keduanya) — test
  `Accept-Encoding, accept-encoding, X-Request-ID` yang membunuh.
- **Double `ForeignResponse` tidak bisa menampung stamp-ulang header**: `[$name => $value,
  ...$this->headers]` menempatkan kunci LAMA menang (semantik later-key-wins PHP) — withHeader
  atas nama yang sudah ada mempertahankan nilai lama. Response rumah menerima obs-text pada
  nilai field, jadi test Kelvin tidak membutuhkan double asing sama sekali.
- **`parse_url()` menjawab port sudah bertipe int engine** (port non-numerik gagal parse
  menyeluruh) — cast `(int)` legacy adalah koersi mati, DIBUANG (phpstan/psalm mengonfirmasi
  int) alih-alih mempertahankan permukaan CastInt no-op.
- **Skanario pembuktian flake lokal diperluas**: pristine-main 9baaa4b pada sandbox ini =
  2 escape DirectoryLoader + 7 TIMEOUT (DotWriter ×2, BodyTransfer, EmittedBody,
  UploadedTransfer, UriEncoder ×2) — set yang SAMA PERSIS pada branch; timeout adalah
  beban-runner (4 thread lokal), bukan regresi. Debris `/tmp/zef-*` dari run sebelumnya
  HARUS dibersihkan sebelum verifikasi (pelajaran H13 flake-glob diulang: 731 file bocor
  membuat initial-test-run pristine gagal 1 test UploadedFile).

## Fase 3 — KDD-H17 (error mapping: middleware PSR-15 ketiga, slice security penutup)

**H17 — slice error mapping menutup fase security, dan dua "zona abadi" baru yang ditemukan
adalah EKUIVALENSI-NILAI intrinsic grammar, bukan kelemahan test** — keduanya dijawab dengan
restrukturisasi + mekanisme ignore BARU line-scoped (`ignoreSourceCodeByRegex`, preseden
perluasan dari method-level). Roadmap "error mapping" dieksekusi sebagai
`ErrorMappingMiddleware implements MiddlewareInterface` — middleware ketiga lane — di atas
delapan kolaborator (`CorrelationId`, `ErrorStatusMap`, `ErrorDisclosure`,
`ErrorResponseFactory`, `RedactedResponse`→`RedactedMessage`+`RedactionCandidate`,
`FallbackResponse`) + kontrak `Psr\Log\LoggerInterface` divendorkan (direktori
`src/Contracts/Psr/Log/` + mapping autoload `Psr\Log\` ternyata SUDAH disiapkan sesi
sebelumnya — .gitkeep dihapus):

- **Bound panjang HIDUP DI DALAM grammar, bukan `strlen` comparison**: klaim X-Request-ID
  legacy dicek `strlen > 128 || preg !== 1` — dua zona mutan (MBString strlen pada domain
  ASCII-grammar abadi; IncrementInteger pada literal 128). Menulis pola
  `/^[A-Za-z0-9._:-]{1,128}$/D` melipat KEDUANYA: bound byte dihitung pattern (byte mode),
  klaim kosong otomatis gagal `{1,128}` (guard `!== ''` mati-redundan, dilipat ala H15-b),
  dan TIDAK ADA strlen/comparison yang tersisa untuk dimutasi. Bonus temuan engine: `$`
  tanpa `D` MENERIMA newline-menjuntai (`"abc\n"` match, karena `$` juga cocok tepat sebelum
  newline final) — bug smuggling legacy yang diperbaiki deviasi-terdokumentasi + test pin
  (`abc\n` diregenerasi; request rumah menolak nilai header demikian saat konstruksi, jadi
  double `ForeignServerRequest` verbatim adalah pembawa jujurnya).
- **Ekivalensi-nilai capture = zona abadi YANG TIDAK BISA dibunuh test mana pun**: dua pasang
  indeks capture membawa nilai yang tidak-terbedakan observabel: (a) `$match[0][1]` (awal
  match) ≡ `$match[1][1]` (awal key) — grammar menjangkarkan seluruh match di byte pertama
  key, SELALU sama; (b) `$match[2][1]` (awal separator) ≡ `$match[3][1]` (awal region nilai)
  sebagai TITIK LANJUT SKAN — region separator (whitespace + satu [=:]) tidak pernah bisa
  menjadi awal kandidat (bukan byte key), jadi kandidat-berikutnya terpilih identik. Mutan
  yang menukar anggota pasangan adalah abadi SECARA MATEMATIK — bukti disiplin rumah
  dijalankan (suite PENUH hijau di bawah tiap mutasi terpasang manual). Jawabannya
  restrukturisasi: `$matchStart` diekstrak ke baris sendiri (mengisolasi zona (a) ke SATU
  baris), advance memakai capture quote `[3]` sehingga arah INCREMENT-nya membaca capture
  TIDAK-TERDEFINISI `[4]` → error dimensi-tak-terdefinisi engine → kill BERSIH (diverifikasi:
  6 error test), dan hanya arah decrement (ekuivalen) yang di-ignore.
- **`ignoreSourceCodeByRegex` = mekanisme line-scoped PERTAMA di infection.json5** (pasangan
  `IncrementInteger`/`DecrementInteger` + pola baris sumber): method-level ignore terlalu
  kasar untuk kedua baris ini — `RedactedMessage::of()` memuat literal lain yang harus tetap
  hidup di bawah mutator (`$offset = 0` terkill test match-at-0; `$edit[0] + $edit[1]` terkill
  test dua-edit). DiffSourceCodeMatcher mencocokkan pola terhadap baris `-` diff
  (`^-\s*pola$`/mu) — pola TANPA `.*` pengapit, persis baris sumbernya. Ignore bertambah
  3 → 6 (1 method MBString + 2 regex line-scoped), semuanya berkomentar bukti.
- **MBString abadi #3: domain ASCII-grammar pada `RedactionCandidate::edit()`** — teks match,
  key, separator, dan token nilai semuanya ASCII BY CONSTRUCTION (kelas byte-mode), jadi
  mb_strlen/mb_substr ≡ strlen/substr untuk SETIAP string yang grammar itu bisa serahkan
  (bukti: suite penuh hijau di bawah keempat swap terpasang serentak). Preseden kluster yang
  sama: BodyBudget::refuses ('8bit'), ETagMiddleware::applyConditionalGet (token GET/HEAD).
- **Idiom advance: offset capture, bukan literal aritmetika** — `+ 1` legacy (Decrement 1→0
  = loop-tanpa-akhir; Increment 1→2 = tak-terobservabel, keduanya mendarat di interior key)
  diganti offset capture murni TANPA aritmetika sama sekali. Pelajaran umum: kandidat
  berikutnya TIDAK BISA dimulai di interior key maupun region separator — rentang
  [awal-match+1, awal-nilai] adalah zona bebas-kandidat, sehingga SETIAP offset di rentang itu
  ekuivalen; pilih ujung yang arah mutannya membaca dimensi tak-terdefinisi.
- **`json_encode` UTF-8 invalid = pemicu double-failure JUJUR** — fallback plain-text 500
  (legacy `internalErrorResponse`) butuh jalur di mana factory itu sendiri gagal; final
  `readonly` tidak bisa di-double, TAPI `json_encode($msg, JSON_THROW_ON_ERROR)` melempar
  JsonException pada pesan yang bukan UTF-8 valid (diverifikasi sandbox) — dev-mode +
  `RuntimeException("\xB1\x31 ...")` adalah end-to-end tanpa double: 500 text/plain +
  correlation-id tetap terkirim. Prod tidak pernah jatuh ke situ (pesan generik ASCII).
- **`error_log` bisa diobservasi lewat ini-routing**: last-resort "logger rusak"
  (legacy menulis nama kelas ke SAPI error log) terlihat dengan
  `ini_set('error_log', $tmpFile)` — error_log() type-0 MENAMBAH ke file itu (diverifikasi
  sandbox) — assert `str_contains('ZEF logging failure: LogicException')` + tearDown restore.
  Test pasangannya: TANPA logger → file TIDAK memuat tulisan itu (pin jalur opt-in `?->`).
  Nullsafe `?->` = seam NOL-mutan (tidak ada mutator infection untuk `?->`): logging
  opt-in sampai lane observability memasang sink nyata.
- **Arsitektur catch-arm RETURN, bukan variabel-jatuh-bersama**: psalm melaporkan
  `$status` UnusedVariable pada bentuk "assign-di-dua-catch, pakai-setelah-try" (aliran
  return-di-dalam-try) — restrukturisasi ke `failureAnswer(Throwable, int, string)` privat:
  tiap catch me-return langsung, 3 return tepat di batas S1142 Sonar, nol variabel bersama.
  Nama parameter `process()` HARUS nama interface (`$request`, `$handler`) + skip
  RenameParamToMatchTypeRector per-file (preseden Message/ETag/Cors — rector menamai dari
  type, psalm dari interface; konflik yang sama keempat kalinya).
- **ForeignResponse + withHeader(string) → getHeaderLine TypeError**: double verbatim
  menyimpan nilai string di peta `list<string>`-nya — getHeaderLine memanggil getHeader yang
  bertipe `array`. Stamp atas response asing di-assert lewat `getHeaders()` (peta mentah,
  kunci hadir) — CORS tidak pernah mengetes jalur ini (hanya emitter membaca double itu).
- **Pin e2e menggantikan const-bersama**: `ErrorStatusMap` mendeklarasikan teks refusal
  (`'Request body exceeds configured size limit.'`) SEKALI lagi secara privat — duplikat
  disengaja — karena test end-to-end MENJALANKAN `RequestFactory::validateIngress()` asli
  atas klaim Content-Large terlalu besar dan melempar sinyal ASLI ke middleware: 413 + judul
  'Content Too Large'. Pemetaan terpin ke throw-site nyata, bukan ke kebetulan const bersama.
- **PHPUnit 13: provider = ATRIBUT `#[DataProvider('...')]`** — docblock `@dataProvider`
  sudah dihapus upstream (ArgumentCountError tanpa argumen). Slevomat menuntut
  `@return iterable<...>` eksplisit pada provider (pelajaran H13 diulang).
- **5 timeout-kill by design pada kode baru** — Break_→continue, `=== null`→`!== null`,
  Decrement offset [3]→[1] (re-match), Plus→Minus pada `$edit[0]+$edit[1]`, Plus→Minus pada
  `$matchStart + mb_strlen`: semuanya loop-tanpa-akhir → timeout = kill terhitung. Bandingkan
  pristine-main 3bae1cf pada sandbox ini: 6 escape (UploadedMove:36 ×5 + :44 — keluarga
  flake glob /tmp yang SAMA PERSIS pada branch) + 7 timeout (DotWriter ×2, BodyTransfer,
  EmittedBody, UploadedTransfer, UriEncoder ×2) — branch menambah 5 timeout baru SEMUANYA
  di kode baru sebagai kill terancang; NOL escape baru. Local mutation:zones gagal pada
  KEDUA sisi (99.79% pristine vs 99.80% branch — kondisi flake lokal yang terdokumentasi,
  engine CI membunuhnya, preseden PR-H/K/L).

### Amendemen H17-a (2026-10-19, kampanye E): wajah boundary pindah lane `Middleware`

Ketika kampanye error-handling enterprise menambahkan seam observability ke
boundary (meter `Telemetry\MeterInterface` di konstructor + pembacaan knob oleh
`ErrorMappingFactory`), deptrac menolak 12 ketergantungan: layer `Http` hanya
boleh bergantung pada `Exception` (doktrin graph), sementara `Kernel` tidak
boleh melihat `Http` sama sekali — padahal `FrameworkDefaults` memasang
factory lewat profil services. Resolusinya mengikuti preseden
`CorrelationMiddleware`/`MetricsMiddleware`/`CorrelationRequestSpan` persis:
wajah PSR-15 yang jawabannya mengalir lewat engine observability hidup di lane
komposisi, bukan di layer HTTP yang engine-blind. Empat kelas pindah namespace
`Zef\Framework\Http\` → `Zef\Framework\Middleware\`:
`ErrorMappingMiddleware` (wajah), `ErrorMappingFactory` (seam komposisi yang
membaca `Config` dan memasang `observability.meter`), `ErrorFaultRecorder`
(kolaborator yang memegang meter + logger PSR-3), `ErrorFaultMetricsRecorder`
(kebijakan inkremen meter). Seluruh mesin render (`ErrorEnvelope`,
`ProblemDetails*`, `HtmlErrorPage*`, `ErrorStatusMap`, negosiator, sanitizer,
keluarga replay) TETAP di `Http` — mereka murni mesin pesan tanpa engine.
`deptrac` kembali 0 violation tanpa satu baris ruleset diubah: perpindahan ini
adalah penegakan graph yang sudah ada, bukan pelonggaran. String factory di
profil `FrameworkDefaults` dan pin literal di `ApplicationBootstrapperTest`
ikut diperbarui — nama kelas adalah kontrak yang dilihat host.

## Fase 4 — KDD-H18 (HEAD request handling: middleware PSR-15 keempat + bench hot-path, slice penutup lane)

**H18 — slice performance phase menutup HTTP-optimizations sekaligus jadi instrumen observabilitas
lane, dan temuan intinya: kepekaan-huruf method adalah JAWABAN bersih atas pertanyaan
alphabet-reachability H15/H16 — bukan mb_strtoupper.** Roadmap "Head Request Handling"
("otomatis mengubah permintaan HEAD menjadi GET untuk mengeksekusi logika tapi membuang body")
dieksekusi sebagai `HeadMiddleware implements MiddlewareInterface` — middleware keempat lane —
di atas dua kolaborator mini (`HeadRequest::isHead`/`asGet` gate + reissue; `HeadResponse::view`
head view) + dua double baru bernama (`RecordingHandler` — handler yang merekam request yang
dijawabnya, `MiddlewareHandler` — adapter middleware→handler untuk tes komposisi pipeline):

- **Gate token-exak `=== 'HEAD'` membunuh zona abadi SEBELUM lahir**: H15 membuktikan gate
  `mb_strtoupper` GET/HEAD abadi matematis (di-ignore, kategori), H16 membuktikan OPTIONS
  terjangkau ſ→S (dibunuh test). Audit alfabet untuk HEAD: H, E, A, D tidak terjangkau dari
  karakter non-ASCII mana pun lewat case-map Unicode (tidak ada ſ/K/Å-analog untuk keempat
  huruf itu; fullwidth ｈ melipat ke Ｈ, tetap fullwidth) — artinya gate mb_strtoupper untuk
  HEAD akan menjadi ignore ke-4 tanpa jalur kill. Jawabannya BUKAN ignore: RFC 9110 §9.1
  mendeklarasikan nama method case-sensitive — `head` huruf-kecil adalah token tak-terdaftar
  yang BERBEDA — jadi perbandingan exak lebih benar secara kontrak DAN membawa kill channel
  gratisnya sendiri (pin pass-through `head` lowercase: handler mengamati `head`, instance
  request assertSame, response assertSame). Deviasi terdokumentasi: legacy
  `HttpRequestRunner` memakai `strtoupper()` (menerima semua case-fold); preseden deviasi
  RFC-correct atas legacy sudah ada (H17 grammar-`D` smuggling fix).
- **Content-Length dijatuhkan di LEVEL RESPONSE, bukan di emitter**: head view
  `withBody(Stream::fromString(''))` + `withoutHeader('Content-Length')` — port verbatim
  `headView()` legacy (ZEF-DEEP-05): body yang dikosongkan membuat CL "berbohong" (deklarasi
  28, tersisa 0) — emitter `ContentLength::reconcile` memang akan menjatuhkannya juga, tapi
  menjatuhkan di level response menjaga objek KONSISTEN-INTERNAL untuk konsumen hilir mana
  pun (forwarder verbatim RoadRunner akan mengapalkan framing basi). RFC 9110 §8.6 hanya
  MEMPERBOLEHKAN CL pada HEAD (tidak mewajibkan) dan §9.3.2 "same header fields as GET"
  terpenuhi untuk validator (`ETag`/`Last-Modified`) + metadata representasi (`Content-Type`).
  Kembar struktural: `NotModifiedResponse` (304 yang sama-sama body-less + CL-less) — dan
  bentuk CHAINED `->withBody()->withoutHeader()` + satu hop assert di seam adalah bentuk
  STABIL: draf pertama per-hop-assert (`$bodyless`/`$headerless`) memicu ping-pong
  RenameVariableToMatchMethodCallReturnTypeRector; bentuk rantai = nama `$message` =
  nama yang diinginkan rector untuk return type `MessageInterface` — stabil tanpa skip.
- **Konflik nama-param rector↔psalm KALI KEEMPAT** (H15-g/H17): `HeadMiddleware::process()`
  + skip `RenameParamToMatchTypeRector` per-file (preseden Message/ETag/Cors/ErrorMapping) —
  vendored `MiddlewareInterface` mendeklarasikan `($request, $handler)`. Kolaborator non-interface
  (`HeadRequest`) TIDAK perlu skip: param di-rename rector ke `$serverRequest` — patuhi, karena
  tidak ada kontrak interface yang dipertaruhkan.
- **Komposisi pipeline = double bernama, bukan anonymous class** (pelajaran PR-L diulang):
  `RecordingHandler` (nullable `?ServerRequestInterface` + assert-idiom 'handle() must be
  called before observed()' — phpstan uninitialized-property dijawab tanpa mengubah kontrak)
  dan `MiddlewareHandler` (middleware + terminal → handler). Test komposisi membuktikan urutan
  yang dianjurkan docblock: Head PALING LUAR → ETag mengamati representasi PENUH → head view
  mengosongkan badan TETAPI validator ETag selamat (hash sha256 atas representasi utuh, bukan
  hash string-kosong — pembeda kill untuk salah-urutan).
- **Bench hot-path = klaim jujur, bukan klaim kemenangan**: `HttpHotPathBench` (4 metode:
  fromServer 39.4μs, wither 2.9μs, lookup 0.5μs, render URI 0.8μs) — setiap metode mengawal
  invarian sendiri (jalan-panas rusak = lane GAGAL, bukan diam-diam melaporkan angka;
  disiplin RouterBench). Kajian jujur atas bullet Fase 4 shim: (a) "Direct Property vs Getter"
  N/A — getter adalah MANDAT interface PSR-7, kolaborator internal sudah readonly promoted;
  (b) "Lazy Loading Arrays" N/A arsitektur — tas query/cookie TIBA sudah-terparse dari SAPI
  ($_GET/$_COOKIE dibaca begitu saja; tidak ada parse_str eager untuk ditunda); (c) "String
  Interning" terpenuhi struktural — `HttpReasonPhrases::MAP` const table + ejaan header
  tersimpan SEKALI di store (PHP interning engine menangani sisanya); (d) "Unset on Finish"
  = tugas lane Kernel (belakangan). Yang DAPAT dikawal sekarang: angka referensi regressi.
- **Angka**: 20 test baru (1512→1532), 3 kelas src baru 100% lines+methods, coverage tetap
  99.43%; filtered infection zona baru: 6 mutan SEMUA terbunuh (MSI 100); full infection vs
  pristine-main fe929d9: +6 mutant baru semua killed, SET escape/timeout/error/syntax-error
  IDENTIK byte-per-byte (verifikasi JSON per-mutant: 6 escape UploadedMove-flake, 12 timeout,
  18 error, 3 syntax — SAMA PERSIS dua sisi); local mutation:zones gagal IDENTIK dua sisi
  (99.80% vs 99.80%) — kondisi flake lokal terdokumentasi, engine CI membunuhnya (preseden
  PR-H/K/L/M).

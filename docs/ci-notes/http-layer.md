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

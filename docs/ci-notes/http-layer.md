# CI Notes — HTTP Layer (Zero-Library Shim, Fase 1)

Decision record untuk lane 1 arah user **"HTTP Layer -> Container -> Middleware"** di bawah
area ROADMAP **HTTP Layer** + **HTTP Layer — Zero-Library Shim**. Dokumen ini menutup Fase 1
(inti PSR-7/17: contracts, stream, Uri, message/response, request objects, uploaded file,
factory). Fase 2 (runtime adapter: `fromGlobals` + emitter) menyusul dengan RENCANA sendiri.
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

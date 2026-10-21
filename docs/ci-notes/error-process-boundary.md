# CI Notes — Error Handling: The Process Boundary (PB)

Decision record untuk lane integrasi kampanye Error Handling System
(`ROADMAP.md`, "Error Handling System (Enterprise-Grade)", Fase 1–4
sudah live melalui #2, #434, #444 dan hardening #508). Lane ini
menutup celah integrasi terakhir yang tersisa: **semua kegagalan yang
request-scope boundary (`ErrorMappingMiddleware`, anggota stack
terluar) tidak akan pernah lihat, karena PHP membangkitkannya di
level proses** — diagnostik native engine (warning, notice,
deprecation), throwable yang tidak tertangkap siapa pun (kegagalan
boot, kegagalan emisi, host glue di luar stack), dan fatal yang
engine mati di atasnya (E_ERROR, E_PARSE, keluarga compile). Semua
tiga lengan kini dialirkan ke sistem error yang sama: record fault
yang bounded-ter-redaksi-privacy-by-default dan jawaban RFC 9457
selama masih ada klien yang mendengarkan.

Model yang diport: **doktrin process-level error handler Symfony
lagacy** (handler boot-time + shutdown observer) — tetapi beban
penjawabnya bukan renderer Symfony: seluruh vocabulary jawaban
(ProblemDetails/ErrorEnvelope/ErrorDisclosure) adalah milik lane
error handling user yang sudah ter-merge, dipakai ulang apa adanya.
Tidak ada renderer baru; ada satu boundary baru.

## KDD-PB1..PB12

- **PB1 (process boundary ≠ request boundary — dua wajah satu
  sistem)**: `ErrorMappingMiddleware` memiliki request yang sedang
  terbang; boundary proses TIDAK — ia memintanya dari SAPI saat
  lengan aktif (`RequestFactory::fromGlobals()`, seam observasi
  ctor). Karena itu boundary TIDAK masuk `middleware.stack`; ia
  dipasang oleh **boot seam** (`ApplicationBootstrapper`, stage
  komposisi terakhir) ketika knob `http.error_handling.
  process_boundary` memintanya. Knob default false — opt-in jujur:
  komitmen process-wide adalah keputusan entry point, dispek di
  profile yang dikirim (`app/configs/http.php`, preseden
  `replay_enabled`).
- **PB2 (kontrak suppress engine dihormati, bukan dikalahkan)**:
  severity yang ter-masking `error_reporting()` (operator `@`, mask
  direktif ini) dikembalikan ke engine utuh (handler jawab false) —
  kontrak engine sendiri. Severity yang PHP abort di atasnya
  (E_USER_ERROR, E_RECOVERABLE_ERROR) **dikonversi** jadi
  `NativeErrorException` (lemparan bertipe) supaya pipeline
  di sekelilingnya bisa map/record/answer lewat jalur bertipe.
  Severity lain direkam sekali dan DIMILIKI (handler jawab true —
  duplicate SAPI write engine tidak pernah terjadi: satu
  diagnostik, satu record, satu tujuan).
- **PB3 (fatal modern adalah Error, bukan fatal telanjang)**:
  undefined-function di PHP 8.4 terbang sebagai `Error` throwable —
  jadi lengan uncaught (bukan lengan shutdown) yang menjawab
  kegagalan engine sendiri. Lengan shutdown hanya untuk keluarga
  yang hanya momen shutdown bisa saksikan: mask fatal
  `DiagnosticSeverity::isFatalAtShutdown()` — **satu sumber**
  (konst FATAL di vocabulary severity engine, duplikat mask dihapus
  saat redistribusi; lihat PB8). Bentuk shape `error_get_last()`
  yang untyped divalidasi fail-closed per-field (type/message/file/
  line masing-masing diuji — mutan LogicalOr per-guard punya saksi
  granular).
- **PB4 (kejujuran SAPI atas metrik)**: boundary proses CLI tidak
  membawa meter — throwable CLI yang tidak tertangkap tidak punya
  semantik HTTP untuk dihitung, dan method fabrikasi dari request
  sintetis akan BERBOHONG di label set `zef.http.errors.total`.
  Fold ini hidup di verdict transmission (`answersHttp()`), bukan
  di konstruksi recorder — satu pertanyaan, dijawab sekali.
- **PB5 (jawaban untuk dua konsumen, refusal tetap milik
  pemanggil)**: SAPI HTTP → emitter lewat transmission fold;
  SAPI CLI → **sink line** bounded-120-karakter yang
  control-byte-stripped (higien display CLI: escape sequence
  tersimpan tak bisa menumpang baris boundary ke terminal operator
  — bound karakter BUKAN byte, saksi multibyte mem-pinnya). Headers
  already shipped → menambahkan dokumen JSON ke body setengah
  ter-emisi = menyajikan sampah → situasi dinamai di SAPI log,
  record tetap bicara. `ship()` mengembalikan bool; ejaan pesan
  refusal ("could not answer {id}" vs "could not answer a fatal")
  tetap milik arm pemanggil.
- **PB6 (registration engine yang tak bisa di-unwind punya seam
  observasi sendiri)**: `register_shutdown_function` adalah satu-satu
  panggilan engine yang void, tak ter-inspeksi, tak bisa
  dilepas — **persis masalah doktrin SapiTransmitter** (test process
  bisa mengeksekusi cabang asli tapi tak pernah mengamati apa yang
  ia kirim). Seam registrar closure di ctor boundary (default:
  panggilan engine asli) membuat kontrak sekali-per-instance ter-amati
  lintas siklus install/restore/re-arm TANPA shutdown sungguhan —
  dan baterai engine-dispatch tetap membuktikan registrar asli di
  subprocess nyata. Kedua handler lain (error/exception) diamati
  push-probe-pop + `ReflectionFunction::getClosureThis()` — arm
  terpasang TANPA memicu satu event engine pun.
- **PB7 (boundary CLI menjawab, engine yang memiliki exit code)**:
  lengan uncaught CLI menulis sink line lalu BIARKAN terminasi
  engine memiliki exit code — handler exception yang menjawab lalu
  exit(0) sendiri akan membohongi operator shell. Sebaliknya
  E_USER_ERROR ter-konversi unwind ke kode pemanggil (test
  subprocess: `CAUGHT ...` exit 0); yang ter-masking jatuh ke arm
  shutdown (exit 255 milik engine, record 'Uncaught fatal' tetap
  tertulis).
- **PB8 (redistribusi arsitektur = disiplin gate, bukan gaya)**:
  potongan pertama boundary membawa seluruh vocabulary lane dalam
  satu kelas (difficulty 38, CC 30) — gate archeology menolak.
  Redistribusi v2 (preseden ErrorFaultRecorder→5 kelas milik user):
  shell + `ProcessNativeDiagnostics`/`ProcessUncaughtThrowables`/
  `ProcessShutdownFatals` (badan tiga arm) + `ProcessBoundaryRecords`
  (fold record) + `ProcessBoundaryTransmission` (verdict + shipping)
  + `ProcessBoundaryFatals` (grammar witness). Pelajaran Halstead
  terkonfirmasi ulang: **menghapus kode bisa MENAIKKAN difficulty**
  (rasio operator/operand — const E_* yang operand-kaya justru
  menurunkan difficulty; composed() yang dipindah menaikkannya di
  Fatals). Servis id pindah ke family-nya sendiri
  (`ProcessErrorBoundaryFactory::SERVICE_ID`, preseden alias
  `ContainerServices::INVENTORY_SERVICE`) supaya seam registrasi
  tidak melingkar dependensi balik ke profile.
- **PB9 (constructor = permukaan observasi, bukan 9 parameter)**:
  constructor pertama membawa 9 parameter (emitter/sink/probe/sapi
  satu per satu) — gate tooManyParameters menolak. Jawaban jujur:
  **transmission sebagai parameter objek** (4 seam pengiriman
  menjadi 1 fold first-class, default-nya engine asli dipunya fold
  sendiri) + registrar seam (PB6) = 7 parameter, tiap seam
  defaulted di unit yang memilikinya. Test helper `boundary()`
  tidak berubah signature-nya.
- **PB10 (lembah attribution subprocess — kelima kalinya)**: mutan
  atas `install()` lolos karena baterai yang membunuhnya hidup di
  SUBPROCESS (koverej parent tidak melihat eksekusi child) —
  infection memilih test peng-cover in-process saja. Solusi bukan
  menyalin test ke in-process, melainkan PB6: seam yang membuat
  registrasi ter-amati in-process. Preseden pelajaran lane-1
  attribution gate, kini untuk infection selection.
- **PB11 (default-constructed boundary di test process adalah
  boundary CLI)**: `new ProcessErrorBoundary()` di proses PHPUnit
  (SAPI cli) menjawab lewat sink line, BUKAN envelope HTTP — test
  disclosure default harus melewati transmission fpm-fcgi eksplisit.
  Jebakan ini menelan satu iterasi mutan; saksinya kini mem-pin
  postur tertutup default (debug=false) di jalur HTTP.
- **PB12 (vocabulary tertutup tanpa k = equivalence terbukti)**:
  fold environment factory ketiga (`mb_strtolower` + allowlist
  {local, development, dev, test, testing}) bergabung pada dua
  entri ignore MBString kakaknya — bukti identik (satu-satunya
  landing lowercase non-ASCII→ASCII di Unicode case map adalah
  KELVIN SIGN→k; tak ada kata ber-k di vocabulary; divergensi apapun
  meleset di kedua ejaan dan gerbang debug tetap tertutup).

## Ekonomi

Tanpa benchmark lane baru (tidak ada hot path baru — boundary aktif
hanya saat kegagalan; jalur bahagia tak tersentuh). Baterai: 42 test
baru + 4 boot-seam + 6 killer + engine-dispatch 3 subprocess.

## Status gerbang lokal

phpunit 4196/9054 · stan 0 · psalm 0 · fixer 0 · phpcs 0 · rector 0
· deptrac 0 · coverage 97.77% · archeology 0 error-level ·
infection changed-set 98/98 MSI 100 · roadmap:gate · zero-deps ·
audit:strict — PASSED. Infection full-tree berjalan background.

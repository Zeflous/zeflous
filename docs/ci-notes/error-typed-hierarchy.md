# CI Notes — Error Handling: The Typed Family (E1)

Decision record untuk tahap pertama kampanye Error Handling System
(`ROADMAP.md`, "Error Handling System (Enterprise-Grade)", Fase 1:
Standardization). E1 mengganti message-sniffing dengan hierarki
exception bertipe: 8 kelas family di bawah `ZefException` +
`CarriesHttpStatus` (marker capability) + `PayloadTooLargeException`
(sinyal ingress yang diketik) + `ErrorStatusMap` v2 tiga-tier +
`ErrorDisclosure` lengan family + middleware reshape tiga kolaborator.

Model yang diport: **github v2.7** (`GlobalErrorHandler`,
`Dispatcher::routeFailureResponse`, `MethodNotAllowedException` +
`RouteNotFoundException` ber-payload method/path/allowedMethods) —
legacy TIDAK punya hierarki 8-kelas, jadi Fase 1 roadmap adalah desain
baru (preseden autoconfig C3).

## KDD-E1..E14

- **E1 (sinyal yang harus sampai status code adalah KELAS, bukan
  perbandingan string)**: `ErrorStatusMap` lama mencocokkan teks pesan
  `BODY_TOO_LARGE` untuk menjawab 413 — bisa dikalahkan oleh refusal
  lain yang kebetulan berkata sama, dan buta terhadap refusal
  413 yang berkata beda. Penggantinya tiga-tier: `instanceof
  CarriesHttpStatus` → `httpStatus()` kontraknya; `HttpException`
  untyped → 400 (postur shim kasar layer); selain itu → 500. Test pin
  pensiunnya: `HttpException` ber-kata budget kini menjawab 400.
- **E2 (capability marker, bukan tangga instanceof-9-kelas)**:
  `CarriesHttpStatus::httpStatus(): int` (kontrak 400..599). Host bisa
  mendefinisikan sinyalnya sendiri dan bergabung pada resolusi yang
  sama TANPA menyentuh kode framework (test: anonymous class 418
  ter-mapped). Mental model Symfony `HttpExceptionInterface`,
  bentuk zeflous: interface di `Exception/`, leaf family `final`.
- **E3 (payload sinyal ikut kelas, bukan derivasi ulang)**:
  `MethodNotAllowedException` membawa `method`/`path`/
  `allowedMethods` (paritas legacy) — payload yang kelak membangun
  header `Allow` di E3 kampanye (router translation); mapper tak
  pernah men-derive ulang apa yang matcher sudah resolve.
  `RateLimitException` membawa `retryAfterSeconds` (untuk
  `Retry-After`). Validasi ctor fail-closed (preseden `ServiceTags`):
  allowed-list kosong / entri bukan non-empty-string / window negatif
  = `InvariantViolationException` ber-way-out — bukan repair senyap.
- **E4 (catchability H2 utuh saat root dibuka)**: `HttpException`
  final → non-final supaya `PayloadTooLargeException extends
  HttpException`. Preseden ganda repo: `DefinitionException` (dilempar
  langsung DAN di-subclass — exclusion phpcs terdokumentasi) dan
  `Request` (parent PSR-7). Pin lewat refleksi (stan tak bisa
  melipatnya): leaf final, root terbuka, refusal di bawah root.
- **E5 (pesan family = publik by contract, TAPI tetap melalui
  redaksi)**: roadmap Fase 1.1 menyebut "message: pesan ramah user" —
  jadi pesan kelas family tampil di production juga (lengan
  `CarriesHttpStatus` di `ErrorDisclosure`, kedua mode), TAPI selalu
  lewat `RedactedMessage::of()`: author yang menyelundupkan
  `password=...` di pesan client-facing dihentikan kebijakan, bukan
  oleh review (generalisasi pelajaran bocor v2.7.0 dari lengan 500 ke
  seluruh family). 413 tetap judul tetap (`Content Too Large`) —
  cek status dulu SEBELUM lengan typed (PayloadTooLarge implements
  CarriesHttpStatus; postur fixed-title harus menang).
- **E6 (lengan middleware tunggal + gerbang log pindah ranah)**:
  ladder catch lama (HttpException/Throwable) menjadi satu catch
  `Throwable` → statusFor → delegasi. Kebijakan "hanya 5xx yang
  direkam" pindah ke `ErrorFaultRecorder` (ranah: pencatatan fault di
  seam PSR-3) — E4 kampanye (level policy: 4xx WARNING) tumbuh di
  sana tanpa mapper berubah bentuk. Batas gerbang diuji DUA SISI
  (pelajaran R4): sinyal custom 499 tidak direkam; 500 direkam.
- **E7 (ranah jawaban = kolaborator sendiri)**: `ErrorEnvelope`
  (disclosure → render factory → fallback plain-text double-failure →
  stempel correlation-id) — E2 kampanye (RFC 9457 problem+json)
  mengganti separuh rendering di situ tanpa mapper berubah bentuk.
  Middleware tinggal: tangkap → resolve → record → delegasi.
- **E8 (saga archeology D-3-ronde)**: D file 20.57 → (ekstrak
  recorder) 19.80 tapi CLASS 20.89 → (ekstrak envelope) PASSED.
  Pengganda N2 tetap pesan/konteks panjang: klaster operand terbesar
  kelas middleware adalah array konteks log (6 kunci + rantai
  method) — memindahkannya ke ranah sendiri adalah pemecahan ranah,
  bukan kosmetik. Pelajaran C2 berulang persis.
- **E9 (rector × phpcs × provider)**: rector mengubah provider saya
  jadi `\Iterator` + FQN docblock — phpcs menolak FQN. Konvensi repo
  = `iterable` lowercase + yield. Rector juga me-rename properti
  (`$faults` → `$errorFaultRecorder`) yang menaikkan repetisi operand
  (E8 sensitif!) — archeology diverifikasi ulang SETELAH rector,
  dan baris panhasil rename dipecah manual. Pelajaran C3 ronde-1
  berulang: rector:check WAJIB diulang setelah edit terakhir.
- **E10 (MultiEdit non-atomik + asumsi konten)**: satu MultiEdit saya
  gagal karena mengasumsikan baris `use` yang tidak ada di file asli
  — semua edit dibatalkan bersih (atomik saat sukses, tapi asumsi
  SALAH tetap membatalkan semuanya). Verifikasi konten aktual sebelum
  menyusun edit multi-bagian (konfirmasi ketiga atas pelajaran C2/C3).
- **E11 (baterai mutasi fresh menangkap 3 escape lokal)**: prefix-pin
  (`assertStringStartsWith`) lolos pada mutan ConcatOperandRemoval —
  pin pesan harus PENUH (`assertSame` per kasus provider, debug-type
  di-provider). Tanpa baterai pra-push, ketiganya lewat ke CI seperti
  ronde-1 #396. Reset operasional terbukti lagi.
- **E12 (psalm baseline +8 UnusedClass API-surface)**: kelas family
  adalah permukaan-lempar host — src/ tidak pernah menginstansiasi
  mereka (host yang melempar dari handler). Preseden
  `ServiceProviderInterface`/`TaggedServices`. Komentar XML di
  baseline TIDAK BOLEH memuat `--` (double hyphen) — DOMDocument
  loadXML crash; teks komentar di-paraphrase.
- **E13 (stan max × hierarki pin)**: `assertInstanceOf(ZefException,
  $finalClass)` = tautologi yang dilipat analisis (pelajaran R4
  konfirmasi). Solusi provider: baris provider bertipe `ZefException`
  (keanggotaan ter-pin oleh signature), capability dicek terhadap
  TIPEnya root (non-tautologis). Pin finality/subclass lewat
  ReflectionClass — opaque bagi stan.
- **E14 (HttpException non-final = keputusan API, bukan kelemahan)**:
  leaf family tetap `final` (disiplin Enterpise Standard); root yang
  membuka adalah kontrak keluarga — sama seperti `ContainerException`
  abstract. `phpcs` exclusion Slevomat RequireAbstractOrFinal
  didokumentasikan dengan justifikasi ganda (dilempar langsung +
  di-subclass), preseden `DefinitionException`.

## Baterai final (pra-push, fresh)

- 3266 test / 6757 assertion; stan max 0; psalm L1 (baseline +8
  ber-preseden); fixer/phpcs/rector ✓; deptrac 0 error; archeology 0
  (3 ronde); coverage 99.20% (gate 90); mutasi changed-set 20 file
  FRESH: 134 mutant, 134 killed, 0 escaped, 0 uncovered — MSI 100%;
  bench ✓ (tak ada hot-path yang tersentuh — sisi-lempar + middleware
  opt-in); smoke 20000 iterasi ✓; audit strict ✓; roadmap:gate ✓
  (lane bukan main — pin tidak dihakimi); docs doctum regenerate ✓.

## Antrean kampanye E

> **Amendemen (2026-10-19):** wajah boundary pindah lane. Saat kampanye E
> melengkapi antrean ini (E2/E4/E5 terpasang di branch), seam observability
> (meter `Telemetry`) + pembacaan `Config` oleh factory membuat deptrac
> menolak 12 ketergantungan — layer `Http` hanya boleh bergantung pada
> `Exception`. Mengikuti preseden `CorrelationMiddleware`/
> `CorrelationRequestSpan`, empat kelas pindah ke lane komposisi
> `Zef\Framework\Middleware\`: `ErrorMappingMiddleware`, `ErrorMappingFactory`,
> `ErrorFaultRecorder`, `ErrorFaultMetricsRecorder`. Mesin render dan seluruh
> kolaborator pesan murni tetap di `Http`. Detail di amendemen H17-a
> (`docs/ci-notes/http-layer.md`).

- **E2**: RFC 9457 problem+json (`type`/`title`/`status`/`detail`/
  `instance` + `trace_id`) + negosiasi Accept + fallback HTML —
  mengganti separuh rendering `ErrorEnvelope` (KDD-E7).
- **E3**: router dispatch translation — konsumen `DispatchOutcome`:
  notFound → 404 problem, methodNotAllowed → 405 + header `Allow`
  dari payload (KDD-E3), constraint reject → 400.
- **E4**: observability — level policy di `ErrorFaultRecorder` (4xx
  WARNING / 5xx ERROR) + span-close-on-error + atribut exception
  (KDD-E6).
- **E5 (backlog)**: DX fase-4 (toolbar, CLI inspector, replay) +
  error analytics — tidak dinaikkan diam-diam.

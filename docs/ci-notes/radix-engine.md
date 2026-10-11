# CI Notes — Radix Tree Engine (The Triad, langkah 3)

Decision record untuk lane Radix Tree Engine di bawah **Configuration System** (`src/Zef/Framework/Config/Routing/`). Dokumen ini melengkapi `docs/ci-notes/config-system.md` (KDD-1..7 lapisan konfigurasi).

## Koreksi scope (2026-10-11)

Eksekusi awal salah menempatkan grammar di `Zef\Framework\Router` dengan scope `feat(router)` dan layer deptrac `Router`. Koreksi (PR #223): radix tree adalah **mesin eksekusi Triad** yang mengompilasi *output sistem konfigurasi* (array rute polos — Standard Route Schema), bukan bagian inisiatif Router enterprise. Namespace: `Zef\Framework\Config\Routing`; exception: `ConfigException` (route data = data konfigurasi); deptrac: collector layer `Config` sudah mencakup sub-namespace; area `Router (Radix Tree)` di ROADMAP-STATUS tetap jujur "No router code yet".

## KDD-R1..R9

- **R1 (grammar)**: pola non-empty, leading `/`, tanpa trailing `/` (kecuali root `/` sendiri), tanpa segmen kosong, brace seimbang & terkurung satu segmen, nama param `[_A-Za-z]\w*` unik per pola. Pesan exception eksak per cabang.
- **R2 (kompresi)**: kompresi karakter dalam run statis; node dipecah saat shared text habis di tengah (split common-prefix). Prioritas match: anak statis (keyed karakter pertama) → edge parameter, dengan backtracking penuh.
- **R3 (dua fase)**: builder mutable saat kompilasi (`RouteTreeBuilder` + inserter/scanner `@internal`) → hasil dibekukan sebagai graf `RadixNode` tanpa mutator publik; `RadixTree` `final readonly`. Tidak ada API mutasi pasca-compile.
- **R4 (parameter anonim)**: edge parameter menangkap tepat satu segmen non-empty sampai `/` berikutnya/EOS; nama + constraint diselesaikan di leaf oleh pemilik pola; constraint di-anchor penuh (`^(?:re)$`) dan dicek terhadap nilai mentah saat match.
- **R5 (duplikasi param-blind, diamendemen 2026-10-11)**: versi asli menolak pola kanonik sama secara mutlak. Amendemen (pra-resource-macro): rute boleh berbagi satu pola kanonik **dengan set method yang disjoint** — konvensi REST (`GET /photos`→index vs `POST /photos`→store adalah dua sumber daya berbeda dengan handler berbeda, sesuai model skema "satu entri per deklarasi method"). Yang tetap ditolak: **klaim method dua kali** pada pola yang sama (ambigu jawaban, pesan menyebut method + pola pemilik pertama) dan nama route duplikat. `RouteTableRegistry` melacak `canonical => method => pola pemilik`; leaf node menyimpan `list<CompiledRoute>` (`RadixNode::routes()`), `LeafRoute::accepting()` mengembalikan kandidat yang lolos constraint per rute, dan `RouteMethodAnswer` memilih jawaban method + menyusun union 405 terurut-unik dari semua kandidat.
- **R6 (exact-path)**: match pada path PERSIS — tanpa toleransi trailing-slash, tanpa collapse `//`, tanpa decode. Input runtime invalid (`''`, tanpa leading `/`) → notFound, bukan exception.
- **R7 (validasi defensif)**: engine memvalidasi kontrak inputnya sendiri (`CompiledRoute::fromArray(mixed)` fail-closed) tanpa bergantung layer Config — defense-in-depth terhadap integrasi yang melewati `RouteSchema`.
- **R8 (method)**: upper-case sekali di kompilasi (normalisasi + dedup + sort) dan sekali di match — **satu titik kebijakan** (`CompiledRoute::answersMethod()`; `RadixTree::match()` meneruskan mentah — dua upper-casing berantai saling menutup mutan). HEAD ≠ GET di level engine. Path cocok + method tak ada → `methodNotAllowed` dengan daftar allowed terurut.
- **R9 (reverse routing)**: generator fail-closed — nama tak dikenal / param kurang / berlebih / non-scalar-non-Stringable / string kosong / pelanggaran constraint → `ConfigException` eksak; nilai di-`rawurlencode`; **round-trip**: URL hasil generate di-match balik ke tree yang sama dan harus resolve ke route asal (dibuktikan test, bukan overhead runtime — validasi constraint saat generate sudah menjaminnya secara analitis).

## Pola teknis yang terbukti (dibawa ke lane berikutnya)

- **Split demi archeology D≤20**: walker monolitik D=45.09 dipecah per tanggung jawab menjadi `PrefixConsumption` (9.8) / `StaticDescent` / `SegmentCapture` (16.8) / `LeafRoute` / `RouteMatcher` (13.13) — dipisahnya juga mencerminkan pemisahan legacy `RouteRadixIndex` (descent / candidate collection / match).
- **Mutant masking**: dua transformasi identik berantai saling menutup mutan masing-masing → desain satu titik kebijakan.
- **`#[CoversClass]` harus mendeklarasikan semua kelas yang dieksekusi** — tanpa itu clover/Sonar new_coverage melihat 0% untuk kelas yang hanya dieksekusi via delegasi, padahal Infection membunuh mutantnya.
- **Rector ↔ psalm**: foreach-all dikonversi rector menjadi `array_all` (psalm menolak closure yang lebih sempit dari stub `callable(mixed, array-key)`); bentuk *continue* + keyed-foreach lolos keduanya.
- **S112 / S1488 / S1142 (Sonar)**: exception milik layer sebagai marker internal (bukan `ErrorException` generik); tanpa variabel sementara; ≤3 return per method (gabung via `??` atau return variabel hasil).
- **Fixer visibility-order**: method yang menjadi private harus dipindah ke akhir kelas — selalu jalankan `composer format` setelah mengubah visibilitas.

## Atribusi

Pola desain (bukan kode — zero-dependency doctrine tetap: `require` hanya `php: ^8.4`) diinspirasi implementasi terbukti pada repo legacy `Zeflous/zef-framework`: `RouteRadixIndex`/`RouteMatcher` (pemisahan descent/kandidat/match, prioritas statis), `RouteCollection` (signature index + name index + deteksi duplikat dua-sisi), `UrlGenerator` (fail-closed, `rawurlencode`, re-validasi constraint saat generate, penolakan nilai kosong).

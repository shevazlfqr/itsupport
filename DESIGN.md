# Design Direction

Status: active direction for IT Support Helpdesk. UI reference: [login desktop](docs/previews/login-desktop.png). Product behavior, access rules, and PWA details: [application guide](docs/APPLICATION.md).

## Brief pemilik produk

Pertahankan bahasa visual biru kobalt, pola ASCII, dan garis teknis dari referensi
pemilik produk tanpa menyalinnya. Typography mengikuti CSS aktif: system sans
untuk isi dan heading, monospace hanya sebagai aksen label/data.

## Arah visual

ASCII Operations: console layanan IT dengan biru kobalt, pola karakter statis,
panel modular, dan pemisah tipis. ENERGY 2 / RHYTHM 2 / MOTION 1.

- Motif karakter `. : + #` dan jalur siku ada pada header halaman dan panel login
  untuk membawa identitas retro computing dari referensi.
- Isi ticket, tabel, form, menu, dan dialog memakai permukaan solid agar mudah dibaca.
- Layout berbasis role tetap: antrean untuk IT, status ticket untuk requester,
  pemantauan operasional untuk admin.
- Border 1px, shadow offset kecil hanya pada menu/dialog; garis dashed untuk
  bantuan dan ajakan tindak lanjut.
- Tidak menambahkan metrik, navigasi, atau label terminal fiktif.

## Palette

| Peran | Light | Dark |
|---|---|---|
| Canvas | `#EAF0F9` | `#071326` |
| Panel | `#F7FAFF` | `#0D203C` |
| Panel raised | `#FFFFFF` | `#132B4A` |
| Teks | `#132B50` | `#E5EFFF` |
| Teks sekunder | `#475F80` | `#B2C3DE` |
| Aksi | `#164FC9` | `#9BBCFE` |
| Header console | `#1046AE` | `#1046AE` |

Biru mengikuti referensi; permukaan solid memisahkan konten dari tekstur.
Hijau, amber, merah tetap untuk status nyata dan disertai label teks.
Teks header `#F4F8FF` dan `#D4E3FF`; tekstur berintensitas rendah menjaga kontras.

## Typography yang dipakai

- UI dan heading utama memakai `Segoe UI, system-ui, -apple-system, BlinkMacSystemFont, sans-serif` agar headline dan paragraf terasa satu suara.
- Display mono hanya dipakai pada label teknis, header tabel, badge, angka statistik, dan marker navigasi.
- `Consolas, Courier New, monospace` dipertahankan sebagai aksen data, bukan font untuk headline panjang.
- Ukuran dan bobot mengikuti hierarki komponen aktif di `public/assets/styles.css`;
  jangan mengubah skala global sebagai efek samping perbaikan komponen.
- Motif adalah SVG lokal dengan karakter monospace, tanpa font atau dependency baru.
- Normalisasi typography lanjutan merupakan pekerjaan terpisah.

## Geometry dan interaksi

- Kontrol, panel, menu, dialog: radius 0px. Status: radius 2px.
- Avatar dan penanda bulat tetap mengikuti fungsi komponen.
- Menu/dialog opaque dan memiliki shadow offset kecil untuk membedakan lapisan.
- Focus outline solid; hover menandai state dengan warna/border.
- Motif ASCII statis, tanpa kedipan atau animasi mengetik.
- Ukuran motif menyesuaikan mobile tanpa mengubah posisi menu dan overflow.

## Alasan keputusan

Biru dan motif karakter membawa referensi pengguna; panel solid menjaga keterbacaan;
border tipis mengurangi kesan balok berat; system sans menjaga keterbacaan dan
monospace memberi aksen operasional. Konten dan jarak komponen mengikuti fungsi
helpdesk yang sudah ada.

## UI system contract

### Component language

- Forms, filters, smart selects, date controls, buttons, cards, dialogs, menus,
  and navigation controls share the same 1px border, square geometry, opaque
  surface, and control-height rhythm.
- Status chips are the intentional exception to square geometry and use a 2px
  radius. Avatars and functional circular markers keep their shape because the
  shape communicates identity or status.
- Resting controls do not use decorative shadows. A small offset shadow is
  reserved for menus and dialogs so the overlay layer is legible.
- Search, smart select, and date controls use the same border and visible 2px
  focus ring. Hover changes the border or surface state without changing the
  component's size.
- The filter row reflows from wide desktop to constrained layouts and then to a
  single-column mobile flow. It must not rely on page-level horizontal clipping.

### Typography contract

- System sans is the shared voice for headings, body copy, inputs, dropdown
  triggers, buttons, and dates. This keeps adjacent controls visually compact
  and consistent.
- `Consolas, Courier New, monospace` is limited to technical labels, table
  headers, badges, statistics, and navigation markers. It is not used for long
  headlines or body paragraphs.
- Filter controls share 13px system sans with the same weight and line height.
  A control must not become bold or switch family only because it is a select,
  date input, or custom trigger.

### Interaction and state rules

- Smart selects expose button semantics and support Enter, Space, Arrow keys,
  Home, End, and Escape. Dynamically inserted selects use the same component,
  so native browser dropdowns do not appear in one-off modal paths.
- Focus is visible in both themes. Overlays use explicit open state, Escape,
  outside-click handling, and focus restoration where the component requires
  it.
- Submit controls expose a busy state, prevent duplicate submits, and show a
  visible processing label. Request failures appear inline with an actionable
  error message.
- Ticket lists distinguish an empty dataset from an active filter with no
  matches. Both states explain what happened and offer the next useful action.
- Ticket detail prioritizes subject, description, current action, facts, and
  activity history in that order. Metadata supports the decision instead of
  competing with it.
- Notifications are a compact activity surface. A 32px ticket glyph explains
  the item, the unread count disappears at zero, and no gradient or unrelated
  icon language is introduced.

### Visual QA boundary

- The local `public/assets/ascii-field.svg` is the decorative theme texture. It is
  static, low-intensity, and must not reduce text or control contrast.
- Theme work does not change the approved font sizes, weights, or workflow
  behavior. It changes the visual contract around those existing components.
- The design must remain readable in light and dark themes and at mobile widths;
  visual texture never replaces a real state, label, or action.

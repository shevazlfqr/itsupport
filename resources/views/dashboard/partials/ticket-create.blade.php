<div class="page-title"><div><span class="eyebrow dark">LAYANAN IT</span><h1>Buat ticket baru</h1><p>Ceritakan kendala dengan jelas supaya tim IT bisa membantu lebih cepat.</p></div></div><form method="post" class="card create-form"><input type="hidden" name="csrf" value="<?= e(
    csrf(),
) ?>"><input type="hidden" name="action" value="create_ticket"><div class="form-section"><span class="step">01</span><div><h3>Apa yang bermasalah?</h3><p>Pilih kategori yang paling sesuai.</p></div></div><div class="category-grid"><?php foreach (
    CATEGORIES
    as $i => $cat
): ?><label class="category-option"><input type="radio" name="category" value="<?= e(
    $cat,
) ?>" <?= $i === 0 ? "required" : "" ?>><span><?= e(
    $cat,
) ?></span></label><?php endforeach; ?></div><hr><div class="form-section"><span class="step">02</span><div><h3>Jelaskan kendalanya</h3><p>Sertakan pesan error dan langkah yang sudah dicoba.</p></div></div><label>Judul ticket<input type="text" name="subject" minlength="5" maxlength="100" placeholder="Contoh: Tidak bisa login ke email kantor" required></label><label>Deskripsi<textarea name="description" minlength="10" rows="6" placeholder="Jelaskan kapan masalah mulai terjadi, dampaknya, dan apa yang sudah kamu coba..." required></textarea></label><label>Prioritas<select name="priority"><?php foreach (
    PRIORITIES
    as $k => $v
): ?><option value="<?= $k ?>" <?= $k === "medium" ? "selected" : "" ?>><?= e(
    $v,
) ?></option><?php endforeach; ?></select><small>Pilih Urgent hanya jika pekerjaan terhenti total atau berdampak ke banyak orang.</small></label><div class="form-actions"><a class="btn ghost" href="index.php">Batal</a><button class="btn primary" type="submit">Kirim Ticket</button></div></form>

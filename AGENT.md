# AGENT Workflow Rules — Fashion Store

> File ini WAJIB dibaca oleh semua model AI sebelum mulai bekerja.
> Aturan di bawah bersifat **mengikat** dan tidak boleh dilanggar.

---

## 1. Siklus Pengembangan (WAJIB DIKUTI)

```
[DEVELOP] → [TEST-LOCAL] → [PASS?] → [COMMIT] → [CI-TEST] → [CI-PASS?] → ✅ SELESAI
                                 ↓                          ↓
                            GAGAL → fix kode            GAGAL → analisa → fix → push ulang
```

| Step | Nama | Deskripsi |
|------|------|-----------|
| 1 | **DEVELOP** | Buat / ubah kode di localhost |
| 2 | **TEST-LOCAL** | Jalankan semua tes di localhost: `python AGENT.py --test`, `python -m pytest tests/`, `npm test`, `npm run lint` |
| 3 | **PASS?** | Jika ada tes **GAGAL** → analisa output error → perbaiki kode → kembali ke step 2 |
| 4 | **PASS?** | Jika **SEMUA** tes lolos (100%) → lanjut ke step 5 |
| 5 | **COMMIT** | `git add .` → `git commit -m "deskripsi perubahan"` → `git push origin <branch>` |
| 6 | **CI-TEST** | CI/CD otomatis berjalan di GitHub Actions. Pantau di `https://github.com/<owner>/<repo>/actions` |
| 7 | **CI-PASS?** | Jika **CI SUCCESS** → ✅ Selesai |
| 8 | **CI-PASS?** | Jika **CI FAILED** → buka log → analisa → fix di local → test ulang → commit & push ulang → ulangi sampai CI hijau ✅ |

---

## 2. Larangan (TIDAK BOLEH DILANGGAR)

| # | Larangan |
|---|----------|
| ❌ | DILARANG push tanpa tes lokal terlebih dahulu |
| ❌ | DILARANG mengabaikan tes yang gagal (walaupun 1 test) |
| ❌ | DILARANG commit langsung ke `main`/`master` tanpa PR/review |
| ❌ | DILARANG menghapus tes yang gagal tanpa memperbaiki kode |
| ❌ | DILARANG menonaktifkan CI pipeline |
| ❌ | DILARANG skip tes dengan alasan apapun |
| ❌ | DILARANG merge PR yang CI-nya merah |

---

## 3. Loop Error CI — Prosedur Lengkap

Jika CI gagal, ikuti prosedur ini **WAJIB**:

### STEP 1 — BACA LOG
- Buka tab **GitHub Actions** di repository
- Klik workflow yang gagal (yang berwarna merah)
- Baca setiap step yang gagal
- Catat: nama test, pesan error, line number, exit code

### STEP 2 — ANALISA
- Apakah error di kode baru atau kode lama?
- Apakah error karena **logic**, **sintaks**, atau **dependency**?
  - Error dependency → update `requirements.txt` / `package.json`
  - Error logic → perbaiki implementasi
  - Error sintaks → perbaiki penulisan kode

### STEP 3 — FIX DI LOCAL
- Perbaiki kode di localhost
- **JANGAN LANGSUNG PUSH**

### STEP 4 — TEST LOCAL LAGI
- Jalankan semua tes lokal: `python AGENT.py --test`
- Pastikan **100% lolos**

### STEP 5 — COMMIT ULANG
```bash
git add .
git commit -m "fix: <deskripsi masalah yang diperbaiki>"
git push origin <branch>
```

### STEP 6 — VERIFIKASI CI
- Pantau GitHub Actions sampai selesai
- Jika masih merah 🔴 → ulangi dari **STEP 1**
- Jika hijau ✅ → selesai

---

## 4. Struktur Direktori (Referensi)

```
fashion_store/
├── AGENT.py              ← Aturan & konfigurasi AI (source of truth)
├── AGENT.md              ← Aturan & konfigurasi AI (markdown — file ini)
├── src/                  ← Kode sumber aplikasi
├── tests/                ← Unit test
├── public/               ← Aset statis
├── package.json          ← Dependency Node.js (jika ada)
├── requirements.txt      ← Dependency Python (jika ada)
└── .github/workflows/    ← Konfigurasi CI/CD
```

---

## 5. Catatan untuk Semua Model AI

1. **WAJIB** membaca file `AGENT.py` dan `AGENT.md` ini **sebelum** mulai mengerjakan tugas apapun
2. Patuhi **semua** aturan di atas tanpa pengecualian
3. Jika ragu tentang suatu perubahan — **TANYA user** sebelum bertindak
4. Jangan pernah berasumsi bahwa tes yang gagal adalah "tidak penting"
5. Dokumentasikan setiap error yang ditemukan di **commit message** agar memudahkan tracking
6. Utamakan **kesederhanaan dan kejujuran** — hindari fitur yang tidak stabil/tidak teruji

---

*"Simple and honest beats sophisticated and broken"*

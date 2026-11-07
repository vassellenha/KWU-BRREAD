# BRREAD! — Staff Portal Guide

Panduan penggunaan sistem BRREAD! untuk anggota tim yang ingin mengakses portal staf berdasarkan role masing-masing.

## Login

**Staff:** `http://127.0.0.1:8000/staff`

| Modul | Username | Password |
|---|---|---|
| MRP | `staffmrp` | `12345678` |
| HR | `staffhr` | `12345678` |
| Accounting | `staffacc` | `12345678` |

**Admin:** `http://127.0.0.1:8000/admin`

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | Hubungi pengelola project |

Halaman `/admin` menampilkan saran **Quick login** satu klik saat `APP_ENV=local`. Di environment lain fitur ini mati kecuali `ADMIN_QUICK_LOGIN=true` di-set secara sengaja, karena siapa pun yang bisa membuka halamannya akan langsung menjadi admin.

Akun staff hanya bisa masuk lewat `/staff`, dan akun admin hanya bisa masuk lewat `/admin`.

## Akses per Role (RBAC)

| Role | MRP | HR | Accounting |
|---|---|---|---|
| Admin | ✓ | ✓ | ✓ |
| MRP Staff | ✓ | — | — |
| HR Staff | — | ✓ | — |
| Accounting Staff | — | — | ✓ |

Sidebar hanya menampilkan modul yang boleh dibuka, dan URL modul lain menjawab halaman 403.

## Material Requirements Planning (MRP)

**URL:** `http://127.0.0.1:8000/staff/mrp`

Modul MRP digunakan untuk mengelola persediaan bahan baku BRREAD! yang terintegrasi langsung dengan customer storefront.

- **Inventory Dashboard:** Monitoring stok, kategori bahan, reorder point, dan status persediaan.
- **Low Stock Alert:** Peringatan otomatis ketika persediaan mencapai batas minimum.
- **Stock Adjustment:** Restock dan koreksi stok secara manual dengan catatan perubahan. Biaya restock yang diisi otomatis tercatat sebagai expense di Accounting.
- **Stock Movement History:** Riwayat lengkap pergerakan stok melalui `/staff/mrp/movements`.
- **Automatic Stock Deduction:** Stok bahan otomatis berkurang setelah customer menyelesaikan checkout.

## Human Resources (HR)

**URL:** `http://127.0.0.1:8000/staff/hr`

- **Employee Directory:** Tambah, ubah, dan nonaktifkan karyawan.
- **Duty Rotation:** 3 karyawan aktif berjaga setiap 3 hari, dihitung otomatis dari urutan rotasi.

## Accounting

**URL:** `http://127.0.0.1:8000/staff/accounting`

- **Cashflow Ledger:** Uang masuk otomatis dari order customer, uang keluar dari expense yang dicatat staff.
- **Financial Report:** Saldo awal, income, expenses, saldo akhir, dan rincian expense per kategori untuk rentang tanggal yang dipilih.
- **Export to Excel:** Mengunduh laporan dan ledger dalam format `.xlsx`.

## Customer Storefront Integration

Ketika customer melakukan checkout melalui keranjang:

1. Sistem memvalidasi stok setiap bahan pada seluruh isi keranjang.
2. Stok setiap bahan berkurang sesuai jumlah pesanan.
3. Transaksi dicatat dalam stock movement log.
4. Dashboard MRP menampilkan stok terbaru.
5. Order muncul sebagai uang masuk di Accounting.

## Technical Information

- **Framework:** Laravel
- **Authentication:** Native Laravel Authentication
- **Authorization:** Role-Based Access Control (RBAC)
- **Frontend:** Tailwind CSS v4
- **Inventory Model:** Component

## Running the Application

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Konfigurasikan database pada file `.env`, kemudian jalankan:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

**Customer Storefront:** `http://127.0.0.1:8000`

**Staff Portal:** `http://127.0.0.1:8000/staff`

**Admin Portal:** `http://127.0.0.1:8000/admin`

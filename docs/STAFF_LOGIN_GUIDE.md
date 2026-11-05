# BRREAD! — Staff Portal Guide

Panduan penggunaan sistem BRREAD! untuk anggota tim yang ingin mengakses portal staf berdasarkan role masing-masing.

## Staff Login

**URL:** `http://127.0.0.1:8000/staff/login`

| Role | Email | Password |
|---|---|---|
| Admin | `admin@brread.id` | Hubungi pengelola project |
| Warehouse | `warehouse@brread.id` | Hubungi pengelola project |

**Available Roles:**
- **Admin:** Akses administratif sistem.
- **Warehouse:** Mengelola stok bahan baku melalui modul MRP.
- **HR:** Disiapkan untuk modul Human Resources.
- **Accountant:** Disiapkan untuk modul Accounting.

> HR dan Accounting masih dalam tahap pengembangan.

## Material Requirements Planning (MRP)

**URL:** `http://127.0.0.1:8000/staff/mrp`

Modul MRP digunakan untuk mengelola persediaan bahan baku BRREAD! yang terintegrasi langsung dengan customer storefront.

### Features

- **Inventory Dashboard:** Monitoring stok, kategori bahan, reorder point, dan status persediaan.
- **Low Stock Alert:** Peringatan otomatis ketika persediaan mencapai batas minimum.
- **Stock Adjustment:** Restock dan koreksi stok secara manual dengan catatan perubahan.
- **Stock Movement History:** Riwayat lengkap pergerakan stok melalui `/staff/mrp/movements`.
- **Automatic Stock Deduction:** Stok bahan otomatis berkurang setelah customer menyelesaikan checkout.

## Customer Storefront Integration

Ketika customer melakukan checkout melalui Sandwich Builder:

1. Sistem mengidentifikasi bahan yang digunakan.
2. Stok setiap bahan yang digunakan berkurang satu unit.
3. Transaksi dicatat dalam stock movement log.
4. Dashboard MRP menampilkan stok terbaru.

Integrasi telah diuji secara end-to-end.

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
php artisan migrate
npm run build
php artisan serve
```

**Customer Storefront:** `http://127.0.0.1:8000`

**Staff Portal:** `http://127.0.0.1:8000/staff/login`

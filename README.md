# WAJIRA Backend API

Backend API untuk sistem manajemen terintegrasi (ERP) yang dirancang untuk mendukung operasional bisnis menyeluruh, meliputi manajemen data master, transaksi barang, logistik, keuangan, dan pelaporan secara real-time.

---

## Fitur Utama

- **Master Data**: Kelola data perusahaan, gudang, barang/material, armada kendaraan, tarif ekspedisi, dan personil (Supplier/Customer).
- **Transaction Management**: Manajemen transaksi unit kendaraan, suku cadang, dan pemesanan logistik.
- **Warehouse Management**: Monitoring inventaris material/barang masuk (receipt) dan keluar (issue), stok opname, serta aktivitas pergudangan.
- **Finance Management**: Pencatatan keuangan otomatis melalui Transaction Flow (buku besar transaksi) dan Cash Flow (arus kas harian) yang dipicu secara otomatis oleh billing, serta manajemen invoice/termin pembayaran.
- **Reporting System**: Laporan registrasi kendaraan (STNK, SKPD, TNKB), performa pengiriman, dan arus kas keuangan.
- **User & Role Management (RBAC)**: Pengendalian akses terperinci berbasis hak akses dan peran (Permissions & Roles) menggunakan Spatie.

---

## Tech Stack

- **Core**: PHP 8.1+ / Laravel Framework
- **Database**: MySQL 8+
- **Authentication**: JWT / Laravel Sanctum
- **Role & Access Control**: Spatie Laravel Permission
- **Testing**: PHPUnit

---

## Panduan Instalasi & Deployment

Pilih metode instalasi yang sesuai dengan lingkungan server Anda.

### 1. Lingkungan Pengembangan (Development Server)
Untuk instalasi lokal atau server development yang membutuhkan data dummy lengkap untuk kebutuhan pengujian:

1. Pastikan file script dapat dieksekusi:
   ```bash
   chmod +x ./dev-install.sh
   ```
2. Jalankan script instalasi development:
   ```bash
   ./dev-install.sh
   ```
   *Script ini otomatis akan:*
   - Mengunduh dependensi composer.
   - Membersihkan cache route dan konfigurasi.
   - Menjalankan unit testing (`php artisan test`) untuk memastikan kode stabil.
   - Menawarkan konfirmasi untuk melakukan seeding database lengkap dengan data dummy (`DummyDataSeeder`, `PersonSeeder`, `MainCompanyWarehouseSeeder`, `TarifSeeder`, dsb).

### 2. Lingkungan Produksi (Production Server)
Untuk instalasi di server produksi demi performa optimal dan keamanan maksimal:

1. Pastikan file script dapat dieksekusi:
   ```bash
   chmod +x ./prod-install.sh
   ```
2. Jalankan script instalasi produksi:
   ```bash
   ./prod-install.sh
   ```
   *Script ini otomatis akan:*
   - Menyetel konfigurasi direktori aman Docker (`/app`).
   - Mengunduh dependensi produksi.
   - Men-generate application key secara aman (`key:generate --force`).
   - Menghubungkan direktori penyimpanan publik (`storage:link`).
   - Menjalankan unit testing untuk verifikasi akhir.
   - Melakukan migrasi database ter-update.
   - Mengaktifkan caching konfigurasi (`config:cache`) untuk meningkatkan performa response API.

---

## Dokumentasi Predefined Data (Seeders)

Aplikasi dilengkapi dengan data awal terdefinisi (predefined data) guna mempercepat setup awal maupun testing. Berikut adalah daftar seeders yang tersedia:

| Nama Seeder | Deskripsi / Tujuan |
| :--- | :--- |
| **`DefaultAccountSeeder`** | Memuat bagan akun standar / Chart of Accounts (COA) untuk sistem akuntansi dan arus kas (Cash Flow). |
| **`CompanySeeder`** | Mendaftarkan badan usaha / perusahaan utama dalam grup Wajira Yanotama. |
| **`PersonSeeder`** | Menyediakan data personil awal termasuk PIC, data Customer, dan Supplier default untuk operasional awal. |
| **`WarehouseGoodsTransactionSeeder`** | Menstimulasi flow transaksi gudang mulai dari Material Receipt hingga Issue, serta pembuatan billing. |
| **`TarifSeeder`** | Mendaftarkan tarif dasar ekspedisi berdasarkan asal, tujuan, dan jenis kendaraan. |
| **`DummyDataSeeder`** | Mengisi database dengan data dummy terintegrasi di seluruh modul (Master Data, Fleet, Logistik, Transaksi, dll) untuk mempermudah demonstrasi aplikasi. |

---

## Dokumentasi API (Postman)

Koleksi API beserta environment variabel untuk pengujian endpoint dapat diakses melalui link berikut:

👉 [Postman Collection & Environment Workspace](https://deraly-dev-workspace.postman.co/workspace/Deraly-Dev-Workspace-Workspace~e9e32e9f-cd9e-4ac6-87e4-c2d77795e00e/collection/39336331-2189027e-e1d7-4b6e-b46a-cd2264fcf5fb?action=share&creator=39336331&active-environment=39336331-6ccd793e-2c31-4457-9637-9328c569efe7)

---

## Perintah Pasca Docker Up

Jika Anda menjalankan aplikasi di dalam container Docker, jalankan perintah berikut untuk menginisialisasi state aplikasi:

```bash
docker exec -it backend php artisan key:generate
docker exec -it backend php artisan migrate
docker exec -it backend php artisan optimize:clear
```
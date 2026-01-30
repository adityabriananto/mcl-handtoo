# 🛠️ Hand Tools Documentation: Handtoo Project Management Tool

Dokumen ini berfungsi sebagai panduan operasional aplikasi **Handtoo** bagi tim atau individu yang akan mengelola, mengembangkan, atau melakukan deployment pada server.

## 1. 📋 Tools Overview

* **Official Name:** MCL-Handtoo
* **Tools Version:** 1.0.0
* **Main Objective:** Handtoo adalah aplikasi Manajemen Proyek internal yang dirancang untuk **memfasilitasi pelacakan tugas terpusat, manajemen tim, dan dokumentasi aset proyek**.
* **Developed By:** TPM - Aditya Briananto

---

## 2. 💻 Technical Details

Aplikasi ini dibangun dengan *stack* teknologi Laravel modern untuk memastikan performa dan skalabilitas:

| Kategori | Detail |
| :--- | :--- |
| **Main Framework** | **Laravel 11** (PHP 8.2+) |
| **Front-end Stack** | Blade & Livewire |
| **Asset Bundler** | **Vite** |
| **Database** | MySQL (Min. versi 8.0) |
| **Deployment Tool** | **Capistrano 3** (Ruby based) |

---

## 3. 🚀 Deployment Guide (Capistrano)

Proyek ini menggunakan **Capistrano** untuk memastikan *Zero Downtime Deployment* melalui mekanisme *Atomic Symlink*.

### Prasyarat Lokal (Developer Machine)
* **Ruby:** Versi 3.2.2 (Disarankan menggunakan `rbenv`).
* **Bundler:** Versi terbaru (`gem install bundler`).
* **SSH Access:** Public key Anda harus terdaftar di server target.

### Struktur Folder Server


Struktur di server (`/var/www/handtoo`) dikelola secara otomatis:
* `releases/`: Menyimpan beberapa versi rilis terakhir.
* `shared/`: Menyimpan file permanen yang tidak berubah antar rilis (seperti `.env`, `storage`, dan folder upload).
* `current/`: Pintasan (*symlink*) yang selalu mengarah ke versi rilis aktif. **Nginx harus diarahkan ke folder ini.**

### Perintah Deployment

1.  **Deploy ke Staging (Branch Default):**
    ```bash
    bundle exec cap staging deploy
    ```
2.  **Deploy Branch Spesifik (Misal: Untuk Code Review):**
    ```bash
    bundle exec cap staging deploy BRANCH=features/add-crunching-logic
    ```
3.  **Rollback (Jika Terjadi Error di Produksi):**
    ```bash
    bundle exec cap staging deploy:rollback
    ```

---

## 4. ⚙️ Installation & Local Setup Guide

Ikuti langkah-langkah berikut untuk menjalankan Handtoo di lingkungan lokal:

1.  **Clone Repository:**
    ```bash
    git clone [REPO LINK]
    cd Handtoo
    ```
2.  **Install PHP Dependencies:**
    ```bash
    composer install
    ```
3.  **Install Front-end Dependencies:**
    ```bash
    npm install && npm run dev
    ```
4.  **Environment Configuration:**
    * Salin file konfigurasi: `cp .env.example .env`
    * Generate Key: `php artisan key:generate`
    * Sesuaikan konfigurasi database (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) di file `.env`.

5.  **Database Setup:**
    ```bash
    php artisan migrate
    ```

---

## 5. ▶️ Cara Menjalankan Aplikasi (Lokal)

Anda membutuhkan dua terminal yang berjalan secara bersamaan:

1.  **Terminal 1 (PHP Server):**
    ```bash
    php artisan serve
    ```
2.  **Terminal 2 (Vite Hot Reload):**
    ```bash
    npm run dev
    ```

---

## 6. ⚠️ Catatan Penting untuk Maintainer

* **Dilarang mengubah file langsung di server** (folder `current/`). Semua perubahan harus melalui Git dan dideploy ulang via Capistrano agar tidak tertimpa rilis baru.
* **Konfigurasi .env Server:** Jika ada perubahan environment di server, ubah file `.env` yang berada di folder `shared/`, bukan di folder rilis.
* **Clean Releases:** Capistrano secara otomatis menyimpan 5 rilis terakhir untuk menghemat ruang disk.

---

**TPM Team - MCL Project**

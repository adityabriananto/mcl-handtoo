# 🛠️ Hand Tools Documentation: Handtoo Project Management Tool

This document serves as the operational guide for the **Handtoo** application for the team or individuals who will manage, develop, or deploy the project on servers.

## 1. 📋 Tools Overview

* **Official Name:** MCL-Handtoo
* **Tools Version:** 1.0.0
* **Main Objective:** Handtoo is an internal Project Management application designed to **facilitate centralized task tracking, team management, and project asset documentation**.
* **Developed By:** TPM - Aditya Briananto

---

## 2. 💻 Technical Details

The application is built using a modern Laravel stack to ensure high performance and scalability:

| Category | Detail |
| :--- | :--- |
| **Main Framework** | **Laravel 11** (PHP 8.2+) |
| **Front-end Stack** | Blade & Livewire |
| **Asset Bundler** | **Vite** |
| **Database** | MySQL (Min. version 8.0) |
| **Deployment Tool** | **Capistrano 3** (Ruby based) |

---

## 3. 🚀 Deployment Guide (Capistrano)

This project utilizes **Capistrano** to ensure *Zero Downtime Deployment* through an *Atomic Symlink* mechanism.

### Local Prerequisites (Developer Machine)
* **Ruby:** Version 3.2.2 (Recommended to manage via `rbenv`).
* **Bundler:** Latest version (`gem install bundler`).
* **SSH Access:** Your public key must be registered on the target server.

### Server Directory Structure


The structure on the server (`/var/www/handtoo`) is managed automatically:
* `releases/`: Stores a history of several previous deployment versions.
* `shared/`: Stores permanent files that persist across releases (such as `.env`, `storage`, and upload folders).
* `current/`: A shortcut (*symlink*) that always points to the active release version. **Nginx must be pointed to this folder.**

### Deployment Commands

1.  **Deploy to Staging (Default Branch):**
    ```bash
    bundle exec cap staging deploy
    ```
2.  **Deploy Specific Branch (e.g., for Code Review):**
    ```bash
    bundle exec cap staging deploy BRANCH=features/add-crunching-logic
    ```
3.  **Rollback (In case of Production Errors):**
    ```bash
    bundle exec cap staging deploy:rollback
    ```

---

## 4. ⚙️ Installation & Local Setup Guide

Follow these steps to run Handtoo in your local environment:

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
    * Copy the configuration file: `cp .env.example .env`
    * Generate Application Key: `php artisan key:generate`
    * Adjust database configuration (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) in the `.env` file.

5.  **Database Setup:**
    ```bash
    php artisan migrate
    ```

---

## 5. ▶️ Running the Application (Local)

You will need two terminals running simultaneously:

1.  **Terminal 1 (PHP Server):**
    ```bash
    php artisan serve
    ```
2.  **Terminal 2 (Vite Hot Reload):**
    ```bash
    npm run dev
    ```

---

## 6. ⚠️ Critical Notes for Maintainers

* **Direct Editing Prohibited:** Do not modify files directly on the server (inside the `current/` folder). All changes must go through Git and be redeployed via Capistrano to prevent data loss during the next release.
* **Server .env Configuration:** If environment changes are required on the server, modify the `.env` file located in the `shared/` folder, not within the release folders.
* **Clean Releases:** Capistrano automatically retains the last 5 releases to optimize disk space.

---

**TPM Team - MCL Project**

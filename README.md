# 🛠️ Hand Tools Documentation: Handtoo Project Management Tool

This document serves as the MCL Hand Tools guide for the **Handtoo** tool to the team/individual who will subsequently manage or operate it.

## 1. 📋 Tools Overview

* **Official Name:** MCL-Handtoo
* **Tools Version:** 1.0.0
* **Main Objective:** Handtoo is an internal Project Management application designed to **facilitate centralized task tracking, team management, and project asset documentation**.
* **Developed By:** TPM - Aditya Briananto

## 2. 💻 Technical Details

This tool was developed using the modern Laravel technology stack:

| Category | Detail |
| :--- | :--- |
| **Main Framework** | **Laravel 11** (PHP) |
| **Front-end Stack** | Blade & Livewire |
| **Asset Bundler** | **Vite** |
| **Database** | MySQL (version 8.0+ recommended) |

### Dependencies and System Prerequisites

To run this tool, your environment must have the following prerequisites installed:

1.  **PHP** version 8.2 or higher.
2.  **Composer** (PHP dependency manager).
3.  **Node.js** and **npm/yarn** (For Vite and front-end dependencies).
4.  **MySQL** or another database configured in the `.env` file.
5.  Web Server (Apache/Nginx/run via `php artisan serve`).

## 3. ⚙️ Installation & Local Setup Guide

Follow these steps to install and set up Handtoo in your local environment:

1.  **Clone Repository:**
    ```bash
    git clone [REPO LINK]
    cd Handtoo
    ```
2.  **Install PHP Dependencies:**
    ```bash
    composer install
    ```
3.  **Install Front-end Dependencies (Node.js):**
    ```bash
    npm install
    # OR
    yarn install
    ```
4.  **Environment Configuration:**
    * Copy the configuration file: `cp .env.example .env`
    * Generate Application Key: `php artisan key:generate`
    * **Edit the `.env` file** and adjust the database configuration (`DB_...`) and application URL (`APP_URL`).

5.  **Database Setup:**
    * Run migrations to create tables: `php artisan migrate`
    * (Optional) Seed dummy data if available: `php artisan db:seed`

## 4. ▶️ How to Run the Application

The Handtoo application requires two processes to run: the PHP Server and Vite.

### Step 1: Run Laravel Server

Execute the following command in the first terminal:

```bash
php artisan serve

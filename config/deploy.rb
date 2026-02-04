# config valid for current version and patch releases of Capistrano
lock "~> 3.20.0"

set :application, "handtoo"
set :repo_url, "git@github.com:adityabriananto/mcl-handtoo.git"

# Gunakan folder induk 'storage' agar semua sub-folder di dalamnya otomatis tersambung
set :linked_dirs, %w{
    public/nfsi
    public/temp-storage
    public/exports
    storage
    vendor
    node_modules
}

set :linked_files, %w{.env}
set :keep_releases, 2

set :ssh_options, {
  forward_agent: true,
  user: 'root'
}

# --- FIX PATH BINARY (SESUAI HASIL WHICH NPM DI SERVER) ---
SSHKit.config.command_map[:npm]      = "/root/.nvm/versions/node/v24.5.0/bin/npm"
SSHKit.config.command_map[:node]     = "/root/.nvm/versions/node/v24.5.0/bin/node"
SSHKit.config.command_map[:php]      = "/usr/bin/php"
SSHKit.config.command_map[:composer] = "/usr/bin/composer"

namespace :deploy do

    desc 'Run Laravel Deployment Tasks'
    task :laravel_tasks do
        on roles(:app) do
            within release_path do
                # 1. Update Autoloader & Install Dependencies
                # Kita jalankan dump-autoload untuk memastikan namespace baru terdaftar
                execute :composer, "install --no-dev --optimize-autoloader"

                # 2. Build Assets (Vite)
                execute :npm, "install"
                execute :npm, "run build"

                # DEKRIPSI ENV
                # Mengambil Key dari environment variable terminal saat Anda menjalankan deploy
                # Perintah: LARAVEL_ENV_ENCRYPTION_KEY=base64:xxx... cap production deploy
                # execute :php, "artisan env:decrypt --key=#{ENV['LARAVEL_ENV_ENCRYPTION_KEY']} --force"

                # 3. Database Migration
                execute :php, "artisan migrate --force"

                # 4. Storage & Permissions
                # Membuat folder jika belum ada dan mengatur akses
                execute :php, "artisan storage:link"
                execute "mkdir -p #{release_path}/bootstrap/cache"
                execute "chmod -R 775 #{shared_path}/storage"
                execute "chmod -R 775 #{release_path}/bootstrap/cache"
                execute "chown -R root:www-data #{shared_path}/storage #{release_path}/bootstrap/cache"

                # 5. Clear & Optimize Cache
                # Jalankan ini terakhir setelah semua class dan file siap
                execute :php, "artisan optimize:clear || true"
                execute :php, "artisan config:cache"
                execute :php, "artisan route:cache"
                execute :php, "artisan view:cache"

            end
        end
    end

    desc 'Reload Web Server'
    task :reload_services do
        on roles(:app) do
            execute "systemctl reload nginx"
            # Optional: execute "systemctl restart php8.2-fpm"
        end
    end

    # Urutan Eksekusi:
    # Jalankan semua task Laravel sebelum symlink 'current' diperbarui
    before :publishing, :laravel_tasks

    # Reload server setelah symlink berhasil dipasang
    after :published, :reload_services

end

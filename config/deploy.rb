# config valid for current version and patch releases of Capistrano
lock "~> 3.20.0"

set :application, "handtoo"
set :repo_url, "git@github.com:adityabriananto/mcl-handtoo.git"

# Folder yang tetap persisten di antara rilis (Shared Folder)
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

# --- FIX PATH BINARY (SESUAI HASIL WHICH DI SERVER) ---
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
                execute :composer, "install --no-dev --optimize-autoloader"

                # 2. Build Assets (Vite)
                execute :npm, "install"
                execute :npm, "run build"

                # 3. Database Migration
                execute :php, "artisan migrate --force --no-interaction"

                # 4. Storage & Permissions
                execute :php, "artisan storage:link"
                execute "mkdir -p #{release_path}/bootstrap/cache"
                execute "chmod -R 775 #{shared_path}/storage"
                execute "chmod -R 775 #{release_path}/bootstrap/cache"
                # Sesuaikan www-data jika user grup server Anda berbeda
                execute "chown -R root:www-data #{shared_path}/storage #{release_path}/bootstrap/cache"

                # 5. Clear & Optimize Cache
                execute :php, "artisan optimize:clear"
                execute :php, "artisan config:cache"
                execute :php, "artisan route:cache"
                execute :php, "artisan view:cache"
            end
        end
    end

    desc 'Restart Supervisor Queue Workers'
    task :restart_supervisor do
        on roles(:app) do
            within release_path do
                # Memberi sinyal ke worker untuk mati & restart otomatis oleh Supervisor
                # Ini memastikan worker menjalankan kode rilis terbaru
                execute :php, "artisan queue:restart"
            end
        end
    end

    desc 'Reload Web Server & PHP-FPM'
    task :reload_services do
        on roles(:app) do
            execute "systemctl reload nginx"
            # Penting: Restart PHP-FPM untuk membersihkan Opcache rilis lama
            execute "systemctl restart php8.2-fpm"
        end
    end

    # --- URUTAN EKSEKUSI ---

    # 1. Jalankan task Laravel sebelum symlink 'current' dipindahkan ke rilis baru
    before :publishing, :laravel_tasks

    # 2. Reload Nginx & PHP-FPM setelah symlink 'current' terpasang
    after :published, :reload_services

    # 3. Terakhir, restart Supervisor agar membaca folder 'current' yang baru
    after :published, :restart_supervisor

end

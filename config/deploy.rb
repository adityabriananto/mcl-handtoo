# config valid for current version and patch releases of Capistrano
lock "~> 3.20.0"

set :application, "handtoo"
set :repo_url, "git@github.com:adityabriananto/mcl-handtoo.git"

set :linked_files, %w{.env}
set :linked_dirs, %w{
    public/nfsi
    public/temp-storage
    public/exports
    storage/logs
    storage/app/private/temp
    storage/app/private/temp_imports
    storage/app/private/uploads
    vendor
    node_modules
}

set :keep_releases, 2

set :ssh_options, {
  forward_agent: true,
  user: 'root'
}

# --- FIX PATH BINARY (SESUAI SERVER ANDA) ---
SSHKit.config.command_map[:npm]      = "/root/.nvm/versions/node/v24.5.0/bin/npm"
SSHKit.config.command_map[:node]     = "/root/.nvm/versions/node/v24.5.0/bin/node"
SSHKit.config.command_map[:php]      = "/usr/bin/php"
SSHKit.config.command_map[:composer] = "/usr/bin/composer"

namespace :deploy do

    desc 'Run Laravel Deployment Tasks'
    task :laravel_tasks do
        on roles(:app) do
            within release_path do
                execute :composer, "install --no-dev --optimize-autoloader"
                execute :npm, "install"
                execute :npm, "run build"

                # URUTAN AMAN: Clear dulu semua, baru Cache
                execute :php, "artisan optimize:clear"
                execute :composer, "dump-autoload -o" # Merefresh class map

                execute :php, "artisan migrate --force"
                execute :php, "artisan storage:link"

                # Buat cache baru
                execute :php, "artisan config:cache"
                execute :php, "artisan route:cache"
                execute :php, "artisan view:cache"

                execute "chmod -R 775 storage bootstrap/cache"
            end
        end
    end

    # Task untuk reload Nginx agar konfigurasi terbaru/cache server bersih
    desc 'Reload Nginx'
    task :reload_nginx do
        on roles(:web) do
            execute "systemctl reload nginx"
        end
    end

    # Urutan eksekusi
    before :publishing, :laravel_tasks
    after :published, :reload_nginx

end

# config valid for current version and patch releases of Capistrano
lock "~> 3.20.0"

set :application, "handtoo"
set :repo_url, "git@github.com:adityabriananto/mcl-handtoo.git"
set :linked_files, %w{.env}
set :linked_dirs, %w{public/nfsi public/temp-storage public/exports storage/logs storage/app/private/temp storage/app/private/temp_imports storage/app/private/uploads}
set :keep_releases, 2

set :ssh_options, {
  forward_agent: true,
  user: 'root'
}

# set :branch, ENV['BRANCH'] || 'staging'

namespace :deploy do

    desc 'Restart application'
        task :restart do
            on roles(:app), in: :sequence, wait: 5 do
                # Your restart mechanism here, for example:
                # execute :touch, release_path.join('tmp/restart.txt')
                execute "chmod a+w #{release_path.join('storage')} -R"
                execute "cd '#{release_path}'; composer install"
                execute "php #{release_path.join('artisan')} migrate"
                execute "php #{release_path.join('artisan')} storage:link"
                # execute "php #{release_path.join('artisan')} migrate --force"
        end
    end

  before :publishing, :restart

    after :restart, :clear_cache do
        on roles(:web), in: :groups, limit: 3, wait: 10 do
            # Here we can do anything such as:
            # within release_path do
            #   execute :rake, 'cache:clear'
            # end
        end
    end

end

# Default branch is :master
# ask :branch, `git rev-parse --abbrev-ref HEAD`.chomp

# Default deploy_to directory is /var/www/my_app_name
# set :deploy_to, "/var/www/my_app_name"

# Default value for :format is :airbrussh.
# set :format, :airbrussh

# You can configure the Airbrussh format using :format_options.
# These are the defaults.
# set :format_options, command_output: true, log_file: "log/capistrano.log", color: :auto, truncate: :auto

# Default value for :pty is false
# set :pty, true

# Default value for :linked_files is []
# append :linked_files, "config/database.yml", 'config/master.key'

# Default value for linked_dirs is []
# append :linked_dirs, "log", "tmp/pids", "tmp/cache", "tmp/sockets", "public/system", "vendor", "storage"

# Default value for default_env is {}
# set :default_env, { path: "/opt/ruby/bin:$PATH" }

# Default value for local_user is ENV['USER']
# set :local_user, -> { `git config user.name`.chomp }

# Default value for keep_releases is 5
# set :keep_releases, 5

# Uncomment the following to require manually verifying the host key before first deploy.
# set :ssh_options, verify_host_key: :secure

<?php

namespace Deployer;

require_once 'recipe/laravel.php';

// Config

set('repository', 'git@github.com:SCP-015/E-sign.git');
set('keep_releases', 5);

set('http_user', 'www-data');
set('writable_mode', 'chmod');
add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// Set PHP Binary explicitly
set('bin/php', '/usr/bin/php8.3');
set('bin/composer', '/usr/bin/php8.3 /usr/bin/composer');

// Hosts

// host('prod')
//     ->set('branch', 'main')
//     ->set('hostname', 'production') // nama di .ssh/config
//     ->set('remote_user', 'ade')
//     ->set('deploy_path', '/var/www/esign');

host('dev')
    ->set('branch', 'main')
    ->set('hostname', 'lab-meet') // nama di .ssh/config
    ->set('remote_user', 'ade')
    ->set('deploy_path', '/var/www/esign');

//task

task('deploy:env-sync', function () {
    // backup file .env jika ada
    run('if [ -f {{deploy_path}}/shared/.env ]; then cp {{deploy_path}}/shared/.env {{deploy_path}}/shared/.env.bak; fi');
    // Cek apakah file .env.example ada
    $envExamplePath = '{{release_path}}/.env.example';
    $envPath = '{{deploy_path}}/shared/.env';

    // Periksa keberadaan file .env.example di server
    if ('exists' === run("[ -f $envExamplePath ] && echo 'exists' || echo 'not exists'")) {
        // Ambil konten dari .env.example
        $envExampleContent = run("cat $envExamplePath");

        // Loop melalui setiap baris .env.example
        $lines = explode("\n", $envExampleContent);
        foreach ($lines as $line) {
            // Cek jika baris bukan komentar dan tidak kosong
            if (! empty($line) && 0 !== strpos($line, '#')) {
                // Ambil nama variabel dari baris
                $varName = explode('=', $line)[0];

                // Cek jika variabel belum ada di .env
                $envContent = run("cat $envPath");
                if (false === strpos($envContent, "$varName=")) {
                    // Jika variabel belum ada, tambahkan ke dalam .env
                    run("echo '$line' >> $envPath");
                    writeln("Added new property: $line");
                }
            }
        }
    } else {
        writeln('.env.example not found, skipping sync.');
    }
});

task('npm:build', function () {
    writeln('>>> Building Assets with Node 20...');
    run('export NVM_DIR="$HOME/.nvm" && [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh" && nvm use 20 && cd {{release_path}} && npm install && npm run build');
});

task('php-fpm:restart', function () {
    $phpVersion = '8.3'; // Langsung set ke 8.3
    $phpFpmService = "php{$phpVersion}-fpm";

    writeln(">>> Restarting PHP-FPM service: {$phpFpmService}");
    run("sudo systemctl restart {$phpFpmService}");
});

task('deploy:generate-cron-config', function () {
    // config in bash

    //run('echo "* * * * * cd {{deploy_path}}/current && php artisan schedule:run >> /dev/null 2>&1" | sudo tee -a /etc/crontab');
    run('echo "* * * * * www-data cd {{deploy_path}}/current && {{bin/php}} artisan schedule:run >> /dev/null 2>&1" | sudo tee -a /tmp/cron.generate');
    // pindahkan ke /etc/crod.d/esign
    run('sudo mv /tmp/cron.generate /etc/cron.d/esign');
    run('sudo chown root:root /etc/cron.d/esign');
    run('sudo chmod 644 /etc/cron.d/esign');
    //run('sudo crontab -u {{remote_user}} /tmp/cron.generate');
});

task('artisan:tenant-migrate', function () {
    run('cd {{release_path}} && {{bin/php}} artisan tenants:migrate --force');
});

desc('Restart Supervisor queue workers');
task('supervisor:restart', function () {
    run('sudo supervisorctl reread');
    run('sudo supervisorctl update');
    run('sudo supervisorctl restart esign-worker:*');
});
// Hooks
after('deploy:vendors', 'npm:build');
before('artisan:migrate', 'deploy:env-sync');
after('artisan:migrate', 'artisan:tenant-migrate');
after('deploy:cleanup', 'php-fpm:restart');
after('deploy:failed', 'deploy:unlock');
after('deploy:cleanup', 'deploy:generate-cron-config');
after('deploy:success', 'supervisor:restart');

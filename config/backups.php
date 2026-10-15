<?php

return [
    'password' => env('BACKUP_ARCHIVE_PASSWORD', ''),
    'mysqldump' => env('BACKUP_MYSQLDUMP', '/usr/bin/mysqldump'),
    'rclone' => env('BACKUP_RCLONE', '/usr/bin/rclone'),
    'rclone_config' => env('BACKUP_RCLONE_CONFIG', '/etc/sanayirandevu/rclone.conf'),
    'remote' => env('BACKUP_DRIVE_REMOTE', 'sanayi-drive:SanayiRandevuYedek'),
    'path' => storage_path('app/private/backups'),
    'uploads' => [storage_path('upload'), storage_path('app/public'), storage_path('app/private/service-photos')],
];

<?php
return [
    'enabled'=>(bool)env('MOBILE_PUSH_ENABLED',false),
    'project_id'=>env('FIREBASE_PROJECT_ID','sanayirandevucom'),
    // Absolute server-local path, outside the repository/public directory.
    'credentials_file'=>env('FIREBASE_CREDENTIALS_FILE'),
];

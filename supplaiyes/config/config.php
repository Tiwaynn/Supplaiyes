<?php
// Konfigurasi SUPPLAIYES. Sesuaikan dengan XAMPP kamu.
return [
    'db_host'  => '127.0.0.1',
    'db_name'  => 'supplaiyes',
    'db_user'  => 'root',
    'db_pass'  => '',

    // Folder project di htdocs, tanpa slash di akhir. Kalau folder bernama "supplaiyes":
    'base_url' => '/supplaiyes',

    'timezone'                 => 'Asia/Jakarta',
    'db_timezone'              => '+07:00',
    'reservation_hold_minutes' => 120,   // batas waktu pengambilan setelah reservasi
    'max_qty'                  => 5,     // maksimal porsi per reservasi
    'priority_hours'           => 3,     // makanan dengan sisa waktu <= ini diberi badge "Prioritas Distribusi"
    'poll_interval_ms'         => 5000,  // interval AJAX polling
];

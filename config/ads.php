<?php

return [
    // 728x90 iframe banner network. Empty key disables the slots.
    'network' => [
        'key' => env('ADS_NETWORK_KEY', 'f6af092191675e839e5e27f212bb9fc8'),
        'src' => env('ADS_NETWORK_SRC', 'https://bauval.org/22/%s'),
        'width' => 728,
        'height' => 90,
        'slots' => (int) env('ADS_NETWORK_SLOTS', 4),
    ],
];

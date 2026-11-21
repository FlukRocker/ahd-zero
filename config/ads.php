<?php

return [
    // 728x90 iframe banner network. Empty key disables the slots.
    'network' => [
        'key' => env('ADS_NETWORK_KEY', 'f6af092191675e839e5e27f212bb9fc8'),
        'src' => env('ADS_NETWORK_SRC', 'https://bauval.org/22/%s'),
        'width' => 728,
        'height' => 90,
        'slots' => (int) env('ADS_NETWORK_SLOTS', 1),
    ],

    // Fixed-size bauval units (name => key, size). ADS_UNITS=0 hides them all; an
    // empty key hides one. Each renders in its own srcdoc iframe, like the network banner.
    'units_enabled' => (bool) env('ADS_UNITS', true),
    'units' => [
        'b468x60' => ['key' => env('ADS_UNIT_468X60', 'a494aa020bb35c259505733d239fc947'), 'w' => 468, 'h' => 60],
        'b300x250' => ['key' => env('ADS_UNIT_300X250', 'b27efac79307ce9792876f90ca8c5671'), 'w' => 300, 'h' => 250],
        'b160x600' => ['key' => env('ADS_UNIT_160X600', '1d831b51a62666c84af3ad63d0e4c414'), 'w' => 160, 'h' => 600],
        'b160x300' => ['key' => env('ADS_UNIT_160X300', '304b8a073f1f1b759f06c18aa636b3d4'), 'w' => 160, 'h' => 300],
        'b320x50' => ['key' => env('ADS_UNIT_320X50', 'bba56c449cea79d568a35635be965663'), 'w' => 320, 'h' => 50],
    ],
];

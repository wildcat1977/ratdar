<?php

return [
    'nearby_radius_km' => (float) env('RADAR_NEARBY_RADIUS_KM', 1),
    'default_lat' => (float) env('RADAR_DEFAULT_LAT', 25.0330),
    'default_lng' => (float) env('RADAR_DEFAULT_LNG', 121.5654),
    'default_zoom' => (int) env('RADAR_DEFAULT_ZOOM', 13),

    // AI 通過後直接核准，不需人工再審
    'auto_approve' => (bool) env('RADAR_AUTO_APPROVE', false),
];

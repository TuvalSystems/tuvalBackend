<?php

return [
    // Disabled and without defaults in production. Enable only after verifying the QA destination.
    'pilot' => [
        'enabled' => (bool) env('QUOTATION_ITEMS_PILOT_ENABLED', false),
        'verified_app_url' => env('QUOTATION_ITEMS_PILOT_VERIFIED_APP_URL', ''),
        'owner_user_id' => (int) env('QUOTATION_ITEMS_PILOT_OWNER_USER_ID', 0),
        'actor_user_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('QUOTATION_ITEMS_PILOT_ACTOR_USER_IDS', ''))), fn ($id) => $id > 0)),
        'default_pv_description' => env('QUOTATION_ITEMS_PILOT_DEFAULT_PV_DESCRIPTION', ''),
        'default_client_ruc' => env('QUOTATION_ITEMS_PILOT_DEFAULT_CLIENT_RUC', ''),
    ],
];

<?php

// Rules-based fraud checks (PRD §15). Detective, not preventive — a flag
// never blocks the purchase itself, it only surfaces for staff review in
// Admin (see FraudCheckService, FraudFlag).
return [
    'velocity' => [
        // Flags a user who initiates more than `max_count` transactions
        // within `window_minutes` — a burst that's a lot more likely to be
        // a compromised account/script than someone paying bills by hand.
        'window_minutes' => (int) env('FRAUD_VELOCITY_WINDOW_MINUTES', 10),
        'max_count' => (int) env('FRAUD_VELOCITY_MAX_COUNT', 5),
    ],
    'unusual_amount' => [
        // A purchase at `multiplier`x this user's own historical average
        // (over their last `sample_size` successful purchases) gets
        // flagged — but only once they have at least `sample_size` of
        // history to compare against; a brand-new user's first few
        // purchases are instead checked against a flat ceiling.
        'multiplier' => (float) env('FRAUD_UNUSUAL_AMOUNT_MULTIPLIER', 5),
        'sample_size' => (int) env('FRAUD_UNUSUAL_AMOUNT_SAMPLE_SIZE', 3),
        'new_user_ceiling' => (float) env('FRAUD_NEW_USER_CEILING', 100000),
    ],
];

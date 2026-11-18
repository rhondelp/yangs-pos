<?php

return [
    // Orders placed before this Manila time belong to the previous business day.
    'business_day_cutoff' => env('POS_BUSINESS_DAY_CUTOFF', '06:00'),
];

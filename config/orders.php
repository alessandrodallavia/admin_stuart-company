<?php

return [
    'minimum_amount_inc_vat' => 150.00,
    // Sales sheets and checkout snapshots store amounts excluding 22% VAT.
    'minimum_amount_ex_vat' => round(150.00 / 1.22, 2),
];

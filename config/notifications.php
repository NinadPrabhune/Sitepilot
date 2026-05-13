<?php

return [
    'cooldown' => [
        'po.created' => 0,
        'po.updated' => 15,
        'po.status_changed' => 0,
        'grn.created' => 0,
        'grn.updated' => 10,
        'grn.status_changed' => 0,
        'indent.created' => 0,
        'indent.updated' => 15,
        'indent.status_changed' => 0,
        'payment_request.created' => 0,
        'payment_request.updated' => 10,
        'payment_request.status_changed' => 0,
        'payment_request.approved' => 0,
        'payment_request.rejected' => 0,
        'invoice.created' => 0,
        'invoice.updated' => 15,
        'invoice.status_changed' => 0,
    ],
    'low_priority_events' => [
        'po.updated',
        'grn.updated',
        'indent.updated',
        'payment_request.updated',
        'invoice.updated',
    ],
];

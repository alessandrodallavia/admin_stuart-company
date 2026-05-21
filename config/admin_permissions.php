<?php

return [
    'roles' => [
        'owner' => 'Amministratore',
        'operator' => 'Operatore',
        'custom' => 'Personalizzato',
    ],

    'permissions' => [
        'whatsapp.view' => [
            'label' => 'Vedere WhatsApp',
            'group' => 'WhatsApp',
        ],
        'whatsapp.manage' => [
            'label' => 'Gestire chat, messaggi, modalità e follow-up',
            'group' => 'WhatsApp',
        ],
        'leads.view' => [
            'label' => 'Vedere lead',
            'group' => 'Leads',
        ],
        'leads.manage' => [
            'label' => 'Modificare lead, preventivi e link Stripe',
            'group' => 'Leads',
        ],
        'admin_users.manage' => [
            'label' => 'Gestire utenti admin e permessi',
            'group' => 'Impostazioni',
        ],
    ],

    'role_permissions' => [
        'owner' => ['*'],
        'operator' => [
            'whatsapp.view',
            'whatsapp.manage',
            'leads.view',
            'leads.manage',
        ],
        'custom' => [],
    ],
];

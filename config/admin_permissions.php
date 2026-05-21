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
        'documents.view' => [
            'label' => 'Vedere documenti, fatture, ordini e pagamenti',
            'group' => 'Documenti',
        ],
        'documents.manage' => [
            'label' => 'Creare e modificare documenti e pagamenti',
            'group' => 'Documenti',
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

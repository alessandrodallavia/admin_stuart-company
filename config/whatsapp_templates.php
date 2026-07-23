<?php

return [
    'default_language' => env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it'),

    'templates' => [
        'followup1_risposta_automatica' => [
            'label' => 'Follow-up 1 risposta automatica',
            'name' => 'lead_senza_risposta',
            'language' => env('WHATSAPP_TEMPLATE_LEAD_SENZA_RISPOSTA_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => "Buongiorno!\nLe scrivo solo per sapere se è ancora interessato al suo progetto.\nMi bastano una breve descrizione dell'utilizzo, la quantità e la personalizzazione desiderata per poterle indicare subito la soluzione più adatta e il relativo costo.\nRimango a disposizione",
            'parameters' => [],
        ],
        'followup2_risposta_automatica' => [
            'label' => 'Follow-up 2 no risposta dopo il prezzo',
            'name' => 'followup2_no_risposta_dopo_prezzo',
            'language' => env('WHATSAPP_TEMPLATE_FOLLOWUP2_RISPOSTA_AUTOMATICA_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => "Buongiorno!\nVolevo sapere se ha avuto modo di valutare la proposta economica che le ho inviato.\nSe desidera possiamo confrontarci anche su soluzioni diverse per trovare quella più adatta alle sue esigenze.\nRimango a disposizione",
            'parameters' => [],
        ],
        'followup3_dopo_anteprima' => [
            'label' => 'Follow-up 3 dopo anteprima',
            'name' => 'followup3_dopo_anteprima',
            'language' => env('WHATSAPP_TEMPLATE_FOLLOWUP3_DOPO_ANTEPRIMA_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => "Buongiorno!\nLe scrivo per sapere se ha avuto modo di visionare l'anteprima grafica e la proposta che le ho inviato.\nSe desidera apportare modifiche oppure ha qualsiasi dubbio, sarò felice di aiutarla.\nRimango a disposizione",
            'parameters' => [],
        ],
        'followup4_dopo_link' => [
            'label' => 'Follow-up 4 dopo link',
            'name' => 'followup4_dopo_link',
            'language' => env('WHATSAPP_TEMPLATE_FOLLOWUP4_DOPO_LINK_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => "Buongiorno!\nLe scrivo perché il suo progetto è pronto per essere confermato.\nSe desidera procedere, il link di pagamento che le ho inviato è ancora valido.\nPer qualsiasi domanda o chiarimento resto a sua disposizione.",
            'parameters' => [],
        ],
        'recensione' => [
            'label' => 'Recensione',
            'name' => 'recensione',
            'language' => env('WHATSAPP_TEMPLATE_RECENSIONE_LANGUAGE', env('WHATSAPP_TEMPLATE_DEFAULT_LANGUAGE', 'it')),
            'body' => "Buongiorno!\n\nSpero che i prodotti siano arrivati correttamente e che siano di suo gradimento.\n\nSe ha qualche minuto, mi farebbe molto piacere ricevere una sua recensione. Per noi è un aiuto prezioso per far conoscere Stuart a nuovi clienti.\n\nPuò lasciare la sua recensione qui:\nhttps://g.page/r/CRZHVSzVcctEEAE/review\n\nLa ringrazio davvero per la fiducia e spero di poter collaborare nuovamente con lei.",
            'parameters' => [],
        ],
    ],
];
 
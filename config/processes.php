<?php

return [
    'base_url' => env('ENDORSEMENT_PROCESSING_BASE_URL', 'https://localhost:64353'),
    'automation_processes' => [
        'endorsement_processing' => [
            'endpoints' => [
                'automate_endorsements' => '/api/PortalEndorsementsProcessing/automate-endorsements'
            ]
        ],
        'claims_processing' => [
            'endpoints' => [
                'automate_claims' => '/api/PortalEndorsementsProcessing/automate-endorsements'
            ]
        ],
        'debits_processing' => [
            'endpoints' => [
                'automate_policies' => '/api/GeneralLedger/post-debits-generated-from-portal'
            ]
        ],
        'recalculate_scheme_balance' => [
            'endpoints' => [
                'recalculate_scheme_balance' => '/api/GeneralLedger/recalculate-scheme-balance'
            ]
        ]
    ]
];
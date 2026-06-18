<?php

return [
    'pricing_template' => [
        'templates_path' => storage_path('app/templates'),

        'columns' => [
            'vehicle_name' => [
                'required' => true,
                'translation_key' => 'imports.vehicle_name',
                'aliases' => [
                    'es' => ['Vehículo', 'Clase vehículo', 'vehicle_class'],
                    'en' => ['Vehicle', 'Vehicle class', 'vehicle_class'],
                    'pt' => ['Veículo', 'Classe do veículo', 'vehicle_class'],
                    'fr' => ['Véhicule', 'Classe véhicule', 'vehicle_class'],
                ],
            ],
            'category' => [
                'required' => true,
                'translation_key' => 'imports.category',
                'aliases' => [
                    'es' => ['Categoría', 'Código ACRISS', 'ACRISS', 'acriss_code'],
                    'en' => ['Category', 'ACRISS code', 'ACRISS', 'acriss_code'],
                    'pt' => ['Categoria', 'Código ACRISS', 'ACRISS', 'acriss_code'],
                    'fr' => ['Catégorie', 'Code ACRISS', 'ACRISS', 'acriss_code'],
                ],
            ],
            'price' => [
                'required' => true,
                'translation_key' => 'imports.price',
                'aliases' => [
                    'es' => ['Precio', 'base_price'],
                    'en' => ['Price', 'base_price'],
                    'pt' => ['Preço', 'base_price'],
                    'fr' => ['Prix', 'base_price'],
                ],
            ],
            'currency' => [
                'required' => true,
                'translation_key' => 'imports.currency',
                'aliases' => [
                    'es' => ['Moneda'],
                    'en' => ['Currency'],
                    'pt' => ['Moeda'],
                    'fr' => ['Devise'],
                ],
            ],
            'valid_from' => [
                'required' => true,
                'translation_key' => 'imports.valid_from',
                'aliases' => [
                    'es' => ['Vigencia desde', 'Válido desde'],
                    'en' => ['Valid from'],
                    'pt' => ['Válido de', 'Vigência desde'],
                    'fr' => ['Valide à partir du', 'Valide du'],
                ],
            ],
            'valid_until' => [
                'required' => true,
                'translation_key' => 'imports.valid_until',
                'aliases' => [
                    'es' => ['Vigencia hasta', 'Válido hasta', 'valid_to'],
                    'en' => ['Valid until', 'Valid to', 'valid_to'],
                    'pt' => ['Válido até', 'Vigência até', 'valid_to'],
                    'fr' => ['Valide jusqu’au', 'Valide au', 'valid_to'],
                ],
            ],
            'promotion' => [
                'required' => false,
                'translation_key' => 'imports.promotion',
                'aliases' => [
                    'es' => ['Promoción', 'rate_plan_code'],
                    'en' => ['Promotion', 'rate_plan_code'],
                    'pt' => ['Promoção', 'rate_plan_code'],
                    'fr' => ['Promotion', 'rate_plan_code'],
                ],
            ],
            'office_code' => [
                'required' => false,
                'translation_key' => 'imports.office_code',
                'aliases' => [
                    'es' => ['Código oficina', 'Oficina'],
                    'en' => ['Office code', 'Office'],
                    'pt' => ['Código da agência', 'Agência'],
                    'fr' => ['Code agence', 'Agence'],
                ],
            ],
        ],
    ],
];

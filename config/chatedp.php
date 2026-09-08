<?php

return [
    'cost_guard' => [
        // The maximum allowed score before a query is blocked
        'max_cost' => 1000,
        
        // Base penalties for grouping by different cardinalities
        'high_cardinality_penalty' => 800,
        'medium_cardinality_penalty' => 300,
        'low_cardinality_penalty' => 100,
        
        // Multiplier when grouping by multiple dimensions
        'dimension_multiplier' => 1.5,
        
        // Reductions/discounts for restrictive boundaries
        'safe_top_n_reduction' => 0.8, // 80% discount for safe limit (e.g. limit 50)
        'date_filter_reduction' => 0.2, // 20% discount for having a date filter
    ],
    
    // Semantic hinting for fallback classification
    'cardinality_hints' => [
        'high' => [
            'customer', 'pelanggan', 'customer_id',
            'invoice', 'faktur', 'nota',
            'transaction', 'transaksi',
            'product', 'produk', 'item', 'barang',
            'inventory', 'serial_number'
        ],
        'medium' => [
            'category', 'kategori',
            'region', 'area', 'wilayah',
            'salesperson', 'sales', 'employee', 'karyawan',
            'brand', 'merek'
        ],
        'low' => [
            'company', 'perusahaan',
            'branch', 'cabang',
            'status',
            'type', 'tipe', 'jenis',
            'year', 'tahun',
            'month', 'bulan',
            'day', 'hari'
        ]
    ],
    
    'query_recognition' => [
        'enabled' => true,
        // Confidence scale 0.0 - 1.0. Higher means we trust the deterministic match and bypass OpenRouter.
        'min_fast_path_confidence' => 0.95,
        'stopwords' => [
            'berapa', 'tampilkan', 'untuk', 'tolong', 'kasih', 'lihat', 'minta', 'data', 'informasi', 'dari', 'di', 'ke', 'pada'
        ],
        'ambiguous_terms' => [
            'tertinggi', 'terendah', 'top', 'bottom', 'terbaik', 'terburuk', 'paling', 'maksimal', 'minimal'
        ],
        'date_keywords' => [
            'bulan ini' => 'this_month',
            'hari ini' => 'today',
            'tahun ini' => 'this_year',
            'kemarin' => 'yesterday',
            'bulan lalu' => 'last_month',
            'tahun lalu' => 'last_year',
            'minggu ini' => 'this_week',
            'minggu lalu' => 'last_week'
        ],
        'aggregation_keywords' => [
            'total' => 'sum',
            'jumlah' => 'sum',
            'rata-rata' => 'avg',
            'rata rata' => 'avg',
            'maksimal' => 'max',
            'tertinggi' => 'max',
            'minimal' => 'min',
            'terendah' => 'min',
            'hitung' => 'count',
            'banyak' => 'count',
            'banyaknya' => 'count'
        ]
    ]
];

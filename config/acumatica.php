<?php

/*
|--------------------------------------------------------------------------
| Load dynamic entities from JSON file (managed via UI CRUD)
|--------------------------------------------------------------------------
*/
$entitiesFile = storage_path('app/acumatica_entities.json');
$dynamicEntities = [];
if (file_exists($entitiesFile)) {
    $dynamicEntities = json_decode(file_get_contents($entitiesFile), true) ?? [];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Acumatica ERP Connection Settings
    |--------------------------------------------------------------------------
    */

    'url'        => env('ACUMATICA_URL', 'https://erp.panganlestari.com/PLUpgradeTest'),
    'tenant'     => env('ACUMATICA_TENANT', 'PL - Upgrade'),
    'username'   => env('ACUMATICA_USERNAME', 'admin'),
    'password'   => env('ACUMATICA_PASSWORD', ''),
    'verify_ssl' => env('ACUMATICA_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | OData Settings
    |--------------------------------------------------------------------------
    */

    'odata_path' => env('ACUMATICA_ODATA_PATH', 'odatav4'),
    'odata_auth' => env('ACUMATICA_ODATA_AUTH', 'basic'), // basic or session
    'odata_include_tenant' => env('ACUMATICA_ODATA_INCLUDE_TENANT', false),
    'page_size'  => 500,
    'timeout'    => 300,

    /*
    |--------------------------------------------------------------------------
    | Entity Definitions (loaded from storage/app/acumatica_entities.json)
    |--------------------------------------------------------------------------
    | Managed via Settings → Entity Configuration UI.
    | Each entity maps an Acumatica OData v4 DAC-based entity name to a local
    | SQL Server table. Tables are created automatically on first sync.
    |
    | odata_entity : Acumatica DAC entity name (PX_Objects_XX_EntityName)
    | table        : Target SQL Server table name
    | type         : 'master' or 'transaction'
    | icon         : Material Symbols icon name for the UI
    | description  : Human-readable label for the dashboard
    */

    'entities' => $dynamicEntities,

    /*
    |--------------------------------------------------------------------------
    | Default Entity Definitions (seed data for first-time setup)
    |--------------------------------------------------------------------------
    */

    'default_entities' => [

        // ── Master Data ──────────────────────────────────────────────────

        'Customer' => [
            'odata_entity' => 'PX_Objects_AR_Customer',
            'table'        => 'acm_customers',
            'type'         => 'master',
            'icon'         => 'person',
            'description'  => 'Master Customer',
        ],

        'CustomerLocation' => [
            'odata_entity' => 'PX_Objects_CR_Location',
            'table'        => 'acm_customer_locations',
            'type'         => 'master',
            'icon'         => 'location_on',
            'description'  => 'Master Customer Location',
        ],

        'InventoryItem' => [
            'odata_entity' => 'PX_Objects_IN_InventoryItem',
            'table'        => 'acm_inventory_items',
            'type'         => 'master',
            'icon'         => 'inventory_2',
            'description'  => 'Master Inventory Item',
        ],

        'Warehouse' => [
            'odata_entity' => 'PX_Objects_IN_INSite',
            'table'        => 'acm_warehouses',
            'type'         => 'master',
            'icon'         => 'warehouse',
            'description'  => 'Master Warehouse',
        ],

        'Branch' => [
            'odata_entity' => 'PX_Objects_GL_Branch',
            'table'        => 'acm_branches',
            'type'         => 'master',
            'icon'         => 'account_tree',
            'description'  => 'Master Branch',
        ],

        'Vendor' => [
            'odata_entity' => 'PX_Objects_AP_Vendor',
            'table'        => 'acm_vendors',
            'type'         => 'master',
            'icon'         => 'local_shipping',
            'description'  => 'Master Vendor',
        ],

        'VendorLocation' => [
            'odata_entity' => 'PX_Objects_CR_Location',
            'table'        => 'acm_vendor_locations',
            'type'         => 'master',
            'icon'         => 'pin_drop',
            'description'  => 'Master Vendor Location',
        ],

        'Employee' => [
            'odata_entity' => 'PX_Objects_EP_EPEmployee',
            'table'        => 'acm_employees',
            'type'         => 'master',
            'icon'         => 'badge',
            'description'  => 'Master Employee',
        ],

        'SalesPerson' => [
            'odata_entity' => 'PX_Objects_AR_SalesPerson',
            'table'        => 'acm_sales_persons',
            'type'         => 'master',
            'icon'         => 'support_agent',
            'description'  => 'Master Sales Person',
        ],

        'CashAccount' => [
            'odata_entity' => 'PX_Objects_CA_CashAccount',
            'table'        => 'acm_cash_accounts',
            'type'         => 'master',
            'icon'         => 'account_balance',
            'description'  => 'Master Cash Account',
        ],

        // ── Transaction Data ─────────────────────────────────────────────

        'SalesOrder' => [
            'odata_entity' => 'PX_Objects_SO_SOOrder',
            'table'        => 'acm_sales_orders',
            'type'         => 'transaction',
            'icon'         => 'shopping_cart',
            'description'  => 'Sales Order',
        ],

        'SalesOrderDetail' => [
            'odata_entity' => 'PX_Objects_SO_SOLine',
            'table'        => 'acm_sales_order_details',
            'type'         => 'transaction',
            'icon'         => 'list_alt',
            'description'  => 'Sales Order Detail',
        ],

        'SalesOrderSplitLine' => [
            'odata_entity' => 'PX_Objects_SO_SOLineSplit',
            'table'        => 'acm_sales_order_splits',
            'type'         => 'transaction',
            'icon'         => 'call_split',
            'description'  => 'Sales Order Split Line',
        ],

        'Shipment' => [
            'odata_entity' => 'PX_Objects_SO_SOShipment',
            'table'        => 'acm_shipments',
            'type'         => 'transaction',
            'icon'         => 'local_shipping',
            'description'  => 'Shipment',
        ],

        'ShipmentDetail' => [
            'odata_entity' => 'PX_Objects_SO_SOShipLine',
            'table'        => 'acm_shipment_details',
            'type'         => 'transaction',
            'icon'         => 'package_2',
            'description'  => 'Shipment Detail',
        ],

        'ShipmentSplitLine' => [
            'odata_entity' => 'PX_Objects_SO_SOShipLineSplit',
            'table'        => 'acm_shipment_splits',
            'type'         => 'transaction',
            'icon'         => 'call_split',
            'description'  => 'Shipment Split Line',
        ],

        'Invoice' => [
            'odata_entity' => 'PX_Objects_AR_ARInvoice',
            'table'        => 'acm_invoices',
            'type'         => 'transaction',
            'icon'         => 'receipt',
            'description'  => 'AR Invoice',
        ],

        'SOInvoice' => [
            'odata_entity' => 'PX_Objects_SO_SOInvoice',
            'table'        => 'acm_so_invoices',
            'type'         => 'transaction',
            'icon'         => 'request_quote',
            'description'  => 'SO Invoice',
        ],

        'SOOrderShipment' => [
            'odata_entity' => 'PX_Objects_SO_SOOrderShipment',
            'table'        => 'acm_so_order_shipments',
            'type'         => 'transaction',
            'icon'         => 'move_to_inbox',
            'description'  => 'SO Order Shipment',
        ],
    ],
];

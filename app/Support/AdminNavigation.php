<?php

namespace App\Support;

use App\Auth\Permission;

/**
 * The Admin portal navigation (target structure). Each item names the permission that gates both
 * the link and its route; a group renders only if the viewer can see at least one of its items.
 */
final class AdminNavigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array{key: string, label: string, route: string, permission: string, active: string[]}>}>
     */
    public static function groups(): array
    {
        return [
            ['label' => 'Overview', 'items' => [
                self::item('dashboard', 'Dashboard', 'admin.dashboard', Permission::DASHBOARD_VIEW, ['admin/dashboard']),
                self::item('notifications', 'Notifications', 'admin.notifications', Permission::ACCOUNT_VIEW, ['admin/notifications']),
            ]],
            ['label' => 'Marketplace', 'items' => [
                self::item('orders', 'Orders', 'admin.orders', Permission::ORDERS_VIEW, ['admin/orders*']),
                self::item('products', 'Products', 'admin.products', Permission::PRODUCTS_VIEW, ['admin/products*']),
                self::item('compliance', 'Seller Compliance', 'admin.compliance', Permission::SELLER_COMPLIANCE_VIEW, ['admin/compliance*']),
            ]],
            ['label' => 'Users', 'items' => [
                self::item('registrations', 'Registrations', 'admin.registrations', Permission::REGISTRATIONS_VIEW, ['admin/registrations*']),
                self::item('users', 'User Accounts', 'admin.users', Permission::USERS_VIEW, ['admin/users*']),
            ]],
            ['label' => 'Operations', 'items' => [
                self::item('logistics', 'Logistics', 'admin.logistics', Permission::LOGISTICS_VIEW, ['admin/logistics']),
                self::item('sorting', 'Sorting Center', 'admin.logistics.sorting', Permission::LOGISTICS_VIEW, ['admin/logistics/sorting-center*']),
                self::item('riders', 'Rider Assignment', 'admin.logistics.riders', Permission::LOGISTICS_VIEW, ['admin/logistics/rider-assignment*']),
            ]],
            ['label' => 'Customer Care', 'items' => [
                self::item('complaints', 'Complaints & Disputes', 'admin.complaints', Permission::COMPLAINTS_VIEW, ['admin/complaints*', 'admin/disputes*']),
                self::item('returns', 'Returns', 'admin.returns', Permission::RETURNS_VIEW, ['admin/returns*']),
                self::item('refunds', 'Refunds', 'admin.refunds', Permission::REFUNDS_VIEW, ['admin/refunds*']),
            ]],
            ['label' => 'Finance', 'items' => [
                self::item('commission', 'Commission', 'admin.commission', Permission::COMMISSION_VIEW, ['admin/commission*']),
                self::item('financial-reports', 'Financial Reports', 'admin.reports', Permission::REPORTS_VIEW, ['admin/reports*']),
            ]],
            ['label' => 'Analytics', 'items' => [
                self::item('analytics', 'Reports', 'admin.analytics', Permission::REPORTS_VIEW, ['admin/analytics*']),
            ]],
            ['label' => 'System', 'items' => [
                self::item('settings', 'Platform Settings', 'admin.settings.index', Permission::SETTINGS_VIEW, ['admin/settings*']),
                self::item('audit-logs', 'Audit Logs', 'admin.audit', Permission::AUDIT_VIEW, ['admin/audit-logs*']),
                self::item('chat', 'Chat / Messaging', 'admin.chat', Permission::MESSAGING_VIEW, ['admin/chat*']),
            ]],
            ['label' => 'Account', 'items' => [
                self::item('account', 'My Account', 'admin.account', Permission::ACCOUNT_VIEW, ['admin/account*']),
            ]],
        ];
    }

    /** SVG path markup (24px viewBox, currentColor) for an item key. */
    public static function icon(string $key): string
    {
        return self::ICONS[$key] ?? '';
    }

    private static function item(string $key, string $label, string $route, string $permission, array $active): array
    {
        return compact('key', 'label', 'route', 'permission', 'active');
    }

    private const ICONS = [
        'dashboard' => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'notifications' => '<path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4a1.5 1.5 0 0 0-3 0v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
        'orders' => '<path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1s-2.4.84-2.82 2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>',
        'products' => '<path d="M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.06 15.94 0 13.36 1.36 12.48.52 11.3 0 10 0 7.42 0 5.36 2.06 5.36 4.64c0 .48.11.92.18 1.36H3v14h18V6h-1zM12 2c1.28 0 2.28 1 2.28 2.28 0 .48-.11.92-.28 1.36h-4c-.17-.44-.28-.88-.28-1.36C9.72 3 10.72 2 12 2zM5 8h14v10H5V8z"/>',
        'compliance' => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>',
        'registrations' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/>',
        'users' => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'logistics' => '<path d="M20 8h-3V4H3a2 2 0 0 0-2 2v11h2a3 3 0 0 0 6 0h6a3 3 0 0 0 6 0h2v-5l-3-4zM6 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm13.5-9 1.96 2.5H17V9.5h2.5zM18 18.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>',
        'sorting' => '<path d="M20 2H4a2 2 0 0 0-2 2v3.01c0 .72.43 1.34 1 1.69V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.7c.57-.35 1-.97 1-1.69V4a2 2 0 0 0-2-2zm-5 12H9v-2h6v2zm5-7H4V4h16v3z"/>',
        'riders' => '<path d="M13.5 5.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM9.8 8.9 7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3A7.3 7.3 0 0 0 19 13v-2a5.2 5.2 0 0 1-4.5-2.5l-1-1.6a2 2 0 0 0-1.7-1c-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>',
        'complaints' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>',
        'returns' => '<path d="M12 5V2L7 7l5 5V8c3.31 0 6 2.69 6 6a6 6 0 0 1-10.24 4.24l-1.42 1.42A8 8 0 0 0 20 14c0-4.42-3.58-8-8-8z"/>',
        'refunds' => '<path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21a3.99 3.99 0 0 0-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4zM3 12l3-3v2h3v2H6v2z"/>',
        'commission' => '<path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>',
        'financial-reports' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM8 18v-5h2v5H8zm3 0v-7h2v7h-2zm3 0v-3h2v3h-2z"/>',
        'analytics' => '<path d="M3.5 18.49 9.5 12.48l4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>',
        'settings' => '<path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96a7.01 7.01 0 0 0-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.47.47 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.37 1.04.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.57 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.47.47 0 0 0-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>',
        'audit-logs' => '<path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.95 8.95 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
        'chat' => '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>',
        'account' => '<path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>',
    ];
}

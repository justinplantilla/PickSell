<?php

namespace App\Auth;

/**
 * The admin permission vocabulary. Every admin operation is authorized through one of these
 * (as a Gate of the same name, or inside a Policy), never through the user's role directly.
 * isSuperAdmin() only tells which role a user has; config/permissions.php maps roles to
 * permissions, so future admin roles need no business-logic changes.
 *
 * Permissions marked "reserved" are part of the vocabulary but no admin route uses them yet.
 */
final class Permission
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const REGISTRATIONS_VIEW = 'registrations.view';
    public const REGISTRATIONS_MANAGE = 'registrations.manage';

    public const USERS_VIEW = 'users.view';
    public const USERS_MANAGE = 'users.manage';

    public const PRODUCTS_VIEW = 'products.view';
    public const PRODUCTS_MODERATE = 'products.moderate';

    public const SELLER_COMPLIANCE_VIEW = 'seller-compliance.view';
    public const SELLER_COMPLIANCE_MANAGE = 'seller-compliance.manage';

    public const ORDERS_VIEW = 'orders.view';                       // reserved
    public const ORDERS_MANAGE = 'orders.manage';                   // reserved
    public const ORDERS_OVERRIDE_STATUS = 'orders.override-status'; // reserved

    public const LOGISTICS_VIEW = 'logistics.view';                         // reserved
    public const LOGISTICS_MANAGE = 'logistics.manage';                     // reserved
    public const LOGISTICS_SCAN = 'logistics.scan';                         // reserved
    public const LOGISTICS_ASSIGN_RIDER = 'logistics.assign-rider';         // reserved
    public const LOGISTICS_RESOLVE_EXCEPTION = 'logistics.resolve-exception'; // reserved

    public const COMPLAINTS_VIEW = 'complaints.view';
    public const COMPLAINTS_MANAGE = 'complaints.manage';

    public const RETURNS_VIEW = 'returns.view';
    public const RETURNS_MANAGE = 'returns.manage';

    public const REFUNDS_VIEW = 'refunds.view';       // reserved
    public const REFUNDS_MANAGE = 'refunds.manage';   // reserved
    public const REFUNDS_APPROVE = 'refunds.approve'; // reserved

    public const COMMISSION_VIEW = 'commission.view';
    public const COMMISSION_MANAGE = 'commission.manage';
    public const COMMISSION_OVERRIDE = 'commission.override'; // reserved

    public const REPORTS_VIEW = 'reports.view';
    public const REPORTS_EXPORT = 'reports.export';

    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_MANAGE = 'settings.manage';

    public const MESSAGING_VIEW = 'messaging.view';
    public const MESSAGING_MANAGE = 'messaging.manage';

    public const AUDIT_VIEW = 'audit.view';     // reserved
    public const AUDIT_EXPORT = 'audit.export'; // reserved

    public const ACCOUNT_VIEW = 'account.view';
    public const ACCOUNT_MANAGE = 'account.manage';

    public const ALL = [
        self::DASHBOARD_VIEW,
        self::REGISTRATIONS_VIEW, self::REGISTRATIONS_MANAGE,
        self::USERS_VIEW, self::USERS_MANAGE,
        self::PRODUCTS_VIEW, self::PRODUCTS_MODERATE,
        self::SELLER_COMPLIANCE_VIEW, self::SELLER_COMPLIANCE_MANAGE,
        self::ORDERS_VIEW, self::ORDERS_MANAGE, self::ORDERS_OVERRIDE_STATUS,
        self::LOGISTICS_VIEW, self::LOGISTICS_MANAGE, self::LOGISTICS_SCAN, self::LOGISTICS_ASSIGN_RIDER, self::LOGISTICS_RESOLVE_EXCEPTION,
        self::COMPLAINTS_VIEW, self::COMPLAINTS_MANAGE,
        self::RETURNS_VIEW, self::RETURNS_MANAGE,
        self::REFUNDS_VIEW, self::REFUNDS_MANAGE, self::REFUNDS_APPROVE,
        self::COMMISSION_VIEW, self::COMMISSION_MANAGE, self::COMMISSION_OVERRIDE,
        self::REPORTS_VIEW, self::REPORTS_EXPORT,
        self::SETTINGS_VIEW, self::SETTINGS_MANAGE,
        self::MESSAGING_VIEW, self::MESSAGING_MANAGE,
        self::AUDIT_VIEW, self::AUDIT_EXPORT,
        self::ACCOUNT_VIEW, self::ACCOUNT_MANAGE,
    ];

    /** @return string[] */
    public static function forRole(?string $role): array
    {
        return config("permissions.roles.{$role}", []);
    }

    /** Portal entry: a user may enter the admin portal if their role holds any admin permission. */
    public static function canEnterAdminPortal(?string $role): bool
    {
        return array_intersect(self::forRole($role), self::ALL) !== [];
    }
}

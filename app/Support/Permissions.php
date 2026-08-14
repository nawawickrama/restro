<?php

namespace App\Support;

/**
 * Every permission the application knows about, in one place.
 *
 * Roles are just bundles of these; code and routes check permissions, never
 * role names, so a restaurant can invent new roles without touching PHP.
 */
final class Permissions
{
    public const VIEW_POS = 'view_pos';

    public const CREATE_ORDERS = 'create_orders';

    public const EDIT_ORDERS = 'edit_orders';

    public const CANCEL_ORDERS = 'cancel_orders';

    public const CHECKOUT_ORDERS = 'checkout_orders';

    public const APPLY_DISCOUNTS = 'apply_discounts';

    public const VIEW_ORDERS = 'view_orders';

    public const PRINT_RECEIPTS = 'print_receipts';

    public const MANAGE_MENU = 'manage_menu';

    public const MANAGE_TABLES = 'manage_tables';

    public const MANAGE_USERS = 'manage_users';

    public const MANAGE_SETTINGS = 'manage_settings';

    public const VIEW_REPORTS = 'view_reports';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_POS,
            self::CREATE_ORDERS,
            self::EDIT_ORDERS,
            self::CANCEL_ORDERS,
            self::CHECKOUT_ORDERS,
            self::APPLY_DISCOUNTS,
            self::VIEW_ORDERS,
            self::PRINT_RECEIPTS,
            self::MANAGE_MENU,
            self::MANAGE_TABLES,
            self::MANAGE_USERS,
            self::MANAGE_SETTINGS,
            self::VIEW_REPORTS,
        ];
    }

    /** What a cashier can do: run the POS, take money, print receipts. */
    public static function cashier(): array
    {
        return [
            self::VIEW_POS,
            self::CREATE_ORDERS,
            self::EDIT_ORDERS,
            self::CHECKOUT_ORDERS,
            self::VIEW_ORDERS,
            self::PRINT_RECEIPTS,
        ];
    }
}

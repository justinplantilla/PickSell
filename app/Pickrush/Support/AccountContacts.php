<?php

namespace App\Pickrush\Support;

use Illuminate\Support\Facades\DB;

/**
 * Email addresses and mobile numbers are unique across every PickSell account: marketplace users
 * (buyers, sellers, admins), logistics staff and riders. Mobile numbers are kept as 09XXXXXXXXX,
 * the marketplace's form, so +639XXXXXXXXX counts as the same number.
 */
final class AccountContacts
{
    public static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /** 09XXXXXXXXX for a valid Philippine mobile number in either accepted form; anything else unchanged. */
    public static function normalizeMobile(?string $mobile): string
    {
        $mobile = preg_replace('/\s+/', '', (string) $mobile);

        return preg_match('/^(?:\+63|0)(9\d{9})$/', $mobile, $m) ? '0'.$m[1] : $mobile;
    }

    public static function emailTaken(string $email): bool
    {
        $email = self::normalizeEmail($email);

        return DB::table('users')->whereRaw('lower(email) = ?', [$email])->exists()
            || DB::table('pr_staff')->whereRaw('lower(email) = ?', [$email])->exists()
            || DB::table('pr_riders')->whereRaw('lower(email) = ?', [$email])->exists();
    }

    /** @param  int|null  $exceptStaffId  a logistics account that may keep its own number */
    public static function mobileTaken(string $mobile, ?int $exceptStaffId = null): bool
    {
        $local = self::normalizeMobile($mobile);
        $forms = [$local, '+63'.substr($local, 1)];

        return DB::table('users')->whereIn('contact_no', $forms)->exists()
            || DB::table('pr_staff')->whereIn('mobile_no', $forms)->when($exceptStaffId, fn ($q, $id) => $q->where('id', '!=', $id))->exists()
            || DB::table('pr_riders')->whereIn('mobile_no', $forms)->exists();
    }
}

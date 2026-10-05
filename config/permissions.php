<?php

use App\Auth\Permission;

return [

    /*
    |--------------------------------------------------------------------------
    | Role → Permission Map
    |--------------------------------------------------------------------------
    |
    | PickSell has a single Super Admin (users.role = 'admin') holding every
    | admin permission. Specialised admin roles (finance, operations, support,
    | ...) are intentionally not introduced yet; when they are, add a role here
    | with a subset of Permission constants. No controller or route changes needed.
    |
    */

    'roles' => [
        'admin' => Permission::ALL,
    ],

];

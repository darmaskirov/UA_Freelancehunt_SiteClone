<?php
// config/routes.php
return [
    // Головна
    '/'         => 'public/index.php',

    // Auth
    '/login'    => 'public/login.php',
    '/register' => 'public/register.php',
    '/logout'   => 'public/logout.php',
    '/profile'  => 'public/profile.php', 
    '/cooperate'  => 'public/cooperate.php', 
    '/app-download'  => 'public/app-download.php',
    '/discount'  => 'public/discount.php',

    //
    '/category/live' => 'public/categories/live.php',
    '/category/game' => 'public/categories/game.php',
    '/category/fishing' => 'public/categories/fishing.php',
    '/category/lottery' => 'public/categories/lottery.php',
    '/category/sport' => 'public/categories/sport.php',
    '/category/poker' => 'public/categories/poker.php',
    '/category/esports' => 'public/categories/esports.php',
    


    // Membership
    '/membership/user-info' => 'public/membership/profile.php',
    '/membership/card-holder' => 'public/membership/card-holder.php',
    '/membership/deposit'   => 'public/membership/deposit.php',
    '/membership/transfer'  => 'public/membership/transfers.php',
    '/membership/withdraw'  => 'public/membership/withdraw.php',
    '/membership/history'   => 'public/membership/history.php',
    '/membership/privileges'   => 'public/membership/privileges.php',
    

    // Admin
    '/admin'              => 'app/admin/admin.php',
    '/admin/login'              => 'app/admin/login.php',
    '/admin/users'        => 'app/admin/users.php',
    '/admin/transactions' => 'app/admin/transactions.php',
    '/admin/settings'     => 'app/admin/settings.php',
    '/admin/logout'       => 'app/admin/logout.php',

    // Інше (приклади з Poople — залишай тільки те, що реально існує)
    '/settings'       => 'src/client/ui_account/edit.php',

    // Помилки
    '/404'   => 'error-pages/404.php',
    '/error' => 'error-pages/errors.php',
];

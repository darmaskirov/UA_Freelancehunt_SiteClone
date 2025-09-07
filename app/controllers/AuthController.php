<?php
class AuthController {
    public static function login() {
        // TODO: зчитати email/password з $_POST, перевірити в БД, встановити $_SESSION['user']
        // Поки: фейк-логін
        $_SESSION['user'] = ['id'=>1, 'email'=>$_POST['email'] ?? 'demo@demo', 'role'=>'user'];
        redirect('/profile');
    }
    public static function register() {
        // TODO: валідація + вставка в БД з password_hash(...)
        redirect('/login');
    }
    public static function logout() {
        session_destroy();
        redirect('/login');
    }
}

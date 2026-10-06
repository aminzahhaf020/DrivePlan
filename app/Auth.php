<?php

namespace App;

// Deze class regelt alles rondom het inloggen en uitloggen van gebruikers.
// Ook wordt hier gecontroleerd of een gebruiker is ingelogd
// en of de gebruiker de juiste rol heeft.
class Auth
{
    // Haalt de ingelogde gebruiker op uit de sessie.
    // Als niemand is ingelogd, wordt null teruggegeven.
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    // Logt een gebruiker in.
    // Er wordt eerst een nieuwe sessie-ID gemaakt voor extra beveiliging.
    // Daarna worden de belangrijkste gegevens van de gebruiker opgeslagen in de sessie.
    public static function login(array $u): void
    {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
    }

    // Logt de huidige gebruiker uit.
    // De sessiegegevens worden verwijderd en daarna wordt de sessie beëindigd.
    public static function logout(): void
    {
        $_SESSION = [];

        // Als PHP gebruikmaakt van een sessie-cookie,
        // wordt deze cookie ook verwijderd.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }

        session_destroy();
    }

    // Controleert of er een gebruiker is ingelogd.
    // Als dat niet zo is, wordt de gebruiker doorgestuurd naar de loginpagina.
    public static function requireLogin(): void
    {
        if (!self::user()) {
            header('Location: /?page=login');
            exit;
        }
    }

    // Controleert of de gebruiker de juiste rol heeft.
    // Bijvoorbeeld 'student' of 'instructor'.
    // Bij een verkeerde rol wordt toegang geweigerd met HTTP-status 403.
    public static function requireRole(string $r): void
    {
        self::requireLogin();

        if ((self::user()['role'] ?? '') !== $r) {
            http_response_code(403);
            throw new \RuntimeException(
                'Je hebt geen toegang tot deze actie.'
            );
        }
    }
}
<?php

namespace App\Enums;

/**
 * Rôle global porté par le compte utilisateur (indépendant des départements).
 * Un compte sans rôle système est un simple membre de département.
 */
enum SystemRole: string
{
    case Admin = 'admin';
    case Directeur = 'directeur';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Directeur => 'Directeur exécutif',
        };
    }
}

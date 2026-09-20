<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur au sein d'un département donné.
 * Une même personne peut cumuler des rôles différents dans plusieurs départements.
 */
enum DepartmentRole: string
{
    case Chef = 'chef';
    case Employe = 'employe';
    case Stagiaire = 'stagiaire';
    case Prestataire = 'prestataire';

    public function label(): string
    {
        return match ($this) {
            self::Chef => 'Chef de département',
            self::Employe => 'Employé',
            self::Stagiaire => 'Stagiaire',
            self::Prestataire => 'Prestataire de services',
        };
    }

    /** Rôles pouvant recevoir des tâches. */
    public static function assignables(): array
    {
        return [self::Employe, self::Stagiaire, self::Prestataire, self::Chef];
    }
}

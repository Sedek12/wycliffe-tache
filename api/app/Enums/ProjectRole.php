<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur au sein d'un projet donné (indépendant de son rôle
 * de département). Une même personne peut cumuler des rôles différents
 * sur plusieurs projets.
 */
enum ProjectRole: string
{
    case ChefDepartement = 'chef_departement';
    case ChefService = 'chef_service';
    case CoordonnateurFacilitateur = 'coordonnateur_facilitateur';
    case FacilitateurZone = 'facilitateur_zone';
    case Moniteur = 'moniteur';

    public function label(): string
    {
        return match ($this) {
            self::ChefDepartement => 'Chef de département',
            self::ChefService => 'Chef de service',
            self::CoordonnateurFacilitateur => 'Coordonnateur / Facilitateur de projet',
            self::FacilitateurZone => 'Facilitateur de zone',
            self::Moniteur => 'Moniteur',
        };
    }

    /**
     * Rôles habilités à gérer le projet (cadrage, planification, documents, budget).
     * Aligné sur le cahier des charges : les 5 rôles projet ont les mêmes droits
     * d'édition — seule la suppression du projet reste réservée au chef de département.
     */
    public static function managerial(): array
    {
        return self::cases();
    }
}

<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Brouillon = 'brouillon';
    case Assignee = 'assignee';
    case EnCours = 'en_cours';
    case Livree = 'livree';
    case Validee = 'validee';
    case ARefaire = 'a_refaire';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Assignee => 'Assignée',
            self::EnCours => 'En cours',
            self::Livree => 'Livrée',
            self::Validee => 'Validée',
            self::ARefaire => 'À refaire',
            self::Annulee => 'Annulée',
        };
    }

    /** Statuts considérés comme terminés (n'entrent plus dans le calcul du retard). */
    public function isClosed(): bool
    {
        return $this === self::Validee || $this === self::Annulee;
    }

    /** Transitions autorisées depuis le statut courant. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Brouillon => [self::Assignee, self::Annulee],
            self::Assignee => [self::EnCours, self::Brouillon, self::Annulee],
            self::EnCours => [self::Livree, self::Annulee],
            self::Livree => [self::Validee, self::ARefaire, self::Annulee],
            self::ARefaire => [self::EnCours, self::Annulee],
            self::Annulee => [self::Assignee],
            self::Validee => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }
}

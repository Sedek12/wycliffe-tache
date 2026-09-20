<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Brouillon = 'brouillon';
    case EnCours = 'en_cours';
    case Cloture = 'cloture';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnCours => 'En cours',
            self::Cloture => 'Clôturé',
        };
    }

    /** Transitions autorisées depuis le statut courant. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Brouillon => [self::EnCours],
            self::EnCours => [self::Cloture, self::Brouillon],
            self::Cloture => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }
}

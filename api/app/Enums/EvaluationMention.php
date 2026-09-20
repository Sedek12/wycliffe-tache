<?php

namespace App\Enums;

/**
 * Mention attribuée à partir d'une note sur 20 (système académique béninois).
 */
enum EvaluationMention: string
{
    case Insuffisant = 'insuffisant';
    case Passable = 'passable';
    case AssezBien = 'assez_bien';
    case Bien = 'bien';
    case TresBien = 'tres_bien';
    case Excellent = 'excellent';

    public function label(): string
    {
        return match ($this) {
            self::Insuffisant => 'Insuffisant',
            self::Passable => 'Passable',
            self::AssezBien => 'Assez bien',
            self::Bien => 'Bien',
            self::TresBien => 'Très bien',
            self::Excellent => 'Excellent',
        };
    }

    /** Déduit la mention d'une note sur 20. */
    public static function fromScore(int|float $score): self
    {
        return match (true) {
            $score < 10 => self::Insuffisant,
            $score < 12 => self::Passable,
            $score < 14 => self::AssezBien,
            $score < 16 => self::Bien,
            $score < 18 => self::TresBien,
            default => self::Excellent,
        };
    }
}

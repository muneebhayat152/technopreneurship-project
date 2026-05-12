<?php

namespace App\Services;

/**
 * Lexicon + lightweight NLP for complaint sentiment (no external API).
 * Uses tokenization, domain lexicons (incl. airline), negation windows, and score aggregation.
 */
class ComplaintSentimentAnalyzer
{
    private const NEGATION_TOKENS = [
        'not', 'no', 'never', 'neither', 'nor', 'without', 'hardly', 'barely', 'scarcely', 'nt',
    ];

    private const NEGATIVE_LEXEMES = [
        'awful', 'terrible', 'horrible', 'hate', 'hated', 'frustrated', 'angry', 'furious',
        'disappointed', 'unacceptable', 'rude', 'scam', 'worst', 'worse', 'bad', 'poor', 'poorly',
        'slow', 'slower', 'late', 'delay', 'delayed', 'delays', 'cancelled', 'canceled', 'overbooked',
        'lost', 'damaged', 'missing', 'refused', 'denied', 'complaint', 'nightmare', 'appalled',
        'unhelpful', 'incompetent', 'disgusting', 'ripoff', 'rip', 'stranded', 'chaotic', 'dirty',
        'cramped', 'bumpy', 'sick', 'vomit', 'rudest', 'lied', 'lying', 'cheat', 'cheated', 'fraud',
    ];

    private const POSITIVE_LEXEMES = [
        'excellent', 'great', 'good', 'wonderful', 'amazing', 'love', 'loved', 'pleased', 'happy',
        'satisfied', 'smooth', 'fast', 'faster',         'quick', 'quickly', 'professional', 'helpful',
        'friendly', 'courteous', 'comfortable', 'clean', 'punctual', 'effortless', 'grateful',
        'thanks', 'thank', 'appreciate', 'appreciated', 'impressed', 'outstanding', 'brilliant',
        'perfect', 'seamless', 'recommend', 'recommendation', 'delightful', 'stellar', 'top',
    ];

    /**
     * @return array{sentiment: 'positive'|'neutral'|'negative', sentiment_score: float, category: string}
     */
    public function analyze(string $rawText): array
    {
        $lower = mb_strtolower($rawText, 'UTF-8');
        $tokens = $this->tokenize($lower);

        $negHit = $this->lexiconSet(self::NEGATIVE_LEXEMES);
        $posHit = $this->lexiconSet(self::POSITIVE_LEXEMES);
        $negation = $this->lexiconSet(self::NEGATION_TOKENS);

        $score = 0.0;
        $negationTtl = 0;

        foreach ($tokens as $t) {
            if ($negation[$t] ?? false) {
                $negationTtl = 4;
                continue;
            }

            $w = 0.0;
            if ($posHit[$t] ?? false) {
                $w = 1.0;
            } elseif ($negHit[$t] ?? false) {
                $w = -1.0;
            }

            if ($w !== 0.0) {
                if ($negationTtl > 0) {
                    $w *= -0.75;
                    $negationTtl--;
                } else {
                    $negationTtl = 0;
                }
                $score += $w;
            } elseif ($negationTtl > 0) {
                $negationTtl--;
            }
        }

        $n = max(1, count($tokens) / 8.0);
        $norm = $score / $n;
        $norm = max(-1.0, min(1.0, $norm));

        if ($norm > 0.12) {
            $label = 'positive';
        } elseif ($norm < -0.12) {
            $label = 'negative';
        } else {
            $label = 'neutral';
        }

        $category = $this->categorize($lower);

        return [
            'sentiment' => $label,
            'sentiment_score' => round($norm, 4),
            'category' => $category,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function lexiconSet(array $words): array
    {
        $m = [];
        foreach ($words as $w) {
            $m[$w] = true;
        }

        return $m;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $parts = preg_split('/[^a-z0-9\']+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p, '\'');
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }

    private function categorize(string $low): string
    {
        if (str_contains($low, 'baggage') || str_contains($low, 'luggage') || str_contains($low, 'suitcase') || str_contains($low, 'lost bag')) {
            return 'baggage';
        }
        if (
            str_contains($low, 'delay') || str_contains($low, 'delayed') || str_contains($low, 'cancellation')
            || str_contains($low, 'cancelled') || str_contains($low, 'canceled') || str_contains($low, 'gate')
            || str_contains($low, 'runway') || str_contains($low, 'tarmac')
        ) {
            return 'flight_ops';
        }
        if (
            str_contains($low, 'booking') || str_contains($low, 'ticket') || str_contains($low, 'reservation')
            || str_contains($low, 'upgrade') || str_contains($low, 'seat assignment')
        ) {
            return 'booking';
        }
        if (str_contains($low, 'crew') || str_contains($low, 'pilot') || str_contains($low, 'attendant') || str_contains($low, 'captain')) {
            return 'crew';
        }
        if (
            str_contains($low, 'meal') || str_contains($low, 'wifi') || str_contains($low, 'entertainment')
            || str_contains($low, 'inflight') || str_contains($low, 'cabin')
        ) {
            return 'inflight';
        }
        if (str_contains($low, 'refund') || str_contains($low, 'payment') || str_contains($low, 'fee') || str_contains($low, 'charge')) {
            return 'payment';
        }
        if (str_contains($low, 'service') || str_contains($low, 'support') || str_contains($low, 'customer care')) {
            return 'service';
        }
        if (str_contains($low, 'delivery') || str_contains($low, 'late')) {
            return 'delivery';
        }

        return 'general';
    }
}

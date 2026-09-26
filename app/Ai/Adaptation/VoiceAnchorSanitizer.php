<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Voice Lock merges produced before `session_relevance` joined the exemplar
 * schema smuggled that field into the prose string as a pseudo-JSON tail
 * (`...last sentence.','session_relevance':'session_1_primary`). The tail was
 * rendered verbatim into Section 4A and the relevance filter never saw a tag.
 * This lifts the tag back out into its own key and restores clean prose.
 */
final class VoiceAnchorSanitizer
{
    private const string RELEVANCE_TAIL = '/\s*[\'"]\s*,\s*[\'"]session_relevance[\'"]\s*:\s*[\'"]?([A-Za-z0-9_]+)[\'"]?\s*$/u';

    /**
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public function sanitizeProfile(array $voiceProfile): array
    {
        if (! is_array($voiceProfile['voice_anchor'] ?? null)) {
            return $voiceProfile;
        }

        $voiceProfile['voice_anchor'] = array_map(
            fn (mixed $exemplar): mixed => is_array($exemplar) ? $this->sanitizeExemplar($exemplar) : $exemplar,
            $voiceProfile['voice_anchor'],
        );

        return $voiceProfile;
    }

    /**
     * @param  array<string, mixed>  $exemplar
     * @return array<string, mixed>
     */
    public function sanitizeExemplar(array $exemplar): array
    {
        $prose = (string) ($exemplar['prose'] ?? '');

        if (preg_match(self::RELEVANCE_TAIL, $prose, $matches) !== 1) {
            return $exemplar;
        }

        $exemplar['prose'] = mb_rtrim((string) preg_replace(self::RELEVANCE_TAIL, '', $prose));

        if (mb_trim((string) ($exemplar['session_relevance'] ?? '')) === '') {
            $exemplar['session_relevance'] = $matches[1];
        }

        return $exemplar;
    }

    /**
     * @param  array<string, mixed>  $voiceProfile
     */
    public function corruptedExemplarCount(array $voiceProfile): int
    {
        return count(array_filter(
            (array) ($voiceProfile['voice_anchor'] ?? []),
            fn (mixed $exemplar): bool => is_array($exemplar)
                && preg_match(self::RELEVANCE_TAIL, (string) ($exemplar['prose'] ?? '')) === 1,
        ));
    }
}

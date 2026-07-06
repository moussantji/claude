<?php

namespace App\Services\AnimeSama;

/**
 * Language mappings ported from the Python `anime_sama_api/langs.py`.
 *
 * anime-sama uses short "language ids" in its URLs (vostfr, vf, vf1, ...) that
 * map to a smaller set of human languages (VOSTFR, VF, ...).
 */
final class Lang
{
    /**
     * Ordered list of every language id, matching the Python `get_args(LangId)`
     * order used when fetching every language page of a season.
     *
     * @var list<string>
     */
    public const LANG_IDS = ['va', 'vcn', 'vf', 'vf1', 'vf2', 'vj', 'vkr', 'vqc', 'vostfr'];

    /**
     * Language -> list of language ids. Iteration order matters: it is used as
     * the fallback order in {@see Episode::consumePlayer()}.
     *
     * @var array<string, list<string>>
     */
    public const LANG_TO_IDS = [
        'VOSTFR' => ['vostfr'],
        'VASTFR' => ['va'],
        'VCN' => ['vcn'],
        'VF' => ['vf', 'vf1', 'vf2'],
        'VJSTFR' => ['vj'],
        'VKR' => ['vkr'],
        'VQC' => ['vqc'],
    ];

    /**
     * Flag image id (from `flag_<id>.png`) -> language.
     *
     * @var array<string, string>
     */
    public const FLAGID_TO_LANG = [
        'cn' => 'VCN',
        'qc' => 'VQC',
        'en' => 'VASTFR',
        'kr' => 'VKR',
        'fr' => 'VF',
        'jp' => 'VJSTFR',
    ];

    /**
     * Allowed catalogue categories (Python `Category` literal).
     *
     * @var list<string>
     */
    public const CATEGORIES = ['Anime', 'Scans', 'Film', 'Autres'];

    /**
     * Allowed languages (Python `Lang` literal).
     *
     * @var list<string>
     */
    public const LANGS = ['VASTFR', 'VCN', 'VF', 'VJSTFR', 'VKR', 'VQC', 'VOSTFR'];

    /**
     * Map a language id back to its human language (e.g. "vf1" -> "VF").
     */
    public static function idToLang(string $langId): string
    {
        foreach (self::LANG_TO_IDS as $lang => $ids) {
            if (in_array($langId, $ids, true)) {
                return $lang;
            }
        }

        return $langId;
    }
}

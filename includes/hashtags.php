<?php

function snapix_normalize_hashtags(string $rawHashtags): string
{
    $rawHashtags = trim(preg_replace('/\s+/u', ' ', $rawHashtags) ?? '');

    if ($rawHashtags === '') {
        return '';
    }

    $parts = preg_split('/\s+/u', $rawHashtags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $seen = [];
    $hashtags = [];

    foreach ($parts as $part) {
        $tag = trim($part);

        if ($tag === '') {
            continue;
        }

        $tag = ltrim($tag, '#');
        $tag = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $tag) ?? '';

        if ($tag === '') {
            continue;
        }

        $tag = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        $tag = '#' . $tag;

        if (isset($seen[$tag])) {
            continue;
        }

        $seen[$tag] = true;
        $hashtags[] = $tag;
    }

    return implode(' ', $hashtags);
}

function snapix_split_hashtags(?string $hashtags): array
{
    $hashtags = trim((string) $hashtags);

    if ($hashtags === '') {
        return [];
    }

    return preg_split('/\s+/u', $hashtags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

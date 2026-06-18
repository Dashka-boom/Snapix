<?php

function snapix_hashtag_to_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function snapix_hashtag_contains_forbidden_word(string $hashtag): bool
{
    $normalized = snapix_hashtag_to_lower($hashtag);
    $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
    $compact = ltrim($compact, '#');

    $forbidden = [
        'убей себя',
        'убейся',
        'суицид',
        'сдохни',
        'умри',
        'ненавижу',
        'террор',
        'наркотики',
        'порно',
        '18+',
    ];

    foreach ($forbidden as $phrase) {
        $phrase = snapix_hashtag_to_lower($phrase);
        $phraseCompact = preg_replace('/\s+/u', '', $phrase) ?? $phrase;

        if ($phraseCompact !== '' && str_contains($compact, $phraseCompact)) {
            return true;
        }
    }

    return false;
}

function snapix_is_valid_safe_hashtag(string $hashtag): bool
{
    if ($hashtag === '' || $hashtag[0] !== '#') {
        return false;
    }

    if (function_exists('mb_strlen') ? mb_strlen($hashtag, 'UTF-8') > 30 : strlen($hashtag) > 30) {
        return false;
    }

    if (!preg_match('/^#[\p{L}\p{N}_]+$/u', $hashtag)) {
        return false;
    }

    return !snapix_hashtag_contains_forbidden_word($hashtag);
}

function snapix_validate_and_normalize_hashtags(string $rawHashtags, ?string &$error = null): string
{
    $error = null;
    $rawHashtags = trim($rawHashtags);

    if ($rawHashtags === '') {
        return '';
    }

    $rawHashtags = preg_replace('/\s+/u', ' ', $rawHashtags) ?? '';

    if (function_exists('mb_strlen') ? mb_strlen($rawHashtags, 'UTF-8') > 300 : strlen($rawHashtags) > 300) {
        $error = 'Поле хештегов не должно превышать 300 символов';
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

        if ($tag[0] !== '#') {
            $error = 'Хештеги должны начинаться с символа #';
            return '';
        }

        if (snapix_hashtag_contains_forbidden_word($tag)) {
            $error = 'Хештег содержит запрещённое слово';
            return '';
        }

        $tag = snapix_hashtag_to_lower($tag);

        if (function_exists('mb_strlen') ? mb_strlen($tag, 'UTF-8') > 30 : strlen($tag) > 30) {
            $error = 'Один хештег не должен превышать 30 символов';
            return '';
        }

        if (!preg_match('/^#[\p{L}\p{N}_]+$/u', $tag)) {
            $error = 'Хештеги могут содержать только буквы, цифры и нижнее подчёркивание';
            return '';
        }

        if (isset($seen[$tag])) {
            continue;
        }

        $seen[$tag] = true;
        $hashtags[] = $tag;

        if (count($hashtags) > 10) {
            $error = 'Можно добавить не больше 10 хештегов';
            return '';
        }
    }

    return implode(' ', $hashtags);
}

function snapix_normalize_hashtags(string $rawHashtags): string
{
    $error = null;
    return snapix_validate_and_normalize_hashtags($rawHashtags, $error);
}

function snapix_normalize_hashtag_search_query(string $query): string
{
    $query = trim($query);

    if (snapix_hashtag_contains_forbidden_word($query)) {
        return '';
    }

    if ($query === '') {
        return '';
    }

    $query = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
    $query = ltrim($query, '#');
    $query = preg_replace('/[^\p{L}\p{N}_]+/u', '', $query) ?? '';

    if ($query === '') {
        return '';
    }

    $hashtag = '#' . snapix_hashtag_to_lower($query);

    return snapix_is_valid_safe_hashtag($hashtag) ? $hashtag : '';
}

function snapix_split_hashtags(?string $hashtags): array
{
    $hashtags = trim((string) $hashtags);

    if ($hashtags === '') {
        return [];
    }

    return preg_split('/\s+/u', $hashtags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function snapix_split_safe_hashtags(?string $hashtags): array
{
    $safeHashtags = [];

    foreach (snapix_split_hashtags($hashtags) as $hashtag) {
        $hashtag = snapix_hashtag_to_lower(trim($hashtag));

        if (snapix_is_valid_safe_hashtag($hashtag)) {
            $safeHashtags[] = $hashtag;
        }
    }

    return array_values(array_unique($safeHashtags));
}

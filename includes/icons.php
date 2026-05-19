<?php
if (!function_exists('snapix_icon')) {
function snapix_icon(string $name): string
{
    $icons = [
        'heart' => '<svg class="snapix-icon snapix-icon-heart" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
        'message-circle' => '<svg class="snapix-icon snapix-icon-message-circle" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.6 8.6 0 0 1-7.7 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.6A8.4 8.4 0 0 1 4 11.5a8.5 8.5 0 1 1 17 0Z"/></svg>',
        'repeat' => '<svg class="snapix-icon snapix-icon-repeat" viewBox="0 0 24 24" aria-hidden="true"><path d="m17 2 4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>',
        'bookmark' => '<svg class="snapix-icon snapix-icon-bookmark" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.8A2.8 2.8 0 0 1 8.8 2h6.4A2.8 2.8 0 0 1 18 4.8V22l-6-3.8L6 22V4.8Z"/></svg>',
        'send' => '<svg class="snapix-icon snapix-icon-send" viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/></svg>',
        'bell' => '<svg class="snapix-icon snapix-icon-bell" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/></svg>',
        'camera' => '<svg class="snapix-icon snapix-icon-camera" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4.5 16 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3l1.5-2.5h5Z"/><circle cx="12" cy="13" r="3.5"/></svg>',
        'mail' => '<svg class="snapix-icon snapix-icon-mail" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="m22 7-10 6L2 7"/></svg>',
        'more-vertical' => '<svg class="snapix-icon snapix-icon-more-vertical" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="12" cy="19" r="1.3"/></svg>',
        'more-horizontal' => '<svg class="snapix-icon snapix-icon-more-horizontal" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>',
        'smile' => '<svg class="snapix-icon snapix-icon-smile" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>',
        'x' => '<svg class="snapix-icon snapix-icon-x" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'edit' => '<svg class="snapix-icon snapix-icon-edit" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>',
        'trash' => '<svg class="snapix-icon snapix-icon-trash" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>',
        'pin' => '<svg class="snapix-icon snapix-icon-pin" viewBox="0 0 24 24" aria-hidden="true"><path d="m16 3 5 5-4 4v4l-2 2-5-5-5 5-1-1 5-5-5-5 2-2h4l4-4Z"/></svg>',
        'reply' => '<svg class="snapix-icon snapix-icon-reply" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 17-6-6 6-6"/><path d="M3 11h12a6 6 0 0 1 6 6v1"/></svg>',
        'forward' => '<svg class="snapix-icon snapix-icon-forward" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 17 6-5-6-5"/><path d="M21 12H9a6 6 0 0 0-6 6v1"/></svg>',
        'copy' => '<svg class="snapix-icon snapix-icon-copy" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    ];

    return $icons[$name] ?? '';
}
}

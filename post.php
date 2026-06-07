<?php
$postId = (int) ($_GET['id'] ?? $_GET['post_id'] ?? 0);
$target = 'index.php';
if ($postId > 0) {
    $target .= '?open_post=' . $postId;
}
header('Location: ' . $target);
exit;

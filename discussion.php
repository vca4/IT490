<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

//Session Validation to check if user is logged in
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// MQ request function
function sendRequest($type, $data = []) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "discussionServer");
    return $client->send_request(array_merge(['type' => $type], $data));
}

// posting new or reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post') {

    $message = trim($_POST['message']);
    $parent_id = $_POST['parent_id'] ?? null;

    if (!empty($message)) {
        sendRequest('add_post', [
            'username'=> $username,
            'message'=> $message,
            'parent_id'=> $parent_id
        ]);

        header("Location: discussion.php");
        exit();
    }
}

// deleting a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    sendRequest('delete_post', [
        'username' => $username,
        'post_id' => $_POST['post_id']
    ]);

    header("Location: discussion.php");
    exit();
}

// editing a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {

    $newMsg = trim($_POST['new_message']);
    $post_id = $_POST['post_id'];

    if (!empty($newMsg)) {
        sendRequest('edit_post', [
            'post_id' => $post_id,
            'username' => $username,
            'message' => $newMsg
        ]);
    }

    header("Location: discussion.php");
    exit();
}

// pull posts
$response = sendRequest('get_posts');
$posts = ($response && $response['status'] === 'success') ? $response['posts'] : [];

// build nested tree
function buildTree($posts) {
    $tree = [];
    $refs = [];

    foreach ($posts as &$post) {
        $post['children'] = [];
        $refs[$post['id']] = &$post;
    }

    foreach ($posts as &$post) {
        if ($post['parent_id']) {
            $refs[$post['parent_id']]['children'][] = &$post;
        } else {
            $tree[] = &$post;
        }
    }
    return $tree;
}

$threadedPosts = buildTree($posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Movie Discussions</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/lux/bootstrap.min.css">

<style>
body { background:#f8f9fa; color:#333; }
.btn-black { background:#000; color:#fff; border:none; }
.btn-black:hover { background:#222; }
.card-clean { background:white; border:1px solid #e0e0e0; border-radius:12px; padding:20px; }
.reply-indent { margin-left:40px; }

.modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
.modal-content { background:white; margin:10% auto; padding:20px; border-radius:10px; width:400px; }
</style>
</head>
<body>

<!-- BACK BUTTON (replaces navbar) -->
<a href="search.php" class="btn btn-black m-3">Back</a>


<div class="container text-center mt-4">
<h1 class="text-primary fw-bold">Movie Discussions</h1>
</div>

<div class="container mt-4" style="max-width: 900px;">

<!-- POST MESSAGE FORM -->
<div class="card-clean mb-4">
<h4 class="fw-bold">Post a Message</h4>

<form method="POST" action="discussion.php">
<p><strong>Logged in as:</strong> <?= htmlspecialchars($username); ?></p>
<textarea id="messageBox" name="message" class="form-control" placeholder="Write your message..." required></textarea>

<input type="hidden" name="action" value="post">
<input type="hidden" id="parent_id" name="parent_id">

<button type="submit" class="btn btn-black mt-3">Post</button>
</form>
</div>

<hr>
<h3 class="fw-bold mb-3 text-primary">All Posts</h3>

<?php

function renderPosts($posts, $isReply, $username) {

    foreach ($posts as $post) {

        echo '<div class="card-clean mb-3 '.($isReply ? "reply-indent" : "").'">';

        echo '<div class="d-flex justify-content-between">';
        echo '<span class="fw-bold text-primary">'.htmlspecialchars($post['username']).'</span>';
        echo '<span class="text-muted" style="font-size:0.8rem;">'.htmlspecialchars($post['created_at']).'</span>';
        echo '</div>';

        echo '<p class="mt-2">'.nl2br(htmlspecialchars($post['message'])).'</p>';

        // LEFT (Reply + Edit) AND RIGHT (Delete)
        echo '<div class="mt-2 d-flex justify-content-between align-items-center">';

            // LEFT SIDE
            echo '<div class="d-flex">';

            echo '<button type="button" class="btn btn-black btn-sm me-2" 
            onclick="replyTo(\''.htmlspecialchars($post['username']).'\','.$post['id'].')">Reply</button>';

            if ($post['username'] === $username) {
                echo '<button type="button" class="btn btn-black btn-sm me-2 editBtn" 
                data-id="'.$post['id'].'"
                data-message="'.htmlspecialchars($post['message']).'">Edit</button>';
            }

            echo '</div>';

            // RIGHT SIDE (Delete)
            if ($post['username'] === $username) {
                echo '<form method="POST" action="discussion.php" style="display:inline-block;">';
                echo '<input type="hidden" name="action" value="delete">';
                echo '<input type="hidden" name="post_id" value="'.$post['id'].'">';
                echo '<button class="btn btn-black btn-sm" onclick="return confirm(\'Delete this post?\')">Delete</button>';
                echo '</form>';
            }

        echo '</div>';

        if (!empty($post['children'])) {
            renderPosts($post['children'], true, $username);
        }

        echo '</div>';
    }
}

if (!empty($threadedPosts)) {
    renderPosts($threadedPosts, false, $username);
} else {
    echo "<p class='text-muted'>No discussions yet. Be the first to post!</p>";
}

?>

</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal">
<div class="modal-content">
<h4 class="fw-bold mb-3">Edit Message</h4>

<form method="POST" action="discussion.php">
<input type="hidden" name="action" value="edit">
<input type="hidden" id="edit_post_id" name="post_id">

<textarea class="form-control" id="edit_message_box" name="new_message" rows="4"></textarea>

<button type="submit" class="btn btn-black mt-3">Save</button>
<button type="button" id="cancelEdit" class="btn btn-secondary mt-3">Cancel</button>
</form>

</div>
</div>

<script>
function replyTo(username, postId) {
    document.getElementById('messageBox').value = '@' + username + ' ';
    document.getElementById('parent_id').value = postId;
}

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('edit_post_id').value = this.dataset.id;
        document.getElementById('edit_message_box').value = this.dataset.message;
        document.getElementById('editModal').style.display = "block";
    });
});

document.getElementById('cancelEdit').addEventListener('click', function () {
    document.getElementById('editModal').style.display = "none";
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

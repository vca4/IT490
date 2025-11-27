<?php
// required files for MQ communication
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

session_start(); 

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// get search term url query
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

// sends user's desired search query to DMZ server by using MQ
function searchMoviesViaDMZ($query) {
    $client = new rabbitMQClient("movieRabbitMQ.ini", "DMZMovieServer");

    $request = [
        'type' => 'movieSearch', 
        'query' => $query
    ];

    $response = $client->send_request($request);

    if ($response && isset($response['returnCode']) && $response['returnCode'] === 1) {
        return $response['data']; 
    }
    return null; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Movie Search</title>

<!-- Bootswatch Lux -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/lux/bootstrap.min.css">

</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">

    <a class="navbar-brand fs-3 fw-bold" href="#">MovieHub</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">

      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="anticipated.php">Upcoming</a></li>
        <li class="nav-item"><a class="nav-link" href="watchlist.php">Watchlist</a></li>
        <li class="nav-item"><a class="nav-link" href="discussion.php">Discussion Board</a></li>
        <li class="nav-item"><a class="nav-link" href="browse.php">Browse</a></li>
        <li class="nav-item"><a class="nav-link" href="my_reviews.php">My Reviews</a></li>
      </ul>

      <form class="d-flex" method="GET" action="search.php">
        <input 
          class="form-control me-2"
          type="search" 
          name="q"
          placeholder="Search..."
          value="<?php echo htmlspecialchars($search_term); ?>"
        >
        <button class="btn btn-success" type="submit">Search</button>
      </form>

      <form class="ms-3" action="logout.php" method="POST">
        <button class="btn btn-danger" type="submit">Logout</button>
      </form>

    </div>
  </div>
</nav>

<!-- TITLE -->
<div class="container text-center mt-5">
    <h1 class="fw-bold text-primary">Search Results</h1>
</div>

<!-- RESULTS GRID -->
<div class="container mt-4">
  <div class="row g-4" id="movieContainer">

<?php
$index = 0;

if ($search_term !== '') {
    $movies = searchMoviesViaDMZ($search_term);

    if ($movies) {
        foreach ($movies as $item) {
            if (!isset($item['movie'])) continue;

            $movie = $item['movie'];

            $id    = htmlspecialchars($movie['ids']['slug']);
            $title = htmlspecialchars($movie['title']);
            $year  = htmlspecialchars($movie['year']);

            // Hide items after 20 for Load More
            $hiddenClass = ($index >= 20) ? "d-none" : "";

            echo "
            <div class='col-12 col-sm-6 col-md-4 col-lg-3 movie-item $hiddenClass'>
              <div class='card p-3 shadow'>

                <h4 class='fw-bold text-primary'>$title</h4>
                <p class='text-muted'>($year)</p>

                <a href='details.php?id=$id' class='btn btn-primary w-100 mt-2'>
                  View Details
                </a>
              </div>
            </div>";

            $index++;
        }
    } else {
        echo "<p class='text-center fs-4 text-secondary'>No results found.</p>";
    }
}
?>

  </div>
</div>

<!-- LOAD MORE -->
<div class="text-center mt-4 mb-5">
    <button 
      id="loadMoreBtn"
      class="btn btn-success btn-lg d-none"
    >Load More</button>
</div>

<!-- JS LOAD MORE LOGIC -->
<script>
let totalMovies = <?php echo $index; ?>;
let loaded = 20;

let btn = document.getElementById("loadMoreBtn");
if (totalMovies > 20) btn.classList.remove("d-none");

btn.addEventListener("click", function () {
    let items = document.querySelectorAll(".movie-item");
    let nextLoad = loaded + 20;

    for (let i = loaded; i < nextLoad && i < items.length; i++) {
        items[i].classList.remove("d-none");
    }

    loaded = nextLoad;

    if (loaded >= items.length) {
        btn.classList.add("d-none");
    }
});
</script>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

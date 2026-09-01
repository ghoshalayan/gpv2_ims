<?php
$content = '<div class="text-center py-5"><h1 class="display-1 text-muted">500</h1><p class="lead">Something went wrong on our side.</p><a href="' . url('/') . '" class="btn btn-primary">Home</a></div>';
$title = 'Server Error';
require APP_ROOT . '/app/Views/layouts/app.php';

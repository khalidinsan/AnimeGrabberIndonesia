<?php
include "animeGrabber.php";

$grab = new animeGrabber();

$anime = $grab->getAnimeByID(1512);

print_r($anime);
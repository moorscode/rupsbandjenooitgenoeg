<?php

include( "class.Database.php" );
include( "functions.php" );

function rankToReadable( $rank ) {
	$readable = array( "PVT", "KRP", "SGT", "MAJ", "ADJ", "LUI", "KPT", "KOL", "GNM", "LGN", "GEN" );

	return $readable[ $rank ];
}

$uid = intval( $_GET['uid'] );

$width  = 25;
$height = 25;

$db = &Database::getInstance();

$rank = 0;

$result = $db->query( "SELECT `points` FROM `global__PlayerInfo` WHERE `player_id`='$uid'" );
if ( $user = $db->assoc( $result ) ) {
	$rank = floor( sqrt( intval( $user['points'] ) / 20 ) );
}

$filename = "rank{$rank}.jpg";
$filename = "../avatars/" . $filename;


list( $width_orig, $height_orig ) = getimagesize( $filename );

$ratio_orig = $width_orig / $height_orig;

if ( $width / $height > $ratio_orig ) {
	$width = $height * $ratio_orig;
}
else {
	$height = $width / $ratio_orig;
}

$file_extension = end( split( "\.", basename( $filename ) ) );

// Resample
$image_p = imagecreatetruecolor( $width, $height );
imagefill( $image_p, 0, 0, imagecolorallocate( $image_p, 255, 255, 255 ) );

switch ( $file_extension ) {
	case "jpg":
	case "jpeg":
		$image = imagecreatefromjpeg( $filename );
		break;
	case "png":
		$image = imagecreatefrompng( $filename );
		break;
	case "gif":
		$image = imagecreatefromgif( $filename );
		break;
}

imagecopyresampled( $image_p, $image, 0, -3, 0, 0, $width, $height, $width_orig, $height_orig );

imagestring( $image_p, 1, 3, 18, rankToReadable( $rank ), imagecolorallocate( $image_p, 0, 0, 0 ) );

header( 'Content-type: image/jpeg' );

// Output
imagejpeg( $image_p, null, 100 );

die();


// 



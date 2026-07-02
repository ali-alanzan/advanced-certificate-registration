<?php

    include('phpqrcode/qrlib.php');
    
    // outputs image directly into browser, as PNG stream
    // QRcode::png('PHP QR Code :)');
    define("EXAMPLE_TMP_URLRELPATH", "");
    $codeContents = '123451234512345123451234512345123451234512345123451234512345123451234512345123451234512345123451234512345';
    $tempDir = EXAMPLE_TMP_URLRELPATH;
    $fileName = '711_test_custom.jpg';
    $outerFrame = 4;
    $pixelPerPoint = 5;
    $jpegQuality = 95;
    
    // generating frame
    $frame = QRcode::text($codeContents, false, QR_ECLEVEL_M);
    
    // rendering frame with GD2 (that should be function by real impl.!!!)
    $h = count($frame);
    $w = strlen($frame[0]);
    // var_dump($h);
    // var_dump($w);
    // die;
    $imgW = $w + 2*$outerFrame;
    $imgH = $h + 2*$outerFrame;
    
    $base_image = imagecreate($imgW, $imgH);
    
    $col[0] = imagecolorallocate($base_image,255,255,255); // BG, white 
    $col[1] = imagecolorallocate($base_image,0,0,0);     // FG, black

    imagefill($base_image, 0, 0, $col[0]);
    for($y=0; $y<$h; $y++) {
        for($x=0; $x<$w; $x++) {
            if ($frame[$y][$x] == '1') {
                imagesetpixel($base_image,$x+$outerFrame,$y+$outerFrame,$col[1]); 
                // print_r([$base_image,$x+$outerFrame,$y+$outerFrame,$col[1]]);

            }
        }
    }

    
    // saving to file
    $target_image = imagecreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
    imagecopyresized(
        $target_image, 
        $base_image, 
        0, 0, 0, 0, 
        120, 120, $imgW, $imgH
    );
    imagedestroy($base_image);
    imagejpeg($target_image, $tempDir.$fileName, $jpegQuality);
    imagedestroy($target_image);

    // displaying
    echo '<img src="'.EXAMPLE_TMP_URLRELPATH.$fileName.'" />';
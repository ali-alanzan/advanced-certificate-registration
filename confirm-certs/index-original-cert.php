<?php 
    
   

    
        
    header('Content-type: image/jpeg');
    $font=realpath('times.ttf');
    $image=imagecreatefromjpeg("official-cert-template.jpg");
    $color=imagecolorallocate($image, 0, 0, 0);
    $color_small=imagecolorallocate($image, 85,85,85);
    
    $date=date('d F, Y');
    imagettftext($image, 8, 0, 165, 725.5, $color_small,$font, $date);
    
    
    $cert_id_counter = time();

    
    $cert_id = "E".$cert_id_counter."B"; 
    
    $c_num=$cert_id;
    imagettftext($image, 8, 0, 202, 767, $color_small,$font, $c_num);
    
    $first_name = "Ali";
    $last_name = "Alanzan";
    $name= "Ali" . ' ' . "Alanzan";
    imagettftext($image, 36, 0, 120, 310, $color,$font, $name);
    
    
    $coursename="Complete studying In International Law and Diplomatic Relations";
 
    imagettftext($image, 32, 0, 120, 550, $color,$font, $coursename);
    

    
    $cert_url = $cert_id.".jpg";
    


    

    include('qr/phpqrcode/qrlib.php');
    

    // $QR=imagecreatefromstring(file_get_contents("https://chart.googleapis.com/chart?chs=99x99&cht=qr&chf=bg,s,EEEEEE&chl=".urlencode( "test-with-ali" )));



    // outputs image directly into browser, as PNG stream
    // QRcode::png('PHP QR Code :)');
    define("EXAMPLE_TMP_URLRELPATH", "");
    $codeContents = 'https://edu.europeanboard.eu/certificate/?get=ooscNuBc2V/zGI48NWWwgGtC4W8KseqzQ1WT';
    $tempDir = EXAMPLE_TMP_URLRELPATH;
    $fileName = '711_test_custom.jpg';
    $outerFrame = 0;
    $pixelPerPoint = 5;
    $jpegQuality = 95;
    
    // generating frame
    $frame = QRcode::text($codeContents, false, QR_ECLEVEL_L);
    
    // rendering frame with GD2 (that should be function by real impl.!!!)
    $h = count($frame);
    $w = strlen($frame[0]);
    
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
            }
        }
    }
    
    // saving to file
    $target_image = imagecreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
    imagecopyresized(
        $target_image, 
        $base_image, 
        0, 0, 0, 0, 
        99,99, $imgW, $imgH
    );
    imagedestroy($base_image);
    imagejpeg($target_image, $tempDir.$fileName, $jpegQuality);
    imagedestroy($target_image);

    // displaying
    // echo '<img src="'.EXAMPLE_TMP_URLRELPATH.$fileName.'" />';
    $QR=imagecreatefromstring(file_get_contents(EXAMPLE_TMP_URLRELPATH.$fileName));

    imagecopyresampled($image, $QR, 1029, 748 , 0, 0, 95, 95, 99, 99);
    imagejpeg($image, 'certificates/'.$cert_url);
    // imagejpeg($image, "cert.jpg");
    imagedestroy($image);
    
?>
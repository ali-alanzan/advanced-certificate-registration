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
    
    // outputs image directly into browser, as PNG stream
    // $QR = QRcode::png('PHP QR Code :)');

    // $QR=imagecreatefromstring(file_get_contents("https://chart.googleapis.com/chart?chs=99x99&cht=qr&chf=bg,s,EEEEEE&chl=".urlencode( "test-with-ali" )));
    // $QR=imagecreatefromstring($QR = QRcode::png('PHP QR Code :)'));

      // text output  
      $codeContents = '12345';
    
      // generating
    //   $text = QRcode::text($codeContents);
      

    // imagecopyresampled($image, $text, 1028, 745 , 0, 0, 99, 99, 99, 99);
    imagejpeg($image, 'certificates/'.$cert_url);
    // imagejpeg($image, "cert.jpg");
    imagedestroy($image);
    
?>
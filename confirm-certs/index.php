<?php
define('CONFIRM_CERTS_DEBUG_OUTPUT', isset($_GET['debug']) && $_GET['debug'] === '1');

error_reporting(E_ALL);
ini_set('log_errors', '1');
if (CONFIRM_CERTS_DEBUG_OUTPUT) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

function confirm_certs_log($message, $context = [])
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    error_log('confirm-certs: ' . $line);

    $logFile = __DIR__ . '/confirm-certs-debug.log';
    if (!is_writable(__DIR__)) {
        $logFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'confirm-certs-debug.log';
    }

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function confirm_certs_fail($message, $context = [])
{
    confirm_certs_log($message, $context);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'confirm-certs error: ' . $message;
    if (!empty($context)) {
        echo "\n\nContext:\n" . print_r($context, true);
    }
    if (!empty($context['log_file'])) {
        echo "\nLog file: " . $context['log_file'];
    }
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        confirm_certs_log('Fatal PHP error', $error);
        if (CONFIRM_CERTS_DEBUG_OUTPUT) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
            }

            echo "\n\nFatal PHP error:\n" . print_r($error, true);
        }
    }
});

confirm_certs_log('Request started', [
    'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
    'uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
    'php_version' => PHP_VERSION,
]);

$missingRequirements = [];
foreach (['gd', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        $missingRequirements[] = 'PHP extension missing: ' . $extension;
    }
}

if (!in_array('phar', stream_get_wrappers(), true)) {
    $missingRequirements[] = 'PHP phar stream wrapper is disabled';
}

foreach (['imagecreatefromjpeg', 'imagejpeg', 'imagettftext', 'imagettfbbox'] as $functionName) {
    if (!function_exists($functionName)) {
        $missingRequirements[] = 'PHP function missing: ' . $functionName;
    }
}

if (!empty($missingRequirements)) {
    confirm_certs_fail('Server requirements are missing.', [
        'missing' => $missingRequirements,
        'loaded_extensions' => get_loaded_extensions(),
    ]);
}

// Get the template parameter from the URL, default to 'default'
$template = isset($_GET['tpl']) ? $_GET['tpl'] : 'default';

// Select the background image based on the parameter
switch ($template) {
    case 'management':
        $templateFile = "official-cert-template-managmentcircle.jpg";
        break;
    case 'eaglestate':
        $templateFile = "official-cert-template-eaglestate.jpg";
        break;
    case 'eagletogether':
        $templateFile = "official-cert-template-eagletogother.jpg";
        break;
    case 'experience':
        $templateFile = "official-cert-template-experience.jpg";
        break;
    case 'card':
        $templateFile = "official-cert-template-card.jpg";
        break;
    case 'seat':
        $templateFile = "official-cert-template-seat.jpg";
        break;
    default:
        $templateFile = "official-cert-template.jpg";

        $name_font_size = 36;
        $course_font_size = 32;


        $nameX = 120;
        $nameY = 310;
        $courseX = 120;
        $courseY = 550;
        break;
}

$templatePath = __DIR__ . '/' . $templateFile;
if (!is_readable($templatePath)) {
    confirm_certs_fail('Certificate template image is not readable.', [
        'template' => $template,
        'path' => $templatePath,
    ]);
}

$image = imagecreatefromjpeg($templatePath);
if (!$image) {
    confirm_certs_fail('Failed to load certificate template image.', [
        'template' => $template,
        'path' => $templatePath,
    ]);
}

if (!CONFIRM_CERTS_DEBUG_OUTPUT) {
    header('Content-type: image/jpeg');
} elseif (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
$color = imagecolorallocate($image, 0, 0, 0);

$arabicLibrary = 'phar://' . __DIR__ . '/ArPHP.phar/Arabic.php';
if (!is_readable(__DIR__ . '/ArPHP.phar')) {
    confirm_certs_fail('ArPHP.phar is not readable.', [
        'path' => __DIR__ . '/ArPHP.phar',
    ]);
}

require_once $arabicLibrary;
if (!class_exists('ArPHP\I18N\Arabic')) {
    confirm_certs_fail('Arabic text library did not load.', [
        'path' => $arabicLibrary,
    ]);
}

function getCertificateFont($weight = 'regular')
{
    $regularFonts = [
        '/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf',
        '/usr/share/fonts/opentype/fonts-hosny-amiri/Amiri-Regular.ttf',
        __DIR__ . '/arial.ttf',
        __DIR__ . '/times.ttf',
    ];

    $boldFonts = [
        '/usr/share/fonts/truetype/noto/NotoNaskhArabic-Bold.ttf',
        '/usr/share/fonts/opentype/fonts-hosny-amiri/Amiri-Bold.ttf',
        __DIR__ . '/arial.ttf',
        __DIR__ . '/times.ttf',
    ];

    $fonts = $weight === 'bold' ? $boldFonts : $regularFonts;

    foreach ($fonts as $font) {
        if (is_readable($font)) {
            return $font;
        }
    }

    return false;
}

function prepareArabicText($text)
{
    static $arabic = null;

    if (!preg_match('/\p{Arabic}/u', $text)) {
        return $text;
    }

    $text = strtr($text, [
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
    ]);

    if ($arabic === null) {
        $arabic = new ArPHP\I18N\Arabic();
    }

    return $arabic->utf8Glyphs($text, mb_strlen($text, 'UTF-8') + 20);
}

function drawRtlText($image, $fontSize, $rightX, $y, $color, $font, $text)
{
    $text = prepareArabicText($text);
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = $rightX - $textWidth;

    imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
}

function fitFontSize($fontSize, $font, $text, $maxWidth)
{
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($box[2] - $box[0]);

    while ($textWidth > $maxWidth && $fontSize > 24) {
        $fontSize -= 2;
        $box = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
    }

    return $fontSize;
}

function drawRtlTextInBox($image, $fontSize, $rightX, $y, $maxWidth, $color, $font, $text)
{
    $text = prepareArabicText($text);
    $fontSize = fitFontSize($fontSize, $font, $text, $maxWidth);
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = $rightX - $textWidth;

    imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
}

function drawCenteredRtlText($image, $fontSize, $centerX, $y, $maxWidth, $color, $font, $text)
{
    $text = prepareArabicText($text);
    $fontSize = fitFontSize($fontSize, $font, $text, $maxWidth);
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = $centerX - ($textWidth / 2);

    imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
}

function getTextWidth($fontSize, $font, $text)
{
    $box = imagettfbbox($fontSize, 0, $font, $text);
    return abs($box[2] - $box[0]);
}

function drawCenteredTextSegments($image, $fontSize, $centerX, $y, $color, $font, $segments)
{
    $preparedSegments = [];
    $totalWidth = 0;

    foreach ($segments as $segment) {
        $segmentFont = isset($segment['font']) ? $segment['font'] : $font;
        $text = $segment['shape'] ? prepareArabicText($segment['text']) : $segment['text'];
        $width = getTextWidth($fontSize, $segmentFont, $text);
        $preparedSegments[] = [
            'text' => $text,
            'width' => $width,
            'font' => $segmentFont,
        ];
        $totalWidth += $width;
    }

    $x = $centerX - ($totalWidth / 2);
    foreach ($preparedSegments as $segment) {
        imagettftext($image, $fontSize, 0, $x, $y, $color, $segment['font'], $segment['text']);
        $x += $segment['width'];
    }
}

function loadImageFromPath($path)
{
    if ($path === '') {
        return false;
    }

    $isUrl = preg_match('/^https?:\/\//i', $path);
    if (!$isUrl && !is_readable($path)) {
        return false;
    }

    $info = getimagesize($path);
    if ($info === false) {
        return false;
    }

    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            return imagecreatefrompng($path);
        case IMAGETYPE_GIF:
            return imagecreatefromgif($path);
        default:
            return false;
    }
}

function resolveUploadedPhoto()
{
    if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        return $_FILES['photo']['tmp_name'];
    }

    if (!isset($_GET['photo']) && !isset($_GET['image'])) {
        return '';
    }

    $photo = isset($_GET['photo']) ? $_GET['photo'] : $_GET['image'];
    if (preg_match('/^https?:\/\//i', $photo)) {
        return $photo;
    }

    $path = realpath($photo);
    if ($path !== false && is_readable($path)) {
        return $path;
    }

    $projectPath = realpath(__DIR__ . '/' . ltrim($photo, '/'));
    if ($projectPath !== false && is_readable($projectPath)) {
        return $projectPath;
    }

    return '';
}

function drawImageCover($dst, $src, $dstX, $dstY, $dstW, $dstH)
{
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $srcRatio = $srcW / $srcH;
    $dstRatio = $dstW / $dstH;

    if ($srcRatio > $dstRatio) {
        $cropH = $srcH;
        $cropW = (int) round($srcH * $dstRatio);
        $srcX = (int) round(($srcW - $cropW) / 2);
        $srcY = 0;
    } else {
        $cropW = $srcW;
        $cropH = (int) round($srcW / $dstRatio);
        $srcX = 0;
        $srcY = (int) round(($srcH - $cropH) / 2);
    }

    imagecopyresampled($dst, $src, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $cropW, $cropH);
}

function drawPlainTextInBox($image, $fontSize, $rightX, $y, $maxWidth, $color, $font, $text)
{
    $fontSize = fitFontSize($fontSize, $font, $text, $maxWidth);
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = $rightX - $textWidth;

    imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
}

function drawManagementCertificate($image, $name, $coursename, $hours, $dateText)
{
    $font = getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $centerX = imagesx($image) / 2;
    $red = imagecolorallocate($image, 232, 0, 0);
    $blue = imagecolorallocate($image, 11, 132, 199);
    $darkBlue = imagecolorallocate($image, 42, 65, 143);
    $white = imagecolorallocate($image, 255, 255, 255);

    drawRtlTextInBox($image, 94, 1500, 1570, 1200, $red, $font, $name);
    imagefilledrectangle($image, 310, 2065, 2170, 2610, $white);
    drawCenteredRtlText($image, 72, $centerX, 2190, 1650, $darkBlue, $font, $coursename);
    drawCenteredRtlText($image, 62, $centerX, 2350, 1450, $blue, $font, 'بإجمالي عدد ' . $hours . ' ساعة تدريبية');
    drawCenteredRtlText($image, 66, $centerX, 2470, 1300, $darkBlue, $font, $dateText);
}

function drawEagleStateCertificate($image, $name, $coursename, $month, $year, $hours)
{
    $font = is_readable('/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf')
        ? '/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf'
        : getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $centerX = imagesx($image) / 2;
    $gold = imagecolorallocate($image, 137, 84, 10);
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    $plainFont = is_readable('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')
        ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
        : $font;

    drawCenteredRtlText($image, 82, $centerX, 1658, 1450, $gold, $font, $name);
    drawCenteredTextSegments($image, 76, $centerX, 2195, $gold, $font, [
        ['text' => ')', 'shape' => false, 'font' => $plainFont],
        ['text' => $coursename, 'shape' => true],
        ['text' => '(', 'shape' => false, 'font' => $plainFont],
    ]);

    imagefilledrectangle($image, 430, 2260, 2050, 2660, $white);
    drawCenteredRtlText($image, 62, $centerX, 2360, 1150, $black, $font, 'خلال شهر ' . $month . ' ' . $year);
    drawCenteredTextSegments($image, 68, $centerX, 2560, $black, $font, [
        ['text' => 'ساعات تدريبية', 'shape' => true],
        ['text' => ' (' . $hours . ') ', 'shape' => false, 'font' => $plainFont],
        ['text' => 'عدد الساعات', 'shape' => true],
    ]);
}

function drawEagleTogetherCertificate($image, $name, $coursename)
{
    $font = is_readable('/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf')
        ? '/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf'
        : getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $centerX = imagesx($image) / 2;
    $black = imagecolorallocate($image, 0, 0, 0);

    drawCenteredRtlText($image, 88, $centerX, 1245, 1450, $black, $font, $name);
    drawCenteredRtlText($image, 82, $centerX, 1725, 1050, $black, $font, $coursename);
}

function drawExperienceCertificate($image, $name, $coursename, $dateText)
{
    $font = is_readable('/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf')
        ? '/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf'
        : getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $red = imagecolorallocate($image, 235, 0, 0);
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    drawCenteredRtlText($image, 72, 960, 615, 980, $red, $font, $name);

    imagefilledrectangle($image, 65, 642, 2415, 1230, $white);
    drawCenteredRtlText($image, 52, 1240, 710, 2320, $black, $font, 'قد قامت بالتدريب لدينا على دورة ' . $coursename . ' تحت إشراف الخبير والمحاضر بالدورة');
    drawCenteredRtlText($image, 52, 1240, 795, 2320, $black, $font, 'خلال شهر ' . $dateText . ' ويشهد لها المركز خلال الفترة المذكورة تشهد لها');
    drawCenteredRtlText($image, 52, 1240, 880, 2320, $black, $font, 'بجدّيتها في التطبيق العملي على الحالات من خلال الدورة وتقديم الأفضل');
    drawCenteredRtlText($image, 52, 1240, 965, 2320, $black, $font, 'وتشهد لها بعلاقتها الطيبة مع المدرسين و الحالات');
    drawCenteredRtlText($image, 52, 1240, 1050, 2320, $black, $font, 'إضافة إلى أخلاقها المثالية والتزامها بالمواعيد وتعليمات الإدارة');
}

function drawSeatCertificate($image, $name, $coursename, $dateText)
{
    $font = getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $centerX = imagesx($image) / 2;
    $red = imagecolorallocate($image, 235, 0, 0);

    drawCenteredRtlText($image, 128, $centerX, 2150, 2300, $red, $font, ' ' . $name . ' ');
    drawCenteredRtlText($image, 104, $centerX, 2865, 1700, $red, $font, $coursename);
    drawCenteredRtlText($image, 86, $centerX, 3405, 1500, $red, $font, $dateText);
}

function drawCardCertificate($image, $name, $coursename, $governorate, $nationalId, $approvalDate, $registrationNumber, $photoPath)
{
    $font = is_readable('/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf')
        ? '/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf'
        : getCertificateFont('bold');
    if ($font === false) {
        imagedestroy($image);
        http_response_code(500);
        exit('No readable TTF font was found.');
    }

    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 190, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    $plainFont = is_readable('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')
        ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
        : $font;

    $photo = loadImageFromPath($photoPath);
    if ($photo !== false) {
        $stamp = imagecreatetruecolor(145, 145);
        imagecopy($stamp, $image, 0, 0, 170, 285, 145, 145);
        imagefilledrectangle($image, 61, 179, 215, 374, $white);
        drawImageCover($image, $photo, 64, 190, 144, 171);
        imagecopy($image, $stamp, 170, 285, 0, 0, 145, 145);
        imagedestroy($stamp);
        imagedestroy($photo);
    }

    drawRtlTextInBox($image, 30, 810, 250, 420, $red, $font, $name);
    drawRtlTextInBox($image, 28, 810, 305, 310, $black, $font, $governorate);
    drawPlainTextInBox($image, 23, 805, 357, 320, $black, $plainFont, $nationalId);
    drawRtlTextInBox($image, 28, 760, 415, 380, $black, $font, $coursename);
    drawRtlTextInBox($image, 23, 805, 465, 320, $black, $font, $approvalDate);
    drawPlainTextInBox($image, 23, 805, 520, 320, $black, $plainFont, $registrationNumber);
}

$font = getCertificateFont();
if ($font === false) {
    imagedestroy($image);
    http_response_code(500);
    exit('No readable TTF font was found.');
}

// Data
switch ($template) {
    case 'management':
        $defaultName = "هناء محمود منصور";
        $defaultCourseName = "إدارة الجودة في الخدمات الصحية";
        break;
    case 'eaglestate':
        $defaultName = "أحمد سيد محمد محمد محمد";
        $defaultCourseName = "التخاطب وتعديل السلوك";
        break;
    case 'eagletogother':
    case 'eagletogether':
        $defaultName = "فاطمة محمود علي ابوسمرة";
        $defaultCourseName = "الحجامة";
        break;
    case 'experience':
        $defaultName = "فاطمة محمود علي ابوسمرة";
        $defaultCourseName = "الحجامة";
        break;
    case 'card':
        $defaultName = "أحمد محمد عماد السيد";
        $defaultCourseName = "أخصائي تخاطب و تعديل سلوك";
        break;
    case 'seat':
        $defaultName = "أسماء أحمد عبدالله أحمد";
        $defaultCourseName = "نور البيان";
        break;
    default:
        $defaultName = "علي العنزان علي علي علي";
        $defaultCourseName = "دورة تدريبية في التأهيل والتخاطب والخ الخ الخ";
        break;
}
$name = isset($_GET['name']) ? $_GET['name'] : $defaultName;
$coursename = isset($_GET['course']) ? $_GET['course'] : $defaultCourseName;
$seatDate = isset($_GET['date']) ? $_GET['date'] : "خلال شهر مارس ٢٠٢٦";
$managementDate = isset($_GET['date']) ? $_GET['date'] : "خلال شهر أكتوبر ٢٠٢٤";
$managementHours = isset($_GET['hours']) ? $_GET['hours'] : "١٥";
$eagleStateMonth = isset($_GET['month']) ? $_GET['month'] : "إبريل";
$eagleStateYear = isset($_GET['year']) ? $_GET['year'] : "٢٠٢٦";
$eagleStateHours = isset($_GET['hours']) ? $_GET['hours'] : "١٠";
$experienceDate = isset($_GET['date']) ? $_GET['date'] : "مايو 2026";
$cardGovernorate = isset($_GET['governorate']) ? $_GET['governorate'] : "القاهرة";
$cardNationalId = isset($_GET['national_id']) ? $_GET['national_id'] : "٢٩٦١٢٠٩٣٤٠٠٥١";
$cardApprovalDate = isset($_GET['approval_date']) ? $_GET['approval_date'] : "٢٠٢٦ / ٣ / ١٥";
$cardRegistrationNumber = isset($_GET['registration']) ? $_GET['registration'] : "٢٥٠٩١";
$cardPhoto = resolveUploadedPhoto();
if ($template === 'card' && $cardPhoto === '' && is_readable(__DIR__ . '/personal-image.png')) {
    $cardPhoto = __DIR__ . '/personal-image.png';
}

// Draw text
if ($template === 'management') {
    drawManagementCertificate($image, $name, $coursename, $managementHours, $managementDate);
} elseif ($template === 'eaglestate') {
    drawEagleStateCertificate($image, $name, $coursename, $eagleStateMonth, $eagleStateYear, $eagleStateHours);
} elseif ($template === 'eagletogother' || $template === 'eagletogether') {
    drawEagleTogetherCertificate($image, $name, $coursename);
} elseif ($template === 'experience') {
    drawExperienceCertificate($image, $name, $coursename, $experienceDate);
} elseif ($template === 'card') {
    drawCardCertificate($image, $name, $coursename, $cardGovernorate, $cardNationalId, $cardApprovalDate, $cardRegistrationNumber, $cardPhoto);
} elseif ($template === 'seat') {
    drawSeatCertificate($image, $name, $coursename, $seatDate);
} else {
    $nameRightX = imagesx($image) - $nameX;
    $courseRightX = imagesx($image) - $courseX;
    drawRtlText($image, $name_font_size, $nameRightX, $nameY, $color, $font, $name);
    drawRtlText($image, $course_font_size, $courseRightX, $courseY, $color, $font, $coursename);
}

// Save and Output
// $cert_url = "certificates/" . time() . ".jpg";
$saveName = isset($_GET['save']) ? basename($_GET['save']) : '1.jpg';
if ($saveName !== '0') {
    if (!preg_match('/^[a-zA-Z0-9._-]+\.jpe?g$/', $saveName)) {
        $saveName = '1.jpg';
    }

    $certificatesDir = __DIR__ . '/certificates';
    if (!is_dir($certificatesDir)) {
        mkdir($certificatesDir, 0755, true);
    }

    $cert_url = $certificatesDir . '/' . $saveName;
    imagejpeg($image, $cert_url, 90);
}
imagejpeg($image, null, 90);
imagedestroy($image);
?>

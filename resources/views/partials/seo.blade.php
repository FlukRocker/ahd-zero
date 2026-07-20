@php
    $defaultTitle = config('app.name', 'Anime HD Zero');
    $defaultDescription = 'ดูอนิเมะออนไลน์ฟรี ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero';

    $seoTitle = trim($__env->yieldContent('title', $defaultTitle));
    $seoDescription = trim($__env->yieldContent('description', $defaultDescription));
    $seoImage = trim($__env->yieldContent('og_image', asset('og-default.jpg')));
    $seoType = trim($__env->yieldContent('og_type', 'website'));
    $seoRobots = trim($__env->yieldContent('robots', 'index,follow,max-image-preview:large,max-snippet:-1'));
    $seoUrl = url()->current();

    // <title> tag capped at ~60 chars for SERP; og/twitter keep full string.
    $titleTag = mb_strlen($seoTitle) > 60 ? mb_substr($seoTitle, 0, 59) . '…' : $seoTitle;
@endphp

<title>{{ $titleTag }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoUrl }}">

<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $seoImage }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

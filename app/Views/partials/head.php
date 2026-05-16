<!DOCTYPE html>
<html lang=" {{$page_language ?? 'en' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{ #<?php if (isset($meta_description)): ?>
        <meta name="description" content="<?= htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <?php if (isset($meta_keywords)): ?>
        <meta name="keywords" content="<?= htmlspecialchars($meta_keywords, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <?php if (isset($favicon_url)): ?>
        <link rel="icon" href="<?= htmlspecialchars($favicon_url, ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
    <?php endif; ?>

    <?php if (isset($css_files) && is_array($css_files)): ?>
        <?php foreach ($css_files as $css_file): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($js_files) && is_array($js_files)): ?>
        <?php foreach ($js_files as $js_file): ?>
            <script src="<?= htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>}}

    <title>{{$title ?? config("app.info.name", "CMS")}}</title>
</head>
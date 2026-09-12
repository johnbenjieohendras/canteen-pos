<?php
$pageTitle = $pageTitle ?? "Back Office";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="/canteen_pos/assets/images/logo.jpg">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?> | Canteen POS
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/canteen_pos/assets/css/dashboard.css"
    >

</head>

<body>

<div class="app-wrapper">
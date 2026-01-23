<?php
include '../../app/config.php';
include '../../global.php';

$access_token = $_COOKIE['access_token'] ?? null;

if (!$access_token) {
    header('Location: /login?redirect=/developer/docs');
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$stmt = $mysqli->prepare("SELECT user_id FROM user_tokens WHERE access_token = ?");
$stmt->bind_param("s", $access_token);
$stmt->execute();
$result = $stmt->get_result();
$user_id = null;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
}
$stmt->close();

if (!$user_id) {
    header('Location: /login?redirect=/developer/docs');
    exit;
}

$stmt = $mysqli->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$username = 'Unknown User';
$profile_picture = 'https://chat.wokki20.nl/uploads/profile-pictures/default-profile.png';
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $username = htmlspecialchars($row['username']);
    $profile_picture = $row['profile_picture'];
}
$stmt->close();

$sidebar = '
<a class="sidebar-item" href="/developer/portal">
    <span class="material-symbols-rounded sidebar-item-icon">home</span>
    <span class="sidebar-item-text">Portal</span>
</a>
<a class="sidebar-item" href="/developer/bots">
    <span class="material-symbols-rounded sidebar-item-icon">smart_toy</span>
    <span class="sidebar-item-text">Bots</span>
</a>
<a class="sidebar-item active" href="/developer/docs">
    <span class="material-symbols-rounded sidebar-item-icon">book_2</span>
    <span class="sidebar-item-text">Documentation</span>
</a>
';

$themeParts = explode(' ', $theme ?? '');
$themeColor = $themeParts[0] ?? '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="generator" content="pdoc 14.7.0"/>
    <title>wokkichat API documentation</title>
<link rel="icon" href="https://chat.wokki20.nl/favicon.ico"/>

    <style>/*! * Bootstrap Reboot v5.0.0 (https://getbootstrap.com/) * Copyright 2011-2021 The Bootstrap Authors * Copyright 2011-2021 Twitter, Inc. * Licensed under MIT (https://github.com/twbs/bootstrap/blob/main/LICENSE) * Forked from Normalize.css, licensed MIT (https://github.com/necolas/normalize.css/blob/master/LICENSE.md) */*,::after,::before{box-sizing:border-box}@media (prefers-reduced-motion:no-preference){:root{scroll-behavior:smooth}}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans","Liberation Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-size:1rem;font-weight:400;line-height:1.5;color:#212529;background-color:#fff;-webkit-text-size-adjust:100%;-webkit-tap-highlight-color:transparent}hr{margin:1rem 0;color:inherit;background-color:currentColor;border:0;opacity:.25}hr:not([size]){height:1px}h1,h2,h3,h4,h5,h6{margin-top:0;margin-bottom:.5rem;font-weight:500;line-height:1.2}h1{font-size:calc(1.375rem + 1.5vw)}@media (min-width:1200px){h1{font-size:2.5rem}}h2{font-size:calc(1.325rem + .9vw)}@media (min-width:1200px){h2{font-size:2rem}}h3{font-size:calc(1.3rem + .6vw)}@media (min-width:1200px){h3{font-size:1.75rem}}h4{font-size:calc(1.275rem + .3vw)}@media (min-width:1200px){h4{font-size:1.5rem}}h5{font-size:1.25rem}h6{font-size:1rem}p{margin-top:0;margin-bottom:1rem}abbr[data-bs-original-title],abbr[title]{-webkit-text-decoration:underline dotted;text-decoration:underline dotted;cursor:help;-webkit-text-decoration-skip-ink:none;text-decoration-skip-ink:none}address{margin-bottom:1rem;font-style:normal;line-height:inherit}ol,ul{padding-left:2rem}dl,ol,ul{margin-top:0;margin-bottom:1rem}ol ol,ol ul,ul ol,ul ul{margin-bottom:0}dt{font-weight:700}dd{margin-bottom:.5rem;margin-left:0}blockquote{margin:0 0 1rem}b,strong{font-weight:bolder}small{font-size:.875em}mark{padding:.2em;background-color:#fcf8e3}sub,sup{position:relative;font-size:.75em;line-height:0;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}a{color:#0d6efd;text-decoration:underline}a:hover{color:#0a58ca}a:not([href]):not([class]),a:not([href]):not([class]):hover{color:inherit;text-decoration:none}code,kbd,pre,samp{font-family:SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:1em;direction:ltr;unicode-bidi:bidi-override}pre{display:block;margin-top:0;margin-bottom:1rem;overflow:auto;font-size:.875em}pre code{font-size:inherit;color:inherit;word-break:normal}code{font-size:.875em;color:#d63384;word-wrap:break-word}a>code{color:inherit}kbd{padding:.2rem .4rem;font-size:.875em;color:#fff;background-color:#212529;border-radius:.2rem}kbd kbd{padding:0;font-size:1em;font-weight:700}figure{margin:0 0 1rem}img,svg{vertical-align:middle}table{caption-side:bottom;border-collapse:collapse}caption{padding-top:.5rem;padding-bottom:.5rem;color:#6c757d;text-align:left}th{text-align:inherit;text-align:-webkit-match-parent}tbody,td,tfoot,th,thead,tr{border-color:inherit;border-style:solid;border-width:0}label{display:inline-block}button{border-radius:0}button:focus:not(:focus-visible){outline:0}button,input,optgroup,select,textarea{margin:0;font-family:inherit;font-size:inherit;line-height:inherit}button,select{text-transform:none}[role=button]{cursor:pointer}select{word-wrap:normal}select:disabled{opacity:1}[list]::-webkit-calendar-picker-indicator{display:none}[type=button],[type=reset],[type=submit],button{-webkit-appearance:button}[type=button]:not(:disabled),[type=reset]:not(:disabled),[type=submit]:not(:disabled),button:not(:disabled){cursor:pointer}::-moz-focus-inner{padding:0;border-style:none}textarea{resize:vertical}fieldset{min-width:0;padding:0;margin:0;border:0}legend{float:left;width:100%;padding:0;margin-bottom:.5rem;font-size:calc(1.275rem + .3vw);line-height:inherit}@media (min-width:1200px){legend{font-size:1.5rem}}legend+*{clear:left}::-webkit-datetime-edit-day-field,::-webkit-datetime-edit-fields-wrapper,::-webkit-datetime-edit-hour-field,::-webkit-datetime-edit-minute,::-webkit-datetime-edit-month-field,::-webkit-datetime-edit-text,::-webkit-datetime-edit-year-field{padding:0}::-webkit-inner-spin-button{height:auto}[type=search]{outline-offset:-2px;-webkit-appearance:textfield}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-color-swatch-wrapper{padding:0}::file-selector-button{font:inherit}::-webkit-file-upload-button{font:inherit;-webkit-appearance:button}output{display:inline-block}iframe{border:0}summary{display:list-item;cursor:pointer}progress{vertical-align:baseline}[hidden]{display:none!important}</style>
    <style>/*! syntax-highlighting.css */pre{line-height:125%;}td.linenos .normal{color:inherit; background-color:transparent; padding-left:5px; padding-right:5px;}span.linenos{color:inherit; background-color:transparent; padding-left:5px; padding-right:5px;}td.linenos .special{color:#000000; background-color:#ffffc0; padding-left:5px; padding-right:5px;}span.linenos.special{color:#000000; background-color:#ffffc0; padding-left:5px; padding-right:5px;}.pdoc-code .hll{background-color:#49483e}.pdoc-code{background:#232629; color:#CCC}.pdoc-code .c{color:#777; font-style:italic}.pdoc-code .err{color:#A61717; background-color:#E3D2D2}.pdoc-code .esc{color:#CCC}.pdoc-code .g{color:#CCC}.pdoc-code .k{color:#7686BB; font-weight:bold}.pdoc-code .l{color:#CCC}.pdoc-code .n{color:#CCC}.pdoc-code .o{color:#CCC}.pdoc-code .x{color:#CCC}.pdoc-code .p{color:#CCC}.pdoc-code .ch{color:#777; font-style:italic}.pdoc-code .cm{color:#777; font-style:italic}.pdoc-code .cp{color:#777; font-style:italic}.pdoc-code .cpf{color:#777; font-style:italic}.pdoc-code .c1{color:#777; font-style:italic}.pdoc-code .cs{color:#777; font-style:italic}.pdoc-code .gd{color:#CCC}.pdoc-code .ge{color:#CCC}.pdoc-code .ges{color:#CCC}.pdoc-code .gr{color:#CCC}.pdoc-code .gh{color:#CCC}.pdoc-code .gi{color:#CCC}.pdoc-code .go{color:#CCC}.pdoc-code .gp{color:#FFF}.pdoc-code .gs{color:#CCC}.pdoc-code .gu{color:#CCC}.pdoc-code .gt{color:#CCC}.pdoc-code .kc{color:#7686BB; font-weight:bold}.pdoc-code .kd{color:#7686BB; font-weight:bold}.pdoc-code .kn{color:#7686BB; font-weight:bold}.pdoc-code .kp{color:#7686BB; font-weight:bold}.pdoc-code .kr{color:#7686BB; font-weight:bold}.pdoc-code .kt{color:#7686BB; font-weight:bold}.pdoc-code .ld{color:#CCC}.pdoc-code .m{color:#4FB8CC}.pdoc-code .s{color:#51CC99}.pdoc-code .na{color:#CCC}.pdoc-code .nb{color:#CCC}.pdoc-code .nc{color:#CCC}.pdoc-code .no{color:#CCC}.pdoc-code .nd{color:#CCC}.pdoc-code .ni{color:#CCC}.pdoc-code .ne{color:#CCC}.pdoc-code .nf{color:#6A6AFF}.pdoc-code .nl{color:#CCC}.pdoc-code .nn{color:#CCC}.pdoc-code .nx{color:#E2828E}.pdoc-code .py{color:#CCC}.pdoc-code .nt{color:#CCC}.pdoc-code .nv{color:#7AB4DB; font-weight:bold}.pdoc-code .ow{color:#CCC}.pdoc-code .pm{color:#CCC}.pdoc-code .w{color:#BBB}.pdoc-code .mb{color:#4FB8CC}.pdoc-code .mf{color:#4FB8CC}.pdoc-code .mh{color:#4FB8CC}.pdoc-code .mi{color:#4FB8CC}.pdoc-code .mo{color:#4FB8CC}.pdoc-code .sa{color:#51CC99}.pdoc-code .sb{color:#51CC99}.pdoc-code .sc{color:#51CC99}.pdoc-code .dl{color:#51CC99}.pdoc-code .sd{color:#51CC99}.pdoc-code .s2{color:#51CC99}.pdoc-code .se{color:#51CC99}.pdoc-code .sh{color:#51CC99}.pdoc-code .si{color:#51CC99}.pdoc-code .sx{color:#51CC99}.pdoc-code .sr{color:#51CC99}.pdoc-code .s1{color:#51CC99}.pdoc-code .ss{color:#51CC99}.pdoc-code .bp{color:#CCC}.pdoc-code .fm{color:#6A6AFF}.pdoc-code .vc{color:#7AB4DB; font-weight:bold}.pdoc-code .vg{color:#BE646C; font-weight:bold}.pdoc-code .vi{color:#7AB4DB; font-weight:bold}.pdoc-code .vm{color:#7AB4DB; font-weight:bold}.pdoc-code .il{color:#4FB8CC}</style>
    <style>/*! theme.css */@import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900');:root{--pdoc-background:var(--clr-popup-a0);--text:var(--clr-text-a0);}body{font-family:'Inter', sans-serif;;}.pdoc{--text:var(--clr-text-a0);--muted:#8c8c8c;--link:var(--clr-primary-a0);--link-hover:var(--clr-primary-a20);--code:#000000;--active:#1a1a1a;--accent:var(--clr-popup-a0);--accent2:var(--clr-popup-a20);--nav-hover:rgba(26, 26, 26, 0.5);--name:#6A6AFF; --def:#7686BB; --annotation:#CCC;}nav{  position:fixed;  top:75px;  left:0;  height:calc(100vh - 75px);  width:230px !important;  max-width:230px;  padding:15px;  transition:transform 0.25s ease;}nav #navtoggle{display:none;}@media (max-width:769px){  nav{transform:translateX(-100%); }  nav:has(#togglestate:checked){transform:translateX(0); }  .header .logo{margin-left:50px; }    .header #navtoggle{right:unset; }  main.pdoc{width:100% !important; }}.header{width:100%;height:75px;position:fixed;top:0;left:0;background-color:var(--clr-popup-a0);border-bottom:1px solid var(--clr-popup-a20);color:var(--text);padding:15px;display:flex;flex-direction:row;align-items:center;justify-content:space-between;z-index:1;}.logo{display:flex;flex-direction:row;align-items:center;gap:5px;width:max-content;}.logo img{height:35px;object-fit:cover;user-select:none;}.logo p{margin:0px;font-weight:var(--font-weight-semibold);user-select:none;}.top-bar-profile{height:24px;display:flex;flex-direction:row;align-items:center;gap:10px;margin-right:25px;border-radius:10px;padding:10px;width:max-content;transition:background-color 0.1s ease;}.top-bar-profile-picture{width:30px;height:30px;border-radius:50%;user-select:none;}.top-bar-username{font-size:16px;font-weight:var(--font-weight-semibold);user-select:none;margin:0px;}@media (max-width:500px){.top-bar-profile{display:none;}}main.pdoc{position:absolute;top:75px;right:0;height:calc(100% - 75px - 30px);width:calc(100% - 231px - 30px);overflow-y:auto;padding:15px;z-index:0;}nav h2{font-size:16px;font-weight:var(--font-weight-semibold) !important;color:var(--clr-text-a30);}nav.pdoc input[type="search"]{background:var(--clr-input-bg-dark);border:1px solid var(--clr-text-a50);border-radius:9px;padding:10px 15px;font-size:16px;color:var(--clr-text-a0);-webkit-box-shadow:0 0 0 1000px var(--clr-input-bg-dark) inset !important;transition:outline 0.3s ease-in-out, color 0.3s ease-in-out, border 0.3s ease-in-out,--webkit-box-shadow 0.3s ease-in-out;}nav.pdoc input[type="search"]:-webkit-autofill{-webkit-box-shadow:0 0 0 1000px var(--clr-input-bg-darkest) inset !important;-webkit-text-fill-color:var(--clr-text-a0) !important;border:1px solid var(--clr-primary-a20) !important;background:var(--clr-input-bg-darkest) !important;}nav.pdoc input[type="search"]:-moz-autofill{box-shadow:0 0 0 1000px var(--clr-input-bg-darkest) inset !important;-moz-text-fill-color:var(--clr-text-a0) !important;border:1px solid var(--clr-primary-a20) !important;background:var(--clr-input-bg-darkest) !important;}nav.pdoc input[type="search"]:focus{outline:var(--clr-primary-a0) solid 1px;box-shadow:0 0 0 2px var(--clr-primary-a20);}</style>
    <style>/*! layout.css */html, body{width:100%;height:100%;}html, main{scroll-behavior:smooth;}body{background-color:var(--pdoc-background);}@media (max-width:769px){#navtoggle{cursor:pointer;position:absolute;width:50px;height:40px;top:1rem;right:1rem;border-color:var(--text);color:var(--text);display:flex;opacity:0.8;z-index:999;}#navtoggle:hover{opacity:1;}#togglestate + div{display:none;}#togglestate:checked + div{display:inherit;}main, header{padding:2rem 3vw;}header + main{margin-top:-3rem;}.git-button{display:none !important;}nav input[type="search"]{max-width:77%;}nav input[type="search"]:first-child{margin-top:-6px;}nav input[type="search"]:valid ~ *{display:none !important;}}@media (min-width:770px){:root{--sidebar-width:clamp(12.5rem, 28vw, 22rem);}nav{position:fixed;overflow:auto;height:100vh;width:var(--sidebar-width);}main, header{padding:3rem 2rem 3rem calc(var(--sidebar-width) + 3rem);width:calc(54rem + var(--sidebar-width));max-width:100%;}header + main{margin-top:-4rem;}#navtoggle{display:none;}}#togglestate{position:absolute;height:0;opacity:0;}nav.pdoc{--pad:clamp(0.5rem, 2vw, 1.75rem);--indent:1.5rem;background-color:var(--accent);border-right:1px solid var(--accent2);box-shadow:0 0 20px rgba(50, 50, 50, .2) inset;padding:0 0 0 var(--pad);overflow-wrap:anywhere;scrollbar-width:thin; scrollbar-color:var(--accent2) transparent; z-index:1}nav.pdoc::-webkit-scrollbar{width:.4rem; }nav.pdoc::-webkit-scrollbar-thumb{background-color:var(--accent2); }nav.pdoc > div{padding:var(--pad) 0;}nav.pdoc .module-list-button{display:inline-flex;align-items:center;color:var(--text);border-color:var(--muted);margin-bottom:1rem;}nav.pdoc .module-list-button:hover{border-color:var(--text);}nav.pdoc input[type=search]{display:block;outline-offset:0;width:calc(100% - var(--pad));}nav.pdoc .logo{max-width:calc(100% - var(--pad));max-height:35vh;display:block;margin:0 auto 1rem;transform:translate(calc(-.5 * var(--pad)), 0);}nav.pdoc ul{list-style:none;padding-left:0;}nav.pdoc > div > ul{margin-left:calc(0px - var(--pad));}nav.pdoc li a{padding:.2rem 0 .2rem calc(var(--pad) + var(--indent));}nav.pdoc > div > ul > li > a{padding-left:var(--pad);}nav.pdoc li{transition:all 100ms;}nav.pdoc li:hover{background-color:var(--nav-hover);}nav.pdoc a, nav.pdoc a:hover{color:var(--text);}nav.pdoc a{display:block;}nav.pdoc > h2:first-of-type{margin-top:1.5rem;}nav.pdoc .class:before{content:"class ";color:var(--muted);}nav.pdoc .function:after{content:"()";color:var(--muted);}nav.pdoc footer:before{content:"";display:block;width:calc(100% - var(--pad));border-top:solid var(--accent2) 1px;margin-top:1.5rem;padding-top:.5rem;}nav.pdoc footer{font-size:small;}</style>
    <style>/*! content.css */.pdoc{color:var(--text);box-sizing:border-box;line-height:1.5;background:none;}.pdoc .pdoc-button{cursor:pointer;display:inline-block;border:solid black 1px;border-radius:2px;font-size:.75rem;padding:calc(0.5em - 1px) 1em;transition:100ms all;}.pdoc .alert{padding:1rem 1rem 1rem calc(1.5rem + 24px);border:1px solid transparent;border-radius:.25rem;background-repeat:no-repeat;background-position:.75rem center;margin-bottom:1rem;}.pdoc .alert > em{display:none;}.pdoc .alert > *:last-child{margin-bottom:0;}.pdoc .alert.note {color:#084298;background-color:#cfe2ff;border-color:#b6d4fe;background-image:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20fill%3D%22%23084298%22%20viewBox%3D%220%200%2016%2016%22%3E%3Cpath%20d%3D%22M8%2016A8%208%200%201%200%208%200a8%208%200%200%200%200%2016zm.93-9.412-1%204.705c-.07.34.029.533.304.533.194%200%20.487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703%200-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381%202.29-.287zM8%205.5a1%201%200%201%201%200-2%201%201%200%200%201%200%202z%22/%3E%3C/svg%3E");}.pdoc .alert.warning{color:#664d03;background-color:#fff3cd;border-color:#ffecb5;background-image:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20fill%3D%22%23664d03%22%20viewBox%3D%220%200%2016%2016%22%3E%3Cpath%20d%3D%22M8.982%201.566a1.13%201.13%200%200%200-1.96%200L.165%2013.233c-.457.778.091%201.767.98%201.767h13.713c.889%200%201.438-.99.98-1.767L8.982%201.566zM8%205c.535%200%20.954.462.9.995l-.35%203.507a.552.552%200%200%201-1.1%200L7.1%205.995A.905.905%200%200%201%208%205zm.002%206a1%201%200%201%201%200%202%201%201%200%200%201%200-2z%22/%3E%3C/svg%3E");}.pdoc .alert.danger{color:#842029;background-color:#f8d7da;border-color:#f5c2c7;background-image:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2224%22%20height%3D%2224%22%20fill%3D%22%23842029%22%20viewBox%3D%220%200%2016%2016%22%3E%3Cpath%20d%3D%22M5.52.359A.5.5%200%200%201%206%200h4a.5.5%200%200%201%20.474.658L8.694%206H12.5a.5.5%200%200%201%20.395.807l-7%209a.5.5%200%200%201-.873-.454L6.823%209.5H3.5a.5.5%200%200%201-.48-.641l2.5-8.5z%22/%3E%3C/svg%3E");}.pdoc .visually-hidden{position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;overflow:hidden !important;clip:rect(0, 0, 0, 0) !important;white-space:nowrap !important;border:0 !important;}.pdoc h1, .pdoc h2, .pdoc h3{font-weight:300;margin:.3em 0;padding:.2em 0;}.pdoc > section:not(.module-info) h1{font-size:1.5rem;font-weight:500;}.pdoc > section:not(.module-info) h2{font-size:1.4rem;font-weight:500;}.pdoc > section:not(.module-info) h3{font-size:1.3rem;font-weight:500;}.pdoc > section:not(.module-info) h4{font-size:1.2rem;}.pdoc > section:not(.module-info) h5{font-size:1.1rem;}.pdoc a{text-decoration:none;color:var(--link);}.pdoc a:hover{color:var(--link-hover);}.pdoc blockquote{margin-left:2rem;}.pdoc pre{border-top:1px solid var(--accent2);border-bottom:1px solid var(--accent2);margin-top:0;margin-bottom:1em;padding:.5rem 0 .5rem .5rem;overflow-x:auto;background-color:var(--code);}.pdoc code{color:var(--text);padding:.2em .4em;margin:0;font-size:85%;background-color:var(--accent);border-radius:6px;}.pdoc a > code{color:inherit;}.pdoc pre > code{display:inline-block;font-size:inherit;background:none;border:none;padding:0;}.pdoc > section:not(.module-info){margin-bottom:1.5rem;}.pdoc .modulename{margin-top:0;font-weight:bold;}.pdoc .modulename a{color:var(--link);transition:100ms all;}.pdoc .git-button{float:right;border:solid var(--link) 1px;}.pdoc .git-button:hover{background-color:var(--link);color:var(--pdoc-background);}.view-source-toggle-state,.view-source-toggle-state ~ .pdoc-code{display:none;}.view-source-toggle-state:checked ~ .pdoc-code{display:block;}.view-source-button{display:inline-block;float:right;font-size:.75rem;line-height:1.5rem;color:var(--muted);padding:0 .4rem 0 1.3rem;cursor:pointer;text-indent:-2px;}.view-source-button > span{visibility:hidden;}.module-info .view-source-button{float:none;display:flex;justify-content:flex-end;margin:-1.2rem .4rem -.2rem 0;}.view-source-button::before{position:absolute;content:"View Source";display:list-item;list-style-type:disclosure-closed;}.view-source-toggle-state:checked ~ .attr .view-source-button::before,.view-source-toggle-state:checked ~ .view-source-button::before{list-style-type:disclosure-open;}.pdoc .docstring{margin-bottom:1.5rem;}.pdoc section:not(.module-info) .docstring{margin-left:clamp(0rem, 5vw - 2rem, 1rem);}.pdoc .docstring .pdoc-code{margin-left:1em;margin-right:1em;}.pdoc h1:target,.pdoc h2:target,.pdoc h3:target,.pdoc h4:target,.pdoc h5:target,.pdoc h6:target,.pdoc .pdoc-code > pre > span:target{background-color:var(--active);box-shadow:-1rem 0 0 0 var(--active);}.pdoc .pdoc-code > pre > span:target{display:block;}.pdoc div:target > .attr,.pdoc section:target > .attr,.pdoc dd:target > a{background-color:var(--active);}.pdoc *{scroll-margin:2rem;}.pdoc .pdoc-code .linenos{user-select:none;}.pdoc .attr:hover{filter:contrast(0.95);}.pdoc section, .pdoc .classattr{position:relative;}.pdoc .headerlink{--width:clamp(1rem, 3vw, 2rem);position:absolute;top:0;left:calc(0rem - var(--width));transition:all 100ms ease-in-out;opacity:0;}.pdoc .headerlink::before{content:"#";display:block;text-align:center;width:var(--width);height:2.3rem;line-height:2.3rem;font-size:1.5rem;}.pdoc .attr:hover ~ .headerlink,.pdoc *:target > .headerlink,.pdoc .headerlink:hover{opacity:1;}.pdoc .attr{display:block;margin:.5rem 0 .5rem;padding:.4rem .4rem .4rem 1rem;background-color:var(--accent);overflow-x:auto;}.pdoc .classattr{margin-left:2rem;}.pdoc .name{color:var(--name);font-weight:bold;}.pdoc .def{color:var(--def);font-weight:bold;}.pdoc .signature{background-color:transparent;}.pdoc .param, .pdoc .return-annotation{white-space:pre;}.pdoc .signature.multiline .param{display:block;}.pdoc .signature.condensed .param{display:inline-block;}.pdoc .annotation{color:var(--annotation);}.pdoc .view-value-toggle-state,.pdoc .view-value-toggle-state ~ .default_value{display:none;}.pdoc .view-value-toggle-state:checked ~ .default_value{display:inherit;}.pdoc .view-value-button{font-size:.5rem;vertical-align:middle;border-style:dashed;margin-top:-0.1rem;}.pdoc .view-value-button:hover{background:white;}.pdoc .view-value-button::before{content:"show";text-align:center;width:2.2em;display:inline-block;}.pdoc .view-value-toggle-state:checked ~ .view-value-button::before{content:"hide";}.pdoc .inherited{margin-left:2rem;}.pdoc .inherited dt{font-weight:700;}.pdoc .inherited dt, .pdoc .inherited dd{display:inline;margin-left:0;margin-bottom:.5rem;}.pdoc .inherited dd:not(:last-child):after{content:", ";}.pdoc .inherited .class:before{content:"class ";}.pdoc .inherited .function a:after{content:"()";}.pdoc .search-result .docstring{overflow:auto;max-height:25vh;}.pdoc .search-result.focused > .attr{background-color:var(--active);}.pdoc .attribution{margin-top:2rem;display:block;opacity:0.5;transition:all 200ms;filter:grayscale(100%);}.pdoc .attribution:hover{opacity:1;filter:grayscale(0%);}.pdoc .attribution img{margin-left:5px;height:35px;vertical-align:middle;width:70px;transition:all 200ms;}.pdoc table{display:block;width:max-content;max-width:100%;overflow:auto;margin-bottom:1rem;}.pdoc table th{font-weight:600;}.pdoc table th, .pdoc table td{padding:6px 13px;border:1px solid var(--accent2);}</style>
    <style>/*! custom.css */</style></head>
<body>
    <nav class="pdoc">
        <label id="navtoggle" for="togglestate" class="pdoc-button"><svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'><path stroke-linecap='round' stroke="currentColor" stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/></svg></label>
        <input id="togglestate" type="checkbox" aria-hidden="true" tabindex="-1">
        <div>
    <?php echo $sidebar; ?>

            <input type="search" placeholder="Search..." role="searchbox" aria-label="search"
                   pattern=".+" required>

            <h2>Contents</h2>
            <ul>
  <li><a href="#intro">Intro</a></li>
  <li><a href="#example">Example</a></li>
  <li><a href="#docs">Docs</a></li>
</ul>


            <h2>Submodules</h2>
            <ul>
                    <li><a href="wokkichat/enums.html">enums</a></li>
                    <li><a href="wokkichat/types.html">types</a></li>
                    <li><a href="wokkichat/addons.html">addons</a></li>
            </ul>

            <h2>API Documentation</h2>
                <ul class="memberlist">
            <li>
                    <a class="class" href="#Bot">Bot</a>
                            <ul class="memberlist">
                        <li>
                                <a class="function" href="#Bot.__init__">Bot</a>
                        </li>
                        <li>
                                <a class="variable" href="#Bot.bot_token">bot_token</a>
                        </li>
                        <li>
                                <a class="variable" href="#Bot.server_id">server_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#Bot.connected">connected</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.event">event</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.command">command</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.connect">connect</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot._async_connect">_async_connect</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.disconnect">disconnect</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.get_user">get_user</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.is_typing">is_typing</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.send_message">send_message</a>
                        </li>
                        <li>
                                <a class="function" href="#Bot.edit_message">edit_message</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#TypingInfo">TypingInfo</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#TypingInfo.server_id">server_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#TypingInfo.channel_id">channel_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#TypingInfo.user_id">user_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#TypingInfo.when">when</a>
                        </li>
                        <li>
                                <a class="function" href="#TypingInfo.get_user">get_user</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#User">User</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#User.id">id</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.username">username</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.display_name">display_name</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.bio">bio</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.status">status</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.profile_picture">profile_picture</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.profile_banner">profile_banner</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.premium">premium</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.bot">bot</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.staff">staff</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.developer">developer</a>
                        </li>
                        <li>
                                <a class="variable" href="#User.created_at">created_at</a>
                        </li>
                        <li>
                                <a class="function" href="#User.is_typing">is_typing</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#Message">Message</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#Message.username">username</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.user_id">user_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.profile_picture">profile_picture</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.id">id</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.bot_message">bot_message</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.message">message</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.created_at">created_at</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.channel">channel</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.parent_message_id">parent_message_id</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.assets">assets</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.command">command</a>
                        </li>
                        <li>
                                <a class="variable" href="#Message.command_user_id">command_user_id</a>
                        </li>
                        <li>
                                <a class="function" href="#Message.get_user">get_user</a>
                        </li>
                        <li>
                                <a class="function" href="#Message.reply">reply</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#Server">Server</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#Server.id">id</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#Channel">Channel</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#Channel.id">id</a>
                        </li>
                        <li>
                                <a class="variable" href="#Channel.server">server</a>
                        </li>
                        <li>
                                <a class="function" href="#Channel.send_message">send_message</a>
                        </li>
                </ul>

            </li>
            <li>
                    <a class="class" href="#ctx">ctx</a>
                            <ul class="memberlist">
                        <li>
                                <a class="variable" href="#ctx.command">command</a>
                        </li>
                        <li>
                                <a class="variable" href="#ctx.user">user</a>
                        </li>
                        <li>
                                <a class="variable" href="#ctx.channel">channel</a>
                        </li>
                        <li>
                                <a class="function" href="#ctx.reply">reply</a>
                        </li>
                </ul>

            </li>
    </ul>


<footer>
    Copyright 2026 Wokki Chat<br>
    – built with pdoc –
</footer>

</div>
    </nav>
<script>
    document.documentElement.classList.add('<?php echo $themeColor; ?>');
</script>
<link rel="stylesheet" href="https://chat.wokki20.nl/assets/styles/colors.css">
<link rel="stylesheet" href="https://chat.wokki20.nl/assets/styles/developer/main.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<div class="header">
    <label id="navtoggle" for="togglestate" class="pdoc-button"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30"><path stroke-linecap="round" stroke="currentColor" stroke-miterlimit="10" stroke-width="2" d="M4 7h22M4 15h22M4 23h22"></path></svg></label>
    <div class="logo">
        <img src="https://chat.wokki20.nl/assets/images/logo-purple.png" alt="Wokki Chat Logo">
        <p>For Developers</p>
    </div>

    <div class="top-bar-profile" id="top-bar-profile">
        <img
            draggable="false"
            class="top-bar-profile-picture"
            src="<?php echo $profile_picture; ?>"
        >
        <p class="top-bar-username">
            <?php echo $username; ?>
        </p>
    </div>
</div>

    <main class="pdoc">
            <section class="module-info">
                        <a class="pdoc-button git-button" href="https://github.com/levkris/Wokki-Chat-Python-SDK/tree/main/wokkichat/__init__.py">Edit on GitHub</a>
                    <h1 class="modulename">
wokkichat    </h1>

                        <div class="docstring"><h2 id="intro">Intro</h2>

<p>Welcome to the documentation of the Python SDK for <a href="https://chat.wokki20.nl/">Wokki Chat</a>! 📖</p>

<p><em>✏️ Want to help us write the docs?
Read <a href="https://github.com/levkris/Wokki-Chat-Python-SDK/blob/wokkichat/CONTRIBUTING.md">CONTRIBUTING.md</a></em></p>

<p>Everyone knows a chat platform without bots is dull and boring,
but not Wokki Chat! The contributors to this library have worked
hard to make the most amazing yet simplistic library for you,
so enjoy!</p>

<pre><code>BLEEP-BLOOP, ENJOY MAKING BOTS! 🤖
- Bjarnos
</code></pre>

<hr />

<h2 id="example">Example</h2>

<p>We know how hard it is to get started with a
library you know nothing about,
so here's an example script to get you going:</p>

<div class="pdoc-code codehilite">
<pre><span></span><code><span class="kn">import</span><span class="w"> </span><span class="nn">dotenv</span><span class="o">,</span><span class="w"> </span><span class="nn">os</span>
<span class="kn">from</span><span class="w"> </span><span class="nn">wokkichat</span><span class="w"> </span><span class="kn">import</span> <span class="n">Bot</span><span class="p">,</span> <span class="n">ctx</span>

<span class="n">dotenv</span><span class="o">.</span><span class="n">load_dotenv</span><span class="p">()</span>
<span class="n">bot</span> <span class="o">=</span> <span class="n">Bot</span><span class="p">(</span><span class="n">os</span><span class="o">.</span><span class="n">environ</span><span class="p">[</span><span class="s2">&quot;TOKEN&quot;</span><span class="p">])</span>

<span class="nd">@bot</span><span class="o">.</span><span class="n">command</span><span class="p">()</span>
<span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">echo</span><span class="p">(</span><span class="n">c</span><span class="p">:</span> <span class="n">ctx</span><span class="p">,</span> <span class="n">text</span><span class="p">:</span> <span class="nb">str</span><span class="p">):</span>
    <span class="k">await</span> <span class="n">c</span><span class="o">.</span><span class="n">reply</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Echoed: </span><span class="si">{</span><span class="n">text</span><span class="si">}</span><span class="s2">&quot;</span><span class="p">)</span>

<span class="n">bot</span><span class="o">.</span><span class="n">connect</span><span class="p">()</span>
</code></pre>
</div>

<p>After installing <code>python-dotenv</code> with pip,
and running the code, try out <code>/echo</code> and see what happens!</p>

<hr />

<h2 id="docs">Docs</h2>
</div>

                        <input id="mod-wokkichat-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">

                        <label class="view-source-button" for="mod-wokkichat-view-source"><span>View Source</span></label>

                        <div class="pdoc-code codehilite"><pre><span></span><span id="L-1"><a href="#L-1"><span class="linenos"> 1</span></a><span class="sd">&quot;&quot;&quot;</span>
</span><span id="L-2"><a href="#L-2"><span class="linenos"> 2</span></a><span class="sd">.. include:: ../subdocs/main.md</span>
</span><span id="L-3"><a href="#L-3"><span class="linenos"> 3</span></a><span class="sd">&quot;&quot;&quot;</span>
</span><span id="L-4"><a href="#L-4"><span class="linenos"> 4</span></a>
</span><span id="L-5"><a href="#L-5"><span class="linenos"> 5</span></a><span class="kn">from</span><span class="w"> </span><span class="nn">.main</span><span class="w"> </span><span class="kn">import</span> <span class="n">Bot</span>
</span><span id="L-6"><a href="#L-6"><span class="linenos"> 6</span></a><span class="kn">from</span><span class="w"> </span><span class="nn">.main</span><span class="w"> </span><span class="kn">import</span> <span class="n">TypingInfo</span><span class="p">,</span> <span class="n">User</span><span class="p">,</span> <span class="n">Message</span><span class="p">,</span> <span class="n">Server</span><span class="p">,</span> <span class="n">Channel</span><span class="p">,</span> <span class="n">ctx</span>
</span><span id="L-7"><a href="#L-7"><span class="linenos"> 7</span></a><span class="kn">from</span><span class="w"> </span><span class="nn">.</span><span class="w"> </span><span class="kn">import</span> <span class="n">enums</span><span class="p">,</span> <span class="n">types</span><span class="p">,</span> <span class="n">addons</span>
</span><span id="L-8"><a href="#L-8"><span class="linenos"> 8</span></a>
</span><span id="L-9"><a href="#L-9"><span class="linenos"> 9</span></a><span class="n">__all__</span> <span class="o">=</span> <span class="p">[</span>
</span><span id="L-10"><a href="#L-10"><span class="linenos">10</span></a>    <span class="s1">&#39;Bot&#39;</span><span class="p">,</span> <span class="c1"># main class</span>
</span><span id="L-11"><a href="#L-11"><span class="linenos">11</span></a>    <span class="s1">&#39;TypingInfo&#39;</span><span class="p">,</span> <span class="s1">&#39;User&#39;</span><span class="p">,</span> <span class="s1">&#39;Message&#39;</span><span class="p">,</span> <span class="s1">&#39;Server&#39;</span><span class="p">,</span> <span class="s1">&#39;Channel&#39;</span><span class="p">,</span> <span class="s1">&#39;ctx&#39;</span><span class="p">,</span> <span class="c1"># additional classes</span>
</span><span id="L-12"><a href="#L-12"><span class="linenos">12</span></a>    <span class="s1">&#39;enums&#39;</span><span class="p">,</span> <span class="s1">&#39;types&#39;</span><span class="p">,</span> <span class="s1">&#39;addons&#39;</span> <span class="c1"># submodules</span>
</span><span id="L-13"><a href="#L-13"><span class="linenos">13</span></a>    <span class="p">]</span>
</span></pre></div>


            </section>
                <section id="Bot">
                            <input id="Bot-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">Bot</span>:

                <label class="view-source-button" for="Bot-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot-383"><a href="#Bot-383"><span class="linenos">383</span></a><span class="k">class</span><span class="w"> </span><span class="nc">Bot</span><span class="p">:</span>
</span><span id="Bot-384"><a href="#Bot-384"><span class="linenos">384</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-385"><a href="#Bot-385"><span class="linenos">385</span></a><span class="sd">    The main class of this library.</span>
</span><span id="Bot-386"><a href="#Bot-386"><span class="linenos">386</span></a><span class="sd">    From this class you can manage everything.</span>
</span><span id="Bot-387"><a href="#Bot-387"><span class="linenos">387</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Bot-388"><a href="#Bot-388"><span class="linenos">388</span></a>
</span><span id="Bot-389"><a href="#Bot-389"><span class="linenos">389</span></a>    <span class="n">bot_token</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Bot-390"><a href="#Bot-390"><span class="linenos">390</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-391"><a href="#Bot-391"><span class="linenos">391</span></a><span class="sd">    The token of your bot.</span>
</span><span id="Bot-392"><a href="#Bot-392"><span class="linenos">392</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Bot-393"><a href="#Bot-393"><span class="linenos">393</span></a>    <span class="n">server_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span>
</span><span id="Bot-394"><a href="#Bot-394"><span class="linenos">394</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-395"><a href="#Bot-395"><span class="linenos">395</span></a><span class="sd">    The server id to connect with (optional).</span>
</span><span id="Bot-396"><a href="#Bot-396"><span class="linenos">396</span></a><span class="sd">    If None, the bot will connect globally.</span>
</span><span id="Bot-397"><a href="#Bot-397"><span class="linenos">397</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Bot-398"><a href="#Bot-398"><span class="linenos">398</span></a>    <span class="n">connected</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="Bot-399"><a href="#Bot-399"><span class="linenos">399</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-400"><a href="#Bot-400"><span class="linenos">400</span></a><span class="sd">    Wether the bot is connected or not.</span>
</span><span id="Bot-401"><a href="#Bot-401"><span class="linenos">401</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Bot-402"><a href="#Bot-402"><span class="linenos">402</span></a>
</span><span id="Bot-403"><a href="#Bot-403"><span class="linenos">403</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot_token</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot-404"><a href="#Bot-404"><span class="linenos">404</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span> <span class="o">=</span> <span class="n">bot_token</span>
</span><span id="Bot-405"><a href="#Bot-405"><span class="linenos">405</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">server_id</span> <span class="o">=</span> <span class="n">server_id</span>
</span><span id="Bot-406"><a href="#Bot-406"><span class="linenos">406</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot-407"><a href="#Bot-407"><span class="linenos">407</span></a>        <span class="c1"># self.button_handler: Callable = None # TODO add button handler logic</span>
</span><span id="Bot-408"><a href="#Bot-408"><span class="linenos">408</span></a>
</span><span id="Bot-409"><a href="#Bot-409"><span class="linenos">409</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="p">:</span> <span class="n">socketio</span><span class="o">.</span><span class="n">AsyncClient</span> <span class="o">=</span> <span class="n">socketio</span><span class="o">.</span><span class="n">AsyncClient</span><span class="p">()</span>
</span><span id="Bot-410"><a href="#Bot-410"><span class="linenos">410</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">asyncio</span><span class="o">.</span><span class="n">AbstractEventLoop</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot-411"><a href="#Bot-411"><span class="linenos">411</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_reconnect_scheduled</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot-412"><a href="#Bot-412"><span class="linenos">412</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">Future</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-413"><a href="#Bot-413"><span class="linenos">413</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">Callable</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-414"><a href="#Bot-414"><span class="linenos">414</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">Callable</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-415"><a href="#Bot-415"><span class="linenos">415</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-416"><a href="#Bot-416"><span class="linenos">416</span></a>
</span><span id="Bot-417"><a href="#Bot-417"><span class="linenos">417</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;connect&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-418"><a href="#Bot-418"><span class="linenos">418</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_connect</span><span class="p">():</span>
</span><span id="Bot-419"><a href="#Bot-419"><span class="linenos">419</span></a>            <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">sleep</span><span class="p">(</span><span class="mi">5</span><span class="p">)</span>
</span><span id="Bot-420"><a href="#Bot-420"><span class="linenos">420</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot-421"><a href="#Bot-421"><span class="linenos">421</span></a>                <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server is active but broken, please report to a developer!&quot;</span><span class="p">)</span>
</span><span id="Bot-422"><a href="#Bot-422"><span class="linenos">422</span></a>
</span><span id="Bot-423"><a href="#Bot-423"><span class="linenos">423</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;bot_connected&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-424"><a href="#Bot-424"><span class="linenos">424</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_bot_connect</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-425"><a href="#Bot-425"><span class="linenos">425</span></a>            <span class="c1"># we were already connected but we need this to confirm our own identity</span>
</span><span id="Bot-426"><a href="#Bot-426"><span class="linenos">426</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">info</span><span class="p">(</span><span class="s2">&quot;Connected!&quot;</span><span class="p">)</span>
</span><span id="Bot-427"><a href="#Bot-427"><span class="linenos">427</span></a>            <span class="c1"># logger.debug((await self.get_user(data.get(&#39;bot_id&#39;, 0))).username)</span>
</span><span id="Bot-428"><a href="#Bot-428"><span class="linenos">428</span></a>
</span><span id="Bot-429"><a href="#Bot-429"><span class="linenos">429</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_ready&quot;</span><span class="p">)</span>
</span><span id="Bot-430"><a href="#Bot-430"><span class="linenos">430</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot-431"><a href="#Bot-431"><span class="linenos">431</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">()</span>
</span><span id="Bot-432"><a href="#Bot-432"><span class="linenos">432</span></a>
</span><span id="Bot-433"><a href="#Bot-433"><span class="linenos">433</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;error&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-434"><a href="#Bot-434"><span class="linenos">434</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_error</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-435"><a href="#Bot-435"><span class="linenos">435</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server error:&quot;</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span><span id="Bot-436"><a href="#Bot-436"><span class="linenos">436</span></a>
</span><span id="Bot-437"><a href="#Bot-437"><span class="linenos">437</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_send_response</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-438"><a href="#Bot-438"><span class="linenos">438</span></a>            <span class="n">req_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;req_id&#39;</span><span class="p">)</span>
</span><span id="Bot-439"><a href="#Bot-439"><span class="linenos">439</span></a>            <span class="k">if</span> <span class="n">req_id</span> <span class="ow">and</span> <span class="n">req_id</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span>
</span><span id="Bot-440"><a href="#Bot-440"><span class="linenos">440</span></a>                <span class="n">fut</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="o">.</span><span class="n">pop</span><span class="p">(</span><span class="n">req_id</span><span class="p">)</span>
</span><span id="Bot-441"><a href="#Bot-441"><span class="linenos">441</span></a>                <span class="k">if</span> <span class="n">fut</span> <span class="o">!=</span> <span class="kc">None</span><span class="p">:</span>
</span><span id="Bot-442"><a href="#Bot-442"><span class="linenos">442</span></a>                    <span class="n">fut</span><span class="o">.</span><span class="n">set_result</span><span class="p">(</span><span class="n">data</span><span class="p">)</span>
</span><span id="Bot-443"><a href="#Bot-443"><span class="linenos">443</span></a>
</span><span id="Bot-444"><a href="#Bot-444"><span class="linenos">444</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;1&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot-445"><a href="#Bot-445"><span class="linenos">445</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;user_info&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot-446"><a href="#Bot-446"><span class="linenos">446</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;send_message_response&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot-447"><a href="#Bot-447"><span class="linenos">447</span></a>
</span><span id="Bot-448"><a href="#Bot-448"><span class="linenos">448</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;bot_command_received&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-449"><a href="#Bot-449"><span class="linenos">449</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_bot_command_received</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-450"><a href="#Bot-450"><span class="linenos">450</span></a>            <span class="n">c</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;command&#39;</span><span class="p">,</span> <span class="s1">&#39;nonexisting&#39;</span><span class="p">)</span>
</span><span id="Bot-451"><a href="#Bot-451"><span class="linenos">451</span></a>            <span class="n">command</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">c</span><span class="p">[</span><span class="mi">1</span><span class="p">:])</span>
</span><span id="Bot-452"><a href="#Bot-452"><span class="linenos">452</span></a>
</span><span id="Bot-453"><a href="#Bot-453"><span class="linenos">453</span></a>            <span class="k">if</span> <span class="n">command</span><span class="p">:</span>
</span><span id="Bot-454"><a href="#Bot-454"><span class="linenos">454</span></a>                <span class="n">context</span> <span class="o">=</span> <span class="n">ctx</span><span class="p">({</span>
</span><span id="Bot-455"><a href="#Bot-455"><span class="linenos">455</span></a>                    <span class="s2">&quot;command&quot;</span><span class="p">:</span> <span class="n">c</span><span class="p">,</span>
</span><span id="Bot-456"><a href="#Bot-456"><span class="linenos">456</span></a>                    <span class="s2">&quot;bot&quot;</span><span class="p">:</span> <span class="bp">self</span><span class="p">,</span>
</span><span id="Bot-457"><a href="#Bot-457"><span class="linenos">457</span></a>                    <span class="s2">&quot;user&quot;</span><span class="p">:</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;sent_by_user_id&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">)),</span>
</span><span id="Bot-458"><a href="#Bot-458"><span class="linenos">458</span></a>                    <span class="s2">&quot;channel&quot;</span><span class="p">:</span> <span class="n">Channel</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;server_id&quot;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">),</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;channel_id&quot;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">))</span>
</span><span id="Bot-459"><a href="#Bot-459"><span class="linenos">459</span></a>                <span class="p">})</span>
</span><span id="Bot-460"><a href="#Bot-460"><span class="linenos">460</span></a>                <span class="k">await</span> <span class="n">command</span><span class="p">(</span><span class="n">context</span><span class="p">,</span> <span class="o">**</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;options&quot;</span><span class="p">,</span> <span class="p">{}))</span>
</span><span id="Bot-461"><a href="#Bot-461"><span class="linenos">461</span></a>
</span><span id="Bot-462"><a href="#Bot-462"><span class="linenos">462</span></a>        <span class="c1"># @self._sio.on(&#39;embed_button_pressed&#39;) # pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-463"><a href="#Bot-463"><span class="linenos">463</span></a>        <span class="c1"># async def on_button_click_received(data: types.JSON):</span>
</span><span id="Bot-464"><a href="#Bot-464"><span class="linenos">464</span></a>        <span class="c1">#     if self.button_handler:</span>
</span><span id="Bot-465"><a href="#Bot-465"><span class="linenos">465</span></a>        <span class="c1">#         await self.button_handler(data)</span>
</span><span id="Bot-466"><a href="#Bot-466"><span class="linenos">466</span></a>
</span><span id="Bot-467"><a href="#Bot-467"><span class="linenos">467</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;user_updated&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-468"><a href="#Bot-468"><span class="linenos">468</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_user_updated</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-469"><a href="#Bot-469"><span class="linenos">469</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_user_updated&quot;</span><span class="p">)</span>
</span><span id="Bot-470"><a href="#Bot-470"><span class="linenos">470</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot-471"><a href="#Bot-471"><span class="linenos">471</span></a>                <span class="n">user</span> <span class="o">=</span> <span class="n">User</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span><span id="Bot-472"><a href="#Bot-472"><span class="linenos">472</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">user</span><span class="p">)</span>
</span><span id="Bot-473"><a href="#Bot-473"><span class="linenos">473</span></a>
</span><span id="Bot-474"><a href="#Bot-474"><span class="linenos">474</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;users_typing&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-475"><a href="#Bot-475"><span class="linenos">475</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_typing_updated</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-476"><a href="#Bot-476"><span class="linenos">476</span></a>            <span class="n">server_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;server_id&#39;</span><span class="p">)</span>
</span><span id="Bot-477"><a href="#Bot-477"><span class="linenos">477</span></a>            <span class="n">channel_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;channel_id&#39;</span><span class="p">)</span>
</span><span id="Bot-478"><a href="#Bot-478"><span class="linenos">478</span></a>            <span class="k">if</span> <span class="n">server_id</span> <span class="ow">and</span> <span class="n">channel_id</span><span class="p">:</span>
</span><span id="Bot-479"><a href="#Bot-479"><span class="linenos">479</span></a>                <span class="n">server</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">server_id</span><span class="p">)</span>
</span><span id="Bot-480"><a href="#Bot-480"><span class="linenos">480</span></a>                <span class="k">if</span> <span class="ow">not</span> <span class="n">server</span><span class="p">:</span>
</span><span id="Bot-481"><a href="#Bot-481"><span class="linenos">481</span></a>                    <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="p">[</span><span class="n">server_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-482"><a href="#Bot-482"><span class="linenos">482</span></a>                    <span class="n">server</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="p">[</span><span class="n">server_id</span><span class="p">]</span>
</span><span id="Bot-483"><a href="#Bot-483"><span class="linenos">483</span></a>
</span><span id="Bot-484"><a href="#Bot-484"><span class="linenos">484</span></a>                <span class="n">old</span> <span class="o">=</span> <span class="n">server</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">channel_id</span><span class="p">)</span>
</span><span id="Bot-485"><a href="#Bot-485"><span class="linenos">485</span></a>                <span class="k">if</span> <span class="ow">not</span> <span class="n">old</span><span class="p">:</span>
</span><span id="Bot-486"><a href="#Bot-486"><span class="linenos">486</span></a>                    <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-487"><a href="#Bot-487"><span class="linenos">487</span></a>                    <span class="n">old</span> <span class="o">=</span> <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span>
</span><span id="Bot-488"><a href="#Bot-488"><span class="linenos">488</span></a>
</span><span id="Bot-489"><a href="#Bot-489"><span class="linenos">489</span></a>                <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot-490"><a href="#Bot-490"><span class="linenos">490</span></a>                <span class="n">channel</span> <span class="o">=</span> <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span>
</span><span id="Bot-491"><a href="#Bot-491"><span class="linenos">491</span></a>
</span><span id="Bot-492"><a href="#Bot-492"><span class="linenos">492</span></a>                <span class="n">user_ids</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;user_ids&#39;</span><span class="p">,</span> <span class="p">[])</span>
</span><span id="Bot-493"><a href="#Bot-493"><span class="linenos">493</span></a>                <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_typing&quot;</span><span class="p">)</span>
</span><span id="Bot-494"><a href="#Bot-494"><span class="linenos">494</span></a>                <span class="k">for</span> <span class="nb">id</span> <span class="ow">in</span> <span class="n">user_ids</span><span class="p">:</span>
</span><span id="Bot-495"><a href="#Bot-495"><span class="linenos">495</span></a>                    <span class="k">if</span> <span class="n">old</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="nb">id</span><span class="p">):</span>
</span><span id="Bot-496"><a href="#Bot-496"><span class="linenos">496</span></a>                        <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span> <span class="o">=</span> <span class="n">old</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span>
</span><span id="Bot-497"><a href="#Bot-497"><span class="linenos">497</span></a>                    <span class="k">else</span><span class="p">:</span>
</span><span id="Bot-498"><a href="#Bot-498"><span class="linenos">498</span></a>                        <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span> <span class="o">=</span> <span class="n">datetime</span><span class="o">.</span><span class="n">now</span><span class="p">()</span>
</span><span id="Bot-499"><a href="#Bot-499"><span class="linenos">499</span></a>
</span><span id="Bot-500"><a href="#Bot-500"><span class="linenos">500</span></a>                    <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot-501"><a href="#Bot-501"><span class="linenos">501</span></a>                        <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">TypingInfo</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">,</span> <span class="nb">id</span><span class="p">,</span> <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]))</span>
</span><span id="Bot-502"><a href="#Bot-502"><span class="linenos">502</span></a>
</span><span id="Bot-503"><a href="#Bot-503"><span class="linenos">503</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;new_message&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-504"><a href="#Bot-504"><span class="linenos">504</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_new_message</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-505"><a href="#Bot-505"><span class="linenos">505</span></a>            <span class="n">req_id</span> <span class="o">=</span> <span class="s2">&quot;_&quot;</span> <span class="o">+</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;req_id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="Bot-506"><a href="#Bot-506"><span class="linenos">506</span></a>            <span class="k">if</span> <span class="n">req_id</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span>
</span><span id="Bot-507"><a href="#Bot-507"><span class="linenos">507</span></a>                <span class="n">fut</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="o">.</span><span class="n">pop</span><span class="p">(</span><span class="n">req_id</span><span class="p">)</span>
</span><span id="Bot-508"><a href="#Bot-508"><span class="linenos">508</span></a>                <span class="k">if</span> <span class="n">fut</span> <span class="o">!=</span> <span class="kc">None</span><span class="p">:</span>
</span><span id="Bot-509"><a href="#Bot-509"><span class="linenos">509</span></a>                    <span class="n">fut</span><span class="o">.</span><span class="n">set_result</span><span class="p">(</span><span class="n">data</span><span class="p">)</span>
</span><span id="Bot-510"><a href="#Bot-510"><span class="linenos">510</span></a>
</span><span id="Bot-511"><a href="#Bot-511"><span class="linenos">511</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_message&quot;</span><span class="p">)</span>
</span><span id="Bot-512"><a href="#Bot-512"><span class="linenos">512</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot-513"><a href="#Bot-513"><span class="linenos">513</span></a>                <span class="c1"># TODO add check whether message isn&#39;t your own here!</span>
</span><span id="Bot-514"><a href="#Bot-514"><span class="linenos">514</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">Message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">))</span>
</span><span id="Bot-515"><a href="#Bot-515"><span class="linenos">515</span></a>
</span><span id="Bot-516"><a href="#Bot-516"><span class="linenos">516</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s2">&quot;*&quot;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot-517"><a href="#Bot-517"><span class="linenos">517</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">catch_all</span><span class="p">(</span><span class="n">event</span><span class="p">,</span> <span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot-518"><a href="#Bot-518"><span class="linenos">518</span></a>            <span class="k">if</span> <span class="n">event</span> <span class="o">==</span> <span class="s2">&quot;user_widget_updated&quot;</span><span class="p">:</span> <span class="k">return</span> <span class="c1"># silence annoying spam message; TODO support it</span>
</span><span id="Bot-519"><a href="#Bot-519"><span class="linenos">519</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">debug</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Unhandled event: </span><span class="si">{</span><span class="n">event</span><span class="si">}</span><span class="s2"> -&gt; </span><span class="si">{</span><span class="n">data</span><span class="si">}</span><span class="s2">&quot;</span><span class="p">)</span>
</span><span id="Bot-520"><a href="#Bot-520"><span class="linenos">520</span></a>
</span><span id="Bot-521"><a href="#Bot-521"><span class="linenos">521</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">event</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">func</span><span class="p">:</span> <span class="n">Callable</span><span class="p">):</span>
</span><span id="Bot-522"><a href="#Bot-522"><span class="linenos">522</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-523"><a href="#Bot-523"><span class="linenos">523</span></a><span class="sd">        Binds a function to an event.</span>
</span><span id="Bot-524"><a href="#Bot-524"><span class="linenos">524</span></a>
</span><span id="Bot-525"><a href="#Bot-525"><span class="linenos">525</span></a><span class="sd">        :param func: The function to be called when the event fires.</span>
</span><span id="Bot-526"><a href="#Bot-526"><span class="linenos">526</span></a><span class="sd">        This function must be async!</span>
</span><span id="Bot-527"><a href="#Bot-527"><span class="linenos">527</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-528"><a href="#Bot-528"><span class="linenos">528</span></a>
</span><span id="Bot-529"><a href="#Bot-529"><span class="linenos">529</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">inspect</span><span class="o">.</span><span class="n">iscoroutinefunction</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot-530"><a href="#Bot-530"><span class="linenos">530</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="s2">&quot;@bot.event must be async!&quot;</span><span class="p">)</span>
</span><span id="Bot-531"><a href="#Bot-531"><span class="linenos">531</span></a>        
</span><span id="Bot-532"><a href="#Bot-532"><span class="linenos">532</span></a>        <span class="n">allowed</span> <span class="o">=</span> <span class="p">[</span><span class="s2">&quot;on_ready&quot;</span><span class="p">,</span> <span class="s2">&quot;on_message&quot;</span><span class="p">,</span> <span class="s2">&quot;on_user_updated&quot;</span><span class="p">,</span> <span class="s2">&quot;on_typing&quot;</span><span class="p">]</span>
</span><span id="Bot-533"><a href="#Bot-533"><span class="linenos">533</span></a>        <span class="k">if</span> <span class="n">func</span><span class="o">.</span><span class="vm">__name__</span> <span class="ow">not</span> <span class="ow">in</span> <span class="n">allowed</span><span class="p">:</span>
</span><span id="Bot-534"><a href="#Bot-534"><span class="linenos">534</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;</span><span class="si">{</span><span class="n">func</span><span class="o">.</span><span class="vm">__name__</span><span class="si">}</span><span class="s2"> not valid for @bot.event&quot;</span><span class="p">)</span>
</span><span id="Bot-535"><a href="#Bot-535"><span class="linenos">535</span></a>        
</span><span id="Bot-536"><a href="#Bot-536"><span class="linenos">536</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="p">[</span><span class="n">func</span><span class="o">.</span><span class="vm">__name__</span><span class="p">]</span> <span class="o">=</span> <span class="n">func</span>
</span><span id="Bot-537"><a href="#Bot-537"><span class="linenos">537</span></a>        <span class="k">return</span> <span class="n">func</span>
</span><span id="Bot-538"><a href="#Bot-538"><span class="linenos">538</span></a>
</span><span id="Bot-539"><a href="#Bot-539"><span class="linenos">539</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">command</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">name</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot-540"><a href="#Bot-540"><span class="linenos">540</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-541"><a href="#Bot-541"><span class="linenos">541</span></a><span class="sd">        Binds a function to a command.</span>
</span><span id="Bot-542"><a href="#Bot-542"><span class="linenos">542</span></a>
</span><span id="Bot-543"><a href="#Bot-543"><span class="linenos">543</span></a><span class="sd">        :param func: The function to be called when the command is used.</span>
</span><span id="Bot-544"><a href="#Bot-544"><span class="linenos">544</span></a><span class="sd">        This function must be async!</span>
</span><span id="Bot-545"><a href="#Bot-545"><span class="linenos">545</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-546"><a href="#Bot-546"><span class="linenos">546</span></a>        <span class="k">def</span><span class="w"> </span><span class="nf">decorator</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot-547"><a href="#Bot-547"><span class="linenos">547</span></a>            <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot-548"><a href="#Bot-548"><span class="linenos">548</span></a>                <span class="c1"># this is actually not needed?</span>
</span><span id="Bot-549"><a href="#Bot-549"><span class="linenos">549</span></a>                <span class="c1"># the only issue is the commands are only passed through when starting</span>
</span><span id="Bot-550"><a href="#Bot-550"><span class="linenos">550</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Commands must be added BEFORE calling bot.connect()&quot;</span><span class="p">)</span>
</span><span id="Bot-551"><a href="#Bot-551"><span class="linenos">551</span></a>
</span><span id="Bot-552"><a href="#Bot-552"><span class="linenos">552</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="n">inspect</span><span class="o">.</span><span class="n">iscoroutinefunction</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot-553"><a href="#Bot-553"><span class="linenos">553</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;@bot.command() must be async&quot;</span><span class="p">)</span>
</span><span id="Bot-554"><a href="#Bot-554"><span class="linenos">554</span></a>
</span><span id="Bot-555"><a href="#Bot-555"><span class="linenos">555</span></a>            <span class="n">cmd_name</span> <span class="o">=</span> <span class="n">name</span> <span class="ow">or</span> <span class="n">func</span><span class="o">.</span><span class="vm">__name__</span>
</span><span id="Bot-556"><a href="#Bot-556"><span class="linenos">556</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="n">cmd_name</span> <span class="ow">or</span> <span class="nb">type</span><span class="p">(</span><span class="n">cmd_name</span><span class="p">)</span> <span class="o">!=</span> <span class="nb">str</span><span class="p">:</span>
</span><span id="Bot-557"><a href="#Bot-557"><span class="linenos">557</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Command name is invalid for command </span><span class="se">\&quot;</span><span class="si">{</span><span class="n">cmd_name</span><span class="si">}</span><span class="se">\&quot;</span><span class="s2">&quot;</span><span class="p">)</span>
</span><span id="Bot-558"><a href="#Bot-558"><span class="linenos">558</span></a>
</span><span id="Bot-559"><a href="#Bot-559"><span class="linenos">559</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">[</span><span class="n">cmd_name</span><span class="p">]</span> <span class="o">=</span> <span class="n">func</span>
</span><span id="Bot-560"><a href="#Bot-560"><span class="linenos">560</span></a>            <span class="k">return</span> <span class="n">func</span>
</span><span id="Bot-561"><a href="#Bot-561"><span class="linenos">561</span></a>        <span class="k">return</span> <span class="n">decorator</span>
</span><span id="Bot-562"><a href="#Bot-562"><span class="linenos">562</span></a>
</span><span id="Bot-563"><a href="#Bot-563"><span class="linenos">563</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">connect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot-564"><a href="#Bot-564"><span class="linenos">564</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-565"><a href="#Bot-565"><span class="linenos">565</span></a><span class="sd">        Official method to connect.</span>
</span><span id="Bot-566"><a href="#Bot-566"><span class="linenos">566</span></a><span class="sd">        Before calling this function, no events</span>
</span><span id="Bot-567"><a href="#Bot-567"><span class="linenos">567</span></a><span class="sd">        or commands will ever fire.</span>
</span><span id="Bot-568"><a href="#Bot-568"><span class="linenos">568</span></a>
</span><span id="Bot-569"><a href="#Bot-569"><span class="linenos">569</span></a><span class="sd">        This function blocks the current thread **forever**.</span>
</span><span id="Bot-570"><a href="#Bot-570"><span class="linenos">570</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-571"><a href="#Bot-571"><span class="linenos">571</span></a>        <span class="n">asyncio</span><span class="o">.</span><span class="n">run</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">_main</span><span class="p">())</span>
</span><span id="Bot-572"><a href="#Bot-572"><span class="linenos">572</span></a>
</span><span id="Bot-573"><a href="#Bot-573"><span class="linenos">573</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">_main</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot-574"><a href="#Bot-574"><span class="linenos">574</span></a>        <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_async_connect</span><span class="p">()</span>
</span><span id="Bot-575"><a href="#Bot-575"><span class="linenos">575</span></a>        <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">Event</span><span class="p">()</span><span class="o">.</span><span class="n">wait</span><span class="p">()</span>
</span><span id="Bot-576"><a href="#Bot-576"><span class="linenos">576</span></a>
</span><span id="Bot-577"><a href="#Bot-577"><span class="linenos">577</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">_async_connect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot-578"><a href="#Bot-578"><span class="linenos">578</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-579"><a href="#Bot-579"><span class="linenos">579</span></a><span class="sd">        Not officially supported method to connect the bot</span>
</span><span id="Bot-580"><a href="#Bot-580"><span class="linenos">580</span></a><span class="sd">        in async. Bot.connect() is a sync wrapper</span>
</span><span id="Bot-581"><a href="#Bot-581"><span class="linenos">581</span></a><span class="sd">        of this method.</span>
</span><span id="Bot-582"><a href="#Bot-582"><span class="linenos">582</span></a>
</span><span id="Bot-583"><a href="#Bot-583"><span class="linenos">583</span></a><span class="sd">        When using, make sure to keep the thread alive after</span>
</span><span id="Bot-584"><a href="#Bot-584"><span class="linenos">584</span></a><span class="sd">        it finishes, or the connection will close itself.</span>
</span><span id="Bot-585"><a href="#Bot-585"><span class="linenos">585</span></a>
</span><span id="Bot-586"><a href="#Bot-586"><span class="linenos">586</span></a><span class="sd">        @public</span>
</span><span id="Bot-587"><a href="#Bot-587"><span class="linenos">587</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-588"><a href="#Bot-588"><span class="linenos">588</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span> <span class="o">=</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">get_running_loop</span><span class="p">()</span>
</span><span id="Bot-589"><a href="#Bot-589"><span class="linenos">589</span></a>        <span class="n">url</span> <span class="o">=</span> <span class="sa">f</span><span class="s2">&quot;https://chat.wokki20.nl?bot_token=</span><span class="si">{</span><span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="si">}</span><span class="s2">&quot;</span>
</span><span id="Bot-590"><a href="#Bot-590"><span class="linenos">590</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">server_id</span><span class="p">:</span>
</span><span id="Bot-591"><a href="#Bot-591"><span class="linenos">591</span></a>            <span class="n">url</span> <span class="o">+=</span> <span class="sa">f</span><span class="s2">&quot;&amp;server_id=</span><span class="si">{</span><span class="bp">self</span><span class="o">.</span><span class="n">server_id</span><span class="si">}</span><span class="s2">&quot;</span>
</span><span id="Bot-592"><a href="#Bot-592"><span class="linenos">592</span></a>
</span><span id="Bot-593"><a href="#Bot-593"><span class="linenos">593</span></a>        <span class="n">max_attempts</span> <span class="o">=</span> <span class="mi">3</span> <span class="c1"># 3 attempts to connect, for if the server is down</span>
</span><span id="Bot-594"><a href="#Bot-594"><span class="linenos">594</span></a>        <span class="k">for</span> <span class="n">attempt</span> <span class="ow">in</span> <span class="nb">range</span><span class="p">(</span><span class="mi">1</span><span class="p">,</span> <span class="n">max_attempts</span> <span class="o">+</span> <span class="mi">1</span><span class="p">):</span>
</span><span id="Bot-595"><a href="#Bot-595"><span class="linenos">595</span></a>            <span class="k">try</span><span class="p">:</span>
</span><span id="Bot-596"><a href="#Bot-596"><span class="linenos">596</span></a>                <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">connect</span><span class="p">(</span><span class="n">url</span><span class="p">,</span> <span class="n">socketio_path</span><span class="o">=</span><span class="s1">&#39;/socket.io&#39;</span><span class="p">,</span> <span class="n">transports</span><span class="o">=</span><span class="p">[</span><span class="s1">&#39;websocket&#39;</span><span class="p">])</span>
</span><span id="Bot-597"><a href="#Bot-597"><span class="linenos">597</span></a>                <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot-598"><a href="#Bot-598"><span class="linenos">598</span></a>                <span class="k">break</span>
</span><span id="Bot-599"><a href="#Bot-599"><span class="linenos">599</span></a>            <span class="k">except</span> <span class="p">(</span><span class="ne">ConnectionError</span><span class="p">,</span> <span class="ne">OSError</span><span class="p">):</span>
</span><span id="Bot-600"><a href="#Bot-600"><span class="linenos">600</span></a>                <span class="k">if</span> <span class="n">attempt</span> <span class="o">&lt;</span> <span class="n">max_attempts</span><span class="p">:</span>
</span><span id="Bot-601"><a href="#Bot-601"><span class="linenos">601</span></a>                    <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Connection failed, retrying (</span><span class="si">{</span><span class="n">attempt</span><span class="si">}</span><span class="s2">/</span><span class="si">{</span><span class="n">max_attempts</span><span class="si">}</span><span class="s2">)...&quot;</span><span class="p">)</span>
</span><span id="Bot-602"><a href="#Bot-602"><span class="linenos">602</span></a>                    <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">sleep</span><span class="p">(</span><span class="mi">3</span><span class="p">)</span>
</span><span id="Bot-603"><a href="#Bot-603"><span class="linenos">603</span></a>                <span class="k">else</span><span class="p">:</span>
</span><span id="Bot-604"><a href="#Bot-604"><span class="linenos">604</span></a>                    <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server appears to be down. Checking again in 10 minutes.&quot;</span><span class="p">)</span>
</span><span id="Bot-605"><a href="#Bot-605"><span class="linenos">605</span></a>                    <span class="k">if</span> <span class="ow">not</span> <span class="nb">getattr</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s2">&quot;_reconnect_scheduled&quot;</span><span class="p">,</span> <span class="kc">False</span><span class="p">):</span>
</span><span id="Bot-606"><a href="#Bot-606"><span class="linenos">606</span></a>                        <span class="bp">self</span><span class="o">.</span><span class="n">_reconnect_scheduled</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot-607"><a href="#Bot-607"><span class="linenos">607</span></a>                        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span><span class="o">.</span><span class="n">create_task</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">_delayed_reconnect</span><span class="p">())</span>
</span><span id="Bot-608"><a href="#Bot-608"><span class="linenos">608</span></a>                    <span class="k">return</span>
</span><span id="Bot-609"><a href="#Bot-609"><span class="linenos">609</span></a>
</span><span id="Bot-610"><a href="#Bot-610"><span class="linenos">610</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">:</span>
</span><span id="Bot-611"><a href="#Bot-611"><span class="linenos">611</span></a>            <span class="n">class_types</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot-612"><a href="#Bot-612"><span class="linenos">612</span></a>                <span class="nb">str</span><span class="p">:</span> <span class="s2">&quot;string&quot;</span><span class="p">,</span>
</span><span id="Bot-613"><a href="#Bot-613"><span class="linenos">613</span></a>                <span class="nb">int</span><span class="p">:</span> <span class="s2">&quot;number&quot;</span><span class="p">,</span>
</span><span id="Bot-614"><a href="#Bot-614"><span class="linenos">614</span></a>                <span class="nb">bool</span><span class="p">:</span> <span class="s2">&quot;boolean&quot;</span>
</span><span id="Bot-615"><a href="#Bot-615"><span class="linenos">615</span></a>            <span class="p">}</span>
</span><span id="Bot-616"><a href="#Bot-616"><span class="linenos">616</span></a>
</span><span id="Bot-617"><a href="#Bot-617"><span class="linenos">617</span></a>            <span class="n">commands_data</span> <span class="o">=</span> <span class="p">[]</span>
</span><span id="Bot-618"><a href="#Bot-618"><span class="linenos">618</span></a>            <span class="k">for</span> <span class="n">name</span><span class="p">,</span> <span class="n">func</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot-619"><a href="#Bot-619"><span class="linenos">619</span></a>                <span class="n">sig</span> <span class="o">=</span> <span class="n">inspect</span><span class="o">.</span><span class="n">signature</span><span class="p">(</span><span class="n">func</span><span class="p">)</span>
</span><span id="Bot-620"><a href="#Bot-620"><span class="linenos">620</span></a>                <span class="n">options</span> <span class="o">=</span> <span class="p">[]</span>
</span><span id="Bot-621"><a href="#Bot-621"><span class="linenos">621</span></a>
</span><span id="Bot-622"><a href="#Bot-622"><span class="linenos">622</span></a>                <span class="n">gotctx</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot-623"><a href="#Bot-623"><span class="linenos">623</span></a>                <span class="k">for</span> <span class="n">pname</span><span class="p">,</span> <span class="n">param</span> <span class="ow">in</span> <span class="n">sig</span><span class="o">.</span><span class="n">parameters</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot-624"><a href="#Bot-624"><span class="linenos">624</span></a>                    <span class="k">if</span> <span class="ow">not</span> <span class="n">gotctx</span><span class="p">:</span>
</span><span id="Bot-625"><a href="#Bot-625"><span class="linenos">625</span></a>                        <span class="n">gotctx</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot-626"><a href="#Bot-626"><span class="linenos">626</span></a>                        <span class="k">continue</span>
</span><span id="Bot-627"><a href="#Bot-627"><span class="linenos">627</span></a>
</span><span id="Bot-628"><a href="#Bot-628"><span class="linenos">628</span></a>                    <span class="k">if</span> <span class="n">param</span><span class="o">.</span><span class="n">kind</span> <span class="ow">in</span> <span class="p">(</span><span class="n">inspect</span><span class="o">.</span><span class="n">Parameter</span><span class="o">.</span><span class="n">VAR_POSITIONAL</span><span class="p">,</span> <span class="n">inspect</span><span class="o">.</span><span class="n">Parameter</span><span class="o">.</span><span class="n">VAR_KEYWORD</span><span class="p">):</span>
</span><span id="Bot-629"><a href="#Bot-629"><span class="linenos">629</span></a>                        <span class="k">continue</span>
</span><span id="Bot-630"><a href="#Bot-630"><span class="linenos">630</span></a>
</span><span id="Bot-631"><a href="#Bot-631"><span class="linenos">631</span></a>                    <span class="n">options</span><span class="o">.</span><span class="n">append</span><span class="p">({</span>
</span><span id="Bot-632"><a href="#Bot-632"><span class="linenos">632</span></a>                        <span class="s2">&quot;option_name&quot;</span><span class="p">:</span> <span class="n">pname</span><span class="p">,</span>
</span><span id="Bot-633"><a href="#Bot-633"><span class="linenos">633</span></a>                        <span class="s2">&quot;option_type&quot;</span><span class="p">:</span> <span class="n">class_types</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">param</span><span class="o">.</span><span class="n">annotation</span><span class="p">,</span> <span class="s2">&quot;string&quot;</span><span class="p">),</span>
</span><span id="Bot-634"><a href="#Bot-634"><span class="linenos">634</span></a>                        <span class="s2">&quot;required&quot;</span><span class="p">:</span> <span class="n">param</span><span class="o">.</span><span class="n">default</span> <span class="o">==</span> <span class="n">inspect</span><span class="o">.</span><span class="n">_empty</span>
</span><span id="Bot-635"><a href="#Bot-635"><span class="linenos">635</span></a>                    <span class="p">})</span>
</span><span id="Bot-636"><a href="#Bot-636"><span class="linenos">636</span></a>
</span><span id="Bot-637"><a href="#Bot-637"><span class="linenos">637</span></a>                <span class="n">commands_data</span><span class="o">.</span><span class="n">append</span><span class="p">({</span>
</span><span id="Bot-638"><a href="#Bot-638"><span class="linenos">638</span></a>                    <span class="s2">&quot;command&quot;</span><span class="p">:</span> <span class="sa">f</span><span class="s2">&quot;/</span><span class="si">{</span><span class="n">name</span><span class="si">}</span><span class="s2">&quot;</span> <span class="k">if</span> <span class="ow">not</span> <span class="n">name</span><span class="o">.</span><span class="n">startswith</span><span class="p">(</span><span class="s2">&quot;/&quot;</span><span class="p">)</span> <span class="k">else</span> <span class="n">name</span><span class="p">,</span>
</span><span id="Bot-639"><a href="#Bot-639"><span class="linenos">639</span></a>                    <span class="s2">&quot;options&quot;</span><span class="p">:</span> <span class="n">options</span>
</span><span id="Bot-640"><a href="#Bot-640"><span class="linenos">640</span></a>                <span class="p">})</span>
</span><span id="Bot-641"><a href="#Bot-641"><span class="linenos">641</span></a>
</span><span id="Bot-642"><a href="#Bot-642"><span class="linenos">642</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">emit</span><span class="p">(</span><span class="s1">&#39;initialize_commands&#39;</span><span class="p">,</span> <span class="p">{</span>
</span><span id="Bot-643"><a href="#Bot-643"><span class="linenos">643</span></a>                <span class="s1">&#39;commands&#39;</span><span class="p">:</span> <span class="n">commands_data</span><span class="p">,</span>
</span><span id="Bot-644"><a href="#Bot-644"><span class="linenos">644</span></a>                <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot-645"><a href="#Bot-645"><span class="linenos">645</span></a>            <span class="p">})</span>
</span><span id="Bot-646"><a href="#Bot-646"><span class="linenos">646</span></a>        <span class="k">else</span><span class="p">:</span>
</span><span id="Bot-647"><a href="#Bot-647"><span class="linenos">647</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">emit</span><span class="p">(</span><span class="s1">&#39;initialize_commands&#39;</span><span class="p">,</span> <span class="p">{</span>
</span><span id="Bot-648"><a href="#Bot-648"><span class="linenos">648</span></a>                <span class="s1">&#39;commands&#39;</span><span class="p">:</span> <span class="p">[],</span>  <span class="c1"># clear up old commands</span>
</span><span id="Bot-649"><a href="#Bot-649"><span class="linenos">649</span></a>                <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot-650"><a href="#Bot-650"><span class="linenos">650</span></a>            <span class="p">})</span>
</span><span id="Bot-651"><a href="#Bot-651"><span class="linenos">651</span></a>
</span><span id="Bot-652"><a href="#Bot-652"><span class="linenos">652</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">_delayed_reconnect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot-653"><a href="#Bot-653"><span class="linenos">653</span></a>        <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">sleep</span><span class="p">(</span><span class="mi">600</span><span class="p">)</span>
</span><span id="Bot-654"><a href="#Bot-654"><span class="linenos">654</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_reconnect_scheduled</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot-655"><a href="#Bot-655"><span class="linenos">655</span></a>        <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_async_connect</span><span class="p">()</span>
</span><span id="Bot-656"><a href="#Bot-656"><span class="linenos">656</span></a>
</span><span id="Bot-657"><a href="#Bot-657"><span class="linenos">657</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">disconnect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot-658"><a href="#Bot-658"><span class="linenos">658</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-659"><a href="#Bot-659"><span class="linenos">659</span></a><span class="sd">        Disconnects the current bot</span>
</span><span id="Bot-660"><a href="#Bot-660"><span class="linenos">660</span></a><span class="sd">        session from the server.</span>
</span><span id="Bot-661"><a href="#Bot-661"><span class="linenos">661</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-662"><a href="#Bot-662"><span class="linenos">662</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot-663"><a href="#Bot-663"><span class="linenos">663</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">disconnect</span><span class="p">()</span>
</span><span id="Bot-664"><a href="#Bot-664"><span class="linenos">664</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot-665"><a href="#Bot-665"><span class="linenos">665</span></a>
</span><span id="Bot-666"><a href="#Bot-666"><span class="linenos">666</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">User</span><span class="p">:</span>
</span><span id="Bot-667"><a href="#Bot-667"><span class="linenos">667</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-668"><a href="#Bot-668"><span class="linenos">668</span></a><span class="sd">        Low-level implementation to get the info of</span>
</span><span id="Bot-669"><a href="#Bot-669"><span class="linenos">669</span></a><span class="sd">        a user by their id.</span>
</span><span id="Bot-670"><a href="#Bot-670"><span class="linenos">670</span></a>
</span><span id="Bot-671"><a href="#Bot-671"><span class="linenos">671</span></a><span class="sd">        :param user_id: The id of the user you want to fetch.</span>
</span><span id="Bot-672"><a href="#Bot-672"><span class="linenos">672</span></a><span class="sd">        :raises RuntimeError: If the user couldn&#39;t be fetched.</span>
</span><span id="Bot-673"><a href="#Bot-673"><span class="linenos">673</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-674"><a href="#Bot-674"><span class="linenos">674</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot-675"><a href="#Bot-675"><span class="linenos">675</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="p">,</span>
</span><span id="Bot-676"><a href="#Bot-676"><span class="linenos">676</span></a>            <span class="s1">&#39;user_id&#39;</span><span class="p">:</span> <span class="n">user_id</span>
</span><span id="Bot-677"><a href="#Bot-677"><span class="linenos">677</span></a>        <span class="p">}</span>
</span><span id="Bot-678"><a href="#Bot-678"><span class="linenos">678</span></a>
</span><span id="Bot-679"><a href="#Bot-679"><span class="linenos">679</span></a>        <span class="n">data</span> <span class="o">=</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;get_user_info&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">)</span>
</span><span id="Bot-680"><a href="#Bot-680"><span class="linenos">680</span></a>        <span class="k">if</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;info&quot;</span><span class="p">):</span>
</span><span id="Bot-681"><a href="#Bot-681"><span class="linenos">681</span></a>            <span class="k">return</span> <span class="n">User</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">[</span><span class="s2">&quot;info&quot;</span><span class="p">])</span>
</span><span id="Bot-682"><a href="#Bot-682"><span class="linenos">682</span></a>        <span class="k">else</span><span class="p">:</span>
</span><span id="Bot-683"><a href="#Bot-683"><span class="linenos">683</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s1">&#39;Couldn</span><span class="se">\&#39;</span><span class="s1">t fetch user with id &quot;</span><span class="si">{</span><span class="n">user_id</span><span class="si">}</span><span class="s1">&quot;&#39;</span><span class="p">)</span>
</span><span id="Bot-684"><a href="#Bot-684"><span class="linenos">684</span></a>        
</span><span id="Bot-685"><a href="#Bot-685"><span class="linenos">685</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">TypingInfo</span><span class="p">]:</span>
</span><span id="Bot-686"><a href="#Bot-686"><span class="linenos">686</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-687"><a href="#Bot-687"><span class="linenos">687</span></a><span class="sd">        Low-level implementation to get the typing info of</span>
</span><span id="Bot-688"><a href="#Bot-688"><span class="linenos">688</span></a><span class="sd">        a user by their id.</span>
</span><span id="Bot-689"><a href="#Bot-689"><span class="linenos">689</span></a>
</span><span id="Bot-690"><a href="#Bot-690"><span class="linenos">690</span></a><span class="sd">        :param user_id: The id of the user you want info of.</span>
</span><span id="Bot-691"><a href="#Bot-691"><span class="linenos">691</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-692"><a href="#Bot-692"><span class="linenos">692</span></a>        <span class="n">result</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot-693"><a href="#Bot-693"><span class="linenos">693</span></a>        <span class="k">for</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channels</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot-694"><a href="#Bot-694"><span class="linenos">694</span></a>            <span class="k">for</span> <span class="n">channel_id</span><span class="p">,</span> <span class="n">users</span> <span class="ow">in</span> <span class="n">channels</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot-695"><a href="#Bot-695"><span class="linenos">695</span></a>                <span class="k">if</span> <span class="n">user_id</span> <span class="ow">in</span> <span class="n">users</span><span class="p">:</span>
</span><span id="Bot-696"><a href="#Bot-696"><span class="linenos">696</span></a>                    <span class="n">result</span> <span class="o">=</span> <span class="n">TypingInfo</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">,</span> <span class="n">user_id</span><span class="p">,</span> <span class="n">users</span><span class="p">[</span><span class="n">user_id</span><span class="p">])</span>
</span><span id="Bot-697"><a href="#Bot-697"><span class="linenos">697</span></a>        <span class="k">return</span> <span class="n">result</span>
</span><span id="Bot-698"><a href="#Bot-698"><span class="linenos">698</span></a>
</span><span id="Bot-699"><a href="#Bot-699"><span class="linenos">699</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">send_message</span><span class="p">(</span>
</span><span id="Bot-700"><a href="#Bot-700"><span class="linenos">700</span></a>            <span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">parent_message_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot-701"><a href="#Bot-701"><span class="linenos">701</span></a>            <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot-702"><a href="#Bot-702"><span class="linenos">702</span></a>            <span class="n">command</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span> <span class="n">command_user_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot-703"><a href="#Bot-703"><span class="linenos">703</span></a>            <span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="Bot-704"><a href="#Bot-704"><span class="linenos">704</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-705"><a href="#Bot-705"><span class="linenos">705</span></a><span class="sd">        Low-level implementation to send a message.</span>
</span><span id="Bot-706"><a href="#Bot-706"><span class="linenos">706</span></a>
</span><span id="Bot-707"><a href="#Bot-707"><span class="linenos">707</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Bot-708"><a href="#Bot-708"><span class="linenos">708</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Bot-709"><a href="#Bot-709"><span class="linenos">709</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-710"><a href="#Bot-710"><span class="linenos">710</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">message</span> <span class="ow">and</span> <span class="ow">not</span> <span class="n">view</span><span class="p">:</span>
</span><span id="Bot-711"><a href="#Bot-711"><span class="linenos">711</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Please pass either a message or a view in Bot.send_message()&quot;</span><span class="p">)</span>
</span><span id="Bot-712"><a href="#Bot-712"><span class="linenos">712</span></a>            <span class="k">return</span>
</span><span id="Bot-713"><a href="#Bot-713"><span class="linenos">713</span></a>
</span><span id="Bot-714"><a href="#Bot-714"><span class="linenos">714</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot-715"><a href="#Bot-715"><span class="linenos">715</span></a>            <span class="s1">&#39;message&#39;</span><span class="p">:</span> <span class="n">message</span><span class="p">,</span>
</span><span id="Bot-716"><a href="#Bot-716"><span class="linenos">716</span></a>            <span class="s1">&#39;server_id&#39;</span><span class="p">:</span> <span class="n">server_id</span><span class="p">,</span>
</span><span id="Bot-717"><a href="#Bot-717"><span class="linenos">717</span></a>            <span class="s1">&#39;channel_id&#39;</span><span class="p">:</span> <span class="n">channel_id</span><span class="p">,</span>
</span><span id="Bot-718"><a href="#Bot-718"><span class="linenos">718</span></a>            <span class="s1">&#39;parent_message_id&#39;</span><span class="p">:</span> <span class="n">parent_message_id</span><span class="p">,</span>
</span><span id="Bot-719"><a href="#Bot-719"><span class="linenos">719</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="p">,</span>
</span><span id="Bot-720"><a href="#Bot-720"><span class="linenos">720</span></a>            <span class="s1">&#39;embed&#39;</span><span class="p">:</span> <span class="n">view</span><span class="o">.</span><span class="n">to_list</span><span class="p">()</span> <span class="k">if</span> <span class="n">view</span> <span class="k">else</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot-721"><a href="#Bot-721"><span class="linenos">721</span></a>            <span class="s1">&#39;command&#39;</span><span class="p">:</span> <span class="n">command</span><span class="p">,</span>
</span><span id="Bot-722"><a href="#Bot-722"><span class="linenos">722</span></a>            <span class="s1">&#39;user_id&#39;</span><span class="p">:</span> <span class="n">command_user_id</span>
</span><span id="Bot-723"><a href="#Bot-723"><span class="linenos">723</span></a>        <span class="p">}</span>
</span><span id="Bot-724"><a href="#Bot-724"><span class="linenos">724</span></a>
</span><span id="Bot-725"><a href="#Bot-725"><span class="linenos">725</span></a>        <span class="n">data</span> <span class="o">=</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;send_message&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">,</span> <span class="n">is_special</span><span class="o">=</span><span class="kc">True</span><span class="p">)</span>
</span><span id="Bot-726"><a href="#Bot-726"><span class="linenos">726</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">data</span><span class="p">:</span> <span class="k">return</span>
</span><span id="Bot-727"><a href="#Bot-727"><span class="linenos">727</span></a>
</span><span id="Bot-728"><a href="#Bot-728"><span class="linenos">728</span></a>        <span class="k">return</span> <span class="n">Message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span><span id="Bot-729"><a href="#Bot-729"><span class="linenos">729</span></a>
</span><span id="Bot-730"><a href="#Bot-730"><span class="linenos">730</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">edit_message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot-731"><a href="#Bot-731"><span class="linenos">731</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot-732"><a href="#Bot-732"><span class="linenos">732</span></a><span class="sd">        Low-level implementation to edit a message.</span>
</span><span id="Bot-733"><a href="#Bot-733"><span class="linenos">733</span></a>
</span><span id="Bot-734"><a href="#Bot-734"><span class="linenos">734</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Bot-735"><a href="#Bot-735"><span class="linenos">735</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Bot-736"><a href="#Bot-736"><span class="linenos">736</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot-737"><a href="#Bot-737"><span class="linenos">737</span></a>
</span><span id="Bot-738"><a href="#Bot-738"><span class="linenos">738</span></a>        <span class="c1"># TODO Fix server side and cast return to bool</span>
</span><span id="Bot-739"><a href="#Bot-739"><span class="linenos">739</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">message</span> <span class="ow">and</span> <span class="ow">not</span> <span class="n">view</span><span class="p">:</span>
</span><span id="Bot-740"><a href="#Bot-740"><span class="linenos">740</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Please pass either a message or a view in Bot.edit_bot_message()&quot;</span><span class="p">)</span>
</span><span id="Bot-741"><a href="#Bot-741"><span class="linenos">741</span></a>            <span class="k">return</span>
</span><span id="Bot-742"><a href="#Bot-742"><span class="linenos">742</span></a>
</span><span id="Bot-743"><a href="#Bot-743"><span class="linenos">743</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot-744"><a href="#Bot-744"><span class="linenos">744</span></a>            <span class="s1">&#39;message_id&#39;</span><span class="p">:</span> <span class="n">message_id</span><span class="p">,</span>
</span><span id="Bot-745"><a href="#Bot-745"><span class="linenos">745</span></a>            <span class="s1">&#39;message&#39;</span><span class="p">:</span> <span class="n">message</span><span class="p">,</span>
</span><span id="Bot-746"><a href="#Bot-746"><span class="linenos">746</span></a>            <span class="s1">&#39;embed&#39;</span><span class="p">:</span> <span class="n">view</span><span class="o">.</span><span class="n">to_list</span><span class="p">()</span> <span class="k">if</span> <span class="n">view</span> <span class="k">else</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot-747"><a href="#Bot-747"><span class="linenos">747</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot-748"><a href="#Bot-748"><span class="linenos">748</span></a>        <span class="p">}</span>
</span><span id="Bot-749"><a href="#Bot-749"><span class="linenos">749</span></a>
</span><span id="Bot-750"><a href="#Bot-750"><span class="linenos">750</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;edit_bot_message&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>The main class of this library.
From this class you can manage everything.</p>
</div>


                            <div id="Bot.__init__" class="classattr">
                                        <input id="Bot.__init__-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="name">Bot</span><span class="signature pdoc-code condensed">(<span class="param"><span class="n">bot_token</span><span class="p">:</span> <span class="nb">str</span>, </span><span class="param"><span class="n">server_id</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span>)</span>

                <label class="view-source-button" for="Bot.__init__-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.__init__"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.__init__-403"><a href="#Bot.__init__-403"><span class="linenos">403</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot_token</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot.__init__-404"><a href="#Bot.__init__-404"><span class="linenos">404</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span> <span class="o">=</span> <span class="n">bot_token</span>
</span><span id="Bot.__init__-405"><a href="#Bot.__init__-405"><span class="linenos">405</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">server_id</span> <span class="o">=</span> <span class="n">server_id</span>
</span><span id="Bot.__init__-406"><a href="#Bot.__init__-406"><span class="linenos">406</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot.__init__-407"><a href="#Bot.__init__-407"><span class="linenos">407</span></a>        <span class="c1"># self.button_handler: Callable = None # TODO add button handler logic</span>
</span><span id="Bot.__init__-408"><a href="#Bot.__init__-408"><span class="linenos">408</span></a>
</span><span id="Bot.__init__-409"><a href="#Bot.__init__-409"><span class="linenos">409</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="p">:</span> <span class="n">socketio</span><span class="o">.</span><span class="n">AsyncClient</span> <span class="o">=</span> <span class="n">socketio</span><span class="o">.</span><span class="n">AsyncClient</span><span class="p">()</span>
</span><span id="Bot.__init__-410"><a href="#Bot.__init__-410"><span class="linenos">410</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">asyncio</span><span class="o">.</span><span class="n">AbstractEventLoop</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot.__init__-411"><a href="#Bot.__init__-411"><span class="linenos">411</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_reconnect_scheduled</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot.__init__-412"><a href="#Bot.__init__-412"><span class="linenos">412</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">Future</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-413"><a href="#Bot.__init__-413"><span class="linenos">413</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">Callable</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-414"><a href="#Bot.__init__-414"><span class="linenos">414</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">:</span> <span class="n">Dict</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">Callable</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-415"><a href="#Bot.__init__-415"><span class="linenos">415</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-416"><a href="#Bot.__init__-416"><span class="linenos">416</span></a>
</span><span id="Bot.__init__-417"><a href="#Bot.__init__-417"><span class="linenos">417</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;connect&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-418"><a href="#Bot.__init__-418"><span class="linenos">418</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_connect</span><span class="p">():</span>
</span><span id="Bot.__init__-419"><a href="#Bot.__init__-419"><span class="linenos">419</span></a>            <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">sleep</span><span class="p">(</span><span class="mi">5</span><span class="p">)</span>
</span><span id="Bot.__init__-420"><a href="#Bot.__init__-420"><span class="linenos">420</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot.__init__-421"><a href="#Bot.__init__-421"><span class="linenos">421</span></a>                <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server is active but broken, please report to a developer!&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-422"><a href="#Bot.__init__-422"><span class="linenos">422</span></a>
</span><span id="Bot.__init__-423"><a href="#Bot.__init__-423"><span class="linenos">423</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;bot_connected&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-424"><a href="#Bot.__init__-424"><span class="linenos">424</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_bot_connect</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-425"><a href="#Bot.__init__-425"><span class="linenos">425</span></a>            <span class="c1"># we were already connected but we need this to confirm our own identity</span>
</span><span id="Bot.__init__-426"><a href="#Bot.__init__-426"><span class="linenos">426</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">info</span><span class="p">(</span><span class="s2">&quot;Connected!&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-427"><a href="#Bot.__init__-427"><span class="linenos">427</span></a>            <span class="c1"># logger.debug((await self.get_user(data.get(&#39;bot_id&#39;, 0))).username)</span>
</span><span id="Bot.__init__-428"><a href="#Bot.__init__-428"><span class="linenos">428</span></a>
</span><span id="Bot.__init__-429"><a href="#Bot.__init__-429"><span class="linenos">429</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_ready&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-430"><a href="#Bot.__init__-430"><span class="linenos">430</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot.__init__-431"><a href="#Bot.__init__-431"><span class="linenos">431</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">()</span>
</span><span id="Bot.__init__-432"><a href="#Bot.__init__-432"><span class="linenos">432</span></a>
</span><span id="Bot.__init__-433"><a href="#Bot.__init__-433"><span class="linenos">433</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;error&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-434"><a href="#Bot.__init__-434"><span class="linenos">434</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_error</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-435"><a href="#Bot.__init__-435"><span class="linenos">435</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server error:&quot;</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span><span id="Bot.__init__-436"><a href="#Bot.__init__-436"><span class="linenos">436</span></a>
</span><span id="Bot.__init__-437"><a href="#Bot.__init__-437"><span class="linenos">437</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_send_response</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-438"><a href="#Bot.__init__-438"><span class="linenos">438</span></a>            <span class="n">req_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;req_id&#39;</span><span class="p">)</span>
</span><span id="Bot.__init__-439"><a href="#Bot.__init__-439"><span class="linenos">439</span></a>            <span class="k">if</span> <span class="n">req_id</span> <span class="ow">and</span> <span class="n">req_id</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span>
</span><span id="Bot.__init__-440"><a href="#Bot.__init__-440"><span class="linenos">440</span></a>                <span class="n">fut</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="o">.</span><span class="n">pop</span><span class="p">(</span><span class="n">req_id</span><span class="p">)</span>
</span><span id="Bot.__init__-441"><a href="#Bot.__init__-441"><span class="linenos">441</span></a>                <span class="k">if</span> <span class="n">fut</span> <span class="o">!=</span> <span class="kc">None</span><span class="p">:</span>
</span><span id="Bot.__init__-442"><a href="#Bot.__init__-442"><span class="linenos">442</span></a>                    <span class="n">fut</span><span class="o">.</span><span class="n">set_result</span><span class="p">(</span><span class="n">data</span><span class="p">)</span>
</span><span id="Bot.__init__-443"><a href="#Bot.__init__-443"><span class="linenos">443</span></a>
</span><span id="Bot.__init__-444"><a href="#Bot.__init__-444"><span class="linenos">444</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;1&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot.__init__-445"><a href="#Bot.__init__-445"><span class="linenos">445</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;user_info&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot.__init__-446"><a href="#Bot.__init__-446"><span class="linenos">446</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;send_message_response&#39;</span><span class="p">,</span> <span class="n">on_send_response</span><span class="p">)</span>
</span><span id="Bot.__init__-447"><a href="#Bot.__init__-447"><span class="linenos">447</span></a>
</span><span id="Bot.__init__-448"><a href="#Bot.__init__-448"><span class="linenos">448</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;bot_command_received&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-449"><a href="#Bot.__init__-449"><span class="linenos">449</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_bot_command_received</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-450"><a href="#Bot.__init__-450"><span class="linenos">450</span></a>            <span class="n">c</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;command&#39;</span><span class="p">,</span> <span class="s1">&#39;nonexisting&#39;</span><span class="p">)</span>
</span><span id="Bot.__init__-451"><a href="#Bot.__init__-451"><span class="linenos">451</span></a>            <span class="n">command</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">c</span><span class="p">[</span><span class="mi">1</span><span class="p">:])</span>
</span><span id="Bot.__init__-452"><a href="#Bot.__init__-452"><span class="linenos">452</span></a>
</span><span id="Bot.__init__-453"><a href="#Bot.__init__-453"><span class="linenos">453</span></a>            <span class="k">if</span> <span class="n">command</span><span class="p">:</span>
</span><span id="Bot.__init__-454"><a href="#Bot.__init__-454"><span class="linenos">454</span></a>                <span class="n">context</span> <span class="o">=</span> <span class="n">ctx</span><span class="p">({</span>
</span><span id="Bot.__init__-455"><a href="#Bot.__init__-455"><span class="linenos">455</span></a>                    <span class="s2">&quot;command&quot;</span><span class="p">:</span> <span class="n">c</span><span class="p">,</span>
</span><span id="Bot.__init__-456"><a href="#Bot.__init__-456"><span class="linenos">456</span></a>                    <span class="s2">&quot;bot&quot;</span><span class="p">:</span> <span class="bp">self</span><span class="p">,</span>
</span><span id="Bot.__init__-457"><a href="#Bot.__init__-457"><span class="linenos">457</span></a>                    <span class="s2">&quot;user&quot;</span><span class="p">:</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;sent_by_user_id&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">)),</span>
</span><span id="Bot.__init__-458"><a href="#Bot.__init__-458"><span class="linenos">458</span></a>                    <span class="s2">&quot;channel&quot;</span><span class="p">:</span> <span class="n">Channel</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;server_id&quot;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">),</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;channel_id&quot;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">))</span>
</span><span id="Bot.__init__-459"><a href="#Bot.__init__-459"><span class="linenos">459</span></a>                <span class="p">})</span>
</span><span id="Bot.__init__-460"><a href="#Bot.__init__-460"><span class="linenos">460</span></a>                <span class="k">await</span> <span class="n">command</span><span class="p">(</span><span class="n">context</span><span class="p">,</span> <span class="o">**</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;options&quot;</span><span class="p">,</span> <span class="p">{}))</span>
</span><span id="Bot.__init__-461"><a href="#Bot.__init__-461"><span class="linenos">461</span></a>
</span><span id="Bot.__init__-462"><a href="#Bot.__init__-462"><span class="linenos">462</span></a>        <span class="c1"># @self._sio.on(&#39;embed_button_pressed&#39;) # pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-463"><a href="#Bot.__init__-463"><span class="linenos">463</span></a>        <span class="c1"># async def on_button_click_received(data: types.JSON):</span>
</span><span id="Bot.__init__-464"><a href="#Bot.__init__-464"><span class="linenos">464</span></a>        <span class="c1">#     if self.button_handler:</span>
</span><span id="Bot.__init__-465"><a href="#Bot.__init__-465"><span class="linenos">465</span></a>        <span class="c1">#         await self.button_handler(data)</span>
</span><span id="Bot.__init__-466"><a href="#Bot.__init__-466"><span class="linenos">466</span></a>
</span><span id="Bot.__init__-467"><a href="#Bot.__init__-467"><span class="linenos">467</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;user_updated&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-468"><a href="#Bot.__init__-468"><span class="linenos">468</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_user_updated</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-469"><a href="#Bot.__init__-469"><span class="linenos">469</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_user_updated&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-470"><a href="#Bot.__init__-470"><span class="linenos">470</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot.__init__-471"><a href="#Bot.__init__-471"><span class="linenos">471</span></a>                <span class="n">user</span> <span class="o">=</span> <span class="n">User</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span><span id="Bot.__init__-472"><a href="#Bot.__init__-472"><span class="linenos">472</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">user</span><span class="p">)</span>
</span><span id="Bot.__init__-473"><a href="#Bot.__init__-473"><span class="linenos">473</span></a>
</span><span id="Bot.__init__-474"><a href="#Bot.__init__-474"><span class="linenos">474</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;users_typing&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-475"><a href="#Bot.__init__-475"><span class="linenos">475</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_typing_updated</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-476"><a href="#Bot.__init__-476"><span class="linenos">476</span></a>            <span class="n">server_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;server_id&#39;</span><span class="p">)</span>
</span><span id="Bot.__init__-477"><a href="#Bot.__init__-477"><span class="linenos">477</span></a>            <span class="n">channel_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;channel_id&#39;</span><span class="p">)</span>
</span><span id="Bot.__init__-478"><a href="#Bot.__init__-478"><span class="linenos">478</span></a>            <span class="k">if</span> <span class="n">server_id</span> <span class="ow">and</span> <span class="n">channel_id</span><span class="p">:</span>
</span><span id="Bot.__init__-479"><a href="#Bot.__init__-479"><span class="linenos">479</span></a>                <span class="n">server</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">server_id</span><span class="p">)</span>
</span><span id="Bot.__init__-480"><a href="#Bot.__init__-480"><span class="linenos">480</span></a>                <span class="k">if</span> <span class="ow">not</span> <span class="n">server</span><span class="p">:</span>
</span><span id="Bot.__init__-481"><a href="#Bot.__init__-481"><span class="linenos">481</span></a>                    <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="p">[</span><span class="n">server_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-482"><a href="#Bot.__init__-482"><span class="linenos">482</span></a>                    <span class="n">server</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="p">[</span><span class="n">server_id</span><span class="p">]</span>
</span><span id="Bot.__init__-483"><a href="#Bot.__init__-483"><span class="linenos">483</span></a>
</span><span id="Bot.__init__-484"><a href="#Bot.__init__-484"><span class="linenos">484</span></a>                <span class="n">old</span> <span class="o">=</span> <span class="n">server</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">channel_id</span><span class="p">)</span>
</span><span id="Bot.__init__-485"><a href="#Bot.__init__-485"><span class="linenos">485</span></a>                <span class="k">if</span> <span class="ow">not</span> <span class="n">old</span><span class="p">:</span>
</span><span id="Bot.__init__-486"><a href="#Bot.__init__-486"><span class="linenos">486</span></a>                    <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-487"><a href="#Bot.__init__-487"><span class="linenos">487</span></a>                    <span class="n">old</span> <span class="o">=</span> <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span>
</span><span id="Bot.__init__-488"><a href="#Bot.__init__-488"><span class="linenos">488</span></a>
</span><span id="Bot.__init__-489"><a href="#Bot.__init__-489"><span class="linenos">489</span></a>                <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span> <span class="o">=</span> <span class="p">{}</span>
</span><span id="Bot.__init__-490"><a href="#Bot.__init__-490"><span class="linenos">490</span></a>                <span class="n">channel</span> <span class="o">=</span> <span class="n">server</span><span class="p">[</span><span class="n">channel_id</span><span class="p">]</span>
</span><span id="Bot.__init__-491"><a href="#Bot.__init__-491"><span class="linenos">491</span></a>
</span><span id="Bot.__init__-492"><a href="#Bot.__init__-492"><span class="linenos">492</span></a>                <span class="n">user_ids</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;user_ids&#39;</span><span class="p">,</span> <span class="p">[])</span>
</span><span id="Bot.__init__-493"><a href="#Bot.__init__-493"><span class="linenos">493</span></a>                <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_typing&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-494"><a href="#Bot.__init__-494"><span class="linenos">494</span></a>                <span class="k">for</span> <span class="nb">id</span> <span class="ow">in</span> <span class="n">user_ids</span><span class="p">:</span>
</span><span id="Bot.__init__-495"><a href="#Bot.__init__-495"><span class="linenos">495</span></a>                    <span class="k">if</span> <span class="n">old</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="nb">id</span><span class="p">):</span>
</span><span id="Bot.__init__-496"><a href="#Bot.__init__-496"><span class="linenos">496</span></a>                        <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span> <span class="o">=</span> <span class="n">old</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span>
</span><span id="Bot.__init__-497"><a href="#Bot.__init__-497"><span class="linenos">497</span></a>                    <span class="k">else</span><span class="p">:</span>
</span><span id="Bot.__init__-498"><a href="#Bot.__init__-498"><span class="linenos">498</span></a>                        <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]</span> <span class="o">=</span> <span class="n">datetime</span><span class="o">.</span><span class="n">now</span><span class="p">()</span>
</span><span id="Bot.__init__-499"><a href="#Bot.__init__-499"><span class="linenos">499</span></a>
</span><span id="Bot.__init__-500"><a href="#Bot.__init__-500"><span class="linenos">500</span></a>                    <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot.__init__-501"><a href="#Bot.__init__-501"><span class="linenos">501</span></a>                        <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">TypingInfo</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">,</span> <span class="nb">id</span><span class="p">,</span> <span class="n">channel</span><span class="p">[</span><span class="nb">id</span><span class="p">]))</span>
</span><span id="Bot.__init__-502"><a href="#Bot.__init__-502"><span class="linenos">502</span></a>
</span><span id="Bot.__init__-503"><a href="#Bot.__init__-503"><span class="linenos">503</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s1">&#39;new_message&#39;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-504"><a href="#Bot.__init__-504"><span class="linenos">504</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">on_new_message</span><span class="p">(</span><span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-505"><a href="#Bot.__init__-505"><span class="linenos">505</span></a>            <span class="n">req_id</span> <span class="o">=</span> <span class="s2">&quot;_&quot;</span> <span class="o">+</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;req_id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="Bot.__init__-506"><a href="#Bot.__init__-506"><span class="linenos">506</span></a>            <span class="k">if</span> <span class="n">req_id</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="p">:</span>
</span><span id="Bot.__init__-507"><a href="#Bot.__init__-507"><span class="linenos">507</span></a>                <span class="n">fut</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_response_futures</span><span class="o">.</span><span class="n">pop</span><span class="p">(</span><span class="n">req_id</span><span class="p">)</span>
</span><span id="Bot.__init__-508"><a href="#Bot.__init__-508"><span class="linenos">508</span></a>                <span class="k">if</span> <span class="n">fut</span> <span class="o">!=</span> <span class="kc">None</span><span class="p">:</span>
</span><span id="Bot.__init__-509"><a href="#Bot.__init__-509"><span class="linenos">509</span></a>                    <span class="n">fut</span><span class="o">.</span><span class="n">set_result</span><span class="p">(</span><span class="n">data</span><span class="p">)</span>
</span><span id="Bot.__init__-510"><a href="#Bot.__init__-510"><span class="linenos">510</span></a>
</span><span id="Bot.__init__-511"><a href="#Bot.__init__-511"><span class="linenos">511</span></a>            <span class="n">event</span> <span class="o">=</span> <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;on_message&quot;</span><span class="p">)</span>
</span><span id="Bot.__init__-512"><a href="#Bot.__init__-512"><span class="linenos">512</span></a>            <span class="k">if</span> <span class="n">event</span><span class="p">:</span>
</span><span id="Bot.__init__-513"><a href="#Bot.__init__-513"><span class="linenos">513</span></a>                <span class="c1"># TODO add check whether message isn&#39;t your own here!</span>
</span><span id="Bot.__init__-514"><a href="#Bot.__init__-514"><span class="linenos">514</span></a>                <span class="k">await</span> <span class="n">event</span><span class="p">(</span><span class="n">Message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">))</span>
</span><span id="Bot.__init__-515"><a href="#Bot.__init__-515"><span class="linenos">515</span></a>
</span><span id="Bot.__init__-516"><a href="#Bot.__init__-516"><span class="linenos">516</span></a>        <span class="nd">@self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">on</span><span class="p">(</span><span class="s2">&quot;*&quot;</span><span class="p">)</span> <span class="c1"># pyright: ignore[reportOptionalCall]</span>
</span><span id="Bot.__init__-517"><a href="#Bot.__init__-517"><span class="linenos">517</span></a>        <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">catch_all</span><span class="p">(</span><span class="n">event</span><span class="p">,</span> <span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Bot.__init__-518"><a href="#Bot.__init__-518"><span class="linenos">518</span></a>            <span class="k">if</span> <span class="n">event</span> <span class="o">==</span> <span class="s2">&quot;user_widget_updated&quot;</span><span class="p">:</span> <span class="k">return</span> <span class="c1"># silence annoying spam message; TODO support it</span>
</span><span id="Bot.__init__-519"><a href="#Bot.__init__-519"><span class="linenos">519</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">debug</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Unhandled event: </span><span class="si">{</span><span class="n">event</span><span class="si">}</span><span class="s2"> -&gt; </span><span class="si">{</span><span class="n">data</span><span class="si">}</span><span class="s2">&quot;</span><span class="p">)</span>
</span></pre></div>


    

                            </div>
                            <div id="Bot.bot_token" class="classattr">
                                <div class="attr variable">
            <span class="name">bot_token</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Bot.bot_token"></a>
    
            <div class="docstring"><p>The token of your bot.</p>
</div>


                            </div>
                            <div id="Bot.server_id" class="classattr">
                                <div class="attr variable">
            <span class="name">server_id</span><span class="annotation">: Union[str, NoneType]</span>

        
    </div>
    <a class="headerlink" href="#Bot.server_id"></a>
    
            <div class="docstring"><p>The server id to connect with (optional).
If None, the bot will connect globally.</p>
</div>


                            </div>
                            <div id="Bot.connected" class="classattr">
                                <div class="attr variable">
            <span class="name">connected</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#Bot.connected"></a>
    
            <div class="docstring"><p>Wether the bot is connected or not.</p>
</div>


                            </div>
                            <div id="Bot.event" class="classattr">
                                        <input id="Bot.event-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">def</span>
        <span class="name">event</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span>, </span><span class="param"><span class="n">func</span><span class="p">:</span> <span class="n">Callable</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot.event-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.event"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.event-521"><a href="#Bot.event-521"><span class="linenos">521</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">event</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">func</span><span class="p">:</span> <span class="n">Callable</span><span class="p">):</span>
</span><span id="Bot.event-522"><a href="#Bot.event-522"><span class="linenos">522</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.event-523"><a href="#Bot.event-523"><span class="linenos">523</span></a><span class="sd">        Binds a function to an event.</span>
</span><span id="Bot.event-524"><a href="#Bot.event-524"><span class="linenos">524</span></a>
</span><span id="Bot.event-525"><a href="#Bot.event-525"><span class="linenos">525</span></a><span class="sd">        :param func: The function to be called when the event fires.</span>
</span><span id="Bot.event-526"><a href="#Bot.event-526"><span class="linenos">526</span></a><span class="sd">        This function must be async!</span>
</span><span id="Bot.event-527"><a href="#Bot.event-527"><span class="linenos">527</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.event-528"><a href="#Bot.event-528"><span class="linenos">528</span></a>
</span><span id="Bot.event-529"><a href="#Bot.event-529"><span class="linenos">529</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">inspect</span><span class="o">.</span><span class="n">iscoroutinefunction</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot.event-530"><a href="#Bot.event-530"><span class="linenos">530</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="s2">&quot;@bot.event must be async!&quot;</span><span class="p">)</span>
</span><span id="Bot.event-531"><a href="#Bot.event-531"><span class="linenos">531</span></a>        
</span><span id="Bot.event-532"><a href="#Bot.event-532"><span class="linenos">532</span></a>        <span class="n">allowed</span> <span class="o">=</span> <span class="p">[</span><span class="s2">&quot;on_ready&quot;</span><span class="p">,</span> <span class="s2">&quot;on_message&quot;</span><span class="p">,</span> <span class="s2">&quot;on_user_updated&quot;</span><span class="p">,</span> <span class="s2">&quot;on_typing&quot;</span><span class="p">]</span>
</span><span id="Bot.event-533"><a href="#Bot.event-533"><span class="linenos">533</span></a>        <span class="k">if</span> <span class="n">func</span><span class="o">.</span><span class="vm">__name__</span> <span class="ow">not</span> <span class="ow">in</span> <span class="n">allowed</span><span class="p">:</span>
</span><span id="Bot.event-534"><a href="#Bot.event-534"><span class="linenos">534</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;</span><span class="si">{</span><span class="n">func</span><span class="o">.</span><span class="vm">__name__</span><span class="si">}</span><span class="s2"> not valid for @bot.event&quot;</span><span class="p">)</span>
</span><span id="Bot.event-535"><a href="#Bot.event-535"><span class="linenos">535</span></a>        
</span><span id="Bot.event-536"><a href="#Bot.event-536"><span class="linenos">536</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_events</span><span class="p">[</span><span class="n">func</span><span class="o">.</span><span class="vm">__name__</span><span class="p">]</span> <span class="o">=</span> <span class="n">func</span>
</span><span id="Bot.event-537"><a href="#Bot.event-537"><span class="linenos">537</span></a>        <span class="k">return</span> <span class="n">func</span>
</span></pre></div>


            <div class="docstring"><p>Binds a function to an event.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>func</strong>:  The function to be called when the event fires.
This function must be async!</li>
</ul>
</div>


                            </div>
                            <div id="Bot.command" class="classattr">
                                        <input id="Bot.command-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">def</span>
        <span class="name">command</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span>, </span><span class="param"><span class="n">name</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot.command-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.command"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.command-539"><a href="#Bot.command-539"><span class="linenos">539</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">command</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">name</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot.command-540"><a href="#Bot.command-540"><span class="linenos">540</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.command-541"><a href="#Bot.command-541"><span class="linenos">541</span></a><span class="sd">        Binds a function to a command.</span>
</span><span id="Bot.command-542"><a href="#Bot.command-542"><span class="linenos">542</span></a>
</span><span id="Bot.command-543"><a href="#Bot.command-543"><span class="linenos">543</span></a><span class="sd">        :param func: The function to be called when the command is used.</span>
</span><span id="Bot.command-544"><a href="#Bot.command-544"><span class="linenos">544</span></a><span class="sd">        This function must be async!</span>
</span><span id="Bot.command-545"><a href="#Bot.command-545"><span class="linenos">545</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.command-546"><a href="#Bot.command-546"><span class="linenos">546</span></a>        <span class="k">def</span><span class="w"> </span><span class="nf">decorator</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot.command-547"><a href="#Bot.command-547"><span class="linenos">547</span></a>            <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot.command-548"><a href="#Bot.command-548"><span class="linenos">548</span></a>                <span class="c1"># this is actually not needed?</span>
</span><span id="Bot.command-549"><a href="#Bot.command-549"><span class="linenos">549</span></a>                <span class="c1"># the only issue is the commands are only passed through when starting</span>
</span><span id="Bot.command-550"><a href="#Bot.command-550"><span class="linenos">550</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Commands must be added BEFORE calling bot.connect()&quot;</span><span class="p">)</span>
</span><span id="Bot.command-551"><a href="#Bot.command-551"><span class="linenos">551</span></a>
</span><span id="Bot.command-552"><a href="#Bot.command-552"><span class="linenos">552</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="n">inspect</span><span class="o">.</span><span class="n">iscoroutinefunction</span><span class="p">(</span><span class="n">func</span><span class="p">):</span>
</span><span id="Bot.command-553"><a href="#Bot.command-553"><span class="linenos">553</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;@bot.command() must be async&quot;</span><span class="p">)</span>
</span><span id="Bot.command-554"><a href="#Bot.command-554"><span class="linenos">554</span></a>
</span><span id="Bot.command-555"><a href="#Bot.command-555"><span class="linenos">555</span></a>            <span class="n">cmd_name</span> <span class="o">=</span> <span class="n">name</span> <span class="ow">or</span> <span class="n">func</span><span class="o">.</span><span class="vm">__name__</span>
</span><span id="Bot.command-556"><a href="#Bot.command-556"><span class="linenos">556</span></a>            <span class="k">if</span> <span class="ow">not</span> <span class="n">cmd_name</span> <span class="ow">or</span> <span class="nb">type</span><span class="p">(</span><span class="n">cmd_name</span><span class="p">)</span> <span class="o">!=</span> <span class="nb">str</span><span class="p">:</span>
</span><span id="Bot.command-557"><a href="#Bot.command-557"><span class="linenos">557</span></a>                <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Command name is invalid for command </span><span class="se">\&quot;</span><span class="si">{</span><span class="n">cmd_name</span><span class="si">}</span><span class="se">\&quot;</span><span class="s2">&quot;</span><span class="p">)</span>
</span><span id="Bot.command-558"><a href="#Bot.command-558"><span class="linenos">558</span></a>
</span><span id="Bot.command-559"><a href="#Bot.command-559"><span class="linenos">559</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">[</span><span class="n">cmd_name</span><span class="p">]</span> <span class="o">=</span> <span class="n">func</span>
</span><span id="Bot.command-560"><a href="#Bot.command-560"><span class="linenos">560</span></a>            <span class="k">return</span> <span class="n">func</span>
</span><span id="Bot.command-561"><a href="#Bot.command-561"><span class="linenos">561</span></a>        <span class="k">return</span> <span class="n">decorator</span>
</span></pre></div>


            <div class="docstring"><p>Binds a function to a command.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>func</strong>:  The function to be called when the command is used.
This function must be async!</li>
</ul>
</div>


                            </div>
                            <div id="Bot.connect" class="classattr">
                                        <input id="Bot.connect-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">def</span>
        <span class="name">connect</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot.connect-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.connect"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.connect-563"><a href="#Bot.connect-563"><span class="linenos">563</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">connect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot.connect-564"><a href="#Bot.connect-564"><span class="linenos">564</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.connect-565"><a href="#Bot.connect-565"><span class="linenos">565</span></a><span class="sd">        Official method to connect.</span>
</span><span id="Bot.connect-566"><a href="#Bot.connect-566"><span class="linenos">566</span></a><span class="sd">        Before calling this function, no events</span>
</span><span id="Bot.connect-567"><a href="#Bot.connect-567"><span class="linenos">567</span></a><span class="sd">        or commands will ever fire.</span>
</span><span id="Bot.connect-568"><a href="#Bot.connect-568"><span class="linenos">568</span></a>
</span><span id="Bot.connect-569"><a href="#Bot.connect-569"><span class="linenos">569</span></a><span class="sd">        This function blocks the current thread **forever**.</span>
</span><span id="Bot.connect-570"><a href="#Bot.connect-570"><span class="linenos">570</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.connect-571"><a href="#Bot.connect-571"><span class="linenos">571</span></a>        <span class="n">asyncio</span><span class="o">.</span><span class="n">run</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">_main</span><span class="p">())</span>
</span></pre></div>


            <div class="docstring"><p>Official method to connect.
Before calling this function, no events
or commands will ever fire.</p>

<p>This function blocks the current thread <strong>forever</strong>.</p>
</div>


                            </div>
                            <div id="Bot._async_connect" class="classattr">
                                        <input id="Bot._async_connect-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">_async_connect</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot._async_connect-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot._async_connect"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot._async_connect-577"><a href="#Bot._async_connect-577"><span class="linenos">577</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">_async_connect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot._async_connect-578"><a href="#Bot._async_connect-578"><span class="linenos">578</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot._async_connect-579"><a href="#Bot._async_connect-579"><span class="linenos">579</span></a><span class="sd">        Not officially supported method to connect the bot</span>
</span><span id="Bot._async_connect-580"><a href="#Bot._async_connect-580"><span class="linenos">580</span></a><span class="sd">        in async. Bot.connect() is a sync wrapper</span>
</span><span id="Bot._async_connect-581"><a href="#Bot._async_connect-581"><span class="linenos">581</span></a><span class="sd">        of this method.</span>
</span><span id="Bot._async_connect-582"><a href="#Bot._async_connect-582"><span class="linenos">582</span></a>
</span><span id="Bot._async_connect-583"><a href="#Bot._async_connect-583"><span class="linenos">583</span></a><span class="sd">        When using, make sure to keep the thread alive after</span>
</span><span id="Bot._async_connect-584"><a href="#Bot._async_connect-584"><span class="linenos">584</span></a><span class="sd">        it finishes, or the connection will close itself.</span>
</span><span id="Bot._async_connect-585"><a href="#Bot._async_connect-585"><span class="linenos">585</span></a>
</span><span id="Bot._async_connect-586"><a href="#Bot._async_connect-586"><span class="linenos">586</span></a><span class="sd">        @public</span>
</span><span id="Bot._async_connect-587"><a href="#Bot._async_connect-587"><span class="linenos">587</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot._async_connect-588"><a href="#Bot._async_connect-588"><span class="linenos">588</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span> <span class="o">=</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">get_running_loop</span><span class="p">()</span>
</span><span id="Bot._async_connect-589"><a href="#Bot._async_connect-589"><span class="linenos">589</span></a>        <span class="n">url</span> <span class="o">=</span> <span class="sa">f</span><span class="s2">&quot;https://chat.wokki20.nl?bot_token=</span><span class="si">{</span><span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="si">}</span><span class="s2">&quot;</span>
</span><span id="Bot._async_connect-590"><a href="#Bot._async_connect-590"><span class="linenos">590</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">server_id</span><span class="p">:</span>
</span><span id="Bot._async_connect-591"><a href="#Bot._async_connect-591"><span class="linenos">591</span></a>            <span class="n">url</span> <span class="o">+=</span> <span class="sa">f</span><span class="s2">&quot;&amp;server_id=</span><span class="si">{</span><span class="bp">self</span><span class="o">.</span><span class="n">server_id</span><span class="si">}</span><span class="s2">&quot;</span>
</span><span id="Bot._async_connect-592"><a href="#Bot._async_connect-592"><span class="linenos">592</span></a>
</span><span id="Bot._async_connect-593"><a href="#Bot._async_connect-593"><span class="linenos">593</span></a>        <span class="n">max_attempts</span> <span class="o">=</span> <span class="mi">3</span> <span class="c1"># 3 attempts to connect, for if the server is down</span>
</span><span id="Bot._async_connect-594"><a href="#Bot._async_connect-594"><span class="linenos">594</span></a>        <span class="k">for</span> <span class="n">attempt</span> <span class="ow">in</span> <span class="nb">range</span><span class="p">(</span><span class="mi">1</span><span class="p">,</span> <span class="n">max_attempts</span> <span class="o">+</span> <span class="mi">1</span><span class="p">):</span>
</span><span id="Bot._async_connect-595"><a href="#Bot._async_connect-595"><span class="linenos">595</span></a>            <span class="k">try</span><span class="p">:</span>
</span><span id="Bot._async_connect-596"><a href="#Bot._async_connect-596"><span class="linenos">596</span></a>                <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">connect</span><span class="p">(</span><span class="n">url</span><span class="p">,</span> <span class="n">socketio_path</span><span class="o">=</span><span class="s1">&#39;/socket.io&#39;</span><span class="p">,</span> <span class="n">transports</span><span class="o">=</span><span class="p">[</span><span class="s1">&#39;websocket&#39;</span><span class="p">])</span>
</span><span id="Bot._async_connect-597"><a href="#Bot._async_connect-597"><span class="linenos">597</span></a>                <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot._async_connect-598"><a href="#Bot._async_connect-598"><span class="linenos">598</span></a>                <span class="k">break</span>
</span><span id="Bot._async_connect-599"><a href="#Bot._async_connect-599"><span class="linenos">599</span></a>            <span class="k">except</span> <span class="p">(</span><span class="ne">ConnectionError</span><span class="p">,</span> <span class="ne">OSError</span><span class="p">):</span>
</span><span id="Bot._async_connect-600"><a href="#Bot._async_connect-600"><span class="linenos">600</span></a>                <span class="k">if</span> <span class="n">attempt</span> <span class="o">&lt;</span> <span class="n">max_attempts</span><span class="p">:</span>
</span><span id="Bot._async_connect-601"><a href="#Bot._async_connect-601"><span class="linenos">601</span></a>                    <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="sa">f</span><span class="s2">&quot;Connection failed, retrying (</span><span class="si">{</span><span class="n">attempt</span><span class="si">}</span><span class="s2">/</span><span class="si">{</span><span class="n">max_attempts</span><span class="si">}</span><span class="s2">)...&quot;</span><span class="p">)</span>
</span><span id="Bot._async_connect-602"><a href="#Bot._async_connect-602"><span class="linenos">602</span></a>                    <span class="k">await</span> <span class="n">asyncio</span><span class="o">.</span><span class="n">sleep</span><span class="p">(</span><span class="mi">3</span><span class="p">)</span>
</span><span id="Bot._async_connect-603"><a href="#Bot._async_connect-603"><span class="linenos">603</span></a>                <span class="k">else</span><span class="p">:</span>
</span><span id="Bot._async_connect-604"><a href="#Bot._async_connect-604"><span class="linenos">604</span></a>                    <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Server appears to be down. Checking again in 10 minutes.&quot;</span><span class="p">)</span>
</span><span id="Bot._async_connect-605"><a href="#Bot._async_connect-605"><span class="linenos">605</span></a>                    <span class="k">if</span> <span class="ow">not</span> <span class="nb">getattr</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s2">&quot;_reconnect_scheduled&quot;</span><span class="p">,</span> <span class="kc">False</span><span class="p">):</span>
</span><span id="Bot._async_connect-606"><a href="#Bot._async_connect-606"><span class="linenos">606</span></a>                        <span class="bp">self</span><span class="o">.</span><span class="n">_reconnect_scheduled</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot._async_connect-607"><a href="#Bot._async_connect-607"><span class="linenos">607</span></a>                        <span class="bp">self</span><span class="o">.</span><span class="n">_loop</span><span class="o">.</span><span class="n">create_task</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">_delayed_reconnect</span><span class="p">())</span>
</span><span id="Bot._async_connect-608"><a href="#Bot._async_connect-608"><span class="linenos">608</span></a>                    <span class="k">return</span>
</span><span id="Bot._async_connect-609"><a href="#Bot._async_connect-609"><span class="linenos">609</span></a>
</span><span id="Bot._async_connect-610"><a href="#Bot._async_connect-610"><span class="linenos">610</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="p">:</span>
</span><span id="Bot._async_connect-611"><a href="#Bot._async_connect-611"><span class="linenos">611</span></a>            <span class="n">class_types</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot._async_connect-612"><a href="#Bot._async_connect-612"><span class="linenos">612</span></a>                <span class="nb">str</span><span class="p">:</span> <span class="s2">&quot;string&quot;</span><span class="p">,</span>
</span><span id="Bot._async_connect-613"><a href="#Bot._async_connect-613"><span class="linenos">613</span></a>                <span class="nb">int</span><span class="p">:</span> <span class="s2">&quot;number&quot;</span><span class="p">,</span>
</span><span id="Bot._async_connect-614"><a href="#Bot._async_connect-614"><span class="linenos">614</span></a>                <span class="nb">bool</span><span class="p">:</span> <span class="s2">&quot;boolean&quot;</span>
</span><span id="Bot._async_connect-615"><a href="#Bot._async_connect-615"><span class="linenos">615</span></a>            <span class="p">}</span>
</span><span id="Bot._async_connect-616"><a href="#Bot._async_connect-616"><span class="linenos">616</span></a>
</span><span id="Bot._async_connect-617"><a href="#Bot._async_connect-617"><span class="linenos">617</span></a>            <span class="n">commands_data</span> <span class="o">=</span> <span class="p">[]</span>
</span><span id="Bot._async_connect-618"><a href="#Bot._async_connect-618"><span class="linenos">618</span></a>            <span class="k">for</span> <span class="n">name</span><span class="p">,</span> <span class="n">func</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_commands</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot._async_connect-619"><a href="#Bot._async_connect-619"><span class="linenos">619</span></a>                <span class="n">sig</span> <span class="o">=</span> <span class="n">inspect</span><span class="o">.</span><span class="n">signature</span><span class="p">(</span><span class="n">func</span><span class="p">)</span>
</span><span id="Bot._async_connect-620"><a href="#Bot._async_connect-620"><span class="linenos">620</span></a>                <span class="n">options</span> <span class="o">=</span> <span class="p">[]</span>
</span><span id="Bot._async_connect-621"><a href="#Bot._async_connect-621"><span class="linenos">621</span></a>
</span><span id="Bot._async_connect-622"><a href="#Bot._async_connect-622"><span class="linenos">622</span></a>                <span class="n">gotctx</span> <span class="o">=</span> <span class="kc">False</span>
</span><span id="Bot._async_connect-623"><a href="#Bot._async_connect-623"><span class="linenos">623</span></a>                <span class="k">for</span> <span class="n">pname</span><span class="p">,</span> <span class="n">param</span> <span class="ow">in</span> <span class="n">sig</span><span class="o">.</span><span class="n">parameters</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot._async_connect-624"><a href="#Bot._async_connect-624"><span class="linenos">624</span></a>                    <span class="k">if</span> <span class="ow">not</span> <span class="n">gotctx</span><span class="p">:</span>
</span><span id="Bot._async_connect-625"><a href="#Bot._async_connect-625"><span class="linenos">625</span></a>                        <span class="n">gotctx</span> <span class="o">=</span> <span class="kc">True</span>
</span><span id="Bot._async_connect-626"><a href="#Bot._async_connect-626"><span class="linenos">626</span></a>                        <span class="k">continue</span>
</span><span id="Bot._async_connect-627"><a href="#Bot._async_connect-627"><span class="linenos">627</span></a>
</span><span id="Bot._async_connect-628"><a href="#Bot._async_connect-628"><span class="linenos">628</span></a>                    <span class="k">if</span> <span class="n">param</span><span class="o">.</span><span class="n">kind</span> <span class="ow">in</span> <span class="p">(</span><span class="n">inspect</span><span class="o">.</span><span class="n">Parameter</span><span class="o">.</span><span class="n">VAR_POSITIONAL</span><span class="p">,</span> <span class="n">inspect</span><span class="o">.</span><span class="n">Parameter</span><span class="o">.</span><span class="n">VAR_KEYWORD</span><span class="p">):</span>
</span><span id="Bot._async_connect-629"><a href="#Bot._async_connect-629"><span class="linenos">629</span></a>                        <span class="k">continue</span>
</span><span id="Bot._async_connect-630"><a href="#Bot._async_connect-630"><span class="linenos">630</span></a>
</span><span id="Bot._async_connect-631"><a href="#Bot._async_connect-631"><span class="linenos">631</span></a>                    <span class="n">options</span><span class="o">.</span><span class="n">append</span><span class="p">({</span>
</span><span id="Bot._async_connect-632"><a href="#Bot._async_connect-632"><span class="linenos">632</span></a>                        <span class="s2">&quot;option_name&quot;</span><span class="p">:</span> <span class="n">pname</span><span class="p">,</span>
</span><span id="Bot._async_connect-633"><a href="#Bot._async_connect-633"><span class="linenos">633</span></a>                        <span class="s2">&quot;option_type&quot;</span><span class="p">:</span> <span class="n">class_types</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="n">param</span><span class="o">.</span><span class="n">annotation</span><span class="p">,</span> <span class="s2">&quot;string&quot;</span><span class="p">),</span>
</span><span id="Bot._async_connect-634"><a href="#Bot._async_connect-634"><span class="linenos">634</span></a>                        <span class="s2">&quot;required&quot;</span><span class="p">:</span> <span class="n">param</span><span class="o">.</span><span class="n">default</span> <span class="o">==</span> <span class="n">inspect</span><span class="o">.</span><span class="n">_empty</span>
</span><span id="Bot._async_connect-635"><a href="#Bot._async_connect-635"><span class="linenos">635</span></a>                    <span class="p">})</span>
</span><span id="Bot._async_connect-636"><a href="#Bot._async_connect-636"><span class="linenos">636</span></a>
</span><span id="Bot._async_connect-637"><a href="#Bot._async_connect-637"><span class="linenos">637</span></a>                <span class="n">commands_data</span><span class="o">.</span><span class="n">append</span><span class="p">({</span>
</span><span id="Bot._async_connect-638"><a href="#Bot._async_connect-638"><span class="linenos">638</span></a>                    <span class="s2">&quot;command&quot;</span><span class="p">:</span> <span class="sa">f</span><span class="s2">&quot;/</span><span class="si">{</span><span class="n">name</span><span class="si">}</span><span class="s2">&quot;</span> <span class="k">if</span> <span class="ow">not</span> <span class="n">name</span><span class="o">.</span><span class="n">startswith</span><span class="p">(</span><span class="s2">&quot;/&quot;</span><span class="p">)</span> <span class="k">else</span> <span class="n">name</span><span class="p">,</span>
</span><span id="Bot._async_connect-639"><a href="#Bot._async_connect-639"><span class="linenos">639</span></a>                    <span class="s2">&quot;options&quot;</span><span class="p">:</span> <span class="n">options</span>
</span><span id="Bot._async_connect-640"><a href="#Bot._async_connect-640"><span class="linenos">640</span></a>                <span class="p">})</span>
</span><span id="Bot._async_connect-641"><a href="#Bot._async_connect-641"><span class="linenos">641</span></a>
</span><span id="Bot._async_connect-642"><a href="#Bot._async_connect-642"><span class="linenos">642</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">emit</span><span class="p">(</span><span class="s1">&#39;initialize_commands&#39;</span><span class="p">,</span> <span class="p">{</span>
</span><span id="Bot._async_connect-643"><a href="#Bot._async_connect-643"><span class="linenos">643</span></a>                <span class="s1">&#39;commands&#39;</span><span class="p">:</span> <span class="n">commands_data</span><span class="p">,</span>
</span><span id="Bot._async_connect-644"><a href="#Bot._async_connect-644"><span class="linenos">644</span></a>                <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot._async_connect-645"><a href="#Bot._async_connect-645"><span class="linenos">645</span></a>            <span class="p">})</span>
</span><span id="Bot._async_connect-646"><a href="#Bot._async_connect-646"><span class="linenos">646</span></a>        <span class="k">else</span><span class="p">:</span>
</span><span id="Bot._async_connect-647"><a href="#Bot._async_connect-647"><span class="linenos">647</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">emit</span><span class="p">(</span><span class="s1">&#39;initialize_commands&#39;</span><span class="p">,</span> <span class="p">{</span>
</span><span id="Bot._async_connect-648"><a href="#Bot._async_connect-648"><span class="linenos">648</span></a>                <span class="s1">&#39;commands&#39;</span><span class="p">:</span> <span class="p">[],</span>  <span class="c1"># clear up old commands</span>
</span><span id="Bot._async_connect-649"><a href="#Bot._async_connect-649"><span class="linenos">649</span></a>                <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot._async_connect-650"><a href="#Bot._async_connect-650"><span class="linenos">650</span></a>            <span class="p">})</span>
</span></pre></div>


            <div class="docstring"><p>Not officially supported method to connect the bot
in async. <a href="#Bot.connect">Bot.connect()</a> is a sync wrapper
of this method.</p>

<p>When using, make sure to keep the thread alive after
it finishes, or the connection will close itself.</p>
</div>


                            </div>
                            <div id="Bot.disconnect" class="classattr">
                                        <input id="Bot.disconnect-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">disconnect</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot.disconnect-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.disconnect"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.disconnect-657"><a href="#Bot.disconnect-657"><span class="linenos">657</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">disconnect</span><span class="p">(</span><span class="bp">self</span><span class="p">):</span>
</span><span id="Bot.disconnect-658"><a href="#Bot.disconnect-658"><span class="linenos">658</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.disconnect-659"><a href="#Bot.disconnect-659"><span class="linenos">659</span></a><span class="sd">        Disconnects the current bot</span>
</span><span id="Bot.disconnect-660"><a href="#Bot.disconnect-660"><span class="linenos">660</span></a><span class="sd">        session from the server.</span>
</span><span id="Bot.disconnect-661"><a href="#Bot.disconnect-661"><span class="linenos">661</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.disconnect-662"><a href="#Bot.disconnect-662"><span class="linenos">662</span></a>        <span class="k">if</span> <span class="bp">self</span><span class="o">.</span><span class="n">connected</span><span class="p">:</span>
</span><span id="Bot.disconnect-663"><a href="#Bot.disconnect-663"><span class="linenos">663</span></a>            <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_sio</span><span class="o">.</span><span class="n">disconnect</span><span class="p">()</span>
</span><span id="Bot.disconnect-664"><a href="#Bot.disconnect-664"><span class="linenos">664</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">connected</span> <span class="o">=</span> <span class="kc">False</span>
</span></pre></div>


            <div class="docstring"><p>Disconnects the current bot
session from the server.</p>
</div>


                            </div>
                            <div id="Bot.get_user" class="classattr">
                                        <input id="Bot.get_user-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">get_user</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span>, </span><span class="param"><span class="n">user_id</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="nb">int</span><span class="p">]</span></span><span class="return-annotation">) -> <span class="n"><a href="#User">User</a></span>:</span></span>

                <label class="view-source-button" for="Bot.get_user-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.get_user"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.get_user-666"><a href="#Bot.get_user-666"><span class="linenos">666</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">User</span><span class="p">:</span>
</span><span id="Bot.get_user-667"><a href="#Bot.get_user-667"><span class="linenos">667</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.get_user-668"><a href="#Bot.get_user-668"><span class="linenos">668</span></a><span class="sd">        Low-level implementation to get the info of</span>
</span><span id="Bot.get_user-669"><a href="#Bot.get_user-669"><span class="linenos">669</span></a><span class="sd">        a user by their id.</span>
</span><span id="Bot.get_user-670"><a href="#Bot.get_user-670"><span class="linenos">670</span></a>
</span><span id="Bot.get_user-671"><a href="#Bot.get_user-671"><span class="linenos">671</span></a><span class="sd">        :param user_id: The id of the user you want to fetch.</span>
</span><span id="Bot.get_user-672"><a href="#Bot.get_user-672"><span class="linenos">672</span></a><span class="sd">        :raises RuntimeError: If the user couldn&#39;t be fetched.</span>
</span><span id="Bot.get_user-673"><a href="#Bot.get_user-673"><span class="linenos">673</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.get_user-674"><a href="#Bot.get_user-674"><span class="linenos">674</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot.get_user-675"><a href="#Bot.get_user-675"><span class="linenos">675</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="p">,</span>
</span><span id="Bot.get_user-676"><a href="#Bot.get_user-676"><span class="linenos">676</span></a>            <span class="s1">&#39;user_id&#39;</span><span class="p">:</span> <span class="n">user_id</span>
</span><span id="Bot.get_user-677"><a href="#Bot.get_user-677"><span class="linenos">677</span></a>        <span class="p">}</span>
</span><span id="Bot.get_user-678"><a href="#Bot.get_user-678"><span class="linenos">678</span></a>
</span><span id="Bot.get_user-679"><a href="#Bot.get_user-679"><span class="linenos">679</span></a>        <span class="n">data</span> <span class="o">=</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;get_user_info&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">)</span>
</span><span id="Bot.get_user-680"><a href="#Bot.get_user-680"><span class="linenos">680</span></a>        <span class="k">if</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s2">&quot;info&quot;</span><span class="p">):</span>
</span><span id="Bot.get_user-681"><a href="#Bot.get_user-681"><span class="linenos">681</span></a>            <span class="k">return</span> <span class="n">User</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">[</span><span class="s2">&quot;info&quot;</span><span class="p">])</span>
</span><span id="Bot.get_user-682"><a href="#Bot.get_user-682"><span class="linenos">682</span></a>        <span class="k">else</span><span class="p">:</span>
</span><span id="Bot.get_user-683"><a href="#Bot.get_user-683"><span class="linenos">683</span></a>            <span class="k">raise</span> <span class="ne">RuntimeError</span><span class="p">(</span><span class="sa">f</span><span class="s1">&#39;Couldn</span><span class="se">\&#39;</span><span class="s1">t fetch user with id &quot;</span><span class="si">{</span><span class="n">user_id</span><span class="si">}</span><span class="s1">&quot;&#39;</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Low-level implementation to get the info of
a user by their id.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>user_id</strong>:  The id of the user you want to fetch.</li>
</ul>

<h6 id="raises">Raises</h6>

<ul>
<li><strong>RuntimeError</strong>:  If the user couldn't be fetched.</li>
</ul>
</div>


                            </div>
                            <div id="Bot.is_typing" class="classattr">
                                        <input id="Bot.is_typing-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">is_typing</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">user_id</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="nb">int</span><span class="p">]</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#TypingInfo">TypingInfo</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="Bot.is_typing-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.is_typing"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.is_typing-685"><a href="#Bot.is_typing-685"><span class="linenos">685</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">TypingInfo</span><span class="p">]:</span>
</span><span id="Bot.is_typing-686"><a href="#Bot.is_typing-686"><span class="linenos">686</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.is_typing-687"><a href="#Bot.is_typing-687"><span class="linenos">687</span></a><span class="sd">        Low-level implementation to get the typing info of</span>
</span><span id="Bot.is_typing-688"><a href="#Bot.is_typing-688"><span class="linenos">688</span></a><span class="sd">        a user by their id.</span>
</span><span id="Bot.is_typing-689"><a href="#Bot.is_typing-689"><span class="linenos">689</span></a>
</span><span id="Bot.is_typing-690"><a href="#Bot.is_typing-690"><span class="linenos">690</span></a><span class="sd">        :param user_id: The id of the user you want info of.</span>
</span><span id="Bot.is_typing-691"><a href="#Bot.is_typing-691"><span class="linenos">691</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.is_typing-692"><a href="#Bot.is_typing-692"><span class="linenos">692</span></a>        <span class="n">result</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot.is_typing-693"><a href="#Bot.is_typing-693"><span class="linenos">693</span></a>        <span class="k">for</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channels</span> <span class="ow">in</span> <span class="bp">self</span><span class="o">.</span><span class="n">_typing_cache</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot.is_typing-694"><a href="#Bot.is_typing-694"><span class="linenos">694</span></a>            <span class="k">for</span> <span class="n">channel_id</span><span class="p">,</span> <span class="n">users</span> <span class="ow">in</span> <span class="n">channels</span><span class="o">.</span><span class="n">items</span><span class="p">():</span>
</span><span id="Bot.is_typing-695"><a href="#Bot.is_typing-695"><span class="linenos">695</span></a>                <span class="k">if</span> <span class="n">user_id</span> <span class="ow">in</span> <span class="n">users</span><span class="p">:</span>
</span><span id="Bot.is_typing-696"><a href="#Bot.is_typing-696"><span class="linenos">696</span></a>                    <span class="n">result</span> <span class="o">=</span> <span class="n">TypingInfo</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">,</span> <span class="n">user_id</span><span class="p">,</span> <span class="n">users</span><span class="p">[</span><span class="n">user_id</span><span class="p">])</span>
</span><span id="Bot.is_typing-697"><a href="#Bot.is_typing-697"><span class="linenos">697</span></a>        <span class="k">return</span> <span class="n">result</span>
</span></pre></div>


            <div class="docstring"><p>Low-level implementation to get the typing info of
a user by their id.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>user_id</strong>:  The id of the user you want info of.</li>
</ul>
</div>


                            </div>
                            <div id="Bot.send_message" class="classattr">
                                        <input id="Bot.send_message-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">send_message</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span>,</span><span class="param">	<span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span>,</span><span class="param">	<span class="n">parent_message_id</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>,</span><span class="param">	<span class="n">message</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="s1">&#39;&#39;</span>,</span><span class="param">	<span class="n">view</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="wokkichat/addons/ui.html#View">wokkichat.addons.ui.View</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>,</span><span class="param">	<span class="n">command</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>,</span><span class="param">	<span class="n">command_user_id</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="nb">int</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#Message">Message</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="Bot.send_message-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.send_message"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.send_message-699"><a href="#Bot.send_message-699"><span class="linenos">699</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">send_message</span><span class="p">(</span>
</span><span id="Bot.send_message-700"><a href="#Bot.send_message-700"><span class="linenos">700</span></a>            <span class="bp">self</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">parent_message_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot.send_message-701"><a href="#Bot.send_message-701"><span class="linenos">701</span></a>            <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot.send_message-702"><a href="#Bot.send_message-702"><span class="linenos">702</span></a>            <span class="n">command</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">,</span> <span class="n">command_user_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span>
</span><span id="Bot.send_message-703"><a href="#Bot.send_message-703"><span class="linenos">703</span></a>            <span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="Bot.send_message-704"><a href="#Bot.send_message-704"><span class="linenos">704</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.send_message-705"><a href="#Bot.send_message-705"><span class="linenos">705</span></a><span class="sd">        Low-level implementation to send a message.</span>
</span><span id="Bot.send_message-706"><a href="#Bot.send_message-706"><span class="linenos">706</span></a>
</span><span id="Bot.send_message-707"><a href="#Bot.send_message-707"><span class="linenos">707</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Bot.send_message-708"><a href="#Bot.send_message-708"><span class="linenos">708</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Bot.send_message-709"><a href="#Bot.send_message-709"><span class="linenos">709</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.send_message-710"><a href="#Bot.send_message-710"><span class="linenos">710</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">message</span> <span class="ow">and</span> <span class="ow">not</span> <span class="n">view</span><span class="p">:</span>
</span><span id="Bot.send_message-711"><a href="#Bot.send_message-711"><span class="linenos">711</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Please pass either a message or a view in Bot.send_message()&quot;</span><span class="p">)</span>
</span><span id="Bot.send_message-712"><a href="#Bot.send_message-712"><span class="linenos">712</span></a>            <span class="k">return</span>
</span><span id="Bot.send_message-713"><a href="#Bot.send_message-713"><span class="linenos">713</span></a>
</span><span id="Bot.send_message-714"><a href="#Bot.send_message-714"><span class="linenos">714</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot.send_message-715"><a href="#Bot.send_message-715"><span class="linenos">715</span></a>            <span class="s1">&#39;message&#39;</span><span class="p">:</span> <span class="n">message</span><span class="p">,</span>
</span><span id="Bot.send_message-716"><a href="#Bot.send_message-716"><span class="linenos">716</span></a>            <span class="s1">&#39;server_id&#39;</span><span class="p">:</span> <span class="n">server_id</span><span class="p">,</span>
</span><span id="Bot.send_message-717"><a href="#Bot.send_message-717"><span class="linenos">717</span></a>            <span class="s1">&#39;channel_id&#39;</span><span class="p">:</span> <span class="n">channel_id</span><span class="p">,</span>
</span><span id="Bot.send_message-718"><a href="#Bot.send_message-718"><span class="linenos">718</span></a>            <span class="s1">&#39;parent_message_id&#39;</span><span class="p">:</span> <span class="n">parent_message_id</span><span class="p">,</span>
</span><span id="Bot.send_message-719"><a href="#Bot.send_message-719"><span class="linenos">719</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span><span class="p">,</span>
</span><span id="Bot.send_message-720"><a href="#Bot.send_message-720"><span class="linenos">720</span></a>            <span class="s1">&#39;embed&#39;</span><span class="p">:</span> <span class="n">view</span><span class="o">.</span><span class="n">to_list</span><span class="p">()</span> <span class="k">if</span> <span class="n">view</span> <span class="k">else</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot.send_message-721"><a href="#Bot.send_message-721"><span class="linenos">721</span></a>            <span class="s1">&#39;command&#39;</span><span class="p">:</span> <span class="n">command</span><span class="p">,</span>
</span><span id="Bot.send_message-722"><a href="#Bot.send_message-722"><span class="linenos">722</span></a>            <span class="s1">&#39;user_id&#39;</span><span class="p">:</span> <span class="n">command_user_id</span>
</span><span id="Bot.send_message-723"><a href="#Bot.send_message-723"><span class="linenos">723</span></a>        <span class="p">}</span>
</span><span id="Bot.send_message-724"><a href="#Bot.send_message-724"><span class="linenos">724</span></a>
</span><span id="Bot.send_message-725"><a href="#Bot.send_message-725"><span class="linenos">725</span></a>        <span class="n">data</span> <span class="o">=</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;send_message&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">,</span> <span class="n">is_special</span><span class="o">=</span><span class="kc">True</span><span class="p">)</span>
</span><span id="Bot.send_message-726"><a href="#Bot.send_message-726"><span class="linenos">726</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">data</span><span class="p">:</span> <span class="k">return</span>
</span><span id="Bot.send_message-727"><a href="#Bot.send_message-727"><span class="linenos">727</span></a>
</span><span id="Bot.send_message-728"><a href="#Bot.send_message-728"><span class="linenos">728</span></a>        <span class="k">return</span> <span class="n">Message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Low-level implementation to send a message.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>message</strong>:  The message text to send.</li>
<li><strong>view</strong>:  The view to send.</li>
</ul>
</div>


                            </div>
                            <div id="Bot.edit_message" class="classattr">
                                        <input id="Bot.edit_message-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">edit_message</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">message_id</span><span class="p">:</span> <span class="nb">str</span>,</span><span class="param">	<span class="n">message</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="s1">&#39;&#39;</span>,</span><span class="param">	<span class="n">view</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="wokkichat/addons/ui.html#View">wokkichat.addons.ui.View</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">):</span></span>

                <label class="view-source-button" for="Bot.edit_message-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Bot.edit_message"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Bot.edit_message-730"><a href="#Bot.edit_message-730"><span class="linenos">730</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">edit_message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">):</span>
</span><span id="Bot.edit_message-731"><a href="#Bot.edit_message-731"><span class="linenos">731</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Bot.edit_message-732"><a href="#Bot.edit_message-732"><span class="linenos">732</span></a><span class="sd">        Low-level implementation to edit a message.</span>
</span><span id="Bot.edit_message-733"><a href="#Bot.edit_message-733"><span class="linenos">733</span></a>
</span><span id="Bot.edit_message-734"><a href="#Bot.edit_message-734"><span class="linenos">734</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Bot.edit_message-735"><a href="#Bot.edit_message-735"><span class="linenos">735</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Bot.edit_message-736"><a href="#Bot.edit_message-736"><span class="linenos">736</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Bot.edit_message-737"><a href="#Bot.edit_message-737"><span class="linenos">737</span></a>
</span><span id="Bot.edit_message-738"><a href="#Bot.edit_message-738"><span class="linenos">738</span></a>        <span class="c1"># TODO Fix server side and cast return to bool</span>
</span><span id="Bot.edit_message-739"><a href="#Bot.edit_message-739"><span class="linenos">739</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="n">message</span> <span class="ow">and</span> <span class="ow">not</span> <span class="n">view</span><span class="p">:</span>
</span><span id="Bot.edit_message-740"><a href="#Bot.edit_message-740"><span class="linenos">740</span></a>            <span class="n">logger</span><span class="o">.</span><span class="n">error</span><span class="p">(</span><span class="s2">&quot;Please pass either a message or a view in Bot.edit_bot_message()&quot;</span><span class="p">)</span>
</span><span id="Bot.edit_message-741"><a href="#Bot.edit_message-741"><span class="linenos">741</span></a>            <span class="k">return</span>
</span><span id="Bot.edit_message-742"><a href="#Bot.edit_message-742"><span class="linenos">742</span></a>
</span><span id="Bot.edit_message-743"><a href="#Bot.edit_message-743"><span class="linenos">743</span></a>        <span class="n">payload</span> <span class="o">=</span> <span class="p">{</span>
</span><span id="Bot.edit_message-744"><a href="#Bot.edit_message-744"><span class="linenos">744</span></a>            <span class="s1">&#39;message_id&#39;</span><span class="p">:</span> <span class="n">message_id</span><span class="p">,</span>
</span><span id="Bot.edit_message-745"><a href="#Bot.edit_message-745"><span class="linenos">745</span></a>            <span class="s1">&#39;message&#39;</span><span class="p">:</span> <span class="n">message</span><span class="p">,</span>
</span><span id="Bot.edit_message-746"><a href="#Bot.edit_message-746"><span class="linenos">746</span></a>            <span class="s1">&#39;embed&#39;</span><span class="p">:</span> <span class="n">view</span><span class="o">.</span><span class="n">to_list</span><span class="p">()</span> <span class="k">if</span> <span class="n">view</span> <span class="k">else</span> <span class="kc">None</span><span class="p">,</span>
</span><span id="Bot.edit_message-747"><a href="#Bot.edit_message-747"><span class="linenos">747</span></a>            <span class="s1">&#39;bot_token&#39;</span><span class="p">:</span> <span class="bp">self</span><span class="o">.</span><span class="n">bot_token</span>
</span><span id="Bot.edit_message-748"><a href="#Bot.edit_message-748"><span class="linenos">748</span></a>        <span class="p">}</span>
</span><span id="Bot.edit_message-749"><a href="#Bot.edit_message-749"><span class="linenos">749</span></a>
</span><span id="Bot.edit_message-750"><a href="#Bot.edit_message-750"><span class="linenos">750</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="n">get_response</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="s1">&#39;edit_bot_message&#39;</span><span class="p">,</span> <span class="n">payload</span><span class="o">=</span><span class="n">payload</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Low-level implementation to edit a message.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>message</strong>:  The message text to send.</li>
<li><strong>view</strong>:  The view to send.</li>
</ul>
</div>


                            </div>
                </section>
                <section id="TypingInfo">
                            <input id="TypingInfo-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">TypingInfo</span>:

                <label class="view-source-button" for="TypingInfo-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#TypingInfo"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="TypingInfo-55"><a href="#TypingInfo-55"><span class="linenos">55</span></a><span class="k">class</span><span class="w"> </span><span class="nc">TypingInfo</span><span class="p">:</span> <span class="c1"># TODO add autorefresh (using @property?)</span>
</span><span id="TypingInfo-56"><a href="#TypingInfo-56"><span class="linenos">56</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-57"><a href="#TypingInfo-57"><span class="linenos">57</span></a><span class="sd">    Contains valuable information about a typing user.</span>
</span><span id="TypingInfo-58"><a href="#TypingInfo-58"><span class="linenos">58</span></a>
</span><span id="TypingInfo-59"><a href="#TypingInfo-59"><span class="linenos">59</span></a><span class="sd">    No default values because if the data is invalid,</span>
</span><span id="TypingInfo-60"><a href="#TypingInfo-60"><span class="linenos">60</span></a><span class="sd">    you wouldn&#39;t get an object of this class.</span>
</span><span id="TypingInfo-61"><a href="#TypingInfo-61"><span class="linenos">61</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="TypingInfo-62"><a href="#TypingInfo-62"><span class="linenos">62</span></a>
</span><span id="TypingInfo-63"><a href="#TypingInfo-63"><span class="linenos">63</span></a>    <span class="n">_bot</span><span class="p">:</span> <span class="s2">&quot;Bot&quot;</span>
</span><span id="TypingInfo-64"><a href="#TypingInfo-64"><span class="linenos">64</span></a>    <span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="TypingInfo-65"><a href="#TypingInfo-65"><span class="linenos">65</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-66"><a href="#TypingInfo-66"><span class="linenos">66</span></a><span class="sd">    The id of the server this user is typing in.</span>
</span><span id="TypingInfo-67"><a href="#TypingInfo-67"><span class="linenos">67</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="TypingInfo-68"><a href="#TypingInfo-68"><span class="linenos">68</span></a>    <span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="TypingInfo-69"><a href="#TypingInfo-69"><span class="linenos">69</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-70"><a href="#TypingInfo-70"><span class="linenos">70</span></a><span class="sd">    The channel id this user is typing in.</span>
</span><span id="TypingInfo-71"><a href="#TypingInfo-71"><span class="linenos">71</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="TypingInfo-72"><a href="#TypingInfo-72"><span class="linenos">72</span></a>    <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span>
</span><span id="TypingInfo-73"><a href="#TypingInfo-73"><span class="linenos">73</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-74"><a href="#TypingInfo-74"><span class="linenos">74</span></a><span class="sd">    The id of the user which is typing.</span>
</span><span id="TypingInfo-75"><a href="#TypingInfo-75"><span class="linenos">75</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="TypingInfo-76"><a href="#TypingInfo-76"><span class="linenos">76</span></a>    <span class="n">when</span><span class="p">:</span> <span class="n">datetime</span>
</span><span id="TypingInfo-77"><a href="#TypingInfo-77"><span class="linenos">77</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-78"><a href="#TypingInfo-78"><span class="linenos">78</span></a><span class="sd">    When this user started typing.</span>
</span><span id="TypingInfo-79"><a href="#TypingInfo-79"><span class="linenos">79</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="TypingInfo-80"><a href="#TypingInfo-80"><span class="linenos">80</span></a>
</span><span id="TypingInfo-81"><a href="#TypingInfo-81"><span class="linenos">81</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot</span><span class="p">:</span> <span class="s2">&quot;Bot&quot;</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">user_id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">,</span> <span class="n">when</span><span class="p">:</span> <span class="n">datetime</span><span class="p">):</span>
</span><span id="TypingInfo-82"><a href="#TypingInfo-82"><span class="linenos">82</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-83"><a href="#TypingInfo-83"><span class="linenos">83</span></a><span class="sd">        @private</span>
</span><span id="TypingInfo-84"><a href="#TypingInfo-84"><span class="linenos">84</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="TypingInfo-85"><a href="#TypingInfo-85"><span class="linenos">85</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span> <span class="o">=</span> <span class="n">bot</span>
</span><span id="TypingInfo-86"><a href="#TypingInfo-86"><span class="linenos">86</span></a>
</span><span id="TypingInfo-87"><a href="#TypingInfo-87"><span class="linenos">87</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">server_id</span> <span class="o">=</span> <span class="n">server_id</span>
</span><span id="TypingInfo-88"><a href="#TypingInfo-88"><span class="linenos">88</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">channel_id</span> <span class="o">=</span> <span class="n">channel_id</span>
</span><span id="TypingInfo-89"><a href="#TypingInfo-89"><span class="linenos">89</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">user_id</span> <span class="o">=</span> <span class="n">user_id</span>
</span><span id="TypingInfo-90"><a href="#TypingInfo-90"><span class="linenos">90</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">when</span> <span class="o">=</span> <span class="n">when</span>
</span><span id="TypingInfo-91"><a href="#TypingInfo-91"><span class="linenos">91</span></a>
</span><span id="TypingInfo-92"><a href="#TypingInfo-92"><span class="linenos">92</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="s2">&quot;User&quot;</span><span class="p">:</span>
</span><span id="TypingInfo-93"><a href="#TypingInfo-93"><span class="linenos">93</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo-94"><a href="#TypingInfo-94"><span class="linenos">94</span></a><span class="sd">        Returns the User associated with this TypingInfo.</span>
</span><span id="TypingInfo-95"><a href="#TypingInfo-95"><span class="linenos">95</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="TypingInfo-96"><a href="#TypingInfo-96"><span class="linenos">96</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">user_id</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Contains valuable information about a typing user.</p>

<p>No default values because if the data is invalid,
you wouldn't get an object of this class.</p>
</div>


                            <div id="TypingInfo.server_id" class="classattr">
                                <div class="attr variable">
            <span class="name">server_id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#TypingInfo.server_id"></a>
    
            <div class="docstring"><p>The id of the server this user is typing in.</p>
</div>


                            </div>
                            <div id="TypingInfo.channel_id" class="classattr">
                                <div class="attr variable">
            <span class="name">channel_id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#TypingInfo.channel_id"></a>
    
            <div class="docstring"><p>The channel id this user is typing in.</p>
</div>


                            </div>
                            <div id="TypingInfo.user_id" class="classattr">
                                <div class="attr variable">
            <span class="name">user_id</span><span class="annotation">: Union[str, int]</span>

        
    </div>
    <a class="headerlink" href="#TypingInfo.user_id"></a>
    
            <div class="docstring"><p>The id of the user which is typing.</p>
</div>


                            </div>
                            <div id="TypingInfo.when" class="classattr">
                                <div class="attr variable">
            <span class="name">when</span><span class="annotation">: datetime.datetime</span>

        
    </div>
    <a class="headerlink" href="#TypingInfo.when"></a>
    
            <div class="docstring"><p>When this user started typing.</p>
</div>


                            </div>
                            <div id="TypingInfo.get_user" class="classattr">
                                        <input id="TypingInfo.get_user-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">get_user</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">) -> <span class="n"><a href="#User">User</a></span>:</span></span>

                <label class="view-source-button" for="TypingInfo.get_user-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#TypingInfo.get_user"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="TypingInfo.get_user-92"><a href="#TypingInfo.get_user-92"><span class="linenos">92</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="s2">&quot;User&quot;</span><span class="p">:</span>
</span><span id="TypingInfo.get_user-93"><a href="#TypingInfo.get_user-93"><span class="linenos">93</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="TypingInfo.get_user-94"><a href="#TypingInfo.get_user-94"><span class="linenos">94</span></a><span class="sd">        Returns the User associated with this TypingInfo.</span>
</span><span id="TypingInfo.get_user-95"><a href="#TypingInfo.get_user-95"><span class="linenos">95</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="TypingInfo.get_user-96"><a href="#TypingInfo.get_user-96"><span class="linenos">96</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">user_id</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Returns the User associated with this TypingInfo.</p>
</div>


                            </div>
                </section>
                <section id="User">
                            <input id="User-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">User</span>:

                <label class="view-source-button" for="User-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#User"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="User-100"><a href="#User-100"><span class="linenos">100</span></a><span class="k">class</span><span class="w"> </span><span class="nc">User</span><span class="p">:</span>
</span><span id="User-101"><a href="#User-101"><span class="linenos">101</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-102"><a href="#User-102"><span class="linenos">102</span></a><span class="sd">    Represents a user or bot account on wokki chat.</span>
</span><span id="User-103"><a href="#User-103"><span class="linenos">103</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-104"><a href="#User-104"><span class="linenos">104</span></a>
</span><span id="User-105"><a href="#User-105"><span class="linenos">105</span></a>    <span class="nb">id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span>
</span><span id="User-106"><a href="#User-106"><span class="linenos">106</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-107"><a href="#User-107"><span class="linenos">107</span></a><span class="sd">    The id of the user.</span>
</span><span id="User-108"><a href="#User-108"><span class="linenos">108</span></a><span class="sd">    </span>
</span><span id="User-109"><a href="#User-109"><span class="linenos">109</span></a><span class="sd">    Int if it&#39;s a real user, or uuid string if it&#39;s a bot.</span>
</span><span id="User-110"><a href="#User-110"><span class="linenos">110</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-111"><a href="#User-111"><span class="linenos">111</span></a>    <span class="n">username</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="User-112"><a href="#User-112"><span class="linenos">112</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-113"><a href="#User-113"><span class="linenos">113</span></a><span class="sd">    The username of the user.</span>
</span><span id="User-114"><a href="#User-114"><span class="linenos">114</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-115"><a href="#User-115"><span class="linenos">115</span></a>    <span class="n">display_name</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="User-116"><a href="#User-116"><span class="linenos">116</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-117"><a href="#User-117"><span class="linenos">117</span></a><span class="sd">    The display name of the user.</span>
</span><span id="User-118"><a href="#User-118"><span class="linenos">118</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-119"><a href="#User-119"><span class="linenos">119</span></a>    <span class="n">bio</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="User-120"><a href="#User-120"><span class="linenos">120</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-121"><a href="#User-121"><span class="linenos">121</span></a><span class="sd">    The bio of the user.</span>
</span><span id="User-122"><a href="#User-122"><span class="linenos">122</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-123"><a href="#User-123"><span class="linenos">123</span></a>    <span class="n">status</span><span class="p">:</span> <span class="n">enums</span><span class="o">.</span><span class="n">status</span>
</span><span id="User-124"><a href="#User-124"><span class="linenos">124</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-125"><a href="#User-125"><span class="linenos">125</span></a><span class="sd">    The current active status of the user.</span>
</span><span id="User-126"><a href="#User-126"><span class="linenos">126</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-127"><a href="#User-127"><span class="linenos">127</span></a>    <span class="n">profile_picture</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="User-128"><a href="#User-128"><span class="linenos">128</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-129"><a href="#User-129"><span class="linenos">129</span></a><span class="sd">    The *relative* url of the user&#39;s pfp.</span>
</span><span id="User-130"><a href="#User-130"><span class="linenos">130</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-131"><a href="#User-131"><span class="linenos">131</span></a>    <span class="n">profile_banner</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="User-132"><a href="#User-132"><span class="linenos">132</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-133"><a href="#User-133"><span class="linenos">133</span></a><span class="sd">    The *relative* url of the user&#39;s banner image.</span>
</span><span id="User-134"><a href="#User-134"><span class="linenos">134</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-135"><a href="#User-135"><span class="linenos">135</span></a>    <span class="n">premium</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="User-136"><a href="#User-136"><span class="linenos">136</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-137"><a href="#User-137"><span class="linenos">137</span></a><span class="sd">    Whether the user owns premium or not.</span>
</span><span id="User-138"><a href="#User-138"><span class="linenos">138</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-139"><a href="#User-139"><span class="linenos">139</span></a>    <span class="n">bot</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="User-140"><a href="#User-140"><span class="linenos">140</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-141"><a href="#User-141"><span class="linenos">141</span></a><span class="sd">    Whether the user is a bot or not.</span>
</span><span id="User-142"><a href="#User-142"><span class="linenos">142</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-143"><a href="#User-143"><span class="linenos">143</span></a>    <span class="n">staff</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="User-144"><a href="#User-144"><span class="linenos">144</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-145"><a href="#User-145"><span class="linenos">145</span></a><span class="sd">    Whether the user is an official staff member or not.</span>
</span><span id="User-146"><a href="#User-146"><span class="linenos">146</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-147"><a href="#User-147"><span class="linenos">147</span></a>    <span class="n">developer</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="User-148"><a href="#User-148"><span class="linenos">148</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-149"><a href="#User-149"><span class="linenos">149</span></a><span class="sd">    Whether the user is an official developer or not.</span>
</span><span id="User-150"><a href="#User-150"><span class="linenos">150</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-151"><a href="#User-151"><span class="linenos">151</span></a>    <span class="n">created_at</span><span class="p">:</span> <span class="n">datetime</span>
</span><span id="User-152"><a href="#User-152"><span class="linenos">152</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-153"><a href="#User-153"><span class="linenos">153</span></a><span class="sd">    The moment the account got created.</span>
</span><span id="User-154"><a href="#User-154"><span class="linenos">154</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="User-155"><a href="#User-155"><span class="linenos">155</span></a>
</span><span id="User-156"><a href="#User-156"><span class="linenos">156</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot</span><span class="p">:</span> <span class="s2">&quot;Bot&quot;</span><span class="p">,</span> <span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="User-157"><a href="#User-157"><span class="linenos">157</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-158"><a href="#User-158"><span class="linenos">158</span></a><span class="sd">        @private</span>
</span><span id="User-159"><a href="#User-159"><span class="linenos">159</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="User-160"><a href="#User-160"><span class="linenos">160</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span> <span class="o">=</span> <span class="n">bot</span>
</span><span id="User-161"><a href="#User-161"><span class="linenos">161</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_update</span><span class="p">(</span><span class="n">data</span><span class="p">)</span>
</span><span id="User-162"><a href="#User-162"><span class="linenos">162</span></a>
</span><span id="User-163"><a href="#User-163"><span class="linenos">163</span></a>    <span class="k">def</span><span class="w"> </span><span class="nf">_update</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="kc">None</span><span class="p">:</span>
</span><span id="User-164"><a href="#User-164"><span class="linenos">164</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="nb">isinstance</span><span class="p">(</span><span class="n">data</span><span class="p">,</span> <span class="nb">dict</span><span class="p">):</span> <span class="k">return</span>
</span><span id="User-165"><a href="#User-165"><span class="linenos">165</span></a>        
</span><span id="User-166"><a href="#User-166"><span class="linenos">166</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">UserId</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;id&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">)</span>
</span><span id="User-167"><a href="#User-167"><span class="linenos">167</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">username</span><span class="p">:</span> <span class="nb">str</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;username&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="User-168"><a href="#User-168"><span class="linenos">168</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">display_name</span><span class="p">:</span> <span class="nb">str</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;display_name&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="User-169"><a href="#User-169"><span class="linenos">169</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">bio</span><span class="p">:</span> <span class="nb">str</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;bio&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="User-170"><a href="#User-170"><span class="linenos">170</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">status</span><span class="p">:</span> <span class="n">enums</span><span class="o">.</span><span class="n">status</span> <span class="o">=</span> <span class="n">enums</span><span class="o">.</span><span class="n">status</span><span class="p">[</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;status&#39;</span><span class="p">,</span> <span class="s1">&#39;offline&#39;</span><span class="p">)</span><span class="o">.</span><span class="n">upper</span><span class="p">()]</span>
</span><span id="User-171"><a href="#User-171"><span class="linenos">171</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">profile_picture</span><span class="p">:</span> <span class="nb">str</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;profile_picture&#39;</span><span class="p">,</span> <span class="s1">&#39;/uploads/profile-pictures/default-profile.png&#39;</span><span class="p">)</span>
</span><span id="User-172"><a href="#User-172"><span class="linenos">172</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">profile_banner</span><span class="p">:</span> <span class="nb">str</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;profile_banner&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="User-173"><a href="#User-173"><span class="linenos">173</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">premium</span><span class="p">:</span> <span class="nb">bool</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;premium&#39;</span><span class="p">,</span> <span class="kc">False</span><span class="p">)</span>
</span><span id="User-174"><a href="#User-174"><span class="linenos">174</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">bot</span><span class="p">:</span> <span class="nb">bool</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;bot&#39;</span><span class="p">,</span> <span class="kc">False</span><span class="p">)</span>
</span><span id="User-175"><a href="#User-175"><span class="linenos">175</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">staff</span><span class="p">:</span> <span class="nb">bool</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;staff&#39;</span><span class="p">,</span> <span class="kc">False</span><span class="p">)</span>
</span><span id="User-176"><a href="#User-176"><span class="linenos">176</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">developer</span><span class="p">:</span> <span class="nb">bool</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;developer&#39;</span><span class="p">,</span> <span class="kc">False</span><span class="p">)</span>
</span><span id="User-177"><a href="#User-177"><span class="linenos">177</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">created_at</span><span class="p">:</span> <span class="n">datetime</span> <span class="o">=</span> <span class="n">datetime</span><span class="o">.</span><span class="n">fromisoformat</span><span class="p">(</span>
</span><span id="User-178"><a href="#User-178"><span class="linenos">178</span></a>            <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;created_at&#39;</span><span class="p">,</span> <span class="s1">&#39;1970-01-01T00:00:00&#39;</span><span class="p">))</span>
</span><span id="User-179"><a href="#User-179"><span class="linenos">179</span></a>        
</span><span id="User-180"><a href="#User-180"><span class="linenos">180</span></a>        <span class="c1"># TODO Add these items</span>
</span><span id="User-181"><a href="#User-181"><span class="linenos">181</span></a>        <span class="c1"># tags? [{tag_name:&quot;staff&quot;, tag_icon:&quot;tag_staff&quot;, created_at:ISO}]</span>
</span><span id="User-182"><a href="#User-182"><span class="linenos">182</span></a>        <span class="c1"># profile_color_primary? #0f1221</span>
</span><span id="User-183"><a href="#User-183"><span class="linenos">183</span></a>        <span class="c1"># profile_color_accent? #0f1221</span>
</span><span id="User-184"><a href="#User-184"><span class="linenos">184</span></a>        <span class="c1"># widgets? {}</span>
</span><span id="User-185"><a href="#User-185"><span class="linenos">185</span></a>
</span><span id="User-186"><a href="#User-186"><span class="linenos">186</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">TypingInfo</span><span class="p">]:</span>
</span><span id="User-187"><a href="#User-187"><span class="linenos">187</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User-188"><a href="#User-188"><span class="linenos">188</span></a><span class="sd">        Returns the current typing info of the user, if available.</span>
</span><span id="User-189"><a href="#User-189"><span class="linenos">189</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="User-190"><a href="#User-190"><span class="linenos">190</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Represents a user or bot account on wokki chat.</p>
</div>


                            <div id="User.id" class="classattr">
                                <div class="attr variable">
            <span class="name">id</span><span class="annotation">: Union[str, int]</span>

        
    </div>
    <a class="headerlink" href="#User.id"></a>
    
            <div class="docstring"><p>The id of the user.</p>

<p>Int if it's a real user, or uuid string if it's a bot.</p>
</div>


                            </div>
                            <div id="User.username" class="classattr">
                                <div class="attr variable">
            <span class="name">username</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#User.username"></a>
    
            <div class="docstring"><p>The username of the user.</p>
</div>


                            </div>
                            <div id="User.display_name" class="classattr">
                                <div class="attr variable">
            <span class="name">display_name</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#User.display_name"></a>
    
            <div class="docstring"><p>The display name of the user.</p>
</div>


                            </div>
                            <div id="User.bio" class="classattr">
                                <div class="attr variable">
            <span class="name">bio</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#User.bio"></a>
    
            <div class="docstring"><p>The bio of the user.</p>
</div>


                            </div>
                            <div id="User.status" class="classattr">
                                <div class="attr variable">
            <span class="name">status</span><span class="annotation">: <a href="wokkichat/enums.html#status">wokkichat.enums.status</a></span>

        
    </div>
    <a class="headerlink" href="#User.status"></a>
    
            <div class="docstring"><p>The current active status of the user.</p>
</div>


                            </div>
                            <div id="User.profile_picture" class="classattr">
                                <div class="attr variable">
            <span class="name">profile_picture</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#User.profile_picture"></a>
    
            <div class="docstring"><p>The <em>relative</em> url of the user's pfp.</p>
</div>


                            </div>
                            <div id="User.profile_banner" class="classattr">
                                <div class="attr variable">
            <span class="name">profile_banner</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#User.profile_banner"></a>
    
            <div class="docstring"><p>The <em>relative</em> url of the user's banner image.</p>
</div>


                            </div>
                            <div id="User.premium" class="classattr">
                                <div class="attr variable">
            <span class="name">premium</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#User.premium"></a>
    
            <div class="docstring"><p>Whether the user owns premium or not.</p>
</div>


                            </div>
                            <div id="User.bot" class="classattr">
                                <div class="attr variable">
            <span class="name">bot</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#User.bot"></a>
    
            <div class="docstring"><p>Whether the user is a bot or not.</p>
</div>


                            </div>
                            <div id="User.staff" class="classattr">
                                <div class="attr variable">
            <span class="name">staff</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#User.staff"></a>
    
            <div class="docstring"><p>Whether the user is an official staff member or not.</p>
</div>


                            </div>
                            <div id="User.developer" class="classattr">
                                <div class="attr variable">
            <span class="name">developer</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#User.developer"></a>
    
            <div class="docstring"><p>Whether the user is an official developer or not.</p>
</div>


                            </div>
                            <div id="User.created_at" class="classattr">
                                <div class="attr variable">
            <span class="name">created_at</span><span class="annotation">: datetime.datetime</span>

        
    </div>
    <a class="headerlink" href="#User.created_at"></a>
    
            <div class="docstring"><p>The moment the account got created.</p>
</div>


                            </div>
                            <div id="User.is_typing" class="classattr">
                                        <input id="User.is_typing-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">is_typing</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#TypingInfo">TypingInfo</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="User.is_typing-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#User.is_typing"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="User.is_typing-186"><a href="#User.is_typing-186"><span class="linenos">186</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">TypingInfo</span><span class="p">]:</span>
</span><span id="User.is_typing-187"><a href="#User.is_typing-187"><span class="linenos">187</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="User.is_typing-188"><a href="#User.is_typing-188"><span class="linenos">188</span></a><span class="sd">        Returns the current typing info of the user, if available.</span>
</span><span id="User.is_typing-189"><a href="#User.is_typing-189"><span class="linenos">189</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="User.is_typing-190"><a href="#User.is_typing-190"><span class="linenos">190</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">is_typing</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Returns the current typing info of the user, if available.</p>
</div>


                            </div>
                </section>
                <section id="Message">
                            <input id="Message-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">Message</span>:

                <label class="view-source-button" for="Message-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Message"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Message-194"><a href="#Message-194"><span class="linenos">194</span></a><span class="k">class</span><span class="w"> </span><span class="nc">Message</span><span class="p">:</span>
</span><span id="Message-195"><a href="#Message-195"><span class="linenos">195</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-196"><a href="#Message-196"><span class="linenos">196</span></a><span class="sd">    Represents a message sent by a user.</span>
</span><span id="Message-197"><a href="#Message-197"><span class="linenos">197</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-198"><a href="#Message-198"><span class="linenos">198</span></a>
</span><span id="Message-199"><a href="#Message-199"><span class="linenos">199</span></a>    <span class="n">username</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Message-200"><a href="#Message-200"><span class="linenos">200</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-201"><a href="#Message-201"><span class="linenos">201</span></a><span class="sd">    The username of the user which sent this message.</span>
</span><span id="Message-202"><a href="#Message-202"><span class="linenos">202</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-203"><a href="#Message-203"><span class="linenos">203</span></a>    <span class="n">user_id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Message-204"><a href="#Message-204"><span class="linenos">204</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-205"><a href="#Message-205"><span class="linenos">205</span></a><span class="sd">    The id of the user which sent this message.</span>
</span><span id="Message-206"><a href="#Message-206"><span class="linenos">206</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-207"><a href="#Message-207"><span class="linenos">207</span></a>    <span class="n">profile_picture</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Message-208"><a href="#Message-208"><span class="linenos">208</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-209"><a href="#Message-209"><span class="linenos">209</span></a><span class="sd">    The *relative* url of the user&#39;s pfp.</span>
</span><span id="Message-210"><a href="#Message-210"><span class="linenos">210</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-211"><a href="#Message-211"><span class="linenos">211</span></a>    <span class="nb">id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Message-212"><a href="#Message-212"><span class="linenos">212</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-213"><a href="#Message-213"><span class="linenos">213</span></a><span class="sd">    The id of the message.</span>
</span><span id="Message-214"><a href="#Message-214"><span class="linenos">214</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-215"><a href="#Message-215"><span class="linenos">215</span></a>    <span class="n">bot_message</span><span class="p">:</span> <span class="nb">bool</span>
</span><span id="Message-216"><a href="#Message-216"><span class="linenos">216</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-217"><a href="#Message-217"><span class="linenos">217</span></a><span class="sd">    Whether the message was sent by a bot or not.</span>
</span><span id="Message-218"><a href="#Message-218"><span class="linenos">218</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-219"><a href="#Message-219"><span class="linenos">219</span></a>    <span class="n">message</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Message-220"><a href="#Message-220"><span class="linenos">220</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-221"><a href="#Message-221"><span class="linenos">221</span></a><span class="sd">    The content text of the message.</span>
</span><span id="Message-222"><a href="#Message-222"><span class="linenos">222</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-223"><a href="#Message-223"><span class="linenos">223</span></a>    <span class="n">created_at</span><span class="p">:</span> <span class="n">datetime</span>
</span><span id="Message-224"><a href="#Message-224"><span class="linenos">224</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-225"><a href="#Message-225"><span class="linenos">225</span></a><span class="sd">    When the message was sent.</span>
</span><span id="Message-226"><a href="#Message-226"><span class="linenos">226</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-227"><a href="#Message-227"><span class="linenos">227</span></a>    <span class="n">channel</span><span class="p">:</span> <span class="s2">&quot;Channel&quot;</span>
</span><span id="Message-228"><a href="#Message-228"><span class="linenos">228</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-229"><a href="#Message-229"><span class="linenos">229</span></a><span class="sd">    The channel the message was sent in.</span>
</span><span id="Message-230"><a href="#Message-230"><span class="linenos">230</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-231"><a href="#Message-231"><span class="linenos">231</span></a>    <span class="n">parent_message_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span>
</span><span id="Message-232"><a href="#Message-232"><span class="linenos">232</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-233"><a href="#Message-233"><span class="linenos">233</span></a><span class="sd">    The id of the parent message, if this message is a reply.</span>
</span><span id="Message-234"><a href="#Message-234"><span class="linenos">234</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-235"><a href="#Message-235"><span class="linenos">235</span></a>    <span class="n">assets</span><span class="p">:</span> <span class="n">List</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span>
</span><span id="Message-236"><a href="#Message-236"><span class="linenos">236</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-237"><a href="#Message-237"><span class="linenos">237</span></a><span class="sd">    A list of all assets atached to this message.</span>
</span><span id="Message-238"><a href="#Message-238"><span class="linenos">238</span></a><span class="sd">    Defaults to an empty list.</span>
</span><span id="Message-239"><a href="#Message-239"><span class="linenos">239</span></a>
</span><span id="Message-240"><a href="#Message-240"><span class="linenos">240</span></a><span class="sd">    Not officially supported yet.</span>
</span><span id="Message-241"><a href="#Message-241"><span class="linenos">241</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-242"><a href="#Message-242"><span class="linenos">242</span></a>    <span class="n">command</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span>
</span><span id="Message-243"><a href="#Message-243"><span class="linenos">243</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-244"><a href="#Message-244"><span class="linenos">244</span></a><span class="sd">    The command name which this message replies to,</span>
</span><span id="Message-245"><a href="#Message-245"><span class="linenos">245</span></a><span class="sd">    if this message is a command response.</span>
</span><span id="Message-246"><a href="#Message-246"><span class="linenos">246</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-247"><a href="#Message-247"><span class="linenos">247</span></a>    <span class="n">command_user_id</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">types</span><span class="o">.</span><span class="n">UserId</span><span class="p">]</span>
</span><span id="Message-248"><a href="#Message-248"><span class="linenos">248</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-249"><a href="#Message-249"><span class="linenos">249</span></a><span class="sd">    The user id of the user which used this command,</span>
</span><span id="Message-250"><a href="#Message-250"><span class="linenos">250</span></a><span class="sd">    if this message is a command response.</span>
</span><span id="Message-251"><a href="#Message-251"><span class="linenos">251</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Message-252"><a href="#Message-252"><span class="linenos">252</span></a>
</span><span id="Message-253"><a href="#Message-253"><span class="linenos">253</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot</span><span class="p">:</span> <span class="s2">&quot;Bot&quot;</span><span class="p">,</span> <span class="n">data</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="Message-254"><a href="#Message-254"><span class="linenos">254</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-255"><a href="#Message-255"><span class="linenos">255</span></a><span class="sd">        @private</span>
</span><span id="Message-256"><a href="#Message-256"><span class="linenos">256</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Message-257"><a href="#Message-257"><span class="linenos">257</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="p">:</span> <span class="n">Bot</span> <span class="o">=</span> <span class="n">bot</span>
</span><span id="Message-258"><a href="#Message-258"><span class="linenos">258</span></a>
</span><span id="Message-259"><a href="#Message-259"><span class="linenos">259</span></a>        <span class="c1"># TODO wrap in user object?</span>
</span><span id="Message-260"><a href="#Message-260"><span class="linenos">260</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">username</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;username&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="Message-261"><a href="#Message-261"><span class="linenos">261</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">user_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;user_id&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">)</span>
</span><span id="Message-262"><a href="#Message-262"><span class="linenos">262</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">profile_picture</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;profile_picture&#39;</span><span class="p">,</span> <span class="s1">&#39;/uploads/profile-pictures/default-profile.png&#39;</span><span class="p">)</span>
</span><span id="Message-263"><a href="#Message-263"><span class="linenos">263</span></a>
</span><span id="Message-264"><a href="#Message-264"><span class="linenos">264</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="Message-265"><a href="#Message-265"><span class="linenos">265</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">bot_message</span> <span class="o">=</span> <span class="nb">bool</span><span class="p">(</span><span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;bot_message&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">))</span>
</span><span id="Message-266"><a href="#Message-266"><span class="linenos">266</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">message</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;message&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span>
</span><span id="Message-267"><a href="#Message-267"><span class="linenos">267</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">created_at</span> <span class="o">=</span> <span class="n">datetime</span><span class="o">.</span><span class="n">fromisoformat</span><span class="p">(</span>
</span><span id="Message-268"><a href="#Message-268"><span class="linenos">268</span></a>            <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;created_at&#39;</span><span class="p">,</span> <span class="s1">&#39;1970-01-01T00:00:00&#39;</span><span class="p">))</span>
</span><span id="Message-269"><a href="#Message-269"><span class="linenos">269</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">channel</span> <span class="o">=</span> <span class="n">Channel</span><span class="p">(</span><span class="n">bot</span><span class="p">,</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;server_id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">),</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;channel_id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">))</span>
</span><span id="Message-270"><a href="#Message-270"><span class="linenos">270</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">parent_message_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;parent_message_id&#39;</span><span class="p">,</span> <span class="s1">&#39;&#39;</span><span class="p">)</span> <span class="c1"># TODO make real message?</span>
</span><span id="Message-271"><a href="#Message-271"><span class="linenos">271</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">assets</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;assets&#39;</span><span class="p">,</span> <span class="p">[])</span>  <span class="c1"># TODO wrap in Image classes?</span>
</span><span id="Message-272"><a href="#Message-272"><span class="linenos">272</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">command</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;command&#39;</span><span class="p">,</span> <span class="kc">None</span><span class="p">)</span>
</span><span id="Message-273"><a href="#Message-273"><span class="linenos">273</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">command_user_id</span> <span class="o">=</span> <span class="n">data</span><span class="o">.</span><span class="n">get</span><span class="p">(</span><span class="s1">&#39;command_user_id&#39;</span><span class="p">,</span> <span class="mi">0</span><span class="p">)</span>
</span><span id="Message-274"><a href="#Message-274"><span class="linenos">274</span></a>        <span class="c1"># TODO how to convert &#39;embed&#39; to view?</span>
</span><span id="Message-275"><a href="#Message-275"><span class="linenos">275</span></a>
</span><span id="Message-276"><a href="#Message-276"><span class="linenos">276</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="s2">&quot;User&quot;</span><span class="p">:</span>
</span><span id="Message-277"><a href="#Message-277"><span class="linenos">277</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-278"><a href="#Message-278"><span class="linenos">278</span></a><span class="sd">        Returns the User associated with this Message.</span>
</span><span id="Message-279"><a href="#Message-279"><span class="linenos">279</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Message-280"><a href="#Message-280"><span class="linenos">280</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">user_id</span><span class="p">)</span>
</span><span id="Message-281"><a href="#Message-281"><span class="linenos">281</span></a>
</span><span id="Message-282"><a href="#Message-282"><span class="linenos">282</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">reply</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="s2">&quot;Message&quot;</span><span class="p">]:</span>
</span><span id="Message-283"><a href="#Message-283"><span class="linenos">283</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message-284"><a href="#Message-284"><span class="linenos">284</span></a><span class="sd">        Replies to this message with another message.</span>
</span><span id="Message-285"><a href="#Message-285"><span class="linenos">285</span></a>
</span><span id="Message-286"><a href="#Message-286"><span class="linenos">286</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Message-287"><a href="#Message-287"><span class="linenos">287</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Message-288"><a href="#Message-288"><span class="linenos">288</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Message-289"><a href="#Message-289"><span class="linenos">289</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="p">:</span> <span class="k">return</span> <span class="c1"># should never happen, but it makes our type checker happy</span>
</span><span id="Message-290"><a href="#Message-290"><span class="linenos">290</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">parent_message_id</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Represents a message sent by a user.</p>
</div>


                            <div id="Message.username" class="classattr">
                                <div class="attr variable">
            <span class="name">username</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Message.username"></a>
    
            <div class="docstring"><p>The username of the user which sent this message.</p>
</div>


                            </div>
                            <div id="Message.user_id" class="classattr">
                                <div class="attr variable">
            <span class="name">user_id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Message.user_id"></a>
    
            <div class="docstring"><p>The id of the user which sent this message.</p>
</div>


                            </div>
                            <div id="Message.profile_picture" class="classattr">
                                <div class="attr variable">
            <span class="name">profile_picture</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Message.profile_picture"></a>
    
            <div class="docstring"><p>The <em>relative</em> url of the user's pfp.</p>
</div>


                            </div>
                            <div id="Message.id" class="classattr">
                                <div class="attr variable">
            <span class="name">id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Message.id"></a>
    
            <div class="docstring"><p>The id of the message.</p>
</div>


                            </div>
                            <div id="Message.bot_message" class="classattr">
                                <div class="attr variable">
            <span class="name">bot_message</span><span class="annotation">: bool</span>

        
    </div>
    <a class="headerlink" href="#Message.bot_message"></a>
    
            <div class="docstring"><p>Whether the message was sent by a bot or not.</p>
</div>


                            </div>
                            <div id="Message.message" class="classattr">
                                <div class="attr variable">
            <span class="name">message</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Message.message"></a>
    
            <div class="docstring"><p>The content text of the message.</p>
</div>


                            </div>
                            <div id="Message.created_at" class="classattr">
                                <div class="attr variable">
            <span class="name">created_at</span><span class="annotation">: datetime.datetime</span>

        
    </div>
    <a class="headerlink" href="#Message.created_at"></a>
    
            <div class="docstring"><p>When the message was sent.</p>
</div>


                            </div>
                            <div id="Message.channel" class="classattr">
                                <div class="attr variable">
            <span class="name">channel</span><span class="annotation">: <a href="#Channel">Channel</a></span>

        
    </div>
    <a class="headerlink" href="#Message.channel"></a>
    
            <div class="docstring"><p>The channel the message was sent in.</p>
</div>


                            </div>
                            <div id="Message.parent_message_id" class="classattr">
                                <div class="attr variable">
            <span class="name">parent_message_id</span><span class="annotation">: Union[str, NoneType]</span>

        
    </div>
    <a class="headerlink" href="#Message.parent_message_id"></a>
    
            <div class="docstring"><p>The id of the parent message, if this message is a reply.</p>
</div>


                            </div>
                            <div id="Message.assets" class="classattr">
                                <div class="attr variable">
            <span class="name">assets</span><span class="annotation">: List[str]</span>

        
    </div>
    <a class="headerlink" href="#Message.assets"></a>
    
            <div class="docstring"><p>A list of all assets atached to this message.
Defaults to an empty list.</p>

<p>Not officially supported yet.</p>
</div>


                            </div>
                            <div id="Message.command" class="classattr">
                                <div class="attr variable">
            <span class="name">command</span><span class="annotation">: Union[str, NoneType]</span>

        
    </div>
    <a class="headerlink" href="#Message.command"></a>
    
            <div class="docstring"><p>The command name which this message replies to,
if this message is a command response.</p>
</div>


                            </div>
                            <div id="Message.command_user_id" class="classattr">
                                <div class="attr variable">
            <span class="name">command_user_id</span><span class="annotation">: Union[str, int, NoneType]</span>

        
    </div>
    <a class="headerlink" href="#Message.command_user_id"></a>
    
            <div class="docstring"><p>The user id of the user which used this command,
if this message is a command response.</p>
</div>


                            </div>
                            <div id="Message.get_user" class="classattr">
                                        <input id="Message.get_user-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">get_user</span><span class="signature pdoc-code condensed">(<span class="param"><span class="bp">self</span></span><span class="return-annotation">) -> <span class="n"><a href="#User">User</a></span>:</span></span>

                <label class="view-source-button" for="Message.get_user-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Message.get_user"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Message.get_user-276"><a href="#Message.get_user-276"><span class="linenos">276</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">get_user</span><span class="p">(</span><span class="bp">self</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="s2">&quot;User&quot;</span><span class="p">:</span>
</span><span id="Message.get_user-277"><a href="#Message.get_user-277"><span class="linenos">277</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message.get_user-278"><a href="#Message.get_user-278"><span class="linenos">278</span></a><span class="sd">        Returns the User associated with this Message.</span>
</span><span id="Message.get_user-279"><a href="#Message.get_user-279"><span class="linenos">279</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Message.get_user-280"><a href="#Message.get_user-280"><span class="linenos">280</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">get_user</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">user_id</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Returns the User associated with this Message.</p>
</div>


                            </div>
                            <div id="Message.reply" class="classattr">
                                        <input id="Message.reply-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">reply</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">message</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="s1">&#39;&#39;</span>,</span><span class="param">	<span class="n">view</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="wokkichat/addons/ui.html#View">wokkichat.addons.ui.View</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#Message">Message</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="Message.reply-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Message.reply"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Message.reply-282"><a href="#Message.reply-282"><span class="linenos">282</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">reply</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="s2">&quot;Message&quot;</span><span class="p">]:</span>
</span><span id="Message.reply-283"><a href="#Message.reply-283"><span class="linenos">283</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Message.reply-284"><a href="#Message.reply-284"><span class="linenos">284</span></a><span class="sd">        Replies to this message with another message.</span>
</span><span id="Message.reply-285"><a href="#Message.reply-285"><span class="linenos">285</span></a>
</span><span id="Message.reply-286"><a href="#Message.reply-286"><span class="linenos">286</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Message.reply-287"><a href="#Message.reply-287"><span class="linenos">287</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Message.reply-288"><a href="#Message.reply-288"><span class="linenos">288</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Message.reply-289"><a href="#Message.reply-289"><span class="linenos">289</span></a>        <span class="k">if</span> <span class="ow">not</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="p">:</span> <span class="k">return</span> <span class="c1"># should never happen, but it makes our type checker happy</span>
</span><span id="Message.reply-290"><a href="#Message.reply-290"><span class="linenos">290</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">parent_message_id</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Replies to this message with another message.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>message</strong>:  The message text to send.</li>
<li><strong>view</strong>:  The view to send.</li>
</ul>
</div>


                            </div>
                </section>
                <section id="Server">
                            <input id="Server-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">Server</span>:

                <label class="view-source-button" for="Server-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Server"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Server-294"><a href="#Server-294"><span class="linenos">294</span></a><span class="k">class</span><span class="w"> </span><span class="nc">Server</span><span class="p">:</span> <span class="c1"># add get_channel or something later?</span>
</span><span id="Server-295"><a href="#Server-295"><span class="linenos">295</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Server-296"><a href="#Server-296"><span class="linenos">296</span></a><span class="sd">    Represents a server on Wokki Chat.</span>
</span><span id="Server-297"><a href="#Server-297"><span class="linenos">297</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Server-298"><a href="#Server-298"><span class="linenos">298</span></a>
</span><span id="Server-299"><a href="#Server-299"><span class="linenos">299</span></a>    <span class="nb">id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Server-300"><a href="#Server-300"><span class="linenos">300</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Server-301"><a href="#Server-301"><span class="linenos">301</span></a><span class="sd">    The id of the server.</span>
</span><span id="Server-302"><a href="#Server-302"><span class="linenos">302</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Server-303"><a href="#Server-303"><span class="linenos">303</span></a>
</span><span id="Server-304"><a href="#Server-304"><span class="linenos">304</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="nb">id</span><span class="p">:</span> <span class="nb">str</span><span class="p">):</span>
</span><span id="Server-305"><a href="#Server-305"><span class="linenos">305</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Server-306"><a href="#Server-306"><span class="linenos">306</span></a><span class="sd">        @private</span>
</span><span id="Server-307"><a href="#Server-307"><span class="linenos">307</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Server-308"><a href="#Server-308"><span class="linenos">308</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">id</span> <span class="o">=</span> <span class="nb">id</span>
</span></pre></div>


            <div class="docstring"><p>Represents a server on Wokki Chat.</p>
</div>


                            <div id="Server.id" class="classattr">
                                <div class="attr variable">
            <span class="name">id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Server.id"></a>
    
            <div class="docstring"><p>The id of the server.</p>
</div>


                            </div>
                </section>
                <section id="Channel">
                            <input id="Channel-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">Channel</span>:

                <label class="view-source-button" for="Channel-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Channel"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Channel-311"><a href="#Channel-311"><span class="linenos">311</span></a><span class="k">class</span><span class="w"> </span><span class="nc">Channel</span><span class="p">:</span>
</span><span id="Channel-312"><a href="#Channel-312"><span class="linenos">312</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel-313"><a href="#Channel-313"><span class="linenos">313</span></a><span class="sd">    Represents a channel on Wokki Chat.</span>
</span><span id="Channel-314"><a href="#Channel-314"><span class="linenos">314</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Channel-315"><a href="#Channel-315"><span class="linenos">315</span></a>
</span><span id="Channel-316"><a href="#Channel-316"><span class="linenos">316</span></a>    <span class="nb">id</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="Channel-317"><a href="#Channel-317"><span class="linenos">317</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel-318"><a href="#Channel-318"><span class="linenos">318</span></a><span class="sd">    The id of the channel.</span>
</span><span id="Channel-319"><a href="#Channel-319"><span class="linenos">319</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Channel-320"><a href="#Channel-320"><span class="linenos">320</span></a>    <span class="n">server</span><span class="p">:</span> <span class="n">Server</span>
</span><span id="Channel-321"><a href="#Channel-321"><span class="linenos">321</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel-322"><a href="#Channel-322"><span class="linenos">322</span></a><span class="sd">    The server which owns this channel.</span>
</span><span id="Channel-323"><a href="#Channel-323"><span class="linenos">323</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="Channel-324"><a href="#Channel-324"><span class="linenos">324</span></a>
</span><span id="Channel-325"><a href="#Channel-325"><span class="linenos">325</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">bot</span><span class="p">:</span> <span class="s2">&quot;Bot&quot;</span><span class="p">,</span> <span class="n">server_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">,</span> <span class="n">channel_id</span><span class="p">:</span> <span class="nb">str</span><span class="p">):</span>
</span><span id="Channel-326"><a href="#Channel-326"><span class="linenos">326</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel-327"><a href="#Channel-327"><span class="linenos">327</span></a><span class="sd">        @private</span>
</span><span id="Channel-328"><a href="#Channel-328"><span class="linenos">328</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Channel-329"><a href="#Channel-329"><span class="linenos">329</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="p">:</span> <span class="n">Bot</span> <span class="o">=</span> <span class="n">bot</span>
</span><span id="Channel-330"><a href="#Channel-330"><span class="linenos">330</span></a>
</span><span id="Channel-331"><a href="#Channel-331"><span class="linenos">331</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">id</span> <span class="o">=</span> <span class="n">channel_id</span>
</span><span id="Channel-332"><a href="#Channel-332"><span class="linenos">332</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">server</span> <span class="o">=</span> <span class="n">Server</span><span class="p">(</span><span class="n">server_id</span><span class="p">)</span>
</span><span id="Channel-333"><a href="#Channel-333"><span class="linenos">333</span></a>
</span><span id="Channel-334"><a href="#Channel-334"><span class="linenos">334</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">send_message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="Channel-335"><a href="#Channel-335"><span class="linenos">335</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel-336"><a href="#Channel-336"><span class="linenos">336</span></a><span class="sd">        Sends a message in the channel.</span>
</span><span id="Channel-337"><a href="#Channel-337"><span class="linenos">337</span></a>
</span><span id="Channel-338"><a href="#Channel-338"><span class="linenos">338</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Channel-339"><a href="#Channel-339"><span class="linenos">339</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Channel-340"><a href="#Channel-340"><span class="linenos">340</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Channel-341"><a href="#Channel-341"><span class="linenos">341</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Represents a channel on Wokki Chat.</p>
</div>


                            <div id="Channel.id" class="classattr">
                                <div class="attr variable">
            <span class="name">id</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#Channel.id"></a>
    
            <div class="docstring"><p>The id of the channel.</p>
</div>


                            </div>
                            <div id="Channel.server" class="classattr">
                                <div class="attr variable">
            <span class="name">server</span><span class="annotation">: <a href="#Server">Server</a></span>

        
    </div>
    <a class="headerlink" href="#Channel.server"></a>
    
            <div class="docstring"><p>The server which owns this channel.</p>
</div>


                            </div>
                            <div id="Channel.send_message" class="classattr">
                                        <input id="Channel.send_message-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">send_message</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">message</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="s1">&#39;&#39;</span>,</span><span class="param">	<span class="n">view</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="wokkichat/addons/ui.html#View">wokkichat.addons.ui.View</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#Message">Message</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="Channel.send_message-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#Channel.send_message"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="Channel.send_message-334"><a href="#Channel.send_message-334"><span class="linenos">334</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">send_message</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="Channel.send_message-335"><a href="#Channel.send_message-335"><span class="linenos">335</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="Channel.send_message-336"><a href="#Channel.send_message-336"><span class="linenos">336</span></a><span class="sd">        Sends a message in the channel.</span>
</span><span id="Channel.send_message-337"><a href="#Channel.send_message-337"><span class="linenos">337</span></a>
</span><span id="Channel.send_message-338"><a href="#Channel.send_message-338"><span class="linenos">338</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="Channel.send_message-339"><a href="#Channel.send_message-339"><span class="linenos">339</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="Channel.send_message-340"><a href="#Channel.send_message-340"><span class="linenos">340</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="Channel.send_message-341"><a href="#Channel.send_message-341"><span class="linenos">341</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span><span class="bp">self</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Sends a message in the channel.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>message</strong>:  The message text to send.</li>
<li><strong>view</strong>:  The view to send.</li>
</ul>
</div>


                            </div>
                </section>
                <section id="ctx">
                            <input id="ctx-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr class">
            
    <span class="def">class</span>
    <span class="name">ctx</span>:

                <label class="view-source-button" for="ctx-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#ctx"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="ctx-344"><a href="#ctx-344"><span class="linenos">344</span></a><span class="k">class</span><span class="w"> </span><span class="nc">ctx</span><span class="p">:</span>
</span><span id="ctx-345"><a href="#ctx-345"><span class="linenos">345</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-346"><a href="#ctx-346"><span class="linenos">346</span></a><span class="sd">    Special context shared in bot commands.</span>
</span><span id="ctx-347"><a href="#ctx-347"><span class="linenos">347</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="ctx-348"><a href="#ctx-348"><span class="linenos">348</span></a>
</span><span id="ctx-349"><a href="#ctx-349"><span class="linenos">349</span></a>    <span class="n">command</span><span class="p">:</span> <span class="nb">str</span>
</span><span id="ctx-350"><a href="#ctx-350"><span class="linenos">350</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-351"><a href="#ctx-351"><span class="linenos">351</span></a><span class="sd">    The name of the used command.</span>
</span><span id="ctx-352"><a href="#ctx-352"><span class="linenos">352</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="ctx-353"><a href="#ctx-353"><span class="linenos">353</span></a>    <span class="n">user</span><span class="p">:</span> <span class="s2">&quot;User&quot;</span>
</span><span id="ctx-354"><a href="#ctx-354"><span class="linenos">354</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-355"><a href="#ctx-355"><span class="linenos">355</span></a><span class="sd">    The user that used the command.</span>
</span><span id="ctx-356"><a href="#ctx-356"><span class="linenos">356</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="ctx-357"><a href="#ctx-357"><span class="linenos">357</span></a>    <span class="n">channel</span><span class="p">:</span> <span class="s2">&quot;Channel&quot;</span>
</span><span id="ctx-358"><a href="#ctx-358"><span class="linenos">358</span></a><span class="w">    </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-359"><a href="#ctx-359"><span class="linenos">359</span></a><span class="sd">    The channel the command was used in.</span>
</span><span id="ctx-360"><a href="#ctx-360"><span class="linenos">360</span></a><span class="sd">    &quot;&quot;&quot;</span>
</span><span id="ctx-361"><a href="#ctx-361"><span class="linenos">361</span></a>
</span><span id="ctx-362"><a href="#ctx-362"><span class="linenos">362</span></a>    <span class="k">def</span><span class="w"> </span><span class="fm">__init__</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">t</span><span class="p">:</span> <span class="n">types</span><span class="o">.</span><span class="n">JSON</span><span class="p">):</span>
</span><span id="ctx-363"><a href="#ctx-363"><span class="linenos">363</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-364"><a href="#ctx-364"><span class="linenos">364</span></a><span class="sd">        @private</span>
</span><span id="ctx-365"><a href="#ctx-365"><span class="linenos">365</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="ctx-366"><a href="#ctx-366"><span class="linenos">366</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="p">:</span> <span class="n">Bot</span> <span class="o">=</span> <span class="n">t</span><span class="p">[</span><span class="s2">&quot;bot&quot;</span><span class="p">]</span>
</span><span id="ctx-367"><a href="#ctx-367"><span class="linenos">367</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">command</span> <span class="o">=</span> <span class="n">t</span><span class="p">[</span><span class="s2">&quot;command&quot;</span><span class="p">]</span>
</span><span id="ctx-368"><a href="#ctx-368"><span class="linenos">368</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">user</span> <span class="o">=</span> <span class="n">t</span><span class="p">[</span><span class="s2">&quot;user&quot;</span><span class="p">]</span>
</span><span id="ctx-369"><a href="#ctx-369"><span class="linenos">369</span></a>        <span class="bp">self</span><span class="o">.</span><span class="n">channel</span> <span class="o">=</span> <span class="n">t</span><span class="p">[</span><span class="s2">&quot;channel&quot;</span><span class="p">]</span>
</span><span id="ctx-370"><a href="#ctx-370"><span class="linenos">370</span></a>
</span><span id="ctx-371"><a href="#ctx-371"><span class="linenos">371</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">reply</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="ctx-372"><a href="#ctx-372"><span class="linenos">372</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx-373"><a href="#ctx-373"><span class="linenos">373</span></a><span class="sd">        Responds to this command with a message.</span>
</span><span id="ctx-374"><a href="#ctx-374"><span class="linenos">374</span></a>
</span><span id="ctx-375"><a href="#ctx-375"><span class="linenos">375</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="ctx-376"><a href="#ctx-376"><span class="linenos">376</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="ctx-377"><a href="#ctx-377"><span class="linenos">377</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="ctx-378"><a href="#ctx-378"><span class="linenos">378</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span>
</span><span id="ctx-379"><a href="#ctx-379"><span class="linenos">379</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">,</span>
</span><span id="ctx-380"><a href="#ctx-380"><span class="linenos">380</span></a>            <span class="n">command</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">command</span><span class="p">,</span> <span class="n">command_user_id</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">user</span><span class="o">.</span><span class="n">id</span>
</span><span id="ctx-381"><a href="#ctx-381"><span class="linenos">381</span></a>            <span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Special context shared in bot commands.</p>
</div>


                            <div id="ctx.command" class="classattr">
                                <div class="attr variable">
            <span class="name">command</span><span class="annotation">: str</span>

        
    </div>
    <a class="headerlink" href="#ctx.command"></a>
    
            <div class="docstring"><p>The name of the used command.</p>
</div>


                            </div>
                            <div id="ctx.user" class="classattr">
                                <div class="attr variable">
            <span class="name">user</span><span class="annotation">: <a href="#User">User</a></span>

        
    </div>
    <a class="headerlink" href="#ctx.user"></a>
    
            <div class="docstring"><p>The user that used the command.</p>
</div>


                            </div>
                            <div id="ctx.channel" class="classattr">
                                <div class="attr variable">
            <span class="name">channel</span><span class="annotation">: <a href="#Channel">Channel</a></span>

        
    </div>
    <a class="headerlink" href="#ctx.channel"></a>
    
            <div class="docstring"><p>The channel the command was used in.</p>
</div>


                            </div>
                            <div id="ctx.reply" class="classattr">
                                        <input id="ctx.reply-view-source" class="view-source-toggle-state" type="checkbox" aria-hidden="true" tabindex="-1">
<div class="attr function">
            
        <span class="def">async def</span>
        <span class="name">reply</span><span class="signature pdoc-code multiline">(<span class="param">	<span class="bp">self</span>,</span><span class="param">	<span class="n">message</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="nb">str</span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="s1">&#39;&#39;</span>,</span><span class="param">	<span class="n">view</span><span class="p">:</span> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="wokkichat/addons/ui.html#View">wokkichat.addons.ui.View</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span></span><span class="return-annotation">) -> <span class="n">Union</span><span class="p">[</span><span class="n"><a href="#Message">Message</a></span><span class="p">,</span> <span class="n">NoneType</span><span class="p">]</span>:</span></span>

                <label class="view-source-button" for="ctx.reply-view-source"><span>View Source</span></label>

    </div>
    <a class="headerlink" href="#ctx.reply"></a>
            <div class="pdoc-code codehilite"><pre><span></span><span id="ctx.reply-371"><a href="#ctx.reply-371"><span class="linenos">371</span></a>    <span class="k">async</span> <span class="k">def</span><span class="w"> </span><span class="nf">reply</span><span class="p">(</span><span class="bp">self</span><span class="p">,</span> <span class="n">message</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="nb">str</span><span class="p">]</span> <span class="o">=</span> <span class="s2">&quot;&quot;</span><span class="p">,</span> <span class="n">view</span><span class="p">:</span> <span class="n">Optional</span><span class="p">[</span><span class="n">ui</span><span class="o">.</span><span class="n">View</span><span class="p">]</span> <span class="o">=</span> <span class="kc">None</span><span class="p">)</span> <span class="o">-&gt;</span> <span class="n">Optional</span><span class="p">[</span><span class="n">Message</span><span class="p">]:</span>
</span><span id="ctx.reply-372"><a href="#ctx.reply-372"><span class="linenos">372</span></a><span class="w">        </span><span class="sd">&quot;&quot;&quot;</span>
</span><span id="ctx.reply-373"><a href="#ctx.reply-373"><span class="linenos">373</span></a><span class="sd">        Responds to this command with a message.</span>
</span><span id="ctx.reply-374"><a href="#ctx.reply-374"><span class="linenos">374</span></a>
</span><span id="ctx.reply-375"><a href="#ctx.reply-375"><span class="linenos">375</span></a><span class="sd">        :param message: The message text to send.</span>
</span><span id="ctx.reply-376"><a href="#ctx.reply-376"><span class="linenos">376</span></a><span class="sd">        :param view: The view to send.</span>
</span><span id="ctx.reply-377"><a href="#ctx.reply-377"><span class="linenos">377</span></a><span class="sd">        &quot;&quot;&quot;</span>
</span><span id="ctx.reply-378"><a href="#ctx.reply-378"><span class="linenos">378</span></a>        <span class="k">return</span> <span class="k">await</span> <span class="bp">self</span><span class="o">.</span><span class="n">_bot</span><span class="o">.</span><span class="n">send_message</span><span class="p">(</span>
</span><span id="ctx.reply-379"><a href="#ctx.reply-379"><span class="linenos">379</span></a>            <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">server</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="bp">self</span><span class="o">.</span><span class="n">channel</span><span class="o">.</span><span class="n">id</span><span class="p">,</span> <span class="n">message</span><span class="o">=</span><span class="n">message</span><span class="p">,</span> <span class="n">view</span><span class="o">=</span><span class="n">view</span><span class="p">,</span>
</span><span id="ctx.reply-380"><a href="#ctx.reply-380"><span class="linenos">380</span></a>            <span class="n">command</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">command</span><span class="p">,</span> <span class="n">command_user_id</span><span class="o">=</span><span class="bp">self</span><span class="o">.</span><span class="n">user</span><span class="o">.</span><span class="n">id</span>
</span><span id="ctx.reply-381"><a href="#ctx.reply-381"><span class="linenos">381</span></a>            <span class="p">)</span>
</span></pre></div>


            <div class="docstring"><p>Responds to this command with a message.</p>

<h6 id="parameters">Parameters</h6>

<ul>
<li><strong>message</strong>:  The message text to send.</li>
<li><strong>view</strong>:  The view to send.</li>
</ul>
</div>


                            </div>
                </section>
    </main>
<script>
    function escapeHTML(html) {
        return document.createElement('div').appendChild(document.createTextNode(html)).parentNode.innerHTML;
    }

    const originalContent = document.querySelector("main.pdoc");
    let currentContent = originalContent;

    function setContent(innerHTML) {
        let elem;
        if (innerHTML) {
            elem = document.createElement("main");
            elem.classList.add("pdoc");
            elem.innerHTML = innerHTML;
        } else {
            elem = originalContent;
        }
        if (currentContent !== elem) {
            currentContent.replaceWith(elem);
            currentContent = elem;
        }
    }

    function getSearchTerm() {
        return (new URL(window.location)).searchParams.get("search");
    }

    const searchBox = document.querySelector(".pdoc input[type=search]");
    searchBox.addEventListener("input", function () {
        let url = new URL(window.location);
        if (searchBox.value.trim()) {
            url.hash = "";
            url.searchParams.set("search", searchBox.value);
        } else {
            url.searchParams.delete("search");
        }
        history.replaceState("", "", url.toString());
        onInput();
    });
    window.addEventListener("popstate", onInput);


    let search, searchErr;

    async function initialize() {
        try {
            search = await new Promise((resolve, reject) => {
                const script = document.createElement("script");
                script.type = "text/javascript";
                script.async = true;
                script.onload = () => resolve(window.pdocSearch);
                script.onerror = (e) => reject(e);
                script.src = "search.js";
                document.getElementsByTagName("head")[0].appendChild(script);
            });
        } catch (e) {
            console.error("Cannot fetch pdoc search index");
            searchErr = "Cannot fetch search index.";
        }
        onInput();

        document.querySelector("nav.pdoc").addEventListener("click", e => {
            if (e.target.hash) {
                searchBox.value = "";
                searchBox.dispatchEvent(new Event("input"));
            }
        });
    }

    function onInput() {
        setContent((() => {
            const term = getSearchTerm();
            if (!term) {
                return null
            }
            if (searchErr) {
                return `<h3>Error: ${searchErr}</h3>`
            }
            if (!search) {
                return "<h3>Searching...</h3>"
            }

            window.scrollTo({top: 0, left: 0, behavior: 'auto'});

            const results = search(term);

            let html;
            if (results.length === 0) {
                html = `No search results for '${escapeHTML(term)}'.`
            } else {
                html = `<h4>${results.length} search result${results.length > 1 ? "s" : ""} for '${escapeHTML(term)}'.</h4>`;
            }
            for (let result of results.slice(0, 10)) {
                let doc = result.doc;
                let url = `${doc.modulename.replaceAll(".", "/")}.html`;
                if (doc.qualname) {
                    url += `#${doc.qualname}`;
                }

                let heading;
                switch (result.doc.kind) {
                    case "function":
                        if (doc.fullname.endsWith(".__init__")) {
                            heading = `<span class="name">${doc.fullname.replace(/\.__init__$/, "")}</span>${doc.signature}`;
                        } else {
                            heading = `<span class="def">${doc.funcdef}</span> <span class="name">${doc.fullname}</span>${doc.signature}`;
                        }
                        break;
                    case "class":
                        heading = `<span class="def">class</span> <span class="name">${doc.fullname}</span>`;
                        if (doc.bases)
                            heading += `<wbr>(<span class="base">${doc.bases}</span>)`;
                        heading += `:`;
                        break;
                    case "variable":
                        heading = `<span class="name">${doc.fullname}</span>`;
                        if (doc.annotation)
                            heading += `<span class="annotation">${doc.annotation}</span>`;
                        if (doc.default_value)
                            heading += `<span class="default_value"> = ${doc.default_value}</span>`;
                        break;
                    default:
                        heading = `<span class="name">${doc.fullname}</span>`;
                        break;
                }
                html += `
                        <section class="search-result">
                        <a href="${url}" class="attr ${doc.kind}">${heading}</a>
                        <div class="docstring">${doc.doc}</div>
                        </section>
                    `;

            }
            return html;
        })());
    }

    if (getSearchTerm()) {
        initialize();
        searchBox.value = getSearchTerm();
        onInput();
    } else {
        searchBox.addEventListener("focus", initialize, {once: true});
    }

    searchBox.addEventListener("keydown", e => {
        if (["ArrowDown", "ArrowUp", "Enter"].includes(e.key)) {
            let focused = currentContent.querySelector(".search-result.focused");
            if (!focused) {
                currentContent.querySelector(".search-result").classList.add("focused");
            } else if (
                e.key === "ArrowDown"
                && focused.nextElementSibling
                && focused.nextElementSibling.classList.contains("search-result")
            ) {
                focused.classList.remove("focused");
                focused.nextElementSibling.classList.add("focused");
                focused.nextElementSibling.scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                    inline: "nearest"
                });
            } else if (
                e.key === "ArrowUp"
                && focused.previousElementSibling
                && focused.previousElementSibling.classList.contains("search-result")
            ) {
                focused.classList.remove("focused");
                focused.previousElementSibling.classList.add("focused");
                focused.previousElementSibling.scrollIntoView({
                    behavior: "smooth",
                    block: "nearest",
                    inline: "nearest"
                });
            } else if (
                e.key === "Enter"
            ) {
                focused.querySelector("a").click();
            }
        }
    });
</script>
</body>
</html>
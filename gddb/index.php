<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameDev DataBase</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @font-face {
            font-family: "PixelizerBold";
            src: url("../swad/static/fonts/PixelizerBold.ttf") format('truetype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --bg: #0C0C0E;
            --bg2: #141416;
            --bg3: #1C1C20;
            --amber: #F5A623;
            --amber-dim: #C2841A;
            --amber-glow: rgba(245, 166, 35, 0.12);
            --text: #F0EDE8;
            --text2: #8C8A86;
            --text3: #4A4846;
            --border: #262628;
            --border2: #333336;
            --green: #3ECA7A;
            --red: #E05252;
            --mono: 'JetBrains Mono', monospace;
            --sans: 'Syne', sans-serif;
            --serif: 'Lora', serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* PAGE SYSTEM */
        .page {
            display: none;
        }

        .page.active {
            display: block;
        }

        /* NAV */
        nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(12, 12, 14, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 48px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo span {
            color: var(--amber);
        }

        .logo-mark {
            width: 28px;
            height: 28px;
            background: var(--amber);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-links {
            display: flex;
            gap: 4px;
            list-style: none;
            font-size: 13px;
        }

        .nav-links a {
            color: var(--text2);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.15s;
            cursor: pointer;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--text);
            background: var(--bg3);
        }

        .nav-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-family: var(--sans);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
            text-decoration: none;
        }

        .btn-ghost {
            background: transparent;
            color: var(--text2);
            border: 1px solid var(--border2);
        }

        .btn-ghost:hover {
            color: var(--text);
            border-color: var(--text3);
        }

        .btn-primary {
            background: var(--amber);
            color: #0C0C0E;
        }

        .btn-primary:hover {
            background: #FFB733;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* TAGS / BADGES */
        .tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
            font-family: var(--mono);
            background: var(--bg3);
            color: var(--text2);
            border: 1px solid var(--border);
        }

        .tag-amber {
            background: rgba(245, 166, 35, 0.1);
            color: var(--amber);
            border-color: rgba(245, 166, 35, 0.3);
        }

        .tag-green {
            background: rgba(62, 202, 122, 0.1);
            color: var(--green);
            border-color: rgba(62, 202, 122, 0.3);
        }

        .tag-free {
            background: rgba(62, 202, 122, 0.08);
            color: var(--green);
            border-color: rgba(62, 202, 122, 0.2);
        }

        /* ===================== HOME PAGE ===================== */
        .hero {
            padding: 100px 48px 80px;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }

        .hero-eyebrow {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 2px;
            color: var(--amber);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .hero-eyebrow::before,
        .hero-eyebrow::after {
            content: '';
            display: block;
            width: 32px;
            height: 1px;
            background: var(--amber);
            opacity: 0.4;
        }

        .hero h1 {
            font-size: clamp(48px, 7vw, 80px);
            font-weight: 800;
            line-height: 0.95;
            letter-spacing: -3px;
            margin-bottom: 24px;
        }

        .hero h1 em {
            font-style: italic;
            font-family: var(--serif);
            color: var(--amber);
            font-weight: 400;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 17px;
            color: var(--text2);
            line-height: 1.65;
            max-width: 540px;
            margin: 0 auto 40px;
            font-family: var(--serif);
        }

        /* SEARCH BAR */
        .search-wrap {
            position: relative;
            max-width: 620px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 16px 60px 16px 52px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 12px;
            color: var(--text);
            font-size: 15px;
            font-family: var(--sans);
            outline: none;
            transition: all 0.15s;
        }

        .search-input::placeholder {
            color: var(--text3);
        }

        .search-input:focus {
            border-color: var(--amber);
            background: var(--bg3);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            pointer-events: none;
        }

        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }

        .search-hints {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 14px;
        }

        .search-hint {
            font-size: 12px;
            color: var(--text3);
            cursor: pointer;
            font-family: var(--mono);
            transition: color 0.1s;
        }

        .search-hint:hover {
            color: var(--amber);
        }

        /* STATS ROW */
        .stats-row {
            display: flex;
            gap: 0;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            max-width: 620px;
            margin: 48px auto 0;
        }

        .stat-item {
            flex: 1;
            padding: 20px 24px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-num {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -1px;
        }

        .stat-num span {
            color: var(--amber);
        }

        .stat-label {
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px;
            font-family: var(--mono);
            letter-spacing: 0.5px;
        }

        /* SECTION */
        .section {
            padding: 0 48px 80px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 2px;
            color: var(--text2);
            font-family: var(--mono);
            text-transform: uppercase;
        }

        .section-link {
            font-size: 12px;
            color: var(--amber);
            cursor: pointer;
            text-decoration: none;
            font-family: var(--mono);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* LESSON CARDS */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .lesson-card {
            background: var(--bg);
            padding: 28px;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .lesson-card:hover {
            background: var(--bg2);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-num {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            font-weight: 500;
        }

        .card-price {
            font-family: var(--mono);
            font-size: 12px;
            font-weight: 600;
        }

        .price-free {
            color: var(--green);
        }

        .price-paid {
            color: var(--amber);
        }

        .card-title {
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.3px;
            color: var(--text);
        }

        .card-desc {
            font-size: 13px;
            color: var(--text2);
            line-height: 1.55;
            font-family: var(--serif);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .card-author {
            font-size: 12px;
            color: var(--text3);
            font-family: var(--mono);
        }

        .card-tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* RESOURCE CARDS */
        .resources-list {
            display: flex;
            flex-direction: column;
            gap: 1px;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--border);
        }

        .resource-row {
            background: var(--bg);
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .resource-row:hover {
            background: var(--bg2);
        }

        .resource-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--bg3);
            border: 1px solid var(--border2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }

        .resource-body {
            flex: 1;
        }

        .resource-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 3px;
        }

        .resource-url {
            font-size: 11px;
            color: var(--text3);
            font-family: var(--mono);
        }

        .resource-desc {
            font-size: 13px;
            color: var(--text2);
            margin-top: 4px;
            font-family: var(--serif);
        }

        .resource-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        /* ===================== SEARCH/CATALOG PAGE ===================== */
        .catalog-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 0;
            min-height: calc(100vh - 60px);
        }

        .sidebar {
            border-right: 1px solid var(--border);
            padding: 32px 24px;
            position: sticky;
            top: 60px;
            height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .sidebar-section {
            margin-bottom: 32px;
        }

        .sidebar-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2px;
            color: var(--text3);
            font-family: var(--mono);
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.1s;
            font-size: 13px;
            color: var(--text2);
            user-select: none;
        }

        .filter-item:hover {
            background: var(--bg3);
            color: var(--text);
        }

        .filter-item.active {
            background: var(--amber-glow);
            color: var(--amber);
        }

        .filter-count {
            margin-left: auto;
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
        }

        .filter-check {
            width: 14px;
            height: 14px;
            border: 1px solid var(--border2);
            border-radius: 3px;
            flex-shrink: 0;
            transition: all 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-item.active .filter-check {
            background: var(--amber);
            border-color: var(--amber);
        }

        .filter-item.active .filter-check::after {
            content: '✓';
            font-size: 9px;
            color: #0C0C0E;
            font-weight: 700;
        }

        .catalog-main {
            padding: 32px 40px;
        }

        .catalog-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .catalog-search-wrap {
            position: relative;
            flex: 1;
            max-width: 480px;
        }

        .catalog-search {
            width: 100%;
            padding: 10px 40px 10px 38px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
            font-family: var(--sans);
            outline: none;
            transition: all 0.15s;
        }

        .catalog-search::placeholder {
            color: var(--text3);
        }

        .catalog-search:focus {
            border-color: var(--amber);
        }

        .results-info {
            font-size: 13px;
            color: var(--text3);
            font-family: var(--mono);
        }

        .results-info strong {
            color: var(--text);
        }

        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0;
        }

        .tab {
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text3);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.15s;
            margin-bottom: -1px;
        }

        .tab:hover {
            color: var(--text);
        }

        .tab.active {
            color: var(--amber);
            border-bottom-color: var(--amber);
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        /* ===================== LESSON DETAIL PAGE ===================== */
        .lesson-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 48px;
            gap: 0;
        }

        .lesson-body {
            padding: 48px 64px 80px 0;
        }

        .lesson-meta-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .breadcrumb {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .breadcrumb span {
            color: var(--text2);
            cursor: pointer;
        }

        .breadcrumb span:hover {
            color: var(--amber);
        }

        .lesson-title-main {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
            margin-bottom: 16px;
        }

        .lesson-subtitle {
            font-family: var(--serif);
            font-size: 16px;
            color: var(--text2);
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .lesson-info-row {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 40px;
            font-size: 13px;
            color: var(--text2);
        }

        .lesson-info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: var(--mono);
            font-size: 12px;
        }

        .lesson-content h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 36px 0 14px;
            letter-spacing: -0.3px;
            color: var(--text);
        }

        .lesson-content h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 28px 0 10px;
            color: var(--text2);
        }

        .lesson-content p {
            font-family: var(--serif);
            font-size: 16px;
            line-height: 1.75;
            color: var(--text2);
            margin-bottom: 18px;
        }

        .lesson-content code {
            font-family: var(--mono);
            font-size: 13px;
            background: var(--bg3);
            border: 1px solid var(--border2);
            padding: 2px 7px;
            border-radius: 4px;
            color: var(--amber);
        }

        .code-block {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            border-bottom: 1px solid var(--border);
        }

        .code-lang {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
        }

        .code-copy {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            cursor: pointer;
        }

        .code-copy:hover {
            color: var(--amber);
        }

        .code-body {
            padding: 20px 18px;
            font-family: var(--mono);
            font-size: 13px;
            line-height: 1.7;
            color: var(--text);
            overflow-x: auto;
        }

        .code-body .kw {
            color: #C792EA;
        }

        .code-body .str {
            color: #C3E88D;
        }

        .code-body .cmt {
            color: var(--text3);
            font-style: italic;
        }

        .code-body .fn {
            color: #82AAFF;
        }

        .code-body .num {
            color: var(--amber);
        }

        /* LESSON SIDEBAR */
        .lesson-sidebar {
            padding: 48px 0 80px;
            border-left: 1px solid var(--border);
            padding-left: 40px;
        }

        .purchase-card {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .purchase-price {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1.5px;
            margin-bottom: 4px;
        }

        .purchase-price small {
            font-size: 14px;
            color: var(--text2);
            font-weight: 400;
            margin-left: 2px;
        }

        .purchase-features {
            list-style: none;
            margin: 18px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .purchase-features li {
            font-size: 13px;
            color: var(--text2);
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--serif);
        }

        .purchase-features li::before {
            content: '';
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(62, 202, 122, 0.15);
            border: 1px solid rgba(62, 202, 122, 0.3);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: var(--green);
        }

        .toc {
            margin-top: 24px;
        }

        .toc-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            color: var(--text3);
            font-family: var(--mono);
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .toc-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text2);
            cursor: pointer;
        }

        .toc-item:hover {
            color: var(--amber);
        }

        .toc-num {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text3);
            width: 20px;
        }

        /* ===================== ADD CONTENT PAGE ===================== */
        .add-layout {
            max-width: 760px;
            margin: 0 auto;
            padding: 64px 48px 80px;
        }

        .add-header {
            margin-bottom: 48px;
        }

        .add-title {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1.5px;
            margin-bottom: 10px;
        }

        .add-subtitle {
            font-family: var(--serif);
            font-size: 16px;
            color: var(--text2);
            line-height: 1.65;
        }

        .type-switcher {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 40px;
        }

        .type-option {
            padding: 20px 24px;
            border-radius: 12px;
            border: 1px solid var(--border2);
            cursor: pointer;
            transition: all 0.15s;
            background: var(--bg2);
        }

        .type-option:hover {
            border-color: var(--text3);
            background: var(--bg3);
        }

        .type-option.active {
            border-color: var(--amber);
            background: var(--amber-glow);
        }

        .type-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .type-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .type-desc {
            font-size: 12px;
            color: var(--text2);
            font-family: var(--serif);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            font-family: var(--mono);
            color: var(--text2);
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-label span {
            color: var(--amber);
            margin-left: 3px;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
            font-family: var(--sans);
            outline: none;
            transition: all 0.15s;
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: var(--text3);
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            border-color: var(--amber);
            background: var(--bg3);
        }

        .form-textarea {
            min-height: 200px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-select {
            appearance: none;
            cursor: pointer;
        }

        .form-select option {
            background: var(--bg2);
        }

        .form-hint {
            font-size: 11px;
            color: var(--text3);
            margin-top: 6px;
            font-family: var(--mono);
        }

        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .price-toggle {
            display: flex;
            gap: 0;
            margin-bottom: 12px;
        }

        .price-btn {
            flex: 1;
            padding: 9px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: 1px solid var(--border2);
            color: var(--text2);
        }

        .price-btn:first-child {
            border-radius: 6px 0 0 6px;
        }

        .price-btn:last-child {
            border-radius: 0 6px 6px 0;
            border-left: none;
        }

        .price-btn.active {
            background: var(--amber);
            color: #0C0C0E;
            border-color: var(--amber);
        }

        .tags-input-wrap {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 8px;
            padding: 8px 12px;
            min-height: 46px;
            transition: border-color 0.15s;
        }

        .tags-input-wrap:focus-within {
            border-color: var(--amber);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--bg3);
            border: 1px solid var(--border2);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-family: var(--mono);
            color: var(--text2);
        }

        .tag-remove {
            cursor: pointer;
            color: var(--text3);
            font-size: 11px;
        }

        .tag-remove:hover {
            color: var(--red);
        }

        .tags-input {
            flex: 1;
            min-width: 80px;
            background: none;
            border: none;
            outline: none;
            color: var(--text);
            font-size: 13px;
            font-family: var(--mono);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            margin-top: 8px;
        }

        .btn-large {
            padding: 14px 32px;
            font-size: 14px;
            border-radius: 10px;
        }

        .moderation-note {
            font-size: 12px;
            color: var(--text3);
            font-family: var(--serif);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 20px;
            padding: 14px 16px;
            background: var(--bg2);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .moderation-note::before {
            content: 'ℹ';
            font-size: 14px;
            color: var(--text3);
            flex-shrink: 0;
        }

        /* SCROLL TRIGGER ANIMATIONS */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-up {
            animation: fadeUp 0.4s ease forwards;
        }

        .fade-up-2 {
            animation: fadeUp 0.4s 0.1s ease both;
        }

        .fade-up-3 {
            animation: fadeUp 0.4s 0.2s ease both;
        }

        /* FOOTER */
        footer {
            border-top: 1px solid var(--border);
            padding: 32px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            font-size: 14px;
            font-weight: 700;
            color: var(--text2);
        }

        .footer-logo span {
            color: var(--amber);
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            font-size: 12px;
            color: var(--text3);
            text-decoration: none;
            cursor: pointer;
        }

        .footer-links a:hover {
            color: var(--text2);
        }

        .footer-copy {
            font-size: 11px;
            color: var(--text3);
            font-family: var(--mono);
        }

        /* HIGHLIGHT */
        mark {
            background: rgba(245, 166, 35, 0.2);
            color: var(--amber);
            border-radius: 2px;
            padding: 0 2px;
        }

        /* SEARCH HIGHLIGHT */
        .search-match {
            color: var(--amber);
            font-weight: 600;
        }
    </style>
</head>

<body>

    <!-- NAV -->
    <nav>
        <div class="nav-logo" onclick="showPage('home')">
            <div class="logo-mark"></div>
            GameDev<span>Database</span> ✕ <span style="font-family: 'PixelizerBold', 'Gill Sans', sans-serif !important; font-size: 2rem; background: linear-gradient(to bottom, #a148aa, #dd743d, #fff837); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Dustore</span>
        </div>
        <ul class="nav-links">
            <li><a onclick="showPage('home')" id="nav-home" class="active">Главная</a></li>
            <li><a onclick="showPage('catalog')" id="nav-catalog">Уроки</a></li>
            <li><a onclick="showPage('catalog')" id="nav-resources">Ресурсы</a></li>
            <li><a onclick="showPage('add')" id="nav-add">Добавить</a></li>
        </ul>
        <?php if (empty($_SESSION['USERDATA']['id'])): ?>
            <div class="nav-right">
                <button class="btn btn-primary btn-sm" onclick="window.location.href='/login'">Войти в аккаунт</button>
            </div>
        <?php else: ?>
            <div class="nav-right">
                <button class="btn btn-primary btn-sm" onclick="window.location.href='/player/<?= $_SESSION['USERDATA']['username'] ?? $_SESSION['USERDATA']['telegram_username'] ?>'"><?= $_SESSION['USERDATA']['username'] ?? $_SESSION['USERDATA']['telegram_username'] ?></button>
            </div>
        <?php endif; ?>
    </nav>

    <!-- ==================== HOME PAGE ==================== -->
    <div id="page-home" class="page active">

        <div class="hero">
            <div class="hero-eyebrow fade-up">БАЗА ЗНАНИЙ ПО ГЕЙМДЕВУ</div>
            <h1 class="fade-up-2">Учись делать<br><em>игры правильно</em></h1>
            <p class="fade-up-3">Уроки от разработчиков, база ресурсов, поиск по любому слову. Всё что нужно — в одном месте.</p>

            <div class="search-wrap fade-up-3">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <input class="search-input" placeholder="Найди любое слово — шейдер, физика, ECS, UI..."
                    id="heroSearch" onkeydown="if(event.key==='Enter'){doSearch()}" />
                <button class="btn btn-primary btn-sm search-btn" onclick="doSearch()">Найти</button>
            </div>

            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-num">3<span>47</span></div>
                    <div class="stat-label">Уроков</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">1<span>290</span></div>
                    <div class="stat-label">Ресурсов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num"><span>84</span></div>
                    <div class="stat-label">Авторов</div>
                </div>
            </div>
        </div>

        <!-- FEATURED LESSONS -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">// Новые уроки</div>
                <a class="section-link" onclick="showPage('catalog')">Все уроки →</a>
            </div>
            <div class="cards-grid">
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#001</span>
                        <span class="card-price price-paid">790 ₽</span>
                    </div>
                    <div class="card-title">Система шейдеров в Unity URP: от нуля до кастомного рендера</div>
                    <div class="card-desc">Разбираем Shader Graph и HLSL в Universal Render Pipeline, пишем cel-shading с нуля, подключаем кастомные passes.</div>
                    <div class="card-footer">
                        <span class="card-author">by shadermage</span>
                        <div class="card-tags">
                            <span class="tag">Unity</span>
                            <span class="tag tag-amber">Шейдеры</span>
                        </div>
                    </div>
                </div>
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#002</span>
                        <span class="card-price price-free">Бесплатно</span>
                    </div>
                    <div class="card-title">ECS в Godot 4: как не утонуть в нодах</div>
                    <div class="card-desc">Пишем Entity-Component-System с нуля используя только GDScript. Сравниваем с обычным подходом на примере 300 врагов.</div>
                    <div class="card-footer">
                        <span class="card-author">by gg_dev</span>
                        <div class="card-tags">
                            <span class="tag">Godot</span>
                            <span class="tag">Архитектура</span>
                        </div>
                    </div>
                </div>
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#003</span>
                        <span class="card-price price-paid">490 ₽</span>
                    </div>
                    <div class="card-title">Процедурная генерация уровней: алгоритм BSP</div>
                    <div class="card-desc">Binary Space Partitioning для генерации данжонов. Пишем на C#, настраиваем параметры комнат, соединяем коридорами.</div>
                    <div class="card-footer">
                        <span class="card-author">by procgen_ru</span>
                        <div class="card-tags">
                            <span class="tag">C#</span>
                            <span class="tag tag-amber">Алгоритмы</span>
                        </div>
                    </div>
                </div>
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#004</span>
                        <span class="card-price price-free">Бесплатно</span>
                    </div>
                    <div class="card-title">Physics в Unreal 5: Chaos и работа с коллизиями</div>
                    <div class="card-desc">Обзор нового физического движка, разрушаемость объектов через Geometry Collections, настройка simulation constraints.</div>
                    <div class="card-footer">
                        <span class="card-author">by unrealmstr</span>
                        <div class="card-tags">
                            <span class="tag">Unreal</span>
                            <span class="tag">Физика</span>
                        </div>
                    </div>
                </div>
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#005</span>
                        <span class="card-price price-paid">1 200 ₽</span>
                    </div>
                    <div class="card-title">Мультиплеер на Unity: Mirror и предсказание ввода</div>
                    <div class="card-desc">Полный курс по Mirror Networking: архитектура клиент-сервер, client-side prediction, lag compensation, rollback.</div>
                    <div class="card-footer">
                        <span class="card-author">by netcode_guy</span>
                        <div class="card-tags">
                            <span class="tag">Unity</span>
                            <span class="tag tag-amber">Multiplayer</span>
                        </div>
                    </div>
                </div>
                <div class="lesson-card" onclick="showLesson()">
                    <div class="card-top">
                        <span class="card-num">#006</span>
                        <span class="card-price price-free">Бесплатно</span>
                    </div>
                    <div class="card-title">Анимации в Godot 4: AnimationTree и State Machine</div>
                    <div class="card-desc">AnimationPlayer, AnimationTree, BlendSpace 2D — полный разбор. Делаем плавные переходы для платформера.</div>
                    <div class="card-footer">
                        <span class="card-author">by anim8r</span>
                        <div class="card-tags">
                            <span class="tag">Godot</span>
                            <span class="tag">Анимация</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESOURCES -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">// База ресурсов</div>
                <a class="section-link" onclick="showPage('catalog')">Все ресурсы →</a>
            </div>
            <div class="resources-list">
                <div class="resource-row">
                    <div class="resource-icon">📚</div>
                    <div class="resource-body">
                        <div class="resource-title">Game Programming Patterns</div>
                        <div class="resource-url">gameprogrammingpatterns.com</div>
                        <div class="resource-desc">Классическая книга по паттернам в геймдеве — бесплатно онлайн</div>
                    </div>
                    <div class="resource-right">
                        <span class="tag tag-free">Бесплатно</span>
                        <span class="tag">Книга</span>
                    </div>
                </div>
                <div class="resource-row">
                    <div class="resource-icon">🎮</div>
                    <div class="resource-body">
                        <div class="resource-title">Unity Learn Premium</div>
                        <div class="resource-url">learn.unity.com</div>
                        <div class="resource-desc">Официальные курсы Unity с проектами, есть бесплатный тир</div>
                    </div>
                    <div class="resource-right">
                        <span class="tag tag-amber">Freemium</span>
                        <span class="tag">Курс</span>
                    </div>
                </div>
                <div class="resource-row">
                    <div class="resource-icon">🛠</div>
                    <div class="resource-body">
                        <div class="resource-title">Awesome Gamedev — GitHub</div>
                        <div class="resource-url">github.com/Calinou/awesome-gamedev</div>
                        <div class="resource-desc">Гигантский список инструментов, туториалов и ресурсов с открытым исходным кодом</div>
                    </div>
                    <div class="resource-right">
                        <span class="tag tag-free">Бесплатно</span>
                        <span class="tag">Список</span>
                    </div>
                </div>
                <div class="resource-row">
                    <div class="resource-icon">🎓</div>
                    <div class="resource-body">
                        <div class="resource-title">GDC Vault — бесплатные доклады</div>
                        <div class="resource-url">gdcvault.com/free</div>
                        <div class="resource-desc">Сотни докладов с GDC в свободном доступе — дизайн, технологии, нарратив</div>
                    </div>
                    <div class="resource-right">
                        <span class="tag tag-free">Бесплатно</span>
                        <span class="tag">Видео</span>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <div class="footer-logo">Gamedev<span>Database</span>, проект Dustore</div>
            <div class="footer-links">
                <a>О проекте</a>
                <a>Правила</a>
                <a>API</a>
                <a>Telegram</a>
            </div>
            <div class="footer-copy">© 2026 ООО Лаборатория безумных проектов</div>
            <div class="footer-copy">© 2026 Авторское право всех ресурсов принадлежит их владельцам</div>
        </footer>
    </div>


    <!-- ==================== CATALOG PAGE ==================== -->
    <div id="page-catalog" class="page">
        <div class="catalog-layout">
            <aside class="sidebar">
                <div class="sidebar-section">
                    <div class="sidebar-label">Тип</div>
                    <div class="filter-item active" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Все
                        <span class="filter-count">1637</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Уроки
                        <span class="filter-count">347</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Ресурсы
                        <span class="filter-count">1290</span>
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Цена</div>
                    <div class="filter-item active" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Любая
                        <span class="filter-count">1637</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Бесплатно
                        <span class="filter-count">1104</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Платные
                        <span class="filter-count">533</span>
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Движок / Платформа</div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Unity
                        <span class="filter-count">214</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Unreal Engine
                        <span class="filter-count">198</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Godot
                        <span class="filter-count">141</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        GameMaker
                        <span class="filter-count">67</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>
                        Движконезависимо
                        <span class="filter-count">302</span>
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Тема</div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Шейдеры<span class="filter-count">89</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Физика<span class="filter-count">74</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Архитектура<span class="filter-count">112</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Мультиплеер<span class="filter-count">56</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Графика / 2D<span class="filter-count">93</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>UI / UX<span class="filter-count">78</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Геймдизайн<span class="filter-count">120</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Монетизация<span class="filter-count">34</span>
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Язык</div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>C#<span class="filter-count">198</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>C++<span class="filter-count">145</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>GDScript<span class="filter-count">134</span>
                    </div>
                    <div class="filter-item" onclick="toggleFilter(this)">
                        <div class="filter-check"></div>Blueprint<span class="filter-count">89</span>
                    </div>
                </div>
            </aside>

            <main class="catalog-main">
                <div class="catalog-top">
                    <div class="catalog-search-wrap">
                        <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                        <input class="catalog-search" id="catalogSearch" placeholder="Поиск по словам, тегам, авторам..." value="" />
                    </div>
                    <span class="results-info">Найдено <strong>1 637</strong> результатов</span>
                </div>

                <div class="tabs">
                    <div class="tab active">Все</div>
                    <div class="tab">Уроки</div>
                    <div class="tab">Ресурсы</div>
                    <div class="tab">Авторы</div>
                </div>

                <div class="catalog-grid">
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Урок</span></div>
                            <span class="card-price price-paid">790 ₽</span>
                        </div>
                        <div class="card-title">Система шейдеров в Unity URP</div>
                        <div class="card-desc">Shader Graph, HLSL, cel-shading, кастомные render passes в Universal Render Pipeline.</div>
                        <div class="card-footer">
                            <span class="card-author">by shadermage</span>
                            <div class="card-tags"><span class="tag">Unity</span><span class="tag tag-amber">Шейдеры</span></div>
                        </div>
                    </div>
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Ресурс</span></div>
                            <span class="card-price price-free">Бесплатно</span>
                        </div>
                        <div class="card-title">Game Programming Patterns</div>
                        <div class="card-desc">Паттерны проектирования специфичные для игровой разработки — обновлённое онлайн-издание.</div>
                        <div class="card-footer">
                            <span class="card-author">gameprogrammingpatterns.com</span>
                            <div class="card-tags"><span class="tag">Книга</span><span class="tag">Архитектура</span></div>
                        </div>
                    </div>
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Урок</span></div>
                            <span class="card-price price-free">Бесплатно</span>
                        </div>
                        <div class="card-title">ECS в Godot 4: как не утонуть в нодах</div>
                        <div class="card-desc">Entity-Component-System с нуля на GDScript, тест производительности с 300 врагами.</div>
                        <div class="card-footer">
                            <span class="card-author">by gg_dev</span>
                            <div class="card-tags"><span class="tag">Godot</span><span class="tag">ECS</span></div>
                        </div>
                    </div>
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Урок</span></div>
                            <span class="card-price price-paid">490 ₽</span>
                        </div>
                        <div class="card-title">Процедурная генерация уровней: BSP</div>
                        <div class="card-desc">Binary Space Partitioning для данжонов. C#, настройка параметров, соединение коридорами.</div>
                        <div class="card-footer">
                            <span class="card-author">by procgen_ru</span>
                            <div class="card-tags"><span class="tag">C#</span><span class="tag tag-amber">Алгоритмы</span></div>
                        </div>
                    </div>
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Ресурс</span></div>
                            <span class="card-price price-free">Бесплатно</span>
                        </div>
                        <div class="card-title">GDC Vault — бесплатные доклады</div>
                        <div class="card-desc">Сотни докладов с Game Developers Conference в открытом доступе.</div>
                        <div class="card-footer">
                            <span class="card-author">gdcvault.com/free</span>
                            <div class="card-tags"><span class="tag">Видео</span><span class="tag">Дизайн</span></div>
                        </div>
                    </div>
                    <div class="lesson-card" onclick="showLesson()">
                        <div class="card-top">
                            <div><span class="tag">Урок</span></div>
                            <span class="card-price price-paid">1 200 ₽</span>
                        </div>
                        <div class="card-title">Мультиплеер на Unity: Mirror + prediction</div>
                        <div class="card-desc">Полная архитектура клиент-сервер, client-side prediction, lag compensation, rollback networking.</div>
                        <div class="card-footer">
                            <span class="card-author">by netcode_guy</span>
                            <div class="card-tags"><span class="tag">Unity</span><span class="tag tag-amber">Multiplayer</span></div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:center;gap:4px;margin-top:32px;padding-top:32px;border-top:1px solid var(--border)">
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">← Пред</button>
                    <button class="btn btn-primary btn-sm" style="font-family:var(--mono)">1</button>
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">2</button>
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">3</button>
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">...</button>
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">42</button>
                    <button class="btn btn-ghost btn-sm" style="font-family:var(--mono)">След →</button>
                </div>
            </main>
        </div>
    </div>


    <!-- ==================== LESSON PAGE ==================== -->
    <div id="page-lesson" class="page">
        <div class="lesson-layout">
            <article class="lesson-body">
                <div class="lesson-meta-top">
                    <div class="breadcrumb">
                        <span onclick="showPage('home')">Главная</span>
                        /
                        <span onclick="showPage('catalog')">Уроки</span>
                        /
                        <span style="color:var(--text3)">Шейдеры Unity URP</span>
                    </div>
                </div>

                <div style="display:flex;gap:8px;margin-bottom:16px">
                    <span class="tag tag-amber">Шейдеры</span>
                    <span class="tag">Unity</span>
                    <span class="tag">URP</span>
                    <span class="tag">HLSL</span>
                </div>

                <h1 class="lesson-title-main">Система шейдеров в Unity URP: от нуля до кастомного рендера</h1>
                <p class="lesson-subtitle">Разбираем Shader Graph и HLSL в Universal Render Pipeline, пишем cel-shading с нуля, подключаем кастомные Renderer Feature passes и настраиваем post-processing.</p>

                <div class="lesson-info-row">
                    <div class="lesson-info-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        shadermage
                    </div>
                    <div class="lesson-info-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        14 марта 2025
                    </div>
                    <div class="lesson-info-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                        ~45 мин чтения
                    </div>
                    <div class="lesson-info-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        2 341 просмотр
                    </div>
                </div>

                <div class="lesson-content">
                    <h2>Что такое URP и почему он важен</h2>
                    <p>Universal Render Pipeline — это скриптуемый рендер-пайплайн от Unity, который заменил устаревший Built-in. Главное его преимущество — <code>Single Pass Rendering</code> и полный контроль над конвейером рендеринга через C# API.</p>
                    <p>В отличие от Built-in, в URP вы можете добавлять кастомные <code>ScriptableRendererFeature</code> — это как middleware для рендера. Именно это позволяет делать outline-эффекты, screen-space рефлексии и многое другое без костылей.</p>

                    <h2>Shader Graph vs. HLSL: когда что использовать</h2>
                    <p>Shader Graph — визуальный редактор для нетехнических художников. HLSL — прямой путь к GPU с полным контролем. На практике в продакшне обычно комбинируют оба подхода: прототипируют в Graph, потом переписывают критичные части в HLSL.</p>

                    <div class="code-block">
                        <div class="code-header">
                            <span class="code-lang">HLSL — BasicUnlitShader.shader</span>
                            <span class="code-copy" onclick="this.textContent='Скопировано!'">Копировать</span>
                        </div>
                        <div class="code-body"><span class="kw">Shader</span> <span class="str">"Custom/CelShading"</span>
                            {
                            Properties
                            {
                            _MainTex (<span class="str">"Texture"</span>, 2D) = <span class="str">"white"</span> {}
                            _RampTex (<span class="str">"Ramp Texture"</span>, 2D) = <span class="str">"white"</span> {}
                            _StepCount (<span class="str">"Step Count"</span>, Range(<span class="num">1</span>, <span class="num">8</span>)) = <span class="num">3</span>
                            }
                            SubShader
                            {
                            Tags { <span class="str">"RenderType"</span>=<span class="str">"Opaque"</span> <span class="str">"RenderPipeline"</span>=<span class="str">"UniversalPipeline"</span> }

                            Pass
                            {
                            HLSLPROGRAM
                            <span class="kw">#pragma</span> vertex vert
                            <span class="kw">#pragma</span> fragment frag
                            <span class="kw">#include</span> <span class="str">"Packages/com.unity.render-pipelines.universal/ShaderLibrary/Core.hlsl"</span>
                            <span class="kw">#include</span> <span class="str">"Packages/com.unity.render-pipelines.universal/ShaderLibrary/Lighting.hlsl"</span>

                            <span class="kw">struct</span> Attributes { float4 positionOS : POSITION; float3 normalOS : NORMAL; float2 uv : TEXCOORD0; };
                            <span class="kw">struct</span> Varyings { float4 positionHCS : SV_POSITION; float3 normalWS : TEXCOORD1; float2 uv : TEXCOORD0; };

                            Varyings <span class="fn">vert</span>(Attributes IN)
                            {
                            Varyings OUT;
                            OUT.positionHCS = <span class="fn">TransformObjectToHClip</span>(IN.positionOS.xyz);
                            OUT.normalWS = <span class="fn">TransformObjectToWorldNormal</span>(IN.normalOS);
                            OUT.uv = IN.uv;
                            <span class="kw">return</span> OUT;
                            }

                            half4 <span class="fn">frag</span>(Varyings IN) : SV_Target
                            {
                            Light mainLight = <span class="fn">GetMainLight</span>();
                            float NdotL = <span class="fn">dot</span>(<span class="fn">normalize</span>(IN.normalWS), mainLight.direction);
                            <span class="cmt">// Квантование освещения на N ступеней</span>
                            float stepped = <span class="fn">floor</span>(NdotL * _StepCount) / _StepCount;
                            <span class="kw">return</span> half4(mainLight.color * stepped, <span class="num">1</span>);
                            }
                            ENDHLSL
                            }
                            }
                            }
                        </div>
                    </div>

                    <h3>Renderer Feature: добавляем outline pass</h3>
                    <p>Outline-эффект в URP реализуется через кастомный <code>ScriptableRendererFeature</code>. Суть — делаем второй проход рендеринга с инвертированными нормалями, масштабированными чуть больше оригинала, и рисуем их одним цветом.</p>
                    <p>Преимущество этого подхода перед post-processing outline — корректная работа с прозрачностью и маскировка за объектами сцены.</p>

                    <h2>Настройка Renderer Feature</h2>
                    <p>В Renderer Asset (Project Settings → Graphics) нажимаем Add Renderer Feature. Создаём класс наследник <code>ScriptableRendererFeature</code>, в нём переопределяем <code>AddRenderPasses()</code> — туда передаём наш кастомный <code>ScriptableRenderPass</code>.</p>
                </div>
            </article>

            <aside class="lesson-sidebar">
                <div class="purchase-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                        <div class="purchase-price">790 ₽<small>/ раз</small></div>
                        <div style="text-align:right">
                            <div style="font-size:11px;color:var(--text3);font-family:var(--mono)">или</div>
                            <div style="font-size:12px;color:var(--amber);font-family:var(--mono)">подписка</div>
                        </div>
                    </div>
                    <ul class="purchase-features">
                        <li>Полный текст урока</li>
                        <li>Все примеры кода (GitHub)</li>
                        <li>Готовый Unity-проект</li>
                        <li>Пожизненный доступ</li>
                    </ul>
                    <button class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-size:14px;border-radius:10px">Купить доступ</button>
                    <button class="btn btn-ghost" style="width:100%;justify-content:center;padding:10px;font-size:13px;border-radius:10px;margin-top:8px">Превью бесплатно</button>
                </div>

                <div class="toc">
                    <div class="toc-title">// Содержание</div>
                    <div class="toc-item"><span class="toc-num">01</span> Что такое URP</div>
                    <div class="toc-item"><span class="toc-num">02</span> Shader Graph vs HLSL</div>
                    <div class="toc-item"><span class="toc-num">03</span> Пишем cel-shading</div>
                    <div class="toc-item"><span class="toc-num">04</span> Renderer Feature: outline</div>
                    <div class="toc-item"><span class="toc-num">05</span> Post-processing stack</div>
                    <div class="toc-item"><span class="toc-num">06</span> Оптимизация шейдеров</div>
                    <div class="toc-item"><span class="toc-num">07</span> Итоги и что дальше</div>
                </div>

                <div style="margin-top:28px">
                    <div class="toc-title">// Автор</div>
                    <div style="display:flex;align-items:center;gap:12px;padding:16px 0;border-bottom:1px solid var(--border)">
                        <div style="width:38px;height:38px;border-radius:50%;background:var(--amber-glow);border:1px solid rgba(245,166,35,0.3);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--amber)">SM</div>
                        <div>
                            <div style="font-size:13px;font-weight:600">shadermage</div>
                            <div style="font-size:11px;color:var(--text3);font-family:var(--mono)">12 уроков · 4.9 ★</div>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--text2);padding:12px 0;font-family:var(--serif);line-height:1.6">
                        Технический художник, 8 лет в геймдеве. Работал в Mundfish и Wargaming. Специализируюсь на VFX и render pipelines.
                    </div>
                </div>
            </aside>
        </div>
    </div>


    <!-- ==================== ADD CONTENT PAGE ==================== -->
    <div id="page-add" class="page">
        <div class="add-layout">
            <div class="add-header">
                <h1 class="add-title">Поделись знаниями</h1>
                <p class="add-subtitle">Добавь урок или ресурс в базу — помоги другим разработчикам. После проверки модератором материал появится на сайте.</p>
            </div>

            <div class="type-switcher">
                <div class="type-option active" onclick="selectType(this)">
                    <div class="type-icon">📝</div>
                    <div class="type-name">Урок</div>
                    <div class="type-desc">Текстовый урок с объяснениями и кодом</div>
                </div>
                <div class="type-option" onclick="selectType(this)">
                    <div class="type-icon">🔗</div>
                    <div class="type-name">Ресурс</div>
                    <div class="type-desc">Ссылка на внешний материал с описанием</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Заголовок <span>*</span></label>
                <input class="form-input" placeholder="Например: «ECS в Unity DOTS: полное руководство»" />
                <div class="form-hint">Чётко и конкретно. Что именно изучит читатель?</div>
            </div>

            <div class="form-group">
                <label class="form-label">Краткое описание <span>*</span></label>
                <input class="form-input" placeholder="2-3 предложения: о чём урок, что нужно знать заранее" />
            </div>

            <div class="form-group">
                <label class="form-label">Движок / Платформа</label>
                <select class="form-select">
                    <option value="">Выберите движок (если применимо)</option>
                    <option>Unity</option>
                    <option>Unreal Engine 5</option>
                    <option>Godot 4</option>
                    <option>GameMaker Studio 2</option>
                    <option>Bevy (Rust)</option>
                    <option>Движконезависимо</option>
                    <option>Другое</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Теги</label>
                <div class="tags-input-wrap" id="tagsWrap">
                    <span class="tag-pill">шейдеры <span class="tag-remove" onclick="removeTag(this)">✕</span></span>
                    <span class="tag-pill">URP <span class="tag-remove" onclick="removeTag(this)">✕</span></span>
                    <input class="tags-input" id="tagInput" placeholder="Добавь тег, Enter" onkeydown="addTag(event)" />
                </div>
                <div class="form-hint">До 8 тегов — помогают при поиске</div>
            </div>

            <div class="form-group">
                <label class="form-label">Цена</label>
                <div class="price-toggle">
                    <div class="price-btn active" onclick="selectPrice(this, 'free')">Бесплатно</div>
                    <div class="price-btn" onclick="selectPrice(this, 'paid')">Платный</div>
                </div>
                <div id="priceInput" style="display:none">
                    <input class="form-input" type="number" placeholder="Цена в рублях" min="0" />
                    <div class="form-hint">Минимум 99 ₽. Платёжи через ЮKassa, 15% комиссия платформы.</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Текст урока <span>*</span></label>
                <textarea class="form-textarea" style="min-height:320px" placeholder="Markdown поддерживается. Используй ## для заголовков, ```код``` для блоков кода, **жирный**, *курсив*..."></textarea>
                <div class="form-hint">Markdown: ## заголовок, ```язык\nкод```, **жирный**, *курсив*, [ссылка](url)</div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary btn-large">Отправить на модерацию</button>
                <button class="btn btn-ghost btn-large">Превью</button>
            </div>

            <div class="moderation-note">
                Материал будет проверен модератором в течение 24-48 часов. Мы проверяем уникальность, качество и соответствие теме геймдева.
            </div>
        </div>
    </div>


    <script>
        function showPage(name) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.getElementById('page-' + name).classList.add('active');
            document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
            const map = {
                home: 'nav-home',
                catalog: 'nav-catalog',
                add: 'nav-add',
                lesson: 'nav-catalog'
            };
            if (map[name]) document.getElementById(map[name]).classList.add('active');
            window.scrollTo(0, 0);
        }

        function showLesson() {
            showPage('lesson');
        }

        function doSearch() {
            const q = document.getElementById('heroSearch').value.trim();
            if (q) {
                document.getElementById('catalogSearch').value = q;
            }
            showPage('catalog');
        }

        function quickSearch(q) {
            document.getElementById('heroSearch').value = q;
            doSearch();
        }

        function toggleFilter(el) {
            el.classList.toggle('active');
        }

        function selectType(el) {
            document.querySelectorAll('.type-option').forEach(o => o.classList.remove('active'));
            el.classList.add('active');
        }

        function selectPrice(el, type) {
            document.querySelectorAll('.price-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('priceInput').style.display = type === 'paid' ? 'block' : 'none';
        }

        function addTag(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const input = document.getElementById('tagInput');
            const val = input.value.trim();
            if (!val) return;
            const wrap = document.getElementById('tagsWrap');
            const pill = document.createElement('span');
            pill.className = 'tag-pill';
            pill.innerHTML = val + ' <span class="tag-remove" onclick="removeTag(this)">✕</span>';
            wrap.insertBefore(pill, input);
            input.value = '';
        }

        function removeTag(el) {
            el.parentElement.remove();
        }

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>

</html>
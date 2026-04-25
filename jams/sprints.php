<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dustore | Спринты</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            min-height: 100vh;
            background: #0d0414;
            font-family: 'Manrope', system-ui, sans-serif;
            color: #e8ddf0;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% -10%, rgba(195, 33, 120, .12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 110%, rgba(120, 20, 80, .10) 0%, transparent 55%);
        }

        ::-webkit-scrollbar {
            width: 4px
        }

        ::-webkit-scrollbar-track {
            background: transparent
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .35);
            border-radius: 4px
        }

        /* ── HEADER ── */
        .header {
            background: rgba(13, 4, 20, .95);
            border-bottom: 1px solid rgba(195, 33, 120, .2);
            padding: 13px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(12px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 17px;
            font-weight: 800;
            color: #e8ddf0;
            letter-spacing: -.3px;
        }

        .logo .brand {
            color: #c32178
        }

        .logo .sep {
            color: rgba(255, 255, 255, .2);
            font-weight: 300;
            font-size: 20px;
            margin: 0 2px
        }

        .header-nav {
            display: flex;
            gap: 6px
        }

        .nav-btn {
            padding: 7px 16px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .5);
            transition: .15s;
            text-decoration: none;
            display: inline-block;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, .1);
            color: #e8ddf0
        }

        .nav-btn.active {
            background: rgba(195, 33, 120, .15);
            color: #e8ddf0;
            border: 1px solid rgba(195, 33, 120, .3)
        }

        .btn-primary {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 7px;
            padding: 8px 18px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            font-family: inherit;
            transition: .15s;
        }

        .btn-primary:hover {
            background: #9e1a66;
            transform: translateY(-1px)
        }

        /* ── CONTAINER ── */
        .container {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 18px
        }

        /* ── HERO ── */
        .hero {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 14px;
            padding: 26px 30px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 80% at 0% 50%, rgba(195, 33, 120, .08), transparent);
            pointer-events: none;
        }

        .hero h1 {
            font-size: 24px;
            font-weight: 800;
            color: #e8ddf0;
            margin-bottom: 5px;
            letter-spacing: -.4px;
        }

        .hero h1 span {
            color: #c32178
        }

        .hero p {
            color: rgba(255, 255, 255, .4);
            font-size: 14px;
            margin-bottom: 20px
        }

        .hero-stats {
            display: flex;
            gap: 28px;
            flex-wrap: wrap
        }

        .hero-stat .val {
            font-size: 20px;
            font-weight: 800;
            color: #e8ddf0
        }

        .hero-stat .lbl {
            color: rgba(255, 255, 255, .35);
            font-size: 11px;
            margin-top: 2px
        }

        /* ── TOOLBAR ── */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 180px
        }

        .search-ico {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, .3);
            font-size: 13px;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 8px;
            padding: 8px 12px 8px 32px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: .15s;
        }

        .search-input:focus {
            border-color: #c32178
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, .3)
        }

        .filters {
            display: flex;
            gap: 5px;
            flex-wrap: wrap
        }

        .filter-btn {
            padding: 7px 13px;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, .1);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            background: rgba(255, 255, 255, .04);
            color: rgba(255, 255, 255, .45);
            transition: .15s;
        }

        .filter-btn.active {
            background: rgba(195, 33, 120, .18);
            border-color: rgba(195, 33, 120, .4);
            color: #e8ddf0
        }

        .filter-btn:hover:not(.active) {
            background: rgba(255, 255, 255, .08);
            color: #e8ddf0
        }

        /* ── GRID ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 12px
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, .25)
        }

        .empty .ico {
            font-size: 40px;
            margin-bottom: 10px
        }

        .empty p {
            font-size: 14px
        }

        /* ── CARD ── */
        .card {
            background: rgba(0, 0, 0, .3);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 12px;
            padding: 18px;
            cursor: pointer;
            transition: .18s;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            border-color: rgba(195, 33, 120, .35);
            transform: translateY(-2px);
            box-shadow: 0 6px 28px rgba(195, 33, 120, .1);
        }

        .card-top {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 12px
        }

        .card-banner {
            font-size: 30px;
            line-height: 1;
            flex-shrink: 0
        }

        .card-meta {
            flex: 1;
            min-width: 0
        }

        .card-meta-row {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            margin-bottom: 3px
        }

        .card-title {
            color: #e8ddf0;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .card-host {
            color: rgba(255, 255, 255, .3);
            font-size: 11px
        }

        .card-desc {
            color: rgba(255, 255, 255, .45);
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 11px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-bottom: 11px
        }

        .tag {
            background: rgba(195, 33, 120, .1);
            border: 1px solid rgba(195, 33, 120, .2);
            color: rgba(195, 33, 120, .9);
            border-radius: 5px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            margin-bottom: 11px
        }

        .stat-box {
            background: rgba(255, 255, 255, .04);
            border-radius: 8px;
            padding: 8px 11px
        }

        .stat-box .s-lbl {
            color: rgba(255, 255, 255, .3);
            font-size: 10px;
            margin-bottom: 2px
        }

        .stat-box .s-val {
            color: #e8ddf0;
            font-weight: 700;
            font-size: 13px
        }

        .prog-wrap {
            margin-top: 2px
        }

        .prog-lbl {
            display: flex;
            justify-content: space-between;
            color: rgba(255, 255, 255, .35);
            font-size: 11px;
            margin-bottom: 4px
        }

        .prog-lbl span {
            color: #e8ddf0;
            font-weight: 600
        }

        .prog-bar {
            background: rgba(255, 255, 255, .06);
            border-radius: 99px;
            height: 4px;
            overflow: hidden
        }

        .prog-fill {
            height: 100%;
            background: #c32178;
            border-radius: 99px;
            transition: width .4s
        }

        /* ── BADGE ── */
        .badge {
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700
        }

        .badge-active {
            background: rgba(34, 197, 94, .1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, .25)
        }

        .badge-upcoming {
            background: rgba(245, 158, 11, .1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .25)
        }

        .badge-ongoing {
            background: rgba(195, 33, 120, .12);
            color: #d946a8;
            border: 1px solid rgba(195, 33, 120, .3)
        }

        .badge-finished {
            background: rgba(107, 114, 128, .1);
            color: rgba(255, 255, 255, .3);
            border: 1px solid rgba(255, 255, 255, .1)
        }

        /* ── OVERLAY ── */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .75);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: .2s;
        }

        .overlay.open {
            opacity: 1;
            pointer-events: all
        }

        /* ── MODAL VIEW ── */
        .modal {
            background: #160822;
            border: 1px solid rgba(195, 33, 120, .3);
            border-radius: 14px;
            max-width: 740px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 28px;
            transform: translateY(16px);
            transition: .2s;
            box-shadow: 0 0 60px rgba(195, 33, 120, .15);
        }

        .overlay.open .modal {
            transform: translateY(0)
        }

        .modal::-webkit-scrollbar {
            width: 4px
        }

        .modal::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .3);
            border-radius: 4px
        }

        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px
        }

        .modal-title-row {
            display: flex;
            gap: 14px;
            align-items: center
        }

        .modal-banner {
            font-size: 42px;
            line-height: 1
        }

        .modal-h2 {
            color: #e8ddf0;
            font-size: 20px;
            font-weight: 800;
            margin: 5px 0 2px;
            letter-spacing: -.3px
        }

        .modal-host {
            color: rgba(255, 255, 255, .35);
            font-size: 12px
        }

        .btn-close {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .5);
            border-radius: 7px;
            padding: 5px 11px;
            cursor: pointer;
            font-size: 16px;
            transition: .15s;
        }

        .btn-close:hover {
            background: rgba(255, 255, 255, .12);
            color: #e8ddf0
        }

        .modal-body-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px
        }

        @media(max-width:580px) {
            .modal-body-cols {
                grid-template-columns: 1fr
            }
        }

        .modal-desc {
            color: rgba(255, 255, 255, .5);
            line-height: 1.7;
            margin-bottom: 16px;
            font-size: 13px
        }

        .theme-box {
            background: rgba(195, 33, 120, .07);
            border: 1px solid rgba(195, 33, 120, .2);
            border-radius: 10px;
            padding: 11px 15px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .theme-box strong {
            color: #c32178
        }

        .modal-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 9px;
            margin-bottom: 18px
        }

        .m-stat {
            background: rgba(255, 255, 255, .04);
            border-radius: 10px;
            padding: 12px;
            text-align: center
        }

        .m-stat .ico {
            font-size: 16px;
            margin-bottom: 3px
        }

        .m-stat .val {
            color: #e8ddf0;
            font-weight: 700;
            font-size: 14px
        }

        .m-stat .lbl {
            color: rgba(255, 255, 255, .3);
            font-size: 10px;
            margin-top: 2px
        }

        .section-title {
            color: #e8ddf0;
            font-weight: 700;
            font-size: 13px;
            margin: 0 0 9px;
            display: block;
            text-transform: uppercase;
            letter-spacing: .05em;
            opacity: .7
        }

        .prize-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 9px 13px;
            margin-bottom: 7px;
        }

        .prize-item .pi-place {
            color: rgba(255, 255, 255, .35);
            font-size: 10px
        }

        .prize-item .pi-reward {
            color: #e8ddf0;
            font-weight: 600;
            font-size: 13px
        }

        .expert-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 9px;
            padding: 9px 13px;
            margin-bottom: 7px;
        }

        .expert-item .av {
            font-size: 22px
        }

        .expert-item .ex-name {
            color: #e8ddf0;
            font-weight: 600;
            font-size: 13px
        }

        .expert-item .ex-role {
            color: rgba(255, 255, 255, .35);
            font-size: 11px
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .07)
        }

        .btn-join {
            flex: 1;
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 9px;
            padding: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            transition: .15s;
        }

        .btn-join:hover {
            background: #9e1a66
        }

        .btn-team {
            flex: 1;
            background: rgba(195, 33, 120, .1);
            border: 1px solid rgba(195, 33, 120, .3);
            color: #e8ddf0;
            border-radius: 9px;
            padding: 12px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            transition: .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .btn-team:hover {
            background: rgba(195, 33, 120, .2);
            border-color: rgba(195, 33, 120, .5)
        }

        .btn-share {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .5);
            border-radius: 9px;
            padding: 12px 15px;
            cursor: pointer;
            font-size: 16px;
            transition: .15s;
        }

        .btn-share:hover {
            background: rgba(255, 255, 255, .1);
            color: #e8ddf0
        }

        /* ── CREATE MODAL ── */
        .create-modal {
            background: #160822;
            border: 1px solid rgba(195, 33, 120, .3);
            border-radius: 14px;
            max-width: 600px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 26px;
            transform: translateY(16px);
            transition: .2s;
            box-shadow: 0 0 60px rgba(195, 33, 120, .15);
        }

        .overlay.open .create-modal {
            transform: translateY(0)
        }

        .create-modal::-webkit-scrollbar {
            width: 4px
        }

        .create-modal::-webkit-scrollbar-thumb {
            background: rgba(195, 33, 120, .3);
            border-radius: 4px
        }

        .steps {
            display: flex;
            gap: 6px;
            margin-bottom: 20px
        }

        .step-tab {
            flex: 1;
            padding: 8px 0;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            background: rgba(255, 255, 255, .05);
            color: rgba(255, 255, 255, .4);
            border: 1px solid rgba(255, 255, 255, .08);
            transition: .15s;
        }

        .step-tab.active {
            background: rgba(195, 33, 120, .18);
            border-color: rgba(195, 33, 120, .4);
            color: #e8ddf0
        }

        .step-panel {
            display: none
        }

        .step-panel.active {
            display: block
        }

        .form-label {
            display: block;
            color: rgba(255, 255, 255, .45);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px
        }

        .form-label .req {
            color: #f87171;
            margin-left: 3px
        }

        .form-input,
        .form-textarea {
            width: 100%;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 8px;
            padding: 9px 12px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            transition: .15s;
        }

        .form-input:focus,
        .form-textarea:focus {
            border-color: #c32178
        }

        .form-textarea {
            resize: vertical
        }

        .form-group {
            margin-bottom: 13px
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px
        }

        .emoji-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 4px
        }

        .emoji-btn {
            width: 36px;
            height: 36px;
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            cursor: pointer;
            font-size: 17px;
            transition: .15s;
        }

        .emoji-btn.selected {
            border: 2px solid #c32178;
            background: rgba(195, 33, 120, .15)
        }

        .dur-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .dur-btn {
            padding: 7px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            color: rgba(255, 255, 255, .5);
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: inherit;
            transition: .15s;
        }

        .dur-btn.active {
            background: rgba(195, 33, 120, .18);
            border-color: rgba(195, 33, 120, .4);
            color: #e8ddf0
        }

        .dynamic-row {
            display: flex;
            gap: 7px;
            align-items: center;
            margin-bottom: 7px
        }

        .dyn-input {
            flex: 1;
            background: rgba(0, 0, 0, .4);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 8px;
            padding: 8px 12px;
            color: #e8ddf0;
            font-size: 13px;
            font-family: inherit;
            outline: none;
        }

        .dyn-input:focus {
            border-color: #c32178
        }

        .btn-remove {
            background: rgba(239, 68, 68, .1);
            border: none;
            color: #f87171;
            border-radius: 7px;
            padding: 7px 10px;
            cursor: pointer
        }

        .btn-add {
            background: rgba(195, 33, 120, .1);
            border: 1px solid rgba(195, 33, 120, .25);
            color: rgba(195, 33, 120, .9);
            border-radius: 7px;
            padding: 5px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
        }

        .expert-block {
            background: rgba(0, 0, 0, .25);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 9px
        }

        .sec-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 9px
        }

        .form-nav {
            display: flex;
            gap: 8px;
            margin-top: 20px
        }

        .btn-next {
            flex: 2;
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 9px;
            padding: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            font-family: inherit;
            transition: .15s;
        }

        .btn-next:hover {
            background: #9e1a66
        }

        .btn-back {
            flex: 1;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            color: rgba(255, 255, 255, .5);
            border-radius: 9px;
            padding: 12px;
            cursor: pointer;
            font-weight: 600;
            font-family: inherit;
            transition: .15s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, .1);
            color: #e8ddf0
        }

        .btn-submit {
            flex: 2;
            background: rgba(34, 197, 94, .15);
            border: 1px solid rgba(34, 197, 94, .3);
            color: #22c55e;
            border-radius: 9px;
            padding: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            font-family: inherit;
            transition: .15s;
        }

        .btn-submit:hover {
            background: rgba(34, 197, 94, .25)
        }

        /* ── L4T TOAST ── */
        .l4t-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            background: #160822;
            border: 1px solid rgba(195, 33, 120, .4);
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 8px 32px rgba(195, 33, 120, .2);
            transform: translateY(20px);
            opacity: 0;
            transition: .3s;
            pointer-events: none;
            max-width: 320px;
        }

        .l4t-toast.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: all
        }

        .l4t-toast-title {
            font-size: 13px;
            font-weight: 700;
            color: #e8ddf0;
            margin-bottom: 4px
        }

        .l4t-toast-body {
            font-size: 12px;
            color: rgba(255, 255, 255, .45);
            line-height: 1.5;
            margin-bottom: 10px
        }

        .l4t-toast-btn {
            background: #c32178;
            border: none;
            color: #fff;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        .l4t-toast-close {
            position: absolute;
            top: 10px;
            right: 12px;
            cursor: pointer;
            color: rgba(255, 255, 255, .3);
            font-size: 14px;
        }

        .l4t-toast-close:hover {
            color: #e8ddf0
        }
    </style>
</head>

<body>

    <header class="header">
        <div class="logo">🎮 <span class="brand">Dustore</span><span class="sep">/</span>Спринты</div>
        <div style="display:flex;align-items:center;gap:10px">
            <div class="header-nav">
                <a class="nav-btn active" href="sprints">Спринты</a>
                <a class="nav-btn" href="participant">Моё участие</a>
                <a class="nav-btn" href="admin">Админка</a>
            </div>
            <button class="btn-primary" onclick="openCreate()">+ Создать спринт</button>
        </div>
    </header>

    <div class="container">

        <div class="hero">
            <h1>Dustore <span>Спринты</span></h1>
            <p>Создавай игры в сжатые сроки · Соревнуйся с командами · Получай признание</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="val" id="stat-total">0</div>
                    <div class="lbl">Спринтов</div>
                </div>
                <div class="hero-stat">
                    <div class="val" id="stat-members">0</div>
                    <div class="lbl">Участников</div>
                </div>
                <div class="hero-stat">
                    <div class="val" id="stat-active">0</div>
                    <div class="lbl">Открытых</div>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-wrap">
                <span class="search-ico">🔍</span>
                <input class="search-input" id="search" placeholder="Поиск спринтов..." oninput="renderGrid()">
            </div>
            <div class="filters">
                <button class="filter-btn active" onclick="setFilter('all',this)">Все</button>
                <button class="filter-btn" onclick="setFilter('active',this)">Регистрация</button>
                <button class="filter-btn" onclick="setFilter('upcoming',this)">Скоро</button>
                <button class="filter-btn" onclick="setFilter('ongoing',this)">Идут</button>
                <button class="filter-btn" onclick="setFilter('finished',this)">Завершены</button>
            </div>
        </div>

        <div class="grid" id="grid"></div>
        <div class="empty" id="empty" style="display:none">
            <div class="ico">🔍</div>
            <p>Спринты не найдены</p>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="overlay" id="view-overlay" onclick="closeView(event)">
        <div class="modal" id="view-modal" onclick="event.stopPropagation()"></div>
    </div>

    <!-- CREATE MODAL -->
    <div class="overlay" id="create-overlay" onclick="closeCreateOverlay(event)">
        <div class="create-modal" onclick="event.stopPropagation()">
            <div class="modal-head">
                <h2 style="color:#e8ddf0;font-size:18px;font-weight:800">🎮 Создать спринт</h2>
                <button class="btn-close" onclick="closeCreate()">✕</button>
            </div>
            <div class="steps">
                <button class="step-tab active" id="tab1" onclick="goStep(1)">1. Основное</button>
                <button class="step-tab" id="tab2" onclick="goStep(2)">2. Время</button>
                <button class="step-tab" id="tab3" onclick="goStep(3)">3. Призы</button>
            </div>

            <div class="step-panel active" id="step1">
                <div class="form-group">
                    <label class="form-label">Иконка</label>
                    <div class="emoji-picker" id="banner-picker"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Название <span class="req">*</span></label>
                    <input class="form-input" id="f-title" placeholder="Pixel Chaos Sprint #4">
                </div>
                <div class="form-group">
                    <label class="form-label">Описание <span class="req">*</span></label>
                    <textarea class="form-textarea" id="f-desc" rows="3" placeholder="Расскажи о спринте, правилах и атмосфере..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Тема</label>
                    <input class="form-input" id="f-theme" placeholder="Скрыть до старта / Изоляция / ...">
                </div>
                <div class="form-group">
                    <label class="form-label">Организатор</label>
                    <input class="form-input" id="f-host" placeholder="Название команды или никнейм">
                </div>
                <div class="form-group">
                    <label class="form-label">Теги (через запятую)</label>
                    <input class="form-input" id="f-tags" placeholder="Unity, 48h, Пиксель-арт">
                </div>
                <div class="form-nav">
                    <button class="btn-next" onclick="goStep(2)">Далее →</button>
                </div>
            </div>

            <div class="step-panel" id="step2">
                <div class="form-row form-group">
                    <div>
                        <label class="form-label">Дата начала <span class="req">*</span></label>
                        <input class="form-input" type="date" id="f-date">
                    </div>
                    <div>
                        <label class="form-label">Время</label>
                        <input class="form-input" type="time" id="f-time" value="12:00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Длительность</label>
                    <div class="dur-btns" id="dur-btns">
                        <button class="dur-btn active" onclick="setDur('24',this)">24ч</button>
                        <button class="dur-btn" onclick="setDur('48',this)">48ч</button>
                        <button class="dur-btn" onclick="setDur('72',this)">72ч</button>
                        <button class="dur-btn" onclick="setDur('96',this)">96ч</button>
                        <button class="dur-btn" onclick="setDur('168',this)">168ч</button>
                    </div>
                    <input class="form-input" type="number" id="f-dur" value="24" style="margin-top:8px;width:110px">
                </div>
                <div class="form-group">
                    <label class="form-label">Макс. участников</label>
                    <input class="form-input" type="number" id="f-maxp" value="100">
                </div>
                <div class="form-nav">
                    <button class="btn-back" onclick="goStep(1)">← Назад</button>
                    <button class="btn-next" onclick="goStep(3)">Далее →</button>
                </div>
            </div>

            <div class="step-panel" id="step3">
                <div class="sec-row">
                    <span class="section-title">🏆 Призовые места</span>
                    <button class="btn-add" onclick="addPrize()">+ Добавить</button>
                </div>
                <div id="prizes-list"></div>
                <div class="sec-row" style="margin-top:16px">
                    <span class="section-title">⭐ Эксперты</span>
                    <button class="btn-add" onclick="addExpert()">+ Добавить</button>
                </div>
                <div id="experts-list"></div>
                <div class="form-nav">
                    <button class="btn-back" onclick="goStep(2)">← Назад</button>
                    <button class="btn-submit" onclick="submitJam()">🚀 Опубликовать</button>
                </div>
            </div>
        </div>
    </div>

    <!-- L4T Toast -->
    <div class="l4t-toast" id="l4t-toast">
        <span class="l4t-toast-close" onclick="closeToast()">✕</span>
        <div class="l4t-toast-title">🤝 Собрать команду на L4T</div>
        <div class="l4t-toast-body">Разместите заявку на бирже L4T — найдите программиста, художника или геймдизайнера для вашего спринта.</div>
        <a href="/l4t" class="l4t-toast-btn">Открыть биржу L4T →</a>
    </div>

    <script>
        showL4tToast()
        
        var BANNERS = ['🎮', '🕹', '👾', '🚀', '🔥', '⚔', '🧩', '🌌', '🎲', '🏆', '💡', '🐉'];
        var AVATARS = ['👤', '🦅', '🌸', '🎭', '🦊', '🐺', '🤖', '🧠', '🎯', '⚡', '🌊', '🔮'];
        var MEDALS = ['🥇', '🥈', '🥉'];
        var jams = [{
            id: 1,
            title: 'К.О.Н.Т.У.Р.',
            desc: 'Конкурс любительских версий. Любые движки. Любые платформы',
            theme: 'Известна до старта',
            banner: '🎮',
            status: 'active', // или upcoming
            start: Date.now() + 2 * 864e5,
            dur: 240,
            maxP: 50,
            curP: 0,
            prizes: [{
                place: '1',
                reward: '1 000 ₽'
            }, {
                place: '2',
                reward: '1 000 ₽'
            }, {
                place: '3',
                reward: '1 000 ₽'
            }],
            experts: [{
                name: 'Иван Иванов',
                role: 'Эксперт',
                av: '🦅'
            }, {
                name: 'Сергей Сергеев',
                role: 'Эксперт',
                av: '🌸'
            }],
            tags: ['Любая платформа', 'Любой движок', 'Авторское'],
            host: 'Dustore'
        }, ];
        var curFilter = 'all',
            selBanner = '🎮',
            selDur = '24';
        var prizes = [{
                place: '1',
                reward: ''
            }],
            experts = [{
                name: '',
                role: '',
                av: '👤'
            }];

        function countdown(ts) {
            var d = ts - Date.now();
            if (d <= 0) return 'Уже началось';
            var days = Math.floor(d / 864e5),
                h = Math.floor((d % 864e5) / 36e5),
                m = Math.floor((d % 36e5) / 6e4);
            if (days > 0) return days + 'д ' + h + 'ч';
            if (h > 0) return h + 'ч ' + m + 'м';
            return m + 'м';
        }

        function badgeHtml(s) {
            var map = {
                active: ['badge-active', 'Регистрация'],
                upcoming: ['badge-upcoming', 'Скоро'],
                ongoing: ['badge-ongoing', 'Идёт'],
                finished: ['badge-finished', 'Завершён']
            };
            var c = map[s] || map['upcoming'];
            return '<span class="badge ' + c[0] + '">' + c[1] + '</span>';
        }

        function medal(i) {
            return MEDALS[i] || '🎖'
        }

        function updateStats() {
            document.getElementById('stat-total').textContent = jams.length;
            document.getElementById('stat-members').textContent = jams.reduce(function(s, j) {
                return s + j.curP
            }, 0);
            document.getElementById('stat-active').textContent = jams.filter(function(j) {
                return j.status !== 'finished'
            }).length;
        }

        function setFilter(f, el) {
            curFilter = f;
            document.querySelectorAll('.filter-btn').forEach(function(b) {
                b.classList.remove('active')
            });
            el.classList.add('active');
            renderGrid();
        }

        function renderGrid() {
            var q = document.getElementById('search').value.toLowerCase();
            var data = jams.filter(function(j) {
                return ((curFilter === 'all') || (j.status === curFilter)) && (!q || j.title.toLowerCase().includes(q) || j.desc.toLowerCase().includes(q));
            });
            var grid = document.getElementById('grid'),
                empty = document.getElementById('empty');
            if (!data.length) {
                grid.innerHTML = '';
                empty.style.display = 'block';
                updateStats();
                return
            }
            empty.style.display = 'none';
            var html = '';
            data.forEach(function(j) {
                var pct = Math.min(100, Math.round(j.curP / j.maxP * 100));
                var tagsHtml = j.tags.map(function(t) {
                    return '<span class="tag">' + t + '</span>'
                }).join('');
                html += '<div class="card" onclick="openView(' + j.id + ')">';
                html += '<div class="card-top"><div class="card-banner">' + j.banner + '</div>';
                html += '<div class="card-meta"><div class="card-meta-row">' + badgeHtml(j.status) + '<span class="card-host">от ' + j.host + '</span></div>';
                html += '<div class="card-title">' + j.title + '</div></div></div>';
                html += '<div class="card-desc">' + j.desc + '</div>';
                html += '<div class="tags">' + tagsHtml + '</div>';
                html += '<div class="card-stats">';
                html += '<div class="stat-box"><div class="s-lbl">⏳ До старта</div><div class="s-val">' + countdown(j.start) + '</div></div>';
                html += '<div class="stat-box"><div class="s-lbl">⌛ Длительность</div><div class="s-val">' + j.dur + 'ч</div></div>';
                html += '</div><div class="prog-wrap">';
                html += '<div class="prog-lbl"><span style="color:rgba(255,255,255,.35)">👥 Участники</span><span>' + j.curP + ' / ' + j.maxP + '</span></div>';
                html += '<div class="prog-bar"><div class="prog-fill" style="width:' + pct + '%"></div></div>';
                html += '</div></div>';
            });
            grid.innerHTML = html;
            updateStats();
        }

        function openView(id) {
            var j = jams.find(function(x) {
                return x.id === id
            });
            if (!j) return;
            var tagsHtml = j.tags.map(function(t) {
                return '<span class="tag">' + t + '</span>'
            }).join('');
            var prizesHtml = j.prizes.map(function(p, i) {
                return '<div class="prize-item"><span style="font-size:20px">' + medal(i) + '</span><div><div class="pi-place">' + p.place + ' место</div><div class="pi-reward">' + p.reward + '</div></div></div>';
            }).join('');
            var expertsHtml = j.experts.map(function(e) {
                return '<div class="expert-item"><span class="av">' + e.av + '</span><div><div class="ex-name">' + e.name + '</div><div class="ex-role">' + e.role + '</div></div></div>';
            }).join('');
            var themeHtml = j.theme ? '<div class="theme-box"><strong>🎯 Тема: </strong>' + j.theme + '</div>' : '';
            var pct = Math.min(100, Math.round(j.curP / j.maxP * 100));

            var html = '<div class="modal-head">';
            html += '<div class="modal-title-row"><span class="modal-banner">' + j.banner + '</span>';
            html += '<div>' + badgeHtml(j.status) + '<div class="modal-h2">' + j.title + '</div><div class="modal-host">Организатор: ' + j.host + '</div></div></div>';
            html += '<button class="btn-close" onclick="closeView()">✕</button></div>';
            html += '<p class="modal-desc">' + j.desc + '</p>';
            html += themeHtml;
            html += '<div class="tags" style="margin-bottom:14px">' + tagsHtml + '</div>';
            html += '<div class="modal-stats">';
            html += '<div class="m-stat"><div class="ico">⏳</div><div class="val">' + countdown(j.start) + '</div><div class="lbl">До старта</div></div>';
            html += '<div class="m-stat"><div class="ico">⌛</div><div class="val">' + j.dur + 'ч</div><div class="lbl">Длительность</div></div>';
            html += '<div class="m-stat"><div class="ico">👥</div><div class="val">' + j.curP + '/' + j.maxP + '</div><div class="lbl">Участники</div></div>';
            html += '</div>';
            html += '<div style="margin-bottom:6px;"><div class="prog-lbl"><span style="color:rgba(255,255,255,.35)">Заполненность</span><span>' + pct + '%</span></div><div class="prog-bar"><div class="prog-fill" style="width:' + pct + '%"></div></div></div>';
            html += '<div class="modal-body-cols">';
            html += '<div><span class="section-title" style="margin-top:16px;display:block">🏆 Призы</span>' + prizesHtml + '</div>';
            html += '<div><span class="section-title" style="margin-top:16px;display:block">⭐ Эксперты</span>' + expertsHtml + '</div>';
            html += '</div>';
            html += '<div class="modal-actions">';
            html += '<button class="btn-join">🎮 Участвовать</button>';
            html += '<button class="btn-team" onclick="showL4tToast()">🤝 Собрать команду на L4T</button>';
            html += '<button class="btn-share">🔗</button>';
            html += '</div>';

            document.getElementById('view-modal').innerHTML = html;
            document.getElementById('view-overlay').classList.add('open');
        }

        function closeView(e) {
            if (!e || e.target === document.getElementById('view-overlay'))
                document.getElementById('view-overlay').classList.remove('open');
        }

        function showL4tToast() {
            var t = document.getElementById('l4t-toast');
            t.classList.add('show');
            setTimeout(function() {
                t.classList.remove('show')
            }, 10000);
        }

        function closeToast() {
            document.getElementById('l4t-toast').classList.remove('show')
        }

        /* CREATE */
        function openCreate() {
            selBanner = '🎮';
            selDur = '24';
            prizes = [{
                place: '1',
                reward: ''
            }];
            experts = [{
                name: '',
                role: '',
                av: '👤'
            }];
            ['f-title', 'f-desc', 'f-theme', 'f-host', 'f-tags', 'f-date'].forEach(function(id) {
                document.getElementById(id).value = ''
            });
            document.getElementById('f-time').value = '12:00';
            document.getElementById('f-dur').value = '24';
            document.getElementById('f-maxp').value = '100';
            buildBannerPicker();
            buildPrizes();
            buildExperts();
            goStep(1);
            document.getElementById('create-overlay').classList.add('open');
        }

        function closeCreate() {
            document.getElementById('create-overlay').classList.remove('open')
        }

        function closeCreateOverlay(e) {
            if (e.target === document.getElementById('create-overlay')) closeCreate()
        }

        function buildBannerPicker() {
            document.getElementById('banner-picker').innerHTML = BANNERS.map(function(b) {
                return '<button class="emoji-btn' + (b === selBanner ? ' selected' : '') + '" onclick="selectBanner(\'' + b + '\',this)">' + b + '</button>';
            }).join('');
        }

        function selectBanner(b, el) {
            selBanner = b;
            document.querySelectorAll('#banner-picker .emoji-btn').forEach(function(x) {
                x.classList.remove('selected')
            });
            el.classList.add('selected');
        }

        function setDur(h, el) {
            selDur = h;
            document.getElementById('f-dur').value = h;
            document.querySelectorAll('.dur-btn').forEach(function(x) {
                x.classList.remove('active')
            });
            el.classList.add('active');
        }

        function goStep(n) {
            for (var i = 1; i <= 3; i++) {
                document.getElementById('step' + i).classList.toggle('active', i === n);
                document.getElementById('tab' + i).classList.toggle('active', i === n);
            }
        }

        function buildPrizes() {
            document.getElementById('prizes-list').innerHTML = prizes.map(function(p, i) {
                return '<div class="dynamic-row"><span style="font-size:18px;flex-shrink:0">' + medal(i) + '</span>' +
                    '<input class="dyn-input" value="' + p.reward + '" placeholder="Приз за ' + p.place + ' место..." oninput="prizes[' + i + '].reward=this.value">' +
                    (i > 0 ? '<button class="btn-remove" onclick="removePrize(' + i + ')">✕</button>' : '') +
                    '</div>';
            }).join('');
        }

        function addPrize() {
            prizes.push({
                place: String(prizes.length + 1),
                reward: ''
            });
            buildPrizes()
        }

        function removePrize(i) {
            prizes.splice(i, 1);
            prizes.forEach(function(p, k) {
                p.place = String(k + 1)
            });
            buildPrizes()
        }

        function buildExperts() {
            document.getElementById('experts-list').innerHTML = experts.map(function(e, i) {
                return '<div class="expert-block"><div class="emoji-picker" style="margin-bottom:7px">' +
                    AVATARS.map(function(a) {
                        return '<button class="emoji-btn' + (a === e.av ? ' selected' : '') + '" onclick="selectAv(' + i + ',\'' + a + '\',this)">' + a + '</button>'
                    }).join('') +
                    '</div><div class="dynamic-row">' +
                    '<input class="dyn-input" value="' + e.name + '" placeholder="Имя эксперта" oninput="experts[' + i + '].name=this.value">' +
                    '<input class="dyn-input" value="' + e.role + '" placeholder="Роль / опыт" oninput="experts[' + i + '].role=this.value">' +
                    (i > 0 ? '<button class="btn-remove" onclick="removeExpert(' + i + ')">✕</button>' : '') +
                    '</div></div>';
            }).join('');
        }

        function addExpert() {
            experts.push({
                name: '',
                role: '',
                av: '👤'
            });
            buildExperts()
        }

        function removeExpert(i) {
            experts.splice(i, 1);
            buildExperts()
        }

        function selectAv(i, a, el) {
            experts[i].av = a;
            var block = el.closest('.expert-block');
            block.querySelectorAll('.emoji-btn').forEach(function(b) {
                b.classList.remove('selected')
            });
            el.classList.add('selected');
        }

        function submitJam() {
            var title = document.getElementById('f-title').value.trim();
            var desc = document.getElementById('f-desc').value.trim();
            var date = document.getElementById('f-date').value;
            if (!title || !desc || !date) {
                alert('Заполни обязательные поля (шаги 1–2)');
                return
            }
            var dt = new Date(date + 'T' + document.getElementById('f-time').value);
            jams.unshift({
                id: Date.now(),
                title: title,
                desc: desc,
                theme: document.getElementById('f-theme').value.trim() || 'Не указана',
                banner: selBanner,
                status: 'upcoming',
                start: dt.getTime(),
                dur: parseInt(document.getElementById('f-dur').value) || 48,
                maxP: parseInt(document.getElementById('f-maxp').value) || 100,
                curP: 0,
                prizes: prizes.filter(function(p) {
                    return p.reward
                }),
                experts: experts.filter(function(e) {
                    return e.name
                }),
                tags: document.getElementById('f-tags').value.split(',').map(function(t) {
                    return t.trim()
                }).filter(Boolean),
                host: document.getElementById('f-host').value.trim() || 'Аноним'
            });
            closeCreate();
            renderGrid();
        }
        renderGrid();
        setInterval(renderGrid, 30000);
    </script>
</body>

</html>
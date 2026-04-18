<?php require_once(__DIR__ . "/swad/static/elements/header.php"); ?>

<div class="content" style="display:flex; height: calc(100vh - 80px); gap:10px;">

    <!-- список диалогов -->
    <div style="width:280px; background:rgba(0,0,0,.3); border-radius:10px; padding:10px;">
        <div style="color:#fff; margin-bottom:10px;">Диалоги</div>

        <div class="chat-item">user1</div>
        <div class="chat-item">user2</div>
    </div>

    <!-- чат -->
    <div style="flex:1; display:flex; flex-direction:column; background:rgba(0,0,0,.25); border-radius:10px;">

        <!-- сообщения -->
        <div style="flex:1; padding:15px; overflow-y:auto;" id="chat-messages">
            <div class="msg">Привет</div>
            <div class="msg me">Ну здарова</div>
        </div>

        <!-- ввод -->
        <div style="padding:10px; border-top:1px solid rgba(255,255,255,.1); display:flex; gap:8px;">
            <input type="text" id="msg-input" class="l4t-input" placeholder="Сообщение...">
            <button class="respond-btn">Отправить</button>
        </div>

    </div>
</div>

<style>
    .chat-item {
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        color: #ccc;
    }

    .chat-item:hover {
        background: rgba(255, 255, 255, .08);
    }

    .msg {
        background: rgba(255, 255, 255, .08);
        padding: 6px 10px;
        border-radius: 6px;
        margin-bottom: 6px;
        max-width: 60%;
    }

    .msg.me {
        margin-left: auto;
        background: rgba(195, 33, 120, .25);
    }
</style>
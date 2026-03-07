/**
 * DustStore Messenger — app.js
 *
 * Протокол сообщений через MAX Bot API:
 *   Текст каждого сообщения = "to_user_id:from_user_id:текст"
 *
 * Пользователь хранит два ID:
 *   myUserId  — MAX user_id (для отправки: send.php использует как "from")
 *   myChatId  — числовой chat_id диалога с ботом (для получения: polling)
 *               ≠ user_id! Определяется через find_chat_id.php или вручную.
 */

class ChatApp {
    constructor() {
        this.serverBase  = localStorage.getItem('serverBase') || '';
        this.myUserId    = localStorage.getItem('myUserId')   || '';
        this.myChatId    = localStorage.getItem('myChatId')   || '';

        this.currentChatId   = null;   // peer user_id
        this.chats           = [];     // [{id (peer user_id), name}]
        this.messages        = {};     // {peer_user_id: [msg, ...]}
        this.seenMids        = new Set(JSON.parse(localStorage.getItem('seenMids') || '[]'));
        this.pollingInterval = null;
        this._bgInterval     = null;
        this.isSending       = false;

        this.initElements();
        this.initEventListeners();
        this.loadLocalData();
        this.renderChats();
        this.registerServiceWorker();

        if (this.myUserId && this.myChatId && this.serverBase) {
            this.startBackgroundPolling();
        }
    }

    /* ═══════════════════════════════ INIT ═══════════════════════════════ */

    initElements() {
        this.chatsScreen       = document.getElementById('chats-screen');
        this.chatScreen        = document.getElementById('chat-screen');
        this.chatsList         = document.getElementById('chatsList');
        this.messagesContainer = document.getElementById('messagesContainer');
        this.chatUserName      = document.getElementById('chatUserName');
        this.messageInput      = document.getElementById('messageInput');
        this.addChatModal      = document.getElementById('addChatModal');
        this.newUserId         = document.getElementById('newUserId');
        this.addChatBtn        = document.getElementById('addChatBtn');
        this.cancelAddChat     = document.getElementById('cancelAddChat');
        this.saveNewChat       = document.getElementById('saveNewChat');
        this.backToChats       = document.getElementById('backToChats');
        this.sendMessageBtn    = document.getElementById('sendMessageBtn');
        this.serverInput       = document.getElementById('serverInput');
        this.saveServerBtn     = document.getElementById('saveServerBtn');
        this.myUserIdInput     = document.getElementById('myUserIdInput');
        this.saveMyUserIdBtn   = document.getElementById('saveMyUserIdBtn');
        this.myChatIdInput     = document.getElementById('myChatIdInput');
        this.saveMyUserIdBtn2  = document.getElementById('saveMyUserIdBtn2');
        this.findChatIdBtn     = document.getElementById('findChatIdBtn');
        this.loadMessagesBtn   = document.getElementById('loadMessagesBtn');

        this.serverInput.value   = this.serverBase;
        this.myUserIdInput.value = this.myUserId;
        this.myChatIdInput.value = this.myChatId;
    }

    initEventListeners() {
        this.addChatBtn.addEventListener('click',    () => this.showAddChatModal());
        this.cancelAddChat.addEventListener('click', () => this.hideAddChatModal());
        this.saveNewChat.addEventListener('click',   () => this.addNewChat());
        this.backToChats.addEventListener('click',   () => this.showChatsScreen());
        this.sendMessageBtn.addEventListener('click',() => this.sendMessage());

        this.messageInput.addEventListener('keypress', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
        });
        window.addEventListener('click', e => {
            if (e.target === this.addChatModal) this.hideAddChatModal();
        });

        // ── Настройки: сервер ──
        this.saveServerBtn.addEventListener('click', () => {
            const url = this.serverInput.value.trim().replace(/\/$/, '');
            if (!url) return this.showToast('⚠️ Введите URL сервера');
            this.serverBase = url;
            localStorage.setItem('serverBase', url);
            this.showToast('✅ Сервер сохранён');
            this.tryStartPolling();
        });
        

        // ── Настройки: мой user_id ──
        this.saveMyUserIdBtn.addEventListener('click', () => {
            const id = this.myUserIdInput.value.trim();
            if (!id || !/^\d+$/.test(id)) return this.showToast('⚠️ user_id — только цифры');
            this.myUserId = id;
            localStorage.setItem('myUserId', id);
            this.showToast('✅ user_id сохранён: ' + id);
            this.tryStartPolling();
        });

        // ── Настройки: мой chat_id ──
        this.saveMyUserIdBtn2.addEventListener('click', () => {
            const id = this.myChatIdInput.value.trim();
            if (!id || !/^\d+$/.test(id)) return this.showToast('⚠️ chat_id — только цифры');
            this.myChatId = id;
            localStorage.setItem('myChatId', id);
            this.showToast('✅ chat_id сохранён: ' + id);
            this.tryStartPolling();
        });

        // ── Автопоиск chat_id ──
        this.findChatIdBtn.addEventListener('click', () => this.findMyChatId());

        this.newUserId.addEventListener('blur', () => {

            const id = this.newUserId.value.trim();

            if (/^\d+$/.test(id)) {
                this.autoFindPeerChatId(id);
            }

        });

        // ── Ручное обновление ──
        this.loadMessagesBtn.addEventListener('click', async () => {
            if (!this.isConfigured()) return this.showToast('⚠️ Настройте сервер и ID');
            this.loadMessagesBtn.disabled = true;
            await this.fetchAndMerge();
            this.loadMessagesBtn.disabled = false;
        });
    }
    

    /* ═══════════════════════════════ CONFIG ════════════════════════════ */

    isConfigured() {
        return !!(this.serverBase && this.myUserId && this.myChatId);
    }

    tryStartPolling() {
        if (this.isConfigured()) this.startBackgroundPolling();
    }

    async findMyChatId() {
        if (!this.serverBase || !this.myUserId) {
            return this.showToast('⚠️ Сначала укажите сервер и user_id');
        }
        this.findChatIdBtn.textContent = '⏳';
        this.findChatIdBtn.disabled    = true;
        try {
            const resp = await fetch(
                `${this.serverBase}/find_chat_id.php?user_id=${encodeURIComponent(this.myUserId)}`,
                { cache: 'no-store' }
            );
            const data = await resp.json();
            if (data.chat_id) {
                this.myChatId = String(data.chat_id);
                localStorage.setItem('myChatId', this.myChatId);
                this.myChatIdInput.value = this.myChatId;
                this.showToast('✅ chat_id найден: ' + this.myChatId);
                this.tryStartPolling();
            } else {
                this.showToast('❌ ' + (data.error || 'chat_id не найден'));
            }
        } catch {
            this.showToast('❌ Ошибка поиска chat_id');
        }
        this.findChatIdBtn.textContent = '🔍';
        this.findChatIdBtn.disabled    = false;
    }

    async autoFindPeerChatId(userId) {

        if (!this.serverBase || !userId) return;

        try {

            const resp = await fetch(
                `${this.serverBase}/find_chat_id.php?user_id=${encodeURIComponent(userId)}`,
                { cache: 'no-store' }
            );

            const data = await resp.json();

            if (data.chat_id) {
                document.getElementById('newChatId').value = data.chat_id;
                this.showToast('chat_id найден автоматически');
                this.newUserId.focus();
            }

        } catch {}

    }

    /* ════════════════════════════ LOCAL DATA ═══════════════════════════ */

    loadLocalData() {
        this.chats    = JSON.parse(localStorage.getItem('chats')    || '[]');
        this.messages = JSON.parse(localStorage.getItem('messages') || '{}');
        this.chats.forEach(c => { if (!this.messages[c.id]) this.messages[c.id] = []; });
    }

    saveLocalData() {
        localStorage.setItem('chats',    JSON.stringify(this.chats));
        localStorage.setItem('messages', JSON.stringify(this.messages));
        const arr = [...this.seenMids];
        if (arr.length > 3000) arr.splice(0, arr.length - 3000);
        localStorage.setItem('seenMids', JSON.stringify(arr));
    }

    /* ═══════════════════════════════ CHATS ══════════════════════════════ */

    renderChats() {
        if (!this.chats.length) {
            this.chatsList.innerHTML =
                '<div class="empty-state">Нет чатов.<br>Нажмите ＋ чтобы добавить собеседника.<br><br>' +
                '<small>Не забудьте настроить сервер и свои ID через ⚙</small></div>';
            return;
        }
        const sorted = [...this.chats].sort((a, b) => {
            const la = this.getLastMsg(a.id), lb = this.getLastMsg(b.id);
            if (!la && !lb) return 0; if (!la) return 1; if (!lb) return -1;
            return new Date(lb.timestamp) - new Date(la.timestamp);
        });
        this.chatsList.innerHTML = sorted.map(c => {
            const last  = this.getLastMsg(c.id);
            const prev  = last ? this.escapeHtml(last.text) : 'Нет сообщений';
            const time  = last ? this.formatTime(last.timestamp) : '';
            const unread = this.hasUnread(c.id);
            return `<div class="chat-item ${unread ? 'unread' : ''}" onclick="app.openChat('${c.id}')">
                <div class="chat-avatar">${c.name.charAt(0).toUpperCase()}</div>
                <div class="chat-info">
                    <div class="chat-name">${this.escapeHtml(c.name)}</div>
                    <div class="chat-last-message">${prev}</div>
                </div>
                <div class="chat-time">${time}</div>
            </div>`;
        }).join('');
    }

    hasUnread(peerId) {
        return (this.messages[peerId] || []).some(m => !m.read && !m.sent);
    }
    getLastMsg(peerId) {
        const a = this.messages[peerId] || [];
        return a[a.length - 1] || null;
    }

    openChat(peerId) {
        this.currentChatId = peerId;
        const c = this.chats.find(x => x.id === peerId);
        this.chatUserName.textContent = c ? c.name : `ID ${peerId}`;
        this.markRead(peerId);
        this.showChatScreen();
        this.renderMessages();
        this.startChatPolling();
    }

    markRead(peerId) {
        let changed = false;
        (this.messages[peerId] || []).forEach(m => {
            if (!m.read && !m.sent) { m.read = true; changed = true; }
        });
        if (changed) { this.saveLocalData(); this.renderChats(); }
    }

    addNewChat() {
        const peerId = this.newUserId.value.trim();
        const peerChatId = document.getElementById('newChatId').value.trim();
        if (!peerId) return this.showToast('⚠️ Введите user_id собеседника');
        if (!/^\d+$/.test(peerId)) return this.showToast('⚠️ user_id — только цифры');
        if (peerId === this.myUserId) return this.showToast('⚠️ Нельзя добавить себя');

        if (this.chats.some(c => c.id === peerId)) {
            // Обновляем chatId если теперь указан
            if (peerChatId) {
                const existing = this.chats.find(c => c.id === peerId);
                if (existing) { existing.chatId = peerChatId; this.saveLocalData(); }
            }
            this.hideAddChatModal();
            return this.openChat(peerId);
        }

        // chatId для получения сообщений:
        // если задан — используем его (гость читает из чата хоста)
        // если не задан — используем свой myChatId (мы сами хост)
        const chatId = peerChatId || this.myChatId;

        this.chats.push({ id: peerId, name: `Пользователь ${peerId}`, chatId });
        this.messages[peerId] = [];
        this.saveLocalData();
        this.renderChats();
        this.hideAddChatModal();
        this.openChat(peerId);
    }

    /* ════════════════════════════ MESSAGES ══════════════════════════════ */

    renderMessages() {
        if (!this.currentChatId) return;
        const msgs = this.messages[this.currentChatId] || [];

        if (!msgs.length) {
            this.messagesContainer.innerHTML =
                '<div class="empty-state">Нет сообщений. Напишите первым!</div>';
            return;
        }

        this.messagesContainer.innerHTML = msgs.map(msg => {
            const cls    = msg.sent ? 'sent' : 'received';
            const extra  = msg.pending ? ' pending' : msg.error ? ' error' : '';
            const status = msg.sent
                ? (msg.pending ? '⏳' : msg.error ? '❌' : msg.delivered ? '✓✓' : '✓')
                : '';
            const stCls = msg.delivered ? 'delivered' : '';
            return `<div class="message ${cls}${extra}" data-id="${msg.id}">
                <div class="message-text">${this.escapeHtml(msg.text)}</div>
                <div class="message-footer">
                    <span class="message-time">${this.formatTime(msg.timestamp)}</span>
                    ${msg.sent ? `<span class="message-status ${stCls}">${status}</span>` : ''}
                </div>
            </div>`;
        }).join('');

        this.scrollToBottom();
    }

    async sendMessage() {
        const text = this.messageInput.value.trim();
        if (!text || !this.currentChatId || this.isSending) return;

        if (!this.myUserId) {
            document.getElementById('headerSettings').classList.add('open');
            return this.showToast('⚠️ Укажите ваш user_id в настройках');
        }
        if (!this.serverBase) {
            document.getElementById('headerSettings').classList.add('open');
            return this.showToast('⚠️ Укажите сервер в настройках');
        }

        this.isSending = true;
        this.messageInput.value = '';

        const tempId = 'p_' + Date.now();
        const tempMsg = {
            id: tempId, text,
            timestamp: new Date().toISOString(),
            sent: true, delivered: false, pending: true, read: true,
        };
        if (!this.messages[this.currentChatId]) this.messages[this.currentChatId] = [];
        this.messages[this.currentChatId].push(tempMsg);
        this.renderMessages();

        try {
            const resp = await fetch(`${this.serverBase}/send.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ to: this.currentChatId, from: this.myUserId, text }),
            });
            const json = await resp.json().catch(() => ({}));
            if (resp.ok) {
                const realId = json.message?.body?.mid || json.mid || tempId;
                tempMsg.id        = String(realId);
                tempMsg.pending   = false;
                tempMsg.delivered = true;
                this.seenMids.add(String(realId));
            } else {
                tempMsg.pending = false;
                tempMsg.error   = true;
                this.showToast('❌ Ошибка: ' + (json.error || resp.status));
            }
        } catch {
            tempMsg.pending = false;
            tempMsg.error   = true;
            this.showToast('❌ Сервер недоступен');
        }

        this.isSending = false;
        this.saveLocalData();
        this.renderMessages();
        this.renderChats();
    }

    formatTime(ts) {

        if (!ts) return '';

        let t = ts;

        if (typeof ts === 'number') {
            t = new Date(ts);
        } else if (!isNaN(ts)) {
            t = new Date(parseInt(ts));
        } else {
            t = new Date(ts);
        }

        return t.toLocaleTimeString('ru-RU',{
            hour:'2-digit',
            minute:'2-digit'
        });

    }

    /* ════════════════════════════ POLLING ═══════════════════════════════ */

    startChatPolling() {
        this.stopPolling();
        this.fetchAndMerge();
        this.pollingInterval = setInterval(() => this.fetchAndMerge(), 4000);
    }

    startBackgroundPolling() {
        if (this._bgInterval) clearInterval(this._bgInterval);
        this._bgInterval = setInterval(() => {
            if (!this.currentChatId) this.fetchAndMerge();
        }, 10000);
    }

    stopPolling() {
        if (this.pollingInterval) { clearInterval(this.pollingInterval); this.pollingInterval = null; }
    }

    /**
     * Загружает переписку с конкретным peer.
     * Сервер мержит: входящие (MAX API, мой chat_id) + исходящие (sent_log).
     * Каждое сообщение содержит direction: 'in' | 'out'.
     */
    async fetchForPeer(peerId) {
        if (!this.isConfigured() || !peerId) return;
        // Используем chatId контакта (чат хоста) или свой myChatId
        const chat = this.chats.find(c => c.id === peerId);
        const chatId = chat?.chatId || this.myChatId;
        if (!chatId) return;

        try {
            const params = new URLSearchParams({
                my_id:   this.myUserId,
                chat_id: chatId,
                peer_id: peerId,
                count:   '20',
            });
            const resp = await fetch(
                `${this.serverBase}/get_messages.php?${params}`,
                { cache: 'no-store' }
            );
            if (!resp.ok) return;

            const msgs = (await resp.json()).messages || [];
            if (!msgs.length) return;

            if (!this.messages[peerId]) this.messages[peerId] = [];
            let changed = false;

            for (const msg of msgs) {
                const mid = String(msg.id);

                // Исходящие: ищем совпадающий pending, обновляем статус
                if (msg.direction === 'out') {
                    const pend = this.messages[peerId].find(m =>
                        m.pending && m.text === msg.text
                    );
                    if (pend) {
                        pend.id = mid; pend.pending = false; pend.delivered = true;
                        this.seenMids.add(mid);
                        changed = true;
                        continue;
                    }
                }

                if (this.seenMids.has(mid)) continue;
                this.seenMids.add(mid);
                if (this.messages[peerId].some(m => m.id === mid)) continue;

                this.messages[peerId].push({
                    id:        mid,
                    text:      msg.text,
                    timestamp: msg.timestamp,
                    sent:      msg.direction === 'out',
                    delivered: msg.direction === 'out',
                    read:      msg.direction === 'out' || this.currentChatId === peerId,
                });
                changed = true;
            }

            if (!changed) return;

            this.messages[peerId].sort((a, b) =>
                new Date(a.timestamp) - new Date(b.timestamp)
            );
            this.saveLocalData();
            this.renderChats();
            if (this.currentChatId === peerId) {
                this.markRead(peerId);
                this.renderMessages();
            }
        } catch { /* нет сети */ }
    }

    async fetchAndMerge() {
        if (this.currentChatId) {
            await this.fetchForPeer(this.currentChatId);
        } else {
            // Фоновый — обходим все известные чаты
            for (const chat of this.chats) {
                await this.fetchForPeer(chat.id);
            }
        }
    }

    /* ══════════════════════════ UI HELPERS ══════════════════════════════ */

    showAddChatModal() {
        this.addChatModal.classList.add('show');
        this.newUserId.value = '';
        document.getElementById('newChatId').value = '';
        setTimeout(() => this.newUserId.focus(), 80);
    }
    hideAddChatModal() { this.addChatModal.classList.remove('show'); }

    showChatsScreen() {
        this.stopPolling();
        this.chatsScreen.classList.add('active');
        this.chatScreen.classList.remove('active');
        this.currentChatId = null;
        this.renderChats();
    }
    showChatScreen() {
        this.chatsScreen.classList.remove('active');
        this.chatScreen.classList.add('active');
    }

    scrollToBottom() {
        requestAnimationFrame(() => {
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        });
    }

    showToast(msg, duration = 2800) {
        const old = document.querySelector('.toast');
        if (old) old.remove();
        const t = document.createElement('div');
        t.className = 'toast'; t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('toast-visible')));
        setTimeout(() => { t.classList.remove('toast-visible'); setTimeout(() => t.remove(), 300); }, duration);
    }

    showNotification(title, body) {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'granted') {
            new Notification(title, { body, icon: '/icons/logo_new.png' });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(p => {
                if (p === 'granted') new Notification(title, { body, icon: '/icons/logo_new.png' });
            });
        }
    }

    formatTime(ts) {
        if (!ts) return '';
        return new Date(ts).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }
    }
}

const app = new ChatApp();
window.app = app;
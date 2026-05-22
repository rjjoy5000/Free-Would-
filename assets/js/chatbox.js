/* ========================================
   Free Would - Chatbox JavaScript
   ======================================== */

document.addEventListener('DOMContentLoaded', () => {
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const sessionsList = document.getElementById('sessions-list');
    const newChatBtn = document.getElementById('new-chat-btn');
    const deleteSessionBtn = document.getElementById('delete-session-btn');
    const exportBtn = document.getElementById('export-btn');
    const modelSelect = document.getElementById('chat-model');
    const tempSlider = document.getElementById('temperature');
    const tempValue = document.getElementById('temp-value');
    const maxTokensInput = document.getElementById('max-tokens');

    let currentSessionId = null;
    let isGenerating = false;

    function generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    function createNewSession() {
        currentSessionId = generateSessionId();
        if (chatMessages) chatMessages.innerHTML = '';
        addMessage('assistant', 'Hello! I\'m your AI assistant. How can I help you today?');
        loadSessions();
        if (chatInput) chatInput.focus();
    }

    function addMessage(role, content) {
        if (!chatMessages) return;

        const typingEl = chatMessages.querySelector('.chat-typing');
        if (typingEl) typingEl.remove();

        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message ' + role;
        msgDiv.innerHTML = `
            <div class="avatar">${role === 'assistant' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>'}</div>
            <div class="bubble">${role === 'assistant' ? renderMarkdown(content) : escapeHtml(content)}</div>
        `;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showTypingIndicator() {
        if (!chatMessages) return;
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-typing';
        typingDiv.innerHTML = `
            <div class="avatar" style="background:var(--gradient-1);color:white;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-robot"></i></div>
            <div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
        `;
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function renderMarkdown(text) {
        return text
            .replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/^\- (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
            .replace(/\n/g, '<br>');
    }

    async function sendMessage() {
        if (isGenerating || !chatInput) return;
        const message = chatInput.value.trim();
        if (!message) return;

        if (!currentSessionId) createNewSession();

        addMessage('user', message);
        chatInput.value = '';
        chatInput.style.height = 'auto';
        isGenerating = true;
        sendBtn.disabled = true;
        showTypingIndicator();

        const token = localStorage.getItem('fw_token');
        const model = modelSelect ? modelSelect.value : 'gpt-3.5-turbo';
        const temperature = tempSlider ? parseFloat(tempSlider.value) : 0.7;
        const maxTokens = maxTokensInput ? parseInt(maxTokensInput.value) : 2048;

        try {
            const res = await fetch('backend/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    action: 'send',
                    message: message,
                    session_id: currentSessionId,
                    model: model,
                    temperature: temperature,
                    max_tokens: maxTokens
                })
            });

            const data = await res.json();

            if (data.success) {
                addMessage('assistant', data.response);
                const creditsEl = document.querySelector('.user-credits');
                if (creditsEl && data.credits_remaining !== undefined) {
                    creditsEl.textContent = data.credits_remaining;
                }
            } else {
                addMessage('assistant', 'Sorry, I encountered an error: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            addMessage('assistant', 'Network error. Please check your connection and try again.');
            console.error(err);
        } finally {
            isGenerating = false;
            sendBtn.disabled = false;
            chatInput.focus();
        }
    }

    async function loadSessions() {
        const token = localStorage.getItem('fw_token');
        try {
            const res = await fetch('backend/chat.php?action=sessions', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (data.success && sessionsList) {
                sessionsList.innerHTML = data.sessions.map(s => `
                    <div class="chat-session-item ${s.session_id === currentSessionId ? 'active' : ''}" data-id="${s.session_id}">
                        <i class="fas fa-comment-dots"></i>
                        <span class="title">${escapeHtml(s.session_name || 'New Chat')}</span>
                        <button class="delete-btn" onclick="event.stopPropagation(); deleteSession('${s.session_id}')"><i class="fas fa-trash"></i></button>
                    </div>
                `).join('');

                sessionsList.querySelectorAll('.chat-session-item').forEach(item => {
                    item.addEventListener('click', () => {
                        loadSession(item.dataset.id);
                    });
                });
            }
        } catch (err) {
            console.error('Failed to load sessions:', err);
        }
    }

    async function loadSession(sessionId) {
        currentSessionId = sessionId;
        const token = localStorage.getItem('fw_token');
        try {
            const res = await fetch(`backend/chat.php?action=history&session_id=${sessionId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();

            if (data.success && chatMessages) {
                chatMessages.innerHTML = '';
                data.messages.forEach(m => addMessage(m.role, m.message));
                loadSessions();
            }
        } catch (err) {
            console.error('Failed to load session:', err);
        }
    }

    if (sendBtn) sendBtn.addEventListener('click', sendMessage);

    if (chatInput) {
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        chatInput.addEventListener('input', () => {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
        });
    }

    if (newChatBtn) newChatBtn.addEventListener('click', createNewSession);

    if (deleteSessionBtn) {
        deleteSessionBtn.addEventListener('click', () => {
            if (currentSessionId) deleteSession(currentSessionId);
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            if (!chatMessages) return;
            const messages = chatMessages.querySelectorAll('.chat-message');
            let text = 'Free Would Chat Export\n' + '='.repeat(40) + '\n\n';
            messages.forEach(m => {
                const role = m.classList.contains('user') ? 'You' : 'AI';
                const content = m.querySelector('.bubble').textContent.trim();
                text += `${role}:\n${content}\n\n`;
            });
            const blob = new Blob([text], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'freewould-chat-' + Date.now() + '.txt';
            a.click();
        });
    }

    if (tempSlider && tempValue) {
        tempSlider.addEventListener('input', () => {
            tempValue.textContent = tempSlider.value;
        });
    }

    window.deleteSession = async function(sessionId) {
        if (!confirm('Delete this chat session?')) return;
        const token = localStorage.getItem('fw_token');
        try {
            const res = await fetch('backend/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({ action: 'delete', session_id: sessionId })
            });
            const data = await res.json();
            if (data.success) {
                if (sessionId === currentSessionId) {
                    createNewSession();
                }
                loadSessions();
                showToast('Session deleted');
            }
        } catch (err) {
            showToast('Failed to delete session', 'error');
        }
    };

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    createNewSession();
    loadSessions();
});

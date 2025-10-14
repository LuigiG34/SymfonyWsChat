const API_URL = '/api';
const MERCURE_URL = 'http://localhost:3000/.well-known/mercure';

let currentUser = null;
let activeChat = null;
let eventSource = null;
let isLoginMode = true;

// Auth Elements
const authScreen = document.getElementById('authScreen');
const chatScreen = document.getElementById('chatScreen');
const authTitle = document.getElementById('authTitle');
const authButton = document.getElementById('authButton');
const switchMode = document.getElementById('switchMode');
const authError = document.getElementById('authError');
const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');

// Chat Elements
const currentUserSpan = document.getElementById('currentUser');
const logoutBtn = document.getElementById('logoutBtn');
const searchBox = document.getElementById('searchBox');
const userList = document.getElementById('userList');
const chatPlaceholder = document.getElementById('chatPlaceholder');
const activeChat_div = document.getElementById('activeChat');
const chatHeaderActive = document.getElementById('chatHeaderActive');
const messagesDiv = document.getElementById('messages');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');

// Loader
const loader = document.getElementById('loader');

function showLoader(show = true) {
    if (show) {loader.classList.remove('hidden');}
    else {loader.classList.add('hidden');}
}

// Check if already logged in
checkAuth();

function showError(message, color = 'red') {
    authError.textContent = message;
    authError.style.color = color;
    authError.classList.remove('hidden');
}

function showChatScreen() {
    authScreen.style.display = 'none';
    chatScreen.style.display = 'block';
    currentUserSpan.textContent = currentUser;
    subscribeMercure();
}

// Auth Handlers
switchMode.addEventListener('click', () => {
    isLoginMode = !isLoginMode;
    authTitle.textContent = isLoginMode ? 'Login' : 'Register';
    authButton.textContent = isLoginMode ? 'Login' : 'Register';
    switchMode.innerHTML = isLoginMode
        ? "Don't have an account? <strong>Register</strong>"
        : "Already have an account? <strong>Login</strong>";
    authError.classList.add('hidden');
});

authButton.addEventListener('click', async () => {
    const username = usernameInput.value.trim();
    const password = passwordInput.value.trim();

    if (!username || !password) {
        showError('Please fill in all fields');
        return;
    }

    showLoader(true);
    const endpoint = isLoginMode ? '/login' : '/register';

    try {
        const response = await fetch(API_URL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (response.ok) {
            if (isLoginMode) {
                currentUser = data.username;
                showChatScreen();
            } else {
                showError('Registration successful! Please login.', 'green');
                isLoginMode = true;
                switchMode.click();
            }
            showLoader(false);
        } else {
            showError(data.error || 'An error occurred');
            showLoader(false);
        }
    } catch (error) {
        showError('Connection error');
        showLoader(false);
    }
});

logoutBtn.addEventListener('click', async () => {
    showLoader(true);
    await fetch(API_URL + '/logout', {
        method: 'POST',
        credentials: 'include'
    });
    location.reload();
});

// Search Users
let searchTimeout;
searchBox.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        const query = searchBox.value.trim();
        if (query.length < 1) {
            userList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Search for users to start chatting</div>';
            return;
        }

        const response = await fetch(API_URL + '/users/search?q=' + encodeURIComponent(query), {
            credentials: 'include'
        });
        const users = await response.json();

        if (users.length === 0) {
            userList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">No users found</div>';
        } else {
            userList.innerHTML = users.map(user =>
                `<div class="user-item" data-username="${user.username}">${user.username}</div>`
            ).join('');

            document.querySelectorAll('.user-item').forEach(item => {
                item.addEventListener('click', () => openChat(item.dataset.username));
            });
        }
    }, 300);
});

// Open Chat
async function openChat(username) {
    showLoader();
    activeChat = username;
    chatPlaceholder.classList.add('hidden');
    activeChat_div.classList.remove('hidden');
    chatHeaderActive.textContent = username;

    document.querySelectorAll('.user-item').forEach(item => {
        item.classList.toggle('active', item.dataset.username === username);
    });

    // Load message history
    const response = await fetch(API_URL + '/messages/' + username, {
        credentials: 'include'
    });
    const messages = await response.json();

    messagesDiv.innerHTML = messages.map(msg => {
        const isSent = msg.sender === currentUser;
        return `
                    <div class="message ${isSent ? 'sent' : 'received'}">
                        <div class="message-bubble">
                            ${msg.content}
                            <div class="message-time">${msg.sentAt}</div>
                        </div>
                    </div>
                `;
    }).join('');

    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    if (response.ok) {
        showLoader(false);
    }
}

// Send Message
sendBtn.addEventListener('click', sendMessage);
messageInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
});

async function sendMessage() {
    const content = messageInput.value.trim();
    if (!content || !activeChat) return;

    const response = await fetch(API_URL + '/messages/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ receiver: activeChat, content })
    });

    if (response.ok) {
        const msg = await response.json();
        appendMessage(currentUser, content, msg.sentAt, true);
        messageInput.value = '';
    }
}

function appendMessage(sender, content, time, isSent) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
    messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${content}
                    <div class="message-time">${time}</div>
                </div>
            `;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

// Mercure WebSocket
async function subscribeMercure() {
    if (eventSource) eventSource.close();

    // Get Mercure token from backend
    const tokenResponse = await fetch(API_URL + '/mercure-token', {
        credentials: 'include'
    });
    const tokenData = await tokenResponse.json();

    const url = new URL(MERCURE_URL);
    url.searchParams.append('topic', 'chat/' + currentUser);
    // Add JWT token as query parameter since EventSource doesn't support headers
    url.searchParams.append('authorization', tokenData.token);

    eventSource = new EventSource(url);

    eventSource.onmessage = (e) => {
        const data = JSON.parse(e.data);
        console.log('Received message:', data);
        if (data.sender === activeChat) {
            appendMessage(data.sender, data.content, data.sentAt, false);
        }
    };

    eventSource.onerror = (error) => {
        console.error('Mercure connection error:', error);
    };
}

// Helpers
async function checkAuth() {
    const response = await fetch(API_URL + '/me', { credentials: 'include' });
    if (response.ok) {
        const data = await response.json();
        currentUser = data.username;
        showChatScreen();
    }
}
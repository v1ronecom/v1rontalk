<template>
    <div class="v1ron-chat-view">
        <!-- Header -->
        <div class="v1ron-chat-header">
            <button class="v1ron-back-btn" @click="$emit('back')">← Back</button>
            <div class="v1ron-chat-char-info">
                <span v-if="character" class="v1ron-chat-char-name">{{ character.name }}</span>
                <span v-if="balance" class="v1ron-balance">💎 {{ balance }}</span>
            </div>
            <button class="v1ron-clear-btn" @click="confirmClear" title="Clear chat history">🗑️</button>
        </div>

        <!-- Messages -->
        <div class="v1ron-messages" ref="messagesContainer">
            <div v-if="loadingHistory" class="v1ron-loading">Loading messages...</div>

            <div v-if="messages.length === 0 && !loadingHistory" class="v1ron-empty-chat">
                <p>Start a conversation with <strong>{{ character?.name || 'character' }}</strong>!</p>
                <p class="v1ron-hint">
                    💡 Try <code>Read file `Documents/notes.txt`</code> to have me read your files.
                </p>
            </div>

            <div v-for="(msg, i) in messages" :key="msg.id || i"
                 :class="['v1ron-message', msg.role === 'user' ? 'v1ron-msg-user' : 'v1ron-msg-assistant']">
                <div class="v1ron-msg-bubble">
                    <div class="v1ron-msg-content" v-html="renderMessage(msg.content)"></div>
                    <div v-if="msg.image_url" class="v1ron-msg-media">
                        <img :src="msg.image_url" alt="Generated image">
                    </div>
                    <div v-if="msg.video_url" class="v1ron-msg-media">
                        <video :src="msg.video_url" controls></video>
                    </div>
                </div>
            </div>

            <div v-if="waiting" class="v1ron-message v1ron-msg-assistant">
                <div class="v1ron-msg-bubble v1ron-thinking">
                    <span class="v1ron-dot">.</span><span class="v1ron-dot">.</span><span class="v1ron-dot">.</span>
                </div>
            </div>
        </div>

        <!-- File context indicator -->
        <div v-if="attachedFile" class="v1ron-file-attachment">
            📎 <strong>{{ attachedFile.name }}</strong>
            <button @click="attachedFile = null">✕</button>
        </div>

        <!-- Input bar -->
        <div class="v1ron-input-bar">
            <button class="v1ron-file-btn" @click="showFilePicker = !showFilePicker" title="Attach file">
                📎
            </button>
            <textarea v-model="inputText"
                      class="v1ron-input"
                      placeholder="Type a message... Use `path/to/file` to reference files"
                      @keydown.enter.exact="sendMessage"
                      rows="1"
                      ref="inputField">
            </textarea>
            <button class="v1ron-send-btn" @click="sendMessage" :disabled="waiting || !inputText.trim()">
                Send
            </button>
        </div>

        <!-- File picker dropdown -->
        <div v-if="showFilePicker" class="v1ron-file-picker-modal">
            <v1ron-file-picker
                :nc-user-id="ncUserId"
                @select="onFileSelected"
                @close="showFilePicker = false"
            />
        </div>
    </div>
</template>

<script>
import apiMixin from '../mixins/api.js'
import V1RonFilePicker from './FileShareDialog.vue'

export default {
    name: 'V1RonChat',
    components: { V1RonFilePicker },
    mixins: [apiMixin],
    props: {
        characterId: { type: Number, required: true },
        ncUserId: { type: String, default: '' },
    },
    data() {
        return {
            inputText: '',
            messages: [],
            character: null,
            balance: '',
            waiting: false,
            loadingHistory: false,
            showFilePicker: false,
            attachedFile: null,
        }
    },
    async mounted() {
        await this.loadCharacter()
        await this.loadHistory()
        await this.loadBalance()
        this.scrollToBottom()
    },
    methods: {
        async loadCharacter() {
            const result = await this.getCharacters(this.ncUserId)
            if (result.success) {
                this.character = (result.characters || []).find(c => c.id === this.characterId) || null
            }
        },
        async loadHistory() {
            this.loadingHistory = true
            const result = await this.getChatHistory(this.characterId, this.ncUserId)
            if (result.success) {
                this.messages = result.messages || []
            }
            this.loadingHistory = false
            this.$nextTick(() => this.scrollToBottom())
        },
        async loadBalance() {
            const result = await this.getBalance(this.ncUserId)
            if (result.success) {
                this.balance = result.balance_formatted || ''
            }
        },
        async sendMessage(e) {
            if (e) e.preventDefault()
            const text = this.inputText.trim()
            if (!text || this.waiting) return

            // Add user message immediately
            this.messages.push({
                id: 'temp-' + Date.now(),
                role: 'user',
                content: text,
            })
            this.inputText = ''
            this.waiting = true
            this.scrollToBottom()

            try {
                let fileContext = ''
                let refUrls = []

                // If a file is attached, read it
                if (this.attachedFile) {
                    if (this.attachedFile.content) {
                        fileContext = this.attachedFile.content
                    } else if (this.attachedFile.downloadUrl) {
                        refUrls = [this.attachedFile.downloadUrl]
                    }
                }

                const result = await this.chat(this.characterId, this.ncUserId, text, fileContext, refUrls)

                if (result.success) {
                    this.messages.push({
                        id: 'resp-' + Date.now(),
                        role: 'assistant',
                        content: result.reply || '...',
                    })
                    this.balance = result.balance || this.balance
                } else {
                    let errorMsg = result.error || 'Failed to get response'
                    if (result.code === 'insufficient_credits') {
                        errorMsg = '⚠️ Insufficient credits. Please purchase more.'
                    }
                    this.messages.push({
                        id: 'err-' + Date.now(),
                        role: 'assistant',
                        content: `⚠️ ${errorMsg}`,
                    })
                }
            } catch (err) {
                this.messages.push({
                    id: 'err-' + Date.now(),
                    role: 'assistant',
                    content: `⚠️ Network error: ${err.message}`,
                })
            }

            this.waiting = false
            this.attachedFile = null
            this.$nextTick(() => this.scrollToBottom())
        },
        async confirmClear() {
            if (!confirm('Clear all chat history with this character?')) return
            await this.clearChat(this.characterId, this.ncUserId)
            this.messages = []
        },
        onFileSelected(file) {
            this.attachedFile = file
            this.showFilePicker = false
            // Focus back on input
            this.$nextTick(() => this.$refs.inputField?.focus())
        },
        scrollToBottom() {
            const container = this.$refs.messagesContainer
            if (container) {
                setTimeout(() => { container.scrollTop = container.scrollHeight }, 50)
            }
        },
        renderMessage(content) {
            if (!content) return ''
            // Escape HTML
            let html = content
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            // Bold
            html = html.replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
            // Code blocks
            html = html.replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
            // Inline code
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>')
            // Newlines
            html = html.replace(/\n/g, '<br>')
            return html
        },
    },
}
</script>

<style scoped>
.v1ron-chat-view {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 400px;
    max-height: 80vh;
    background: #f5f7fa;
    border-radius: 12px;
    overflow: hidden;
}
.v1ron-chat-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    gap: 10px;
}
.v1ron-back-btn, .v1ron-clear-btn {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 13px;
}
.v1ron-chat-char-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}
.v1ron-chat-char-name {
    font-weight: 600;
    font-size: 15px;
}
.v1ron-balance {
    font-size: 12px;
    color: #e67e22;
    background: #fef5e7;
    padding: 2px 8px;
    border-radius: 10px;
}
.v1ron-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.v1ron-empty-chat {
    text-align: center;
    padding: 40px;
    color: #888;
}
.v1ron-empty-chat .v1ron-hint {
    font-size: 0.9em;
    opacity: 0.7;
    margin-top: 10px;
}
.v1ron-empty-chat code {
    background: #e8e8e8;
    padding: 2px 6px;
    border-radius: 3px;
}
.v1ron-message {
    display: flex;
}
.v1ron-msg-user {
    justify-content: flex-end;
}
.v1ron-msg-assistant {
    justify-content: flex-start;
}
.v1ron-msg-bubble {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}
.v1ron-msg-user .v1ron-msg-bubble {
    background: #0082c9;
    color: #fff;
    border-bottom-right-radius: 4px;
}
.v1ron-msg-assistant .v1ron-msg-bubble {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-bottom-left-radius: 4px;
}
.v1ron-thinking {
    color: #999;
    font-size: 24px;
    padding: 8px 20px;
}
.v1ron-dot {
    animation: v1ron-blink 1.4s infinite;
    font-size: 28px;
}
.v1ron-dot:nth-child(2) { animation-delay: 0.2s; }
.v1ron-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes v1ron-blink {
    0%, 100% { opacity: 0.2; }
    50% { opacity: 1; }
}
.v1ron-msg-content pre {
    background: #f0f0f0;
    padding: 8px;
    border-radius: 6px;
    overflow-x: auto;
    font-size: 12px;
}
.v1ron-msg-content code {
    background: rgba(0,0,0,0.08);
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 13px;
}
.v1ron-msg-user .v1ron-msg-content code {
    background: rgba(255,255,255,0.2);
}
.v1ron-msg-media {
    margin-top: 8px;
}
.v1ron-msg-media img,
.v1ron-msg-media video {
    max-width: 100%;
    max-height: 300px;
    border-radius: 8px;
}
.v1ron-file-attachment {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #e8f5e9;
    border-top: 1px solid #c8e6c9;
    font-size: 13px;
}
.v1ron-file-attachment button {
    border: none;
    background: none;
    cursor: pointer;
    color: #666;
    font-size: 16px;
}
.v1ron-input-bar {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 10px 16px;
    background: #fff;
    border-top: 1px solid #e8e8e8;
}
.v1ron-file-btn {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
}
.v1ron-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    resize: none;
    outline: none;
    font-family: inherit;
}
.v1ron-input:focus {
    border-color: #0082c9;
}
.v1ron-send-btn {
    padding: 10px 20px;
    background: #0082c9;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.v1ron-send-btn:disabled {
    opacity: 0.5;
    cursor: default;
}
.v1ron-file-picker-modal {
    position: absolute;
    bottom: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 12px 12px 0 0;
    max-height: 300px;
    overflow-y: auto;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
    z-index: 100;
}
.v1ron-chat-view {
    position: relative;
}
</style>

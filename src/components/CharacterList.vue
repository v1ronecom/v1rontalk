<template>
    <div class="v1ron-char-list">
        <!-- Account status bar -->
        <div v-if="balance || syncing" class="v1ron-account-bar">
            <span v-if="syncing" class="v1ron-syncing">Connecting...</span>
            <template v-else>
                <span class="v1ron-balance-badge">💎 {{ balance }} credits</span>
                <button class="v1ron-sync-btn" @click="doSync" title="Sync account">↻</button>
            </template>
        </div>

        <div class="v1ron-char-list-header">
            <h2>AI Characters</h2>
            <button class="v1ron-refresh-btn" @click="loadCharacters" :disabled="loading">
                {{ loading ? 'Loading...' : '↻ Refresh' }}
            </button>
        </div>

        <div v-if="syncError" class="v1ron-error">{{ syncError }}</div>
        <div v-if="loading && chars.length === 0" class="v1ron-loading">Loading characters...</div>
        <div v-if="error" class="v1ron-error">{{ error }}</div>

        <div v-if="chars.length === 0 && !loading && !syncing" class="v1ron-empty">
            <p>No characters available.</p>
            <p class="v1ron-hint">Make sure WordPress is configured and characters are set to public.</p>
        </div>

        <!-- Assigned characters section -->
        <template v-if="assignedChars.length > 0">
            <h3 class="v1ron-section-label">Your Characters</h3>
            <div class="v1ron-char-grid">
                <div v-for="char in assignedChars" :key="'a-' + char.id"
                     class="v1ron-char-card v1ron-char-assigned"
                     @click="$emit('select', char.id)">
                    <div class="v1ron-char-avatar">
                        <img v-if="char.avatar" :src="char.avatar" :alt="char.name">
                        <div v-else class="v1ron-avatar-placeholder">{{ char.name.charAt(0) }}</div>
                    </div>
                    <div class="v1ron-char-info">
                        <h3>{{ char.name }} <span class="v1ron-assigned-badge">Assigned</span></h3>
                        <p class="v1ron-char-desc">{{ truncatedDesc(char.description) }}</p>
                        <span v-if="char.affection > 0" class="v1ron-char-affection">❤️ {{ formatNumber(char.affection) }}</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Public characters section -->
        <template v-if="publicChars.length > 0">
            <h3 class="v1ron-section-label" v-if="assignedChars.length > 0">Public Characters</h3>
            <div class="v1ron-char-grid">
                <div v-for="char in publicChars" :key="'p-' + char.id"
                     class="v1ron-char-card"
                     @click="$emit('select', char.id)">
                    <div class="v1ron-char-avatar">
                        <img v-if="char.avatar" :src="char.avatar" :alt="char.name">
                        <div v-else class="v1ron-avatar-placeholder">{{ char.name.charAt(0) }}</div>
                    </div>
                    <div class="v1ron-char-info">
                        <h3>{{ char.name }}</h3>
                        <p class="v1ron-char-desc">{{ truncatedDesc(char.description) }}</p>
                        <span v-if="char.affection > 0" class="v1ron-char-affection">❤️ {{ formatNumber(char.affection) }}</span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import apiMixin from '../mixins/api.js'

export default {
    name: 'V1RonCharacterList',
    mixins: [apiMixin],
    props: {
        ncUserId: { type: String, default: '' },
    },
    data() {
        return {
            chars: [],
            loading: false,
            syncing: false,
            error: '',
            syncError: '',
            balance: '',
        }
    },
    computed: {
        assignedChars() {
            return this.chars.filter(c => c.affection > 0 || c.is_assigned)
        },
        publicChars() {
            const assignedIds = new Set(this.assignedChars.map(c => c.id))
            return this.chars.filter(c => c.is_public && !assignedIds.has(c.id))
        },
    },
    async mounted() {
        await this.doSync()
    },
    methods: {
        async doSync() {
            this.syncing = true
            this.syncError = ''

            // Sync NC user to WP and get initial balance + characters
            const syncResult = await this.syncUser()
            if (syncResult.success) {
                this.balance = syncResult.balance || ''
                if (syncResult.characters && syncResult.characters.length > 0) {
                    this.chars = syncResult.characters
                    this.syncing = false
                    return
                }
            } else {
                this.syncError = syncResult.error || 'Could not sync account'
            }

            this.syncing = false
            await this.loadCharacters()
        },
        async loadCharacters() {
            this.loading = true
            this.error = ''
            const result = await this.getCharacters(this.ncUserId)
            if (result.success) {
                this.chars = result.characters || []
            } else {
                this.error = result.error || 'Failed to load characters'
            }
            this.loading = false
        },
        truncatedDesc(desc) {
            if (!desc) return ''
            return desc.length > 120 ? desc.substring(0, 120) + '...' : desc
        },
        formatNumber(n) {
            return Number(n).toLocaleString()
        },
    },
}
</script>

<style scoped>
.v1ron-char-list {
    padding: 15px;
    max-width: 800px;
    margin: 0 auto;
}
.v1ron-account-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    background: #f0f7ff;
    border: 1px solid #d0e8f7;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}
.v1ron-balance-badge {
    flex: 1;
    color: #0082c9;
    font-weight: 600;
}
.v1ron-syncing { color: #888; font-style: italic; }
.v1ron-sync-btn {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 16px;
    color: #0082c9;
    padding: 2px 6px;
    border-radius: 4px;
}
.v1ron-sync-btn:hover { background: #d0e8f7; }
.v1ron-char-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.v1ron-char-list-header h2 { margin: 0; }
.v1ron-section-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #888;
    margin: 16px 0 10px;
}
.v1ron-refresh-btn {
    padding: 6px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #f8f9fa;
    cursor: pointer;
}
.v1ron-loading, .v1ron-error, .v1ron-empty {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}
.v1ron-error { color: #e74c3c; }
.v1ron-hint { font-size: 0.9em; opacity: 0.7; }
.v1ron-char-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-bottom: 8px;
}
.v1ron-char-card {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.v1ron-char-card:hover {
    border-color: #0082c9;
    box-shadow: 0 2px 12px rgba(0,130,201,0.15);
    transform: translateY(-2px);
}
.v1ron-char-assigned {
    border-left: 3px solid #0082c9;
}
.v1ron-assigned-badge {
    font-size: 10px;
    font-weight: 600;
    color: #0082c9;
    background: #e3f2fd;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 6px;
    vertical-align: middle;
}
.v1ron-char-avatar {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    background: #f0f0f0;
}
.v1ron-char-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.v1ron-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: #0082c9;
    background: #e3f2fd;
}
.v1ron-char-info { flex: 1; min-width: 0; }
.v1ron-char-info h3 { margin: 0 0 5px; font-size: 16px; }
.v1ron-char-desc { margin: 0; font-size: 13px; color: #666; line-height: 1.4; }
.v1ron-char-affection {
    display: inline-block;
    margin-top: 6px;
    font-size: 12px;
    color: #e74c3c;
}
</style>

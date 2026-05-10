/**
 * V1Ron Talk Bot — Main entry point
 *
 * Provides the Vue app that can be mounted on any page
 * or used as a standalone panel.
 */

import Vue from 'vue'
import V1RonChat from './components/ChatView.vue'
import V1RonCharacterList from './components/CharacterList.vue'
import V1RonFilePicker from './components/FileShareDialog.vue'

// Expose a global init function for Talk integration
window.V1RonTalk = {
    /**
     * Initialize the V1Ron chat UI in a container element.
     * @param {string|HTMLElement} container - Container element or selector
     * @param {object} options - Initialization options
     */
    init: function (container, options = {}) {
        const el = typeof container === 'string'
            ? document.querySelector(container)
            : container

        if (!el) {
            console.error('[V1RonTalk] Container not found:', container)
            return
        }

        new Vue({
            el: el,
            components: {
                V1RonChat,
                V1RonCharacterList,
                V1RonFilePicker,
            },
            data: {
                view: options.view || 'characters', // 'characters' | 'chat' | 'files'
                selectedCharId: options.charId || null,
                ncUserId: options.ncUserId || (window.V1RonTalkConfig && window.V1RonTalkConfig.ncUserId) || '',
            },
            template: `
                <div class="v1rontalk-app">
                    <v1ron-character-list
                        v-if="view === 'characters'"
                        :nc-user-id="ncUserId"
                        @select="onSelectCharacter"
                    />
                    <v1ron-chat
                        v-else-if="view === 'chat' && selectedCharId"
                        :character-id="selectedCharId"
                        :nc-user-id="ncUserId"
                        @back="view = 'characters'"
                    />
                </div>
            `,
            methods: {
                onSelectCharacter(charId) {
                    this.selectedCharId = charId
                    this.view = 'chat'
                },
            },
        })
    },
}

// Auto-init if config is present
document.addEventListener('DOMContentLoaded', function () {
    if (window.V1RonTalkConfig && window.V1RonTalkConfig.container) {
        V1RonTalk.init(window.V1RonTalkConfig.container, window.V1RonTalkConfig)
    }
})

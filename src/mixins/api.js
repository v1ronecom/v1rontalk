/**
 * API mixin for communicating with the V1Ron WordPress backend
 * via the Nextcloud proxy endpoint.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    methods: {
        /**
         * Proxy a request to the WordPress V1Ron API.
         */
        async v1ronApi(endpoint, params = {}, method = 'GET') {
            try {
                const url = generateUrl('/apps/v1rontalk/api/v1ron/proxy')
                const response = await axios.post(url, {
                    endpoint,
                    params,
                    method,
                })
                return response.data
            } catch (error) {
                console.error('[V1RonTalk] API error:', error)
                const data = error.response?.data
                if (data) return data
                return { success: false, error: error.message }
            }
        },

        /**
         * Get available characters.
         */
        async getCharacters(ncUserId) {
            return this.v1ronApi('characters', { user_id: ncUserId, public: '1' })
        },

        /**
         * Send a chat message to a character.
         */
        async chat(characterId, ncUserId, message, fileContext = '', refUrls = []) {
            return this.v1ronApi(`characters/${characterId}/chat`, {
                user_id: ncUserId,
                message,
                file_context: fileContext,
                ref_file_urls: refUrls,
            }, 'POST')
        },

        /**
         * Get chat history with a character.
         */
        async getChatHistory(characterId, ncUserId, limit = 50) {
            return this.v1ronApi(`characters/${characterId}/messages`, {
                user_id: ncUserId,
                limit,
            })
        },

        /**
         * Clear chat history.
         */
        async clearChat(characterId, ncUserId) {
            return this.v1ronApi(`characters/${characterId}/messages`, {
                user_id: ncUserId,
            }, 'DELETE')
        },

        /**
         * Get user's credit balance.
         */
        async getBalance(ncUserId) {
            return this.v1ronApi('user/balance', { user_id: ncUserId })
        },

        /**
         * Ingest a file into the character's knowledge base.
         */
        async ingestKnowledge(characterId, ncUserId, title, content, isPrivate = false) {
            return this.v1ronApi(`characters/${characterId}/knowledge`, {
                user_id: ncUserId,
                title,
                content,
                is_private: isPrivate,
            }, 'POST')
        },
    },
}

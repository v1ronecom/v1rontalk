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
                return { success: false, error: error.message || String(error) }
            }
        },

        /**
         * Helper for internal NC app endpoints (not proxied to WordPress).
         *
         * Retries once on the Chrome "message channel closed" error that occurs
         * when Nextcloud's service worker is not yet ready to handle requests on
         * first page load.
         */
        async ncApi(path, data = {}, method = 'POST', _retried = false) {
            try {
                const url = generateUrl('/apps/v1rontalk' + path)
                const response = method === 'GET'
                    ? await axios.get(url, { params: data })
                    : await axios.post(url, data)
                return response.data
            } catch (error) {
                // Chrome throws this when the SW message channel closes mid-flight.
                // Waiting one tick lets the SW context fully initialize before retry.
                if (!_retried && error && typeof error.message === 'string'
                    && error.message.includes('message channel closed')) {
                    await new Promise(resolve => setTimeout(resolve, 300))
                    return this.ncApi(path, data, method, true)
                }
                console.error('[V1RonTalk] NC API error:', error)
                return { success: false, error: error.message || String(error) }
            }
        },

        /**
         * Sync the current Nextcloud user to WordPress.
         * Returns balance, wp_user_id, and assigned characters.
         */
        async syncUser() {
            return this.ncApi('/api/user/sync', {})
        },

        /**
         * Get available characters.
         * When ncUserId is present, fetches public + user-assigned characters.
         */
        async getCharacters(ncUserId) {
            // public=0 returns all characters the user can access (public + assigned)
            const params = ncUserId
                ? { user_id: ncUserId, public: '0' }
                : { public: '1' }
            return this.v1ronApi('characters', params)
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

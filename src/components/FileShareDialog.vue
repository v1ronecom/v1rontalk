<template>
    <div class="v1ron-file-picker">
        <div class="v1ron-fp-header">
            <h3>Select a File</h3>
            <button class="v1ron-fp-close" @click="$emit('close')">✕</button>
        </div>

        <!-- Breadcrumb / current path -->
        <div class="v1ron-fp-breadcrumb">
            <a @click="currentPath = ''; loadDirectory('')">/</a>
            <template v-for="(part, i) in pathParts">
                <span class="v1ron-fp-sep">/</span>
                <a v-if="i < pathParts.length - 1"
                   @click="navigateToPath(part.index)">{{ part.name }}</a>
                <span v-else>{{ part.name }}</span>
            </template>
        </div>

        <div v-if="loading" class="v1ron-fp-loading">Loading...</div>

        <div v-if="error" class="v1ron-fp-error">{{ error }}</div>

        <div class="v1ron-fp-items">
            <div v-for="item in items" :key="item.path"
                 :class="['v1ron-fp-item', item.type === 'folder' ? 'v1ron-folder' : 'v1ron-file']"
                 @click="item.type === 'folder' ? openFolder(item) : selectFile(item)">
                <span class="v1ron-fp-icon">
                    {{ item.type === 'folder' ? '📁' : getFileIcon(item.name) }}
                </span>
                <span class="v1ron-fp-name">{{ item.name }}</span>
                <span v-if="item.type === 'file'" class="v1ron-fp-size">{{ formatSize(item.size) }}</span>
            </div>

            <div v-if="items.length === 0 && !loading" class="v1ron-fp-empty">
                This folder is empty.
            </div>
        </div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'V1RonFilePicker',
    props: {
        ncUserId: { type: String, required: true },
    },
    data() {
        return {
            items: [],
            currentPath: '',
            loading: false,
            error: '',
        }
    },
    computed: {
        pathParts() {
            if (!this.currentPath) return []
            const parts = this.currentPath.split('/').filter(Boolean)
            let path = ''
            return parts.map((name, i) => {
                path = path ? path + '/' + name : name
                return { name, path, index: i + 1 }
            })
        },
    },
    mounted() {
        this.loadDirectory('/')
    },
    methods: {
        async loadDirectory(path) {
            this.loading = true
            this.error = ''
            try {
                const url = generateUrl('/apps/v1rontalk/api/file/list')
                const response = await axios.post(url, {
                    user_id: this.ncUserId,
                    path: path || '/',
                })
                const data = response.data
                if (data.success) {
                    this.items = (data.items || []).filter(item =>
                        !item.name.startsWith('.')
                    )
                } else {
                    this.error = data.error || 'Failed to list directory'
                }
            } catch (err) {
                this.error = 'Network error: ' + err.message
            }
            this.loading = false
        },
        openFolder(item) {
            this.currentPath = item.path
            this.loadDirectory(item.path)
        },
        navigateToPath(index) {
            const parts = this.currentPath.split('/').filter(Boolean)
            const newPath = parts.slice(0, index).join('/')
            this.currentPath = newPath
            this.loadDirectory('/' + newPath)
        },
        async selectFile(item) {
            // For text files, read the content
            const textMimes = ['text/', 'application/json', 'application/xml', 'application/csv']
            const isText = textMimes.some(m => item.mime?.startsWith(m))
            const isImage = item.mime?.startsWith('image/')

            if (isText) {
                try {
                    const url = generateUrl('/apps/v1rontalk/api/file/read')
                    const response = await axios.post(url, {
                        user_id: this.ncUserId,
                        path: item.path,
                    })
                    const data = response.data
                    if (data.success && data.content) {
                        this.$emit('select', {
                            name: item.name,
                            path: item.path,
                            content: data.content,
                            mime: item.mime,
                            size: item.size,
                        })
                    } else {
                        this.$emit('select', {
                            name: item.name,
                            path: item.path,
                            mime: item.mime,
                            size: item.size,
                        })
                    }
                } catch (err) {
                    // Emit basic info without content
                    this.$emit('select', {
                        name: item.name,
                        path: item.path,
                        mime: item.mime,
                        size: item.size,
                    })
                }
            } else if (isImage) {
                // For images, emit the path so it can be used as ref
                this.$emit('select', {
                    name: item.name,
                    path: item.path,
                    mime: item.mime,
                    size: item.size,
                    isImage: true,
                })
            } else {
                this.$emit('select', {
                    name: item.name,
                    path: item.path,
                    mime: item.mime,
                    size: item.size,
                })
            }
        },
        getFileIcon(name) {
            const ext = name.split('.').pop()?.toLowerCase()
            const icons = {
                pdf: '📕', doc: '📘', docx: '📘',
                xls: '📗', xlsx: '📗',
                ppt: '📙', pptx: '📙',
                txt: '📄', md: '📄',
                js: '📜', py: '📜', php: '📜',
                jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️', webp: '🖼️',
                mp3: '🎵', wav: '🎵', flac: '🎵',
                mp4: '🎬', mov: '🎬', webm: '🎬',
                zip: '📦', tar: '📦', gz: '📦',
            }
            return icons[ext] || '📄'
        },
        formatSize(bytes) {
            if (!bytes) return ''
            if (bytes < 1024) return bytes + ' B'
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
            return (bytes / 1048576).toFixed(1) + ' MB'
        },
        formatMtime(timestamp) {
            if (!timestamp) return ''
            return new Date(timestamp * 1000).toLocaleDateString()
        },
    },
}
</script>

<style scoped>
.v1ron-file-picker {
    padding: 10px;
}
.v1ron-fp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.v1ron-fp-header h3 {
    margin: 0;
    font-size: 14px;
}
.v1ron-fp-close {
    border: none;
    background: none;
    font-size: 18px;
    cursor: pointer;
    color: #666;
}
.v1ron-fp-breadcrumb {
    font-size: 12px;
    margin-bottom: 8px;
    padding: 4px 0;
}
.v1ron-fp-breadcrumb a {
    color: #0082c9;
    cursor: pointer;
}
.v1ron-fp-breadcrumb a:hover {
    text-decoration: underline;
}
.v1ron-fp-sep {
    margin: 0 3px;
    color: #999;
}
.v1ron-fp-items {
    max-height: 220px;
    overflow-y: auto;
}
.v1ron-fp-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
}
.v1ron-fp-item:hover {
    background: #f0f4ff;
}
.v1ron-fp-icon {
    font-size: 16px;
    flex-shrink: 0;
}
.v1ron-fp-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.v1ron-fp-size {
    color: #999;
    font-size: 11px;
    flex-shrink: 0;
}
.v1ron-fp-loading, .v1ron-fp-error, .v1ron-fp-empty {
    text-align: center;
    padding: 20px;
    color: #999;
    font-size: 13px;
}
.v1ron-fp-error {
    color: #e74c3c;
}
</style>

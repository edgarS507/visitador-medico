/**
 * apiClient.js — Wrapper fetch() hacia api/*.php
 */
const ApiClient = {
    base: 'api/',

    async get(endpoint) {
        try {
            const res = await fetch(this.base + endpoint);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        } catch (err) {
            console.error('[ApiClient.get]', endpoint, err);
            throw err;
        }
    },

    async post(endpoint, data) {
        try {
            const res = await fetch(this.base + endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        } catch (err) {
            console.error('[ApiClient.post]', endpoint, err);
            throw err;
        }
    },

    async put(endpoint, data) {
        try {
            const res = await fetch(this.base + endpoint, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        } catch (err) {
            console.error('[ApiClient.put]', endpoint, err);
            throw err;
        }
    },

    async delete(endpoint) {
        try {
            const res = await fetch(this.base + endpoint, { method: 'DELETE' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        } catch (err) {
            console.error('[ApiClient.delete]', endpoint, err);
            throw err;
        }
    }
};

/* ── Cola offline básica (Fase 6 del .md) ── */
const OfflineQueue = {
    KEY: 'visitador_offline_queue',

    push(item) {
        const q = this.getAll();
        q.push({ ...item, _ts: Date.now() });
        localStorage.setItem(this.KEY, JSON.stringify(q));
    },

    getAll() {
        return JSON.parse(localStorage.getItem(this.KEY) || '[]');
    },

    clear() {
        localStorage.removeItem(this.KEY);
    },

    async flush() {
        const q = this.getAll();
        if (!q.length) return { synced: 0, errors: 0 };
        let synced = 0, errors = 0;
        const remaining = [];
        for (const item of q) {
            try {
                if (item.method === 'POST')   await ApiClient.post(item.endpoint, item.data);
                else if (item.method === 'PUT')    await ApiClient.put(item.endpoint, item.data);
                else if (item.method === 'DELETE') await ApiClient.delete(item.endpoint);
                synced++;
            } catch {
                remaining.push(item);
                errors++;
            }
        }
        localStorage.setItem(this.KEY, JSON.stringify(remaining));
        return { synced, errors };
    }
};

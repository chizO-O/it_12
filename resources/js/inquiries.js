// Lightweight same-origin polling for Phase 6. Do not insert user-generated text as HTML.
const messageList = document.getElementById('conversation-messages');
if (messageList?.dataset.conversationUpdates) {
    let latest = Number(messageList.dataset.lastId || 0);
    let busy = false;
    messageList.scrollTop = messageList.scrollHeight;

    async function refresh() {
        if (busy || document.visibilityState !== 'visible') return;
        busy = true;
        try {
            const url = new URL(messageList.dataset.conversationUpdates, window.location.origin);
            url.searchParams.set('after', String(latest));
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const result = await response.json();
            for (const message of result.messages || []) {
                if (Number(message.id) <= latest) continue;
                const outer = document.createElement('article');
                outer.dataset.messageId = String(message.id);
                outer.className = `flex ${message.mine ? 'justify-end' : 'justify-start'}`;
                const panel = document.createElement('div');
                panel.className = `max-w-[88%] break-words rounded-2xl px-4 py-3 text-sm ${message.mine ? 'bg-emerald-900 text-white' : 'bg-stone-100 text-stone-800'}`;
                const heading = document.createElement('p');
                heading.className = 'text-xs font-semibold opacity-70';
                heading.textContent = `${message.sender} · ${message.sent_at}`;
                const body = document.createElement('p');
                body.className = 'mt-2 whitespace-pre-wrap';
                body.textContent = message.body;
                panel.append(heading, body);
                outer.append(panel);
                messageList.append(outer);
                latest = Number(message.id);
            }
            if ((result.messages || []).length) messageList.scrollTop = messageList.scrollHeight;
        } catch (_) {
            // Temporary connection failure: retry on the next interval.
        } finally {
            busy = false;
        }
    }

    window.setInterval(refresh, 10000);
}

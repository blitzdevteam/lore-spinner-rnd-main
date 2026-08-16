import { onUnmounted, ref } from 'vue';

export function useTypewriter() {
    const displayedHtml = ref('');
    const isTyping = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let targetHtml = '';
    let index = 0;
    let charsPerTick = 3;
    let tickMs = 12;

    const tick = () => {
        timer = null;

        if (index >= targetHtml.length) {
            isTyping.value = false;
            return;
        }

        let chunk = '';
        let remaining = charsPerTick;

        while (remaining > 0 && index < targetHtml.length) {
            if (targetHtml[index] === '<') {
                const closeIdx = targetHtml.indexOf('>', index);
                if (closeIdx === -1) {
                    break;
                }

                chunk += targetHtml.substring(index, closeIdx + 1);
                index = closeIdx + 1;
                continue;
            }

            if (targetHtml[index] === '&') {
                const semiIdx = targetHtml.indexOf(';', index);
                if (semiIdx !== -1 && semiIdx - index < 10) {
                    chunk += targetHtml.substring(index, semiIdx + 1);
                    index = semiIdx + 1;
                    remaining--;
                    continue;
                }

                if (/^&[#a-zA-Z0-9]*$/.test(targetHtml.slice(index))) {
                    break;
                }
            }

            chunk += targetHtml[index];
            index++;
            remaining--;
        }

        if (!chunk) {
            isTyping.value = false;
            return;
        }

        displayedHtml.value += chunk;
        timer = setTimeout(tick, tickMs);
    };

    const startTimer = () => {
        if (timer || index >= targetHtml.length) {
            return;
        }

        isTyping.value = true;
        tick();
    };

    const start = (html: string, nextCharsPerTick = 3, nextTickMs = 12) => {
        cancel();
        displayedHtml.value = '';
        targetHtml = html;
        index = 0;
        charsPerTick = nextCharsPerTick;
        tickMs = nextTickMs;
        startTimer();
    };

    const streamTo = (html: string, nextCharsPerTick = 4, nextTickMs = 16) => {
        if (!html.startsWith(targetHtml) || index > html.length) {
            start(html, nextCharsPerTick, nextTickMs);
            return;
        }

        targetHtml = html;
        charsPerTick = nextCharsPerTick;
        tickMs = nextTickMs;
        startTimer();
    };

    const complete = (html: string) => {
        cancel();
        targetHtml = html;
        index = html.length;
        displayedHtml.value = html;
        isTyping.value = false;
    };

    const cancel = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        isTyping.value = false;
    };

    const skipToEnd = (html: string) => {
        cancel();
        targetHtml = html;
        index = html.length;
        displayedHtml.value = html;
    };

    onUnmounted(() => cancel());

    return { displayedHtml, isTyping, start, streamTo, complete, cancel, skipToEnd };
}
